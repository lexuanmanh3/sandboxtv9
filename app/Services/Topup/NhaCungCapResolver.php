<?php

namespace App\Services\Topup;

use App\Contracts\NhaCungCapTopupInterface;
use App\Models\KetNoiNhaCungCap;
use App\Services\Topup\AppotaPay\AppotaPayErrorMapper;
use App\Services\Topup\AppotaPay\AppotaPayJwt;
use App\Services\Topup\AppotaPay\AppotaPaySignature;
use App\Services\Topup\AppotaPay\AppotaPayTopupProvider;
use InvalidArgumentException;

final class NhaCungCapResolver
{
    public function resolve(KetNoiNhaCungCap $ketNoi): NhaCungCapTopupInterface
    {
        $maNcc = strtoupper((string) $ketNoi->nhaCungCap->ma_ncc);
        if ($maNcc === 'APPOTAPAY') {
            return new AppotaPayTopupProvider($ketNoi, app(AppotaPayJwt::class), app(AppotaPaySignature::class), app(AppotaPayErrorMapper::class));
        }

        throw new InvalidArgumentException("Chưa có adapter cho nhà cung cấp {$maNcc}.");
    }
}
