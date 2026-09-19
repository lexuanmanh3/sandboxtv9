<?php

namespace App\Jobs;

use App\DTOs\YeuCauNapTienDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Models\DonHang;
use App\Models\LanGoiNhaCungCap;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\DonHangStateMachine;
use App\Services\Topup\DuLieuNhayCam;
use App\Services\Topup\NhaCungCapResolver;
use App\Services\Topup\NhaCungCapRoutingService;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessMobileTopupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $donHangId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('topup-order-'.$this->donHangId))->expireAfter(180)->dontRelease()];
    }

    public function handle(
        NhaCungCapRoutingService $routing,
        NhaCungCapResolver $resolver,
        DonHangStateMachine $state,
        TelegramAlertService $telegramAlert,
        ViNguoiDungService $viService
    ): void {
        $donHang = DB::transaction(function () use ($state) {
            $order = DonHang::query()->lockForUpdate()->findOrFail($this->donHangId);
            if (in_array(strtoupper($order->trang_thai_don_hang), ['SUCCESS', 'PROVIDER_PENDING', 'MANUAL_REVIEW', 'REFUND_PENDING', 'REFUNDED', 'FAILED'], true)) {
                return null;
            }
            if (strtoupper($order->trang_thai_don_hang) === 'QUEUED') {
                $state->chuyen($order, TrangThaiDonHang::PROCESSING, 'Worker bắt đầu xử lý');
                $order->update(['bat_dau_xu_ly_luc' => now()]);
            }
            return $order;
        });
        if (!$donHang) return;

        \App\Models\B2bOrderOutbox::where('don_hang_id', $donHang->id)
            ->where('status', 'PENDING')
            ->update(['status' => 'PROCESSING', 'locked_until' => now()->addMinutes(5)]);

        // Chống nạp trùng khi worker trước bị timeout/crash giữa chừng:
        // Nếu đã có lần gọi CHARGING đang PROCESSING, tuyệt đối không tạo partner_ref_id mới
        $inFlightCall = $donHang->lanGoiNhaCungCap()
            ->where('loai_yeu_cau', 'CHARGING')
            ->where('trang_thai', 'PROCESSING')
            ->orderByDesc('id')
            ->first();

        if ($inFlightCall) {
            DB::transaction(function () use ($donHang, $inFlightCall, $state) {
                $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
                if ($order->trang_thai_don_hang !== TrangThaiDonHang::PROVIDER_PENDING->value) {
                    $state->chuyen($order, TrangThaiDonHang::PROVIDER_PENDING, 'Phát hiện yêu cầu nạp đang xử lý dở dang, chuyển sang tra cứu trạng thái');
                }
                $inFlightCall->update([
                    'trang_thai' => 'UNKNOWN_OR_PENDING',
                    'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
                ]);
            });
            CheckMobileTopupStatusJob::dispatch($inFlightCall->id)->delay(now()->addSeconds(10));
            return;
        }

        foreach ($routing->danhSach($donHang) as $mapping) {
            $lanGoi = LanGoiNhaCungCap::create([
                'don_hang_id' => $donHang->id,
                'cau_hinh_dich_vu_id' => $mapping->cauHinhDichVu?->id,
                'nha_cung_cap_id' => $mapping->nha_cung_cap_id,
                'ket_noi_nha_cung_cap_id' => $mapping->ket_noi_nha_cung_cap_id,
                'san_pham_nha_cung_cap_id' => $mapping->id,
                'lan_thu' => $donHang->lanGoiNhaCungCap()->count() + 1,
                'loai_yeu_cau' => 'CHARGING',
                'partner_ref_id' => $this->partnerRef(),
                'ma_san_pham_ncc' => $mapping->ma_san_pham_ncc,
                'endpoint' => '/api/v2/service/topup/charging',
                'trang_thai' => 'PROCESSING',
                'bat_dau_luc' => now(),
            ]);

            $dto = new YeuCauNapTienDTO(
                $lanGoi->partner_ref_id,
                $donHang->tai_khoan_nhan,
                $mapping->ma_san_pham_ncc,
                $donHang->nha_mang_thuc_te ?: $donHang->nha_mang_yeu_cau ?: $mapping->ma_nha_mang_ncc,
                $donHang->loai_thue_bao_thuc_te ?: $donHang->loai_thue_bao_yeu_cau ?: $mapping->loai_thue_bao ?: 'PREPAID'
            );
            $lanGoi->update(['request_json' => DuLieuNhayCam::che((array) $dto)]);

            // Đo thời gian gọi NCC để phục vụ cảnh báo giao dịch chậm
            $startMicro = microtime(true);
            try {
                $ketQua = $resolver->resolve($mapping->ketNoi)->napTien($dto);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("ProcessMobileTopupJob: Ngoại lệ khi gọi NCC {$mapping->nhaCungCap?->ten_ncc}: " . $e->getMessage(), [
                    'don_hang_id' => $donHang->id,
                    'lan_goi_id'  => $lanGoi->id,
                ]);
                $ketQua = new \App\DTOs\KetQuaNhaCungCapDTO(
                    \App\Enums\KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
                    null,
                    $e->getMessage() ?: 'Lỗi ngoại lệ khi kết nối nhà cung cấp'
                );
            }
            $durationSeconds = microtime(true) - $startMicro;

            // Kiểm tra cảnh báo xử lý GD chậm
            $alertConfig = $mapping->ketNoi->cau_hinh_canh_bao_loi ?? [];
            $slowThreshold = (int) ($alertConfig['canh_bao_xu_ly_cham_giay'] ?? 0);
            if ($slowThreshold > 0 && $durationSeconds >= $slowThreshold) {
                $telegramAlert->alertSlowTransaction($lanGoi, $durationSeconds, $slowThreshold, $mapping->ketNoi);
            }

            DB::transaction(function () use ($donHang, $lanGoi, $mapping, $ketQua, $state) {
                $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
                $lanGoi->update([
                    'response_json' => DuLieuNhayCam::che($ketQua->duLieu),
                    'http_status' => $ketQua->httpStatus,
                    'response_signature' => $ketQua->responseSignature,
                    'chu_ky_hop_le' => $ketQua->chuKyHopLe,
                    'ma_giao_dich_ncc' => $ketQua->maGiaoDichNcc,
                    'ma_loi_ncc' => $ketQua->maLoi,
                    'so_du_ncc_sau_giao_dich' => $ketQua->soDu,
                    'thoi_gian_giao_dich_ncc' => $ketQua->thoiGianGiaoDich,
                    'ket_qua_xac_dinh' => $ketQua->ketQua->value,
                    'trang_thai' => $ketQua->ketQua->value,
                    'ket_thuc_luc' => now(),
                ]);

                if ($ketQua->ketQua === KetQuaNhaCungCap::SUCCESS) {
                    $state->chuyen($order, TrangThaiDonHang::SUCCESS, 'Nhà cung cấp xác nhận thành công');
                    $order->update([
                        'nha_cung_cap_thanh_cong_id' => $mapping->nha_cung_cap_id,
                        'lan_goi_thanh_cong_id' => $lanGoi->id,
                        'gia_von_thuc_te' => $mapping->gia_nhap,
                        'hoan_thanh_luc' => now()
                    ]);

                    // Xử lý B2B: Chuyển khoản giữ sang công nợ và tạo outbox webhook
                    if ($order->dai_ly_api_id) {
                        app(\App\Services\DaiLyApi\B2bCreditService::class)->chotCongNoThanhCong($order);
                        app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.success');
                        \App\Models\B2bOrderOutbox::where('don_hang_id', $order->id)->update(['status' => 'PROCESSED', 'processed_at' => now()]);
                    }
                } elseif ($ketQua->ketQua === KetQuaNhaCungCap::UNKNOWN_OR_PENDING) {
                    $state->chuyen($order, TrangThaiDonHang::PROVIDER_PENDING, 'Kết quả NCC chưa xác định');
                    CheckMobileTopupStatusJob::dispatch($lanGoi->id)->delay(now()->addSeconds(10))->afterCommit();
                }
            });

            // Xử lý Circuit Breaker & Cảnh báo lỗi
            $circuitConfig = $mapping->ketNoi->cau_hinh_dong_tu_dong ?? [];
            $maxConsecutiveFailures = (int) ($circuitConfig['so_gd_that_bai_lien_tiep'] ?? 0);
            $suspendSeconds = (int) ($circuitConfig['thoi_gian_dong'] ?? $circuitConfig['thoi_gian_dong_giay'] ?? 0);
            $cacheKey = "circuit_breaker_fails_{$mapping->ketNoi->id}";

            if ($ketQua->ketQua === KetQuaNhaCungCap::SUCCESS) {
                // Reset bộ đếm lỗi liên tiếp khi giao dịch thành công
                Cache::forget($cacheKey);
                $telegramAlert->alertOrderSuccess($donHang->fresh(), $lanGoi);
                return;
            }

            // Cả lỗi từ chối lẫn lỗi đang chờ xử lý liên tiếp (nghẽn mạng/mã 34) đều được tính vào bộ đếm ngắt mạch
            $isFailureForCircuit = in_array($ketQua->ketQua, [KetQuaNhaCungCap::DEFINITIVE_FAILURE, KetQuaNhaCungCap::UNKNOWN_OR_PENDING], true);

            if ($isFailureForCircuit) {
                // Gửi cảnh báo giao dịch cho tất cả trường hợp không thành công (ngoại trừ mã lỗi cấu hình bỏ qua)
                $lanGoiFresh = $lanGoi->fresh() ?: $lanGoi;
                $telegramAlert->alertTransactionFailure($lanGoiFresh, $ketQua->thongBao ?: 'Giao dịch phát sinh phản hồi chưa thành công từ NCC', $mapping->ketNoi);

                // Kiểm tra xem mã lỗi có thuộc danh sách bỏ qua không
                $ignoredCodes = array_filter(array_map('trim', explode(',', (string) ($circuitConfig['ma_loi_bo_qua'] ?? ''))));
                if (!$lanGoiFresh->ma_loi_ncc || !in_array((string) $lanGoiFresh->ma_loi_ncc, $ignoredCodes, true)) {
                    if (!Cache::has($cacheKey)) {
                        Cache::add($cacheKey, 1, now()->addHours(24));
                        $currentFailures = 1;
                    } else {
                        $currentFailures = (int) Cache::increment($cacheKey);
                    }

                    // Kích hoạt Circuit Breaker nếu chạm hoặc vượt ngưỡng lỗi liên tiếp
                    if ($maxConsecutiveFailures > 0 && $currentFailures >= $maxConsecutiveFailures) {
                        // Cập nhật trạng thái trực tiếp trong Database sang TẠM DỪNG
                        $mapping->ketNoi->update(['trang_thai' => 'tam_dung']);
                        $mapping->nhaCungCap?->update(['trang_thai' => 'tam_dung']);

                        Cache::forget($cacheKey); // Reset đếm lỗi sau khi đã ngắt
                        // Lưu cờ đánh dấu circuit breaker bị tripped
                        Cache::put("circuit_breaker_tripped_{$mapping->ketNoi->id}", true, now()->addSeconds($suspendSeconds > 0 ? $suspendSeconds : 300));
                        $telegramAlert->alertCircuitBreakerTriggered($mapping->ketNoi, $currentFailures, $suspendSeconds);
                    }
                }
            }

            if ($ketQua->ketQua !== KetQuaNhaCungCap::DEFINITIVE_FAILURE) return;

            // Nếu tuyến cấu hình chặn fallback khi lỗi chắc chắn (ket_thuc_cau_hinh), dừng luồng ngay không thử NCC khác
            if ($mapping->cauHinhDichVu?->ket_thuc_cau_hinh) {
                break;
            }
        }

        DB::transaction(function () use ($donHang, $state) {
            $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
            if (strtoupper($order->trang_thai_don_hang) === 'PROCESSING') {
                $state->chuyen($order, TrangThaiDonHang::FAILED, 'Tất cả NCC đều thất bại chắc chắn');
                $order->update(['that_bai_luc' => now()]);

                // Tự động hoàn tiền ví nếu đơn từ frontend và đã thanh toán
                if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                    app(\App\Services\Topup\HoanTienDonHangService::class)->hoanTien(
                        orderId: $order->id,
                        actorType: 'system',
                        actorId: null,
                        actorName: 'worker',
                        reason: 'Tất cả NCC đều thất bại chắc chắn',
                        skipProviderCheck: true
                    );
                }

                // Xử lý B2B: Giải phóng khoản giữ hạn mức và gửi webhook thông báo thất bại
                if ($order->dai_ly_api_id) {
                    app(\App\Services\DaiLyApi\B2bCreditService::class)->giaiPhongKhoanGiu($order, 'Tất cả NCC đều thất bại chắc chắn');
                    app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.failed');
                    \App\Models\B2bOrderOutbox::where('don_hang_id', $order->id)->update(['status' => 'PROCESSED', 'processed_at' => now()]);
                }
            }
        });
    }

    private function partnerRef(): string
    {
        return 'TP'.now()->format('YmdHis').strtoupper(Str::random(12));
    }
}
