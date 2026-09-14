<?php

namespace App\Services\DaiLyApi;

use App\Models\DaiLyApi;
use App\Models\SanPham;

class KiemTraQuyenDaiLyApiService
{
    /**
     * Hàm này dùng để kiểm tra API Partner có được phép mua sản phẩm hay không.
     *
     * Sau này khi API Partner gửi đơn hàng qua API, controller chỉ cần gọi hàm này.
     * Nếu sau này đổi logic phân quyền, mình chỉ sửa trong service này.
     */
    public function kiemTraQuyenSanPham(DaiLyApi $daiLyApi, SanPham $sanPham): array
    {
        /**
         * Kiểm tra trạng thái đại lý API.
         * Nếu đại lý API bị khóa hoặc tạm dừng thì không cho tạo đơn.
         */
        if ($daiLyApi->trang_thai !== 'hoat_dong') {
            return [
                'duoc_phep' => false,
                'ly_do' => 'Đại lý API không hoạt động.',
                'ma_loi' => 'DAI_LY_API_KHONG_HOAT_DONG',
            ];
        }

        /**
         * Kiểm tra trạng thái sản phẩm.
         * Nếu sản phẩm bị tắt thì không cho tạo đơn.
         */
        if (!in_array($sanPham->trang_thai, ['hoat_dong', 'ACTIVE'])) {
            return [
                'duoc_phep' => false,
                'ly_do' => 'Sản phẩm không hoạt động.',
                'ma_loi' => 'SAN_PHAM_KHONG_HOAT_DONG',
            ];
        }

        /**
         * Kiểm tra tầng 1: đại lý API có được cấp dịch vụ của sản phẩm không.
         *
         * Ví dụ:
         * Sản phẩm Viettel 20K thuộc dịch vụ PIN_CODE.
         * Đại lý API phải được cấp PIN_CODE thì mới được đi tiếp.
         */
        $coDichVu = $daiLyApi->dichVu()
            ->whereKey($sanPham->dich_vu_id)
            ->exists();

        if (!$coDichVu) {
            return [
                'duoc_phep' => false,
                'ly_do' => 'Đại lý API chưa được cấp dịch vụ này.',
                'ma_loi' => 'CHUA_DUOC_CAP_DICH_VU',
            ];
        }

        /**
         * Kiểm tra tầng 2: đại lý API có được cấp loại sản phẩm không.
         *
         * Ví dụ:
         * Trong dịch vụ PIN_CODE có Viettel, Mobifone, Garena.
         * Đại lý API phải được cấp đúng loại sản phẩm của sản phẩm đó.
         */
        $coLoaiSanPham = $daiLyApi->loaiSanPham()
            ->whereKey($sanPham->loai_san_pham_id)
            ->exists();

        if (!$coLoaiSanPham) {
            return [
                'duoc_phep' => false,
                'ly_do' => 'Đại lý API chưa được cấp loại sản phẩm này.',
                'ma_loi' => 'CHUA_DUOC_CAP_LOAI_SAN_PHAM',
            ];
        }

        /**
         * Kiểm tra tầng 3: đại lý API có được cấp sản phẩm cụ thể không.
         *
         * Đây là tầng quyền chặt nhất.
         * Sản phẩm nào chưa nằm trong bảng dai_ly_api_san_pham_duoc_phep thì không được mua.
         */
        $coSanPham = $daiLyApi->sanPham()
            ->whereKey($sanPham->id)
            ->exists();

        if (!$coSanPham) {
            return [
                'duoc_phep' => false,
                'ly_do' => 'Đại lý API chưa được cấp sản phẩm này.',
                'ma_loi' => 'CHUA_DUOC_CAP_SAN_PHAM',
            ];
        }

        /**
         * Nếu qua đủ 3 tầng quyền thì cho phép tạo đơn.
         */
        return [
            'duoc_phep' => true,
            'ly_do' => 'Đại lý API được phép dùng sản phẩm này.',
            'ma_loi' => null,
        ];
    }
}