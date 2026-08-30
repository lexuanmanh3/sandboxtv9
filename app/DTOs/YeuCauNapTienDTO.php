<?php

namespace App\DTOs;

final class YeuCauNapTienDTO
{
    public function __construct(
        public string $partnerRefId,
        public string $soDienThoai,
        public string $maSanPhamNcc,
        public string $nhaMang,
        public string $loaiThueBao
    ) {
    }
}
