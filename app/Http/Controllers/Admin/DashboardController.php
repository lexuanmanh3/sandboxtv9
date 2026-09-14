<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LanGoiNhaCungCap;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard tổng quan toàn hệ thống tv9tech.
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. Chỉ số Đơn hàng & Doanh thu
        $ordersToday = DonHang::whereDate('created_at', $today)->count();
        $successOrdersToday = DonHang::whereDate('created_at', $today)
            ->where('trang_thai_don_hang', 'Success')
            ->count();
        $failedOrdersToday = DonHang::whereDate('created_at', $today)
            ->where('trang_thai_don_hang', 'Failed')
            ->count();

        $revenueToday = DonHang::whereDate('created_at', $today)
            ->where('trang_thai_don_hang', 'Success')
            ->sum('gia_ban') ?? 0;

        $revenueMonth = DonHang::where('created_at', '>=', $startOfMonth)
            ->where('trang_thai_don_hang', 'Success')
            ->sum('gia_ban') ?? 0;

        $profitToday = DonHang::whereDate('created_at', $today)
            ->where('trang_thai_don_hang', 'Success')
            ->sum('loi_nhuan') ?? 0;

        $successRate = $ordersToday > 0 
            ? round(($successOrdersToday / $ordersToday) * 100, 1) 
            : 100;

        // Đơn hàng cần xử lý / đối soát thủ công
        $manualReviewCount = DonHang::whereIn('trang_thai_don_hang', ['Manual_Review', 'Pending', 'Processing'])->count();

        // 2. Chỉ số Đối tác & Nhà cung cấp
        $totalProviders = NhaCungCap::count();
        $activeProviders = NhaCungCap::where('trang_thai', 'Active')->count();
        $providers = NhaCungCap::withCount('ketNoi')->latest('id')->limit(6)->get();

        $totalPartners = DaiLyApi::count();
        $activePartners = DaiLyApi::where('trang_thai', 'active')->count();

        // 3. Danh mục & Sản phẩm
        $totalServices = DichVu::count();
        $totalProducts = SanPham::count();
        $totalUsers = User::count();

        // 4. Danh sách giao dịch mới nhất (10 đơn)
        $recentOrders = DonHang::with(['nguoiDung', 'daiLyApi', 'nhaCungCap'])
            ->latest('id')
            ->limit(10)
            ->get();

        // 5. Thống kê 7 ngày gần nhất
        $dailyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $dayStr = $day->format('Y-m-d');
            $label = $day->format('d/m');

            $count = DonHang::whereDate('created_at', $dayStr)->count();
            $rev = DonHang::whereDate('created_at', $dayStr)
                ->where('trang_thai_don_hang', 'Success')
                ->sum('gia_ban') ?? 0;

            $dailyTrends[] = [
                'date' => $label,
                'orders' => $count,
                'revenue' => (float) $rev,
            ];
        }

        return view('admin.dashboard', compact(
            'ordersToday',
            'successOrdersToday',
            'failedOrdersToday',
            'revenueToday',
            'revenueMonth',
            'profitToday',
            'successRate',
            'manualReviewCount',
            'totalProviders',
            'activeProviders',
            'providers',
            'totalPartners',
            'activePartners',
            'totalServices',
            'totalProducts',
            'totalUsers',
            'recentOrders',
            'dailyTrends'
        ));
    }
}
