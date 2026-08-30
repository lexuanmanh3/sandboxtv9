<?php

namespace App\Services\Topup;

use App\Enums\TrangThaiDonHang;
use App\Models\DonHang;
use App\Models\LichSuTrangThaiDonHang;
use DomainException;

final class DonHangStateMachine
{
    private const ALLOWED = [
        'CREATED' => ['WAITING_PAYMENT'], 'WAITING_PAYMENT' => ['PAID'], 'PAID' => ['QUEUED'],
        'QUEUED' => ['PROCESSING'], 'PROCESSING' => ['SUCCESS', 'PROVIDER_PENDING', 'FAILED', 'REFUND_PENDING'],
        'PROVIDER_PENDING' => ['SUCCESS', 'FAILED', 'MANUAL_REVIEW', 'REFUND_PENDING'],
        'MANUAL_REVIEW' => ['SUCCESS', 'FAILED', 'REFUND_PENDING'],
        'FAILED' => ['REFUND_PENDING', 'REFUNDED'], 'REFUND_PENDING' => ['REFUNDED'],
    ];

    public function chuyen(DonHang $donHang, TrangThaiDonHang $moi, ?string $lyDo = null, string $nguon = 'he_thong'): void
    {
        $cu = strtoupper((string) $donHang->trang_thai_don_hang);
        if (!in_array($moi->value, self::ALLOWED[$cu] ?? [], true)) {
            throw new DomainException("Không được chuyển trạng thái {$cu} sang {$moi->value}.");
        }

        $donHang->trang_thai_don_hang = $moi->value;
        $donHang->save();
        LichSuTrangThaiDonHang::create([
            'don_hang_id' => $donHang->id, 'trang_thai_cu' => $cu, 'trang_thai_moi' => $moi->value,
            'ly_do' => $lyDo, 'nguon_thay_doi' => $nguon,
        ]);
    }
}
