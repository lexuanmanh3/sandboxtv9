<?php

namespace App\Jobs;

use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Models\DonHang;
use App\Models\LanGoiNhaCungCap;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\DonHangStateMachine;
use App\Services\Topup\DuLieuNhayCam;
use App\Services\Topup\NhaCungCapResolver;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckMobileTopupStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;
    public int $businessAttempt = 1;

    public function __construct(public int $lanGoiNhaCungCapId, int $businessAttempt = 1)
    {
        $this->businessAttempt = max(1, $businessAttempt);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('topup-call-'.$this->lanGoiNhaCungCapId))->expireAfter(120)];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 180, 300, 450, 600, 900, 1200];
    }

    public function handle(
        NhaCungCapResolver $resolver,
        DonHangStateMachine $state,
        ViNguoiDungService $viService,
        TelegramAlertService $telegramAlert
    ): void {
        $lanGoi = LanGoiNhaCungCap::with(['ketNoi.nhaCungCap', 'donHang'])->findOrFail($this->lanGoiNhaCungCapId);
        if (in_array($lanGoi->ket_qua_xac_dinh, ['SUCCESS', 'DEFINITIVE_FAILURE'], true)) return;

        $circuitConfig = $lanGoi->ketNoi?->cau_hinh_dong_tu_dong ?? [];
        $maxAttempts = max(1, (int) ($circuitConfig['so_lan_kiem_tra_lai'] ?? 6));

        // Luôn dùng đúng kết nối và partnerRefId của lần charging ban đầu.
        $result = $resolver->resolve($lanGoi->ketNoi)->kiemTraTrangThai($lanGoi->partner_ref_id);

        DB::transaction(function () use ($lanGoi, $result, $state, $viService, $telegramAlert) {
            // Thứ tự khóa nhất quán: DonHang -> LanGoiNhaCungCap -> KhoanGiuHanMuc -> CauHinhApiDaiLy/ViNguoiDung
            $order = DonHang::query()->lockForUpdate()->findOrFail($lanGoi->don_hang_id);
            $call = LanGoiNhaCungCap::query()->lockForUpdate()->findOrFail($lanGoi->id);

            $call->update([
                'response_json' => DuLieuNhayCam::che($result->duLieu),
                'http_status' => $result->httpStatus,
                'response_signature' => $result->responseSignature,
                'chu_ky_hop_le' => $result->chuKyHopLe,
                'ma_giao_dich_ncc' => $result->maGiaoDichNcc ?: $call->ma_giao_dich_ncc,
                'ma_loi_ncc' => $result->maLoi,
                'ket_qua_xac_dinh' => $result->ketQua->value,
                'trang_thai' => $result->ketQua->value,
                'kiem_tra_lai_luc' => now(),
            ]);

            $currentOrderStatus = strtoupper((string) $order->trang_thai_don_hang);

            if ($result->ketQua === KetQuaNhaCungCap::SUCCESS) {
                // Cho phép cả PROVIDER_PENDING và MANUAL_REVIEW nhận kết quả thành công muộn
                if (in_array($currentOrderStatus, ['PROVIDER_PENDING', 'MANUAL_REVIEW'], true)) {
                    $state->chuyen($order, TrangThaiDonHang::SUCCESS, 'Kiểm tra trạng thái xác nhận thành công từ nhà cung cấp');
                    $order->update([
                        'nha_cung_cap_thanh_cong_id' => $call->nha_cung_cap_id,
                        'lan_goi_thanh_cong_id' => $call->id,
                        'hoan_thanh_luc' => now(),
                    ]);

                    // Xử lý B2B: Chốt công nợ đúng một lần và kích hoạt webhook outbox
                    if ($order->dai_ly_api_id) {
                        app(\App\Services\DaiLyApi\B2bCreditService::class)->chotCongNoThanhCong($order);
                        app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.success');
                        \App\Models\B2bOrderOutbox::where('don_hang_id', $order->id)->update(['status' => 'PROCESSED', 'processed_at' => now()]);
                    }

                    // Gửi thông báo sau khi commit — lỗi thông báo không làm rollback trạng thái SUCCESS
                    DB::afterCommit(function () use ($order, $call, $telegramAlert) {
                        try {
                            $telegramAlert->alertOrderSuccess($order->fresh() ?: $order, $call);
                        } catch (\Throwable $te) {
                            Log::warning("Lỗi gửi Telegram alert thành công đơn #{$order->id}: " . $te->getMessage());
                        }
                    });
                } elseif (in_array($currentOrderStatus, ['REFUNDED', 'REFUND_PENDING'], true)) {
                    // Sự kiện bất thường nghiêm trọng: NCC báo thành công sau khi đơn đã hoàn tiền
                    // Tuyệt đối không tự trừ lại ví khách hoặc tự ghi công nợ, không ghi đè SUCCESS
                    Log::emergency(
                        "CẢNH BÁO ĐỐI SOÁT BẤT THƯỜNG: NCC báo SUCCESS cho đơn đã hoàn tiền trước đó (#{$order->ma_don_hang}). Không ghi đè trạng thái, không trừ lại tiền ví/công nợ!", [
                            'order_id' => $order->id,
                            'call_id' => $call->id,
                            'partner_ref' => $call->partner_ref_id,
                            'ncc_tx_id' => $call->ma_giao_dich_ncc,
                        ]
                    );
                    DB::afterCommit(function () use ($order, $telegramAlert) {
                        try {
                            $telegramAlert->alertManualReview(
                                $order->fresh() ?: $order,
                                "CẢNH BÁO ĐỐI SOÁT: NCC vừa xác nhận thành công đơn #{$order->ma_don_hang} nhưng đơn đã hoàn tiền trước đó. Cần kế toán đối soát thủ công với NCC!"
                            );
                        } catch (\Throwable) {}
                    });
                }
            } elseif ($result->ketQua === KetQuaNhaCungCap::DEFINITIVE_FAILURE) {
                // Cho phép cả PROVIDER_PENDING và MANUAL_REVIEW nhận kết quả thất bại cuối cùng
                if (in_array($currentOrderStatus, ['PROVIDER_PENDING', 'MANUAL_REVIEW'], true)) {
                    $state->chuyen($order, TrangThaiDonHang::FAILED, 'Kiểm tra trạng thái xác nhận thất bại cuối từ nhà cung cấp');
                    $order->update(['that_bai_luc' => now()]);

                    // Hoàn tiền ví B2C qua unified HoanTienDonHangService (xác minh giao dịch trừ ví gốc)
                    if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                        app(\App\Services\Topup\HoanTienDonHangService::class)->hoanTien(
                            orderId: $order->id,
                            actorType: 'system',
                            actorId: null,
                            actorName: 'status_checker',
                            reason: "Hoàn tiền tự động do NCC từ chối (#{$order->ma_don_hang})",
                            skipProviderCheck: true // An toàn vì đã xác nhận DEFINITIVE_FAILURE ngay tại đây
                        );
                    }

                    // Xử lý B2B: Giải phóng khoản giữ hạn mức đúng một lần và gửi webhook thất bại
                    if ($order->dai_ly_api_id) {
                        app(\App\Services\DaiLyApi\B2bCreditService::class)->giaiPhongKhoanGiu($order, "Hoàn tiền tự động do NCC từ chối (#{$order->ma_don_hang})");
                        app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.failed');
                        \App\Models\B2bOrderOutbox::where('don_hang_id', $order->id)->update(['status' => 'PROCESSED', 'processed_at' => now()]);
                    }
                }
            }
        });

        // Xử lý khi kết quả vẫn UNKNOWN_OR_PENDING
        if ($result->ketQua === KetQuaNhaCungCap::UNKNOWN_OR_PENDING) {
            if ($this->businessAttempt >= $maxAttempts) {
                DB::transaction(function () use ($lanGoi, $state, $maxAttempts, $telegramAlert) {
                    $order = DonHang::query()->lockForUpdate()->findOrFail($lanGoi->don_hang_id);
                    if (strtoupper((string) $order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                        // NGUYÊN TẮC: Timeout hoặc hết lượt tra cứu KHÔNG PHẢI thất bại.
                        // Tuyệt đối không hoàn tiền, không giải phóng hạn mức B2B, không gửi webhook order.failed.
                        $state->chuyen($order, TrangThaiDonHang::MANUAL_REVIEW, "Hết {$maxAttempts} lần kiểm tra nhanh (SLA Timeout) nhưng NCC vẫn Pending, chuyển sang đối soát thủ công");
                        $order->update([
                            'trang_thai_doi_soat' => 'cho_doi_soat',
                        ]);

                        DB::afterCommit(function () use ($order, $telegramAlert, $maxAttempts) {
                            $cacheKey = "alert_manual_review_{$order->id}";
                            if (Cache::add($cacheKey, true, now()->addHours(24))) {
                                try {
                                    $telegramAlert->alertManualReview(
                                        $order->fresh() ?: $order,
                                        "Hết {$maxAttempts} lần kiểm tra nhanh nhưng kết quả vẫn chưa xác định (PENDING). Đơn đã chuyển sang ĐỐI SOÁT THỦ CÔNG (giữ nguyên tiền/hạn mức)."
                                    );
                                } catch (\Throwable $te) {
                                    Log::warning("Lỗi gửi Telegram alert đối soát đơn #{$order->id}: " . $te->getMessage());
                                }
                            }
                        });
                    }
                });
                return;
            }

            // Tách số lần tra cứu nghiệp vụ khỏi queue retry hạ tầng
            $nextAttempt = $this->businessAttempt + 1;
            $delaySeconds = $this->backoff()[min($this->businessAttempt - 1, count($this->backoff()) - 1)];
            self::dispatch($this->lanGoiNhaCungCapId, $nextAttempt)->delay(now()->addSeconds($delaySeconds));
        }
    }
}
