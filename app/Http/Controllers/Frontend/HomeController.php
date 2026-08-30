<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private ViNguoiDungService $viService) {}

    public function index()
    {
        return view('home');
    }

    public function loaisanpham(Request $request)
    {
        // NOTE: Chỉ lấy các loại sản phẩm thuộc Dịch vụ Nạp tiền điện thoại (MOBILE_TOPUP)
        $categories = LoaiSanPham::whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->where(function ($q) {
                $q->whereHas('dichVu', function ($dq) {
                    $dq->where('ma_dich_vu', 'MOBILE_TOPUP');
                })->orWhere(function ($sub) {
                    $sub->whereIn('ma_loai_san_pham', ['VTE_TOPUP', 'VMS_TOPUP', 'VNA_TOPUP', 'VNM_TOPUP', 'WT_TOPUP', 'GMOBILE_TOPUP']);
                });
            })
            ->orderBy('thu_tu')
            ->get();

        // Fallback nếu chưa có nhóm _TOPUP
        if ($categories->isEmpty()) {
            $categories = LoaiSanPham::whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
                ->whereIn('ma_loai_san_pham', ['VTE', 'VMS', 'VNA', 'VNM', 'WT', 'GMOBILE'])
                ->orderBy('thu_tu')
                ->get();
        }

        $selectedCategoryId = $request->get('category_id');
        $selectedCategory   = $categories->firstWhere('id', $selectedCategoryId)
            ?? $categories->firstWhere('ma_loai_san_pham', 'VTE_TOPUP')
            ?? $categories->firstWhere('ma_loai_san_pham', 'VTE')
            ?? $categories->first();

        $products = collect();
        if ($selectedCategory) {
            $products = SanPham::where('loai_san_pham_id', $selectedCategory->id)
                ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
                ->orderBy('menh_gia')
                ->get();
        }

        // Lấy số dư ví của user hiện tại (tạo nếu chưa có)
        $vi = $this->viService->layHoacTao(auth()->id());

        return view('loaisanpham', [
            'categories'       => $categories,
            'selectedCategory' => $selectedCategory,
            'products'         => $products,
            'vi'               => $vi,
        ]);
    }

    /**
     * Trang lịch sử giao dịch nạp tiền.
     */
    public function lichSuGiaoDich(Request $request)
    {
        $userId = auth()->id();
        $vi     = $this->viService->layHoacTao($userId);

        $orders = DonHang::where('nguoi_dung_id', $userId)
            ->where('nguon_don', 'frontend')
            ->with(['loaiSanPham'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('topup-history', compact('orders', 'vi'));
    }
}
