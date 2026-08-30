<?php

namespace App\Services\Topup;

use App\Enums\TrangThaiDonHang;
use App\Jobs\ProcessMobileTopupJob;
use App\Models\DonHang;
use Illuminate\Support\Facades\DB;

final class ThanhToanDonHangService
{
    public function __construct(private DonHangStateMachine $state) {}

    public function danhDauDaThanhToan(int $donHangId, string $maGiaoDichDaXacMinh): DonHang
    {
        return DB::transaction(function () use ($donHangId, $maGiaoDichDaXacMinh) {
            $order = DonHang::query()->lockForUpdate()->findOrFail($donHangId);
            if (in_array(strtoupper($order->trang_thai_don_hang), ['PAID', 'QUEUED', 'PROCESSING', 'PROVIDER_PENDING', 'SUCCESS'], true)) {
                return $order;
            }
            $this->state->chuyen($order, TrangThaiDonHang::PAID, 'Callback thanh toán đã xác minh: '.$maGiaoDichDaXacMinh, 'payment_callback');
            $order->update(['trang_thai_thanh_toan' => 'da_thanh_toan', 'thanh_toan_luc' => now()]);
            $this->state->chuyen($order, TrangThaiDonHang::QUEUED, 'Đưa đơn vào hàng đợi');
            ProcessMobileTopupJob::dispatch($order->id)->afterCommit();
            return $order;
        });
    }
}
