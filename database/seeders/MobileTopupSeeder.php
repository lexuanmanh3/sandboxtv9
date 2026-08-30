<?php

namespace Database\Seeders;

use App\Models\DichVu;
use App\Models\KetNoiNhaCungCap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use Illuminate\Database\Seeder;

class MobileTopupSeeder extends Seeder
{
    public function run(): void
    {
        $service = DichVu::updateOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại', 'trang_thai' => 'hoat_dong', 'thu_tu' => 70,
        ]);
        $provider = NhaCungCap::updateOrCreate(['ma_ncc' => 'APPOTAPAY'], [
            'ten_ncc' => 'AppotaPay', 'trang_thai' => 'hoat_dong',
        ]);
        $connection = KetNoiNhaCungCap::firstOrCreate([
            'nha_cung_cap_id' => $provider->id, 'ten_ket_noi' => 'AppotaPay Sandbox',
        ], [
            'moi_truong' => 'SANDBOX', 'base_url' => 'https://gateway.dev.appotapay.com',
            'auth_type' => 'JWT_HS256', 'trang_thai' => 'tam_dung',
        ]);

        foreach ([['VTE_TOPUP', 'Viettel', 'viettel'], ['VMS_TOPUP', 'Mobifone', 'mobifone'], ['VNA_TOPUP', 'Vinaphone', 'vinaphone']] as [$typeCode, $name, $providerCode]) {
            $type = LoaiSanPham::updateOrCreate(['ma_loai_san_pham' => $typeCode], [
                'ten_loai_san_pham' => $name, 'dich_vu_id' => $service->id, 'trang_thai' => 'hoat_dong',
            ]);
            foreach ([10000, 20000, 50000] as $amount) {
                $product = SanPham::updateOrCreate(['ma_san_pham' => 'MOBILE_TOPUP_'.$typeCode.'_'.$amount], [
                    'dich_vu_id' => $service->id, 'loai_san_pham_id' => $type->id,
                    'ten_san_pham' => $name.' '.number_format($amount, 0, ',', '.').'đ',
                    'menh_gia' => $amount, 'trang_thai' => 'hoat_dong',
                ]);
                SanPhamNhaCungCap::updateOrCreate([
                    'ket_noi_nha_cung_cap_id' => $connection->id, 'ma_san_pham_ncc' => $providerCode.'_'.($amount / 1000),
                ], [
                    'san_pham_id' => $product->id, 'nha_cung_cap_id' => $provider->id,
                    'ma_nha_mang_ncc' => $providerCode, 'loai_dich_vu_ncc' => 'MOBILE_TOPUP',
                    'loai_thue_bao' => 'PREPAID', 'menh_gia_ncc' => $amount,
                    'muc_uu_tien' => 100, 'trang_thai' => 'INACTIVE',
                ]);
            }
        }
    }
}
