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
        protected KiemTraQuyenDaiLyApiService $quyenService,
        protected \App\Services\DaiLyApi\B2bPricingService $pricingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');
        $cauHinh = $partner->cauHinhApi;

        $excludedIds = (array) ($cauHinh?->san_pham_loai_tru ?? []);

        // Lấy tất cả sản phẩm đang hoạt động kèm danh mục và dịch vụ
        $sanPhams = SanPham::with(['dichVu', 'loaiSanPham'])
            ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->get();

        // Lấy bảng giá riêng của đại lý (nếu có)
        $bangGiaList = BangGiaDaiLy::where('dai_ly_api_id', $partner->id)
            ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
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

            // Áp dụng định giá thống nhất qua B2bPricingService
            $customPrice = $bangGiaList[$sp->id] ?? null;
            $priceData = $this->pricingService->tinhGia($partner, $sp, $customPrice);

            $serviceKey = $dichVu->ma_dich_vu;
            if (!isset($servicesMap[$serviceKey])) {
                $servicesMap[$serviceKey] = [
                    'service_code' => $dichVu->ma_dich_vu,
                    'service_name' => $dichVu->ten_dich_vu,
                    'categories' => [],
                ];
            }

            $catCode = $loaiSp->ma_loai_san_pham ?: ('CAT_' . $loaiSp->id);
            $catName = $loaiSp->ten_loai_san_pham ?: $catCode;

            if (!isset($servicesMap[$serviceKey]['categories'][$catCode])) {
                $servicesMap[$serviceKey]['categories'][$catCode] = [
                    'category_code' => $catCode,
                    'category_name' => $catName,
                    'products' => [],
                ];
            }

            $servicesMap[$serviceKey]['categories'][$catCode]['products'][] = [
                'product_code' => $sp->ma_san_pham,
                'product_name' => $sp->ten_san_pham,
                'face_value' => $priceData['face_value'],
                'price' => $priceData['price'],
                'discount' => $priceData['discount'],
                'discount_rate' => $priceData['discount_rate'],
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
