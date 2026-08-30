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

    public int $tries = 3;

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
            if (in_array(strtoupper($order->trang_thai_don_hang), ['SUCCESS', 'PROVIDER_PENDING', 'MANUAL_REVIEW'], true)) {
                return null;
            }
            if (strtoupper($order->trang_thai_don_hang) === 'QUEUED') {
                $state->chuyen($order, TrangThaiDonHang::PROCESSING, 'Worker bắt đầu xử lý');
                $order->update(['bat_dau_xu_ly_luc' => now()]);
            }
            return $order;
        });
        if (!$donHang) return;

        foreach ($routing->danhSach($donHang) as $mapping) {
            $lanGoi = LanGoiNhaCungCap::create([
                'don_hang_id' => $donHang->id,
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
                $telegramAlert->alertTransactionFailure($lanGoi, $ketQua->thongBao ?: 'Giao dịch phát sinh phản hồi chưa thành công từ NCC', $mapping->ketNoi);

                // Kiểm tra xem mã lỗi có thuộc danh sách bỏ qua không
                $ignoredCodes = array_filter(array_map('trim', explode(',', (string) ($circuitConfig['ma_loi_bo_qua'] ?? ''))));
                if (!$lanGoi->ma_loi_ncc || !in_array((string) $lanGoi->ma_loi_ncc, $ignoredCodes, true)) {
                    $currentFailures = (int) Cache::get($cacheKey, 0) + 1;
                    Cache::put($cacheKey, $currentFailures, now()->addHours(24));

                    // Kích hoạt Circuit Breaker nếu chạm hoặc vượt ngưỡng lỗi liên tiếp (mặc định 3 lỗi)
                    if ($maxConsecutiveFailures > 0 && $currentFailures >= $maxConsecutiveFailures) {
                        $suspendTTL = $suspendSeconds > 0 ? $suspendSeconds : 300;
                        Cache::put("circuit_breaker_tripped_{$mapping->ket_noi_nha_cung_cap_id}", true, now()->addSeconds($suspendTTL));
                        Cache::forget($cacheKey); // Reset đếm lỗi sau khi đã ngắt
                        $telegramAlert->alertCircuitBreakerTriggered($mapping->ketNoi, $currentFailures, $suspendTTL);
                    }
                }
            }

            if ($ketQua->ketQua !== KetQuaNhaCungCap::DEFINITIVE_FAILURE) return;
        }

        DB::transaction(function () use ($donHang, $state, $viService) {
            $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
            if (strtoupper($order->trang_thai_don_hang) === 'PROCESSING') {
                $state->chuyen($order, TrangThaiDonHang::FAILED, 'Tất cả NCC đều thất bại chắc chắn');
                $order->update(['that_bai_luc' => now()]);

                // Tự động hoàn tiền ví nếu đơn từ frontend và đã thanh toán
                if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                    $state->chuyen($order, TrangThaiDonHang::REFUND_PENDING, 'Bắt đầu hoàn tiền cho người dùng');
                    $viService->hoanTien(
                        (int) $order->nguoi_dung_id,
                        (float) $order->gia_ban,
                        $order,
                        "Hoàn tiền đơn nạp điện thoại thất bại #{$order->ma_don_hang}"
                    );
                    $state->chuyen($order, TrangThaiDonHang::REFUNDED, 'Hoàn tiền ví thành công', 'vi_service');
                }
            }
        });
    }

    private function partnerRef(): string
    {
        return 'TP'.now()->format('YmdHis').strtoupper(Str::random(12));
    }
}
