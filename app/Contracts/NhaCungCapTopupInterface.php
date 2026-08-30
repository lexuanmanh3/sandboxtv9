<?php

namespace App\Contracts;

use App\DTOs\KetQuaNhaCungCapDTO;
use App\DTOs\KetQuaSoDuDTO;
use App\DTOs\KetQuaThueBao;
use App\DTOs\YeuCauNapTienDTO;
use Illuminate\Support\Collection;

interface NhaCungCapTopupInterface
{
    public function kiemTraThueBao(string $soDienThoai): KetQuaThueBao;
    public function layDanhSachSanPham(?string $endpoint = null): Collection;
    public function layGoiDataTheoThueBao(string $soDienThoai, string $loaiThueBao): Collection;
    public function napTien(YeuCauNapTienDTO $dto): KetQuaNhaCungCapDTO;
    public function kiemTraTrangThai(string $partnerRefId): KetQuaNhaCungCapDTO;
    public function kiemTraSoDu(): KetQuaSoDuDTO;
}
