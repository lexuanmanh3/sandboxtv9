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
use Illuminate\Support\Facades\DB;

class CheckMobileTopupStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public function __construct(public int $lanGoiNhaCungCapId) {}

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
        $actionOnTimeout = $circuitConfig['hanh_dong_khi_het_gio'] ?? 'TU_DONG_HOAN_TIEN';

        // Luôn dùng đúng kết nối và partnerRefId của lần charging ban đầu.
        $result = $resolver->resolve($lanGoi->ketNoi)->kiemTraTrangThai($lanGoi->partner_ref_id);
        DB::transaction(function () use ($lanGoi, $result, $state, $viService, $telegramAlert) {
            $call = LanGoiNhaCungCap::query()->lockForUpdate()->findOrFail($lanGoi->id);
            $order = DonHang::query()->lockForUpdate()->findOrFail($call->don_hang_id);
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

            if ($result->ketQua === KetQuaNhaCungCap::SUCCESS) {
                if (strtoupper($order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                    $state->chuyen($order, TrangThaiDonHang::SUCCESS, 'Kiểm tra trạng thái xác nhận thành công');
                    $order->update([
                        'nha_cung_cap_thanh_cong_id' => $call->nha_cung_cap_id,
                        'lan_goi_thanh_cong_id' => $call->id,
                        'hoan_thanh_luc' => now()
                    ]);

                    // Xử lý B2B: Chốt công nợ và kích hoạt webhook outbox
                    if ($order->dai_ly_api_id) {
                        app(\App\Services\DaiLyApi\B2bCreditService::class)->chotCongNoThanhCong($order);
                        app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.success');
                    }

                    // Gửi thông báo sau khi commit — lỗi thông báo không làm rollback trạng thái SUCCESS
                    DB::afterCommit(function () use ($order, $call, $telegramAlert) {
                        try {
                            $telegramAlert->alertOrderSuccess($order->fresh() ?: $order, $call);
                        } catch (\Throwable $te) {
                            \Illuminate\Support\Facades\Log::warning("Lỗi gửi Telegram alert thành công đơn #{$order->id}: " . $te->getMessage());
                        }
                    });
                } elseif (in_array(strtoupper($order->trang_thai_don_hang), ['REFUNDED', 'REFUND_PENDING'], true)) {
                    // Sự kiện bất thường: NCC báo thành công sau khi đơn đã hoàn tiền
                    // Không ghi đè SUCCESS, không trừ lại tiền ví người dùng, ghi nhận đối soát
                    \Illuminate\Support\Facades\Log::emergency(
                        "ĐỐI SOÁT BẤT THƯỜNG: NCC báo SUCCESS cho đơn đã hoàn tiền (#{$order->ma_don_hang}). Không ghi đè trạng thái, không trừ lại ví.", [
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
            } elseif ($result->ketQua === KetQuaNhaCungCap::DEFINITIVE_FAILURE && strtoupper($order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                $state->chuyen($order, TrangThaiDonHang::FAILED, 'Kiểm tra trạng thái xác nhận thất bại cuối');
                $order->update(['that_bai_luc' => now()]);

                // Tự động hoàn tiền ví qua unified HoanTienDonHangService
                if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                    app(\App\Services\Topup\HoanTienDonHangService::class)->hoanTien(
                        orderId: $order->id,
                        actorType: 'system',
                        actorId: null,
                        actorName: 'status_checker',
                        reason: "Hoàn tiền tự động do NCC từ chối (#{$order->ma_don_hang})",
                        skipProviderCheck: true
                    );
                }

                // Xử lý B2B: Giải phóng khoản giữ và gửi webhook thất bại
                if ($order->dai_ly_api_id) {
                    app(\App\Services\DaiLyApi\B2bCreditService::class)->giaiPhongKhoanGiu($order, "Hoàn tiền tự động do NCC từ chối (#{$order->ma_don_hang})");
                    app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.failed');
                }
            }
        });

        if ($result->ketQua === KetQuaNhaCungCap::UNKNOWN_OR_PENDING) {
            if ($this->attempts() >= $maxAttempts) {
                DB::transaction(function () use ($lanGoi, $state, $actionOnTimeout, $maxAttempts, $telegramAlert) {
                    $order = DonHang::query()->lockForUpdate()->findOrFail($lanGoi->don_hang_id);
                    if (strtoupper($order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                        if ($actionOnTimeout === 'TU_DONG_HOAN_TIEN') {
                            $state->chuyen($order, TrangThaiDonHang::FAILED, "Hết {$maxAttempts} lần kiểm tra (SLA Timeout) nhưng NCC vẫn Pending -> Tự động hoàn tiền");
                            $order->update(['that_bai_luc' => now()]);

                            if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                                app(\App\Services\Topup\HoanTienDonHangService::class)->hoanTien(
                                    orderId: $order->id,
                                    actorType: 'system',
                                    actorId: null,
                                    actorName: 'sla_timeout',
                                    reason: "Hoàn tiền tự động do quá thời hạn chờ NCC (#{$order->ma_don_hang})",
                                    skipProviderCheck: true
                                );
                            }

                            // Xử lý B2B: Giải phóng khoản giữ và gửi webhook thất bại
                            if ($order->dai_ly_api_id) {
                                app(\App\Services\DaiLyApi\B2bCreditService::class)->giaiPhongKhoanGiu($order, "Quá thời hạn kiểm tra NCC (SLA Timeout)");
                                app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.failed');
                            }
                        } else {
                            $state->chuyen($order, TrangThaiDonHang::MANUAL_REVIEW, "Hết {$maxAttempts} lần kiểm tra nhưng kết quả vẫn chưa xác định, chuyển Admin duyệt");
                            DB::afterCommit(function () use ($order, $telegramAlert, $maxAttempts) {
                                try {
                                    $telegramAlert->alertManualReview($order->fresh() ?: $order, "Hết {$maxAttempts} lần kiểm tra nhưng kết quả vẫn chưa xác định, chuyển Admin đối soát");
                                } catch (\Throwable $te) {
                                    \Illuminate\Support\Facades\Log::warning("Lỗi gửi Telegram alert đối soát đơn #{$order->id}: " . $te->getMessage());
                                }
                            });
                        }
                    }
                });
                return;
            }
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
        }
    }
}
