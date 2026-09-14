<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Models\KhoanGiuHanMuc;
use App\Models\LanGoiNhaCungCap;
use Illuminate\Http\Request;

class SystemOperationController extends Controller
{
    public function providerCalls(Request $request)
    {
        $query = LanGoiNhaCungCap::with(['nhaCungCap', 'donHang', 'ketNoiNhaCungCap']);

        if ($request->filled('ma_don_hang')) {
            $query->whereHas('donHang', function($q) use ($request) {
                $q->where('ma_don_hang', 'like', '%' . $request->ma_don_hang . '%');
            });
        }

        if ($request->filled('partner_ref_id')) {
            $query->where('partner_ref_id', 'like', '%' . $request->partner_ref_id . '%');
        }

        if ($request->filled('trang_thai')) {
            $query->where('trang_thai', $request->trang_thai);
        }

        $logs = $query->orderByDesc('id')->paginate(30)->withQueryString();

        return view('admin.b2b.operations.provider-calls', compact('logs'));
    }

    public function creditHolds(Request $request)
    {
        $query = KhoanGiuHanMuc::with(['daiLyApi', 'donHang']);

        if ($request->filled('dai_ly_api_id')) {
            $query->where('dai_ly_api_id', $request->dai_ly_api_id);
        }

        if ($request->filled('trang_thai')) {
            $query->where('trang_thai', $request->trang_thai);
        }

        $holds = $query->orderByDesc('id')->paginate(30)->withQueryString();
        
        return view('admin.b2b.operations.credit-holds', compact('holds'));
    }

    public function releaseCreditHold(Request $request, $id)
    {
        $hold = KhoanGiuHanMuc::findOrFail($id);
        
        if ($hold->trang_thai !== 'DANG_GIU') {
            return back()->with('error', 'Khoản giữ này đã được xử lý (Giải phóng hoặc Chốt).');
        }

        // TODO: Gọi Service giải phóng hạn mức (B2bCreditService)
        $hold->update([
            'trang_thai' => 'DA_GIAI_PHONG',
            'giai_phong_luc' => now(),
            'ly_do_giai_phong' => 'Nhả hạn mức thủ công bởi Admin'
        ]);

        return back()->with('success', 'Đã giải phóng khoản giữ hạn mức thành công.');
    }
}
