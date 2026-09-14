<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use Illuminate\Http\Request;
use Illuminate\View\View;

class B2bOrderManagementController extends Controller
{
    public function index(Request $request): View
    {
        $query = DonHang::with(['daiLyApi', 'sanPham', 'loaiSanPham', 'lanGoiNhaCungCap', 'khoanGiuHanMuc', 'webhookOutbox'])
            ->where('nguon_don', 'b2b');

        if ($partnerId = $request->input('dai_ly_api_id')) {
            $query->where('dai_ly_api_id', $partnerId);
        }

        if ($status = $request->input('trang_thai')) {
            $query->where('trang_thai_don_hang', $status);
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('ma_don_hang', 'like', "%{$search}%")
                    ->orWhere('ma_don_doi_tac', 'like', "%{$search}%")
                    ->orWhere('tai_khoan_nhan', 'like', "%{$search}%");
            });
        }

        if ($from = $request->input('tu_ngay')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('den_ngay')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $orders = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $partners = DaiLyApi::orderBy('ten_dai_ly_api')->get();

        return view('admin.b2b.orders', compact('orders', 'partners'));
    }

    public function show(int $id): View
    {
        $order = DonHang::with(['daiLyApi', 'sanPham', 'loaiSanPham', 'lanGoiNhaCungCap.nhaCungCap', 'khoanGiuHanMuc', 'soPhatSinhCongNo', 'webhookOutbox.lichSuGui'])
            ->where('nguon_don', 'b2b')
            ->findOrFail($id);

        return view('admin.b2b.order-detail', compact('order'));
    }
}
