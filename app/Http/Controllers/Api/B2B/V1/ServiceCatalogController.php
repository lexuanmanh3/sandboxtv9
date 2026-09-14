<?php

namespace App\Http\Controllers\Api\B2B\V1;

use App\Http\Controllers\Controller;
use App\Models\BangGiaDaiLy;
use App\Models\DaiLyApi;
use App\Models\SanPham;
use App\Services\DaiLyApi\KiemTraQuyenDaiLyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    public function __construct(
        protected KiemTraQuyenDaiLyApiService $quyenService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');
        $cauHinh = $partner->cauHinhApi;

        $excludedIds = (array) ($cauHinh?->san_pham_loai_tru ?? []);

        // Lấy tất cả sản phẩm đang hoạt động kèm danh mục và dịch vụ
        $sanPhams = SanPham::with(['dichVu', 'loaiSanPham'])
            ->where('trang_thai', 'hoat_dong')
            ->get();

        // Lấy bảng giá riêng của đại lý (nếu có)
        $bangGiaList = BangGiaDaiLy::where('dai_ly_api_id', $partner->id)
            ->where('trang_thai', 'hoat_dong')
            ->get()
            ->keyBy('san_pham_id');

        $servicesMap = [];

        foreach ($sanPhams as $sp) {
            // Kiểm tra bị loại trừ
            if (in_array((string) $sp->id, $excludedIds, true) || in_array($sp->ma_san_pham, $excludedIds, true)) {
                continue;
            }

            // Kiểm tra quyền 3 tầng
            $check = $this->quyenService->kiemTraQuyenSanPham($partner, $sp);
            if (!$check['duoc_phep']) {
                continue;
            }

            $dichVu = $sp->dichVu;
            $loaiSp = $sp->loaiSanPham;

            if (!$dichVu || !$loaiSp) {
                continue;
            }

            $menhGia = (float) $sp->menh_gia;
            $giaBan = (float) ($sp->gia_ban ?: $menhGia);
            $chietKhau = (float) ($sp->chiet_khau ?: ($menhGia - $giaBan));

            // Áp dụng bảng giá riêng nếu có
            if (isset($bangGiaList[$sp->id])) {
                $customPrice = $bangGiaList[$sp->id];
                if ($customPrice->loai_chiet_khau === 'FIXED_PRICE' && (float) $customPrice->gia_ban_ap_dung > 0) {
                    $giaBan = (float) $customPrice->gia_ban_ap_dung;
                    $chietKhau = max(0.0, $menhGia - $giaBan);
                } else {
                    $pt = (float) $customPrice->gia_tri_chiet_khau;
                    $chietKhau = round($menhGia * ($pt / 100), 2);
                    $giaBan = $menhGia - $chietKhau;
                }
            }

            $serviceKey = $dichVu->ma_dich_vu;
            if (!isset($servicesMap[$serviceKey])) {
                $servicesMap[$serviceKey] = [
                    'service_code' => $dichVu->ma_dich_vu,
                    'service_name' => $dichVu->ten_dich_vu,
                    'categories' => [],
                ];
            }

            $catKey = $loaiSp->ma_loai;
            if (!isset($servicesMap[$serviceKey]['categories'][$catKey])) {
                $servicesMap[$serviceKey]['categories'][$catKey] = [
                    'category_code' => $loaiSp->ma_loai,
                    'category_name' => $loaiSp->ten_loai,
                    'products' => [],
                ];
            }

            $servicesMap[$serviceKey]['categories'][$catKey]['products'][] = [
                'product_code' => $sp->ma_san_pham,
                'product_name' => $sp->ten_san_pham,
                'face_value' => $menhGia,
                'price' => $giaBan,
                'discount' => $chietKhau,
                'discount_rate' => $menhGia > 0 ? round(($chietKhau / $menhGia) * 100, 2) : 0,
            ];
        }

        // Chuẩn hóa dạng mảng tuần tự
        $result = [];
        foreach ($servicesMap as $s) {
            $categories = array_values($s['categories']);
            $s['categories'] = $categories;
            $result[] = $s;
        }

        return response()->json([
            'success' => true,
            'partner_code' => $partner->ma_dai_ly_api,
            'total_services' => count($result),
            'data' => $result,
        ]);
    }
}
