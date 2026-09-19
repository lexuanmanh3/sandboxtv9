<?php

namespace App\Services\DaiLyApi;

use App\Models\BangGiaDaiLy;
use App\Models\DaiLyApi;
use App\Models\SanPham;

class B2bPricingService
{
    /**
     * Tính toán giá bán và chiết khấu chuẩn hóa cho đại lý B2B.
     * Sử dụng chung thống nhất giữa API /services và API tạo đơn POST /orders.
     *
     * @return array{face_value: int, price: int, discount: int, discount_rate: float}
     */
    public function tinhGia(DaiLyApi $daiLy, SanPham $sanPham, ?BangGiaDaiLy $bangGia = null): array
    {
        $menhGia = (int) round((float) $sanPham->menh_gia);

        if ($bangGia === null) {
            $bangGia = BangGiaDaiLy::where('dai_ly_api_id', $daiLy->id)
                ->where('san_pham_id', $sanPham->id)
                ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
                ->first();
        }

        if ($bangGia) {
            if ($bangGia->loai_chiet_khau === 'FIXED_PRICE' && (float) $bangGia->gia_ban_ap_dung > 0) {
                $giaBan = (int) round((float) $bangGia->gia_ban_ap_dung);
                $chietKhau = max(0, $menhGia - $giaBan);
            } else {
                $phanTram = (float) $bangGia->gia_tri_chiet_khau;
                $chietKhau = (int) round($menhGia * ($phanTram / 100));
                $giaBan = max(0, $menhGia - $chietKhau);
            }
        } else {
            $giaBan = (int) round((float) ($sanPham->gia_ban ?: $menhGia));
            $chietKhau = (int) round((float) ($sanPham->chiet_khau ?: ($menhGia - $giaBan)));
        }

        $discountRate = $menhGia > 0 ? round(($chietKhau / $menhGia) * 100, 2) : 0.0;

        return [
            'face_value' => $menhGia,
            'price' => $giaBan,
            'discount' => $chietKhau,
            'discount_rate' => $discountRate,
        ];
    }
}
