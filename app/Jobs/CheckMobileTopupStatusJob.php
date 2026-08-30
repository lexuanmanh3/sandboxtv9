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
        DB::transaction(function () use ($lanGoi, $result, $state, $viService) {
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

            if ($result->ketQua === KetQuaNhaCungCap::SUCCESS && strtoupper($order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                $state->chuyen($order, TrangThaiDonHang::SUCCESS, 'Kiểm tra trạng thái xác nhận thành công');
                $order->update([
                    'nha_cung_cap_thanh_cong_id' => $call->nha_cung_cap_id,
                    'lan_goi_thanh_cong_id' => $call->id,
                    'hoan_thanh_luc' => now()
                ]);
            } elseif ($result->ketQua === KetQuaNhaCungCap::DEFINITIVE_FAILURE && strtoupper($order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                $state->chuyen($order, TrangThaiDonHang::FAILED, 'Kiểm tra trạng thái xác nhận thất bại cuối');
                $order->update(['that_bai_luc' => now()]);

                // Tự động hoàn tiền ví nếu đơn từ frontend và đã trừ tiền
                if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                    $state->chuyen($order, TrangThaiDonHang::REFUND_PENDING, 'Tự động hoàn tiền cho người dùng do đơn thất bại');
                    $viService->hoanTien(
                        $order->nguoi_dung_id,
                        (float) $order->gia_ban,
                        $order,
                        "Hoàn tiền đơn hàng #{$order->ma_don_hang} do NCC từ chối"
                    );
                    $state->chuyen($order, TrangThaiDonHang::REFUNDED, 'Hoàn tiền ví thành công');
                    $order->update(['trang_thai_thanh_toan' => 'REFUNDED']);
                }
            }
        });

        if ($result->ketQua === KetQuaNhaCungCap::UNKNOWN_OR_PENDING) {
            if ($this->attempts() >= $maxAttempts) {
                DB::transaction(function () use ($lanGoi, $state, $viService, $actionOnTimeout, $maxAttempts) {
                    $order = DonHang::query()->lockForUpdate()->findOrFail($lanGoi->don_hang_id);
                    if (strtoupper($order->trang_thai_don_hang) === 'PROVIDER_PENDING') {
                        if ($actionOnTimeout === 'TU_DONG_HOAN_TIEN') {
                            $state->chuyen($order, TrangThaiDonHang::FAILED, "Hết {$maxAttempts} lần kiểm tra (SLA Timeout) nhưng NCC vẫn Pending -> Tự động hoàn tiền");
                            $order->update(['that_bai_luc' => now()]);

                            if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                                $state->chuyen($order, TrangThaiDonHang::REFUND_PENDING, 'Tự động hoàn tiền SLA cho người dùng');
                                $viService->hoanTien(
                                    $order->nguoi_dung_id,
                                    (float) $order->gia_ban,
                                    $order,
                                    "Hoàn tiền đơn hàng #{$order->ma_don_hang} do quá thời hạn chờ NCC"
                                );
                                $state->chuyen($order, TrangThaiDonHang::REFUNDED, 'Hoàn tiền ví thành công');
                                $order->update(['trang_thai_thanh_toan' => 'REFUNDED']);
                            }
                        } else {
                            $state->chuyen($order, TrangThaiDonHang::MANUAL_REVIEW, "Hết {$maxAttempts} lần kiểm tra nhưng kết quả vẫn chưa xác định, chuyển Admin duyệt");
                        }
                    }
                });
                return;
            }
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
        }
    }
}
