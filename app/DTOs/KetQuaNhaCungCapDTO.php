<?php

namespace App\DTOs;

use App\Enums\KetQuaNhaCungCap;

final class KetQuaNhaCungCapDTO
{
    public function __construct(
        public KetQuaNhaCungCap $ketQua,
        public ?string $maLoi = null,
        public ?string $thongBao = null,
        public ?string $maGiaoDichNcc = null,
        public ?bool $chuKyHopLe = null,
        public ?string $responseSignature = null,
        public ?string $soDu = null,
        public ?string $thoiGianGiaoDich = null,
        public array $duLieu = [],
        public ?int $httpStatus = null
    ) {
    }
}
