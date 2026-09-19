<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Jobs\CheckMobileTopupStatusJob;
use App\Models\KhoanGiuHanMuc;
use App\Models\LanGoiNhaCungCap;
use App\Services\DaiLyApi\B2bCreditService;
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

    /**
     * Tra cứu lại trạng thái thủ công từ Admin cho lần gọi NCC.
     */
    public function recheckProviderCall(Request $request, $id)
    {
        $call = LanGoiNhaCungCap::with('donHang')->findOrFail($id);

        if ($call->ket_qua_xac_dinh === 'SUCCESS') {
            return back()->with('info', 'Lần gọi này đã xác nhận thành công trước đó.');
        }

        if ($call->donHang && $call->donHang->trang_thai_don_hang === 'SUCCESS') {
            return back()->with('info', 'Đơn hàng đã hoàn tất thành công.');
        }

        CheckMobileTopupStatusJob::dispatch($call->id);

        return back()->with('success', "Đã gửi yêu cầu tra cứu lại trạng thái cho lần gọi #{$call->id} tới Nhà cung cấp.");
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
        $hold = KhoanGiuHanMuc::with('donHang')->findOrFail($id);
        
        // Trạng thái hợp lệ của khoản giữ là HOLDING
        if ($hold->trang_thai !== 'HOLDING') {
            return back()->with('error', 'Khoản giữ này đã được xử lý (đã Chốt hoặc đã Giải phóng).');
        }

        // NGUYÊN TẮC: Không nhả hạn mức khi đơn hàng đang chờ phản hồi từ NCC
        if ($hold->donHang && in_array(strtoupper((string) $hold->donHang->trang_thai_don_hang), ['PROVIDER_PENDING', 'PROCESSING'], true)) {
            return back()->with('error', 'Không thể nhả hạn mức khi đơn hàng đang chờ phản hồi từ Nhà cung cấp. Cần tra cứu kết quả cuối hoặc xử lý đối soát trước!');
        }

        if ($hold->donHang) {
            app(B2bCreditService::class)->giaiPhongKhoanGiu(
                $hold->donHang,
                'Nhả hạn mức thủ công bởi Admin #' . (auth()->id() ?? 'system')
            );
        } else {
            $hold->update([
                'trang_thai' => 'RELEASED',
                'giai_phong_luc' => now(),
                'ly_do_giai_phong' => 'Nhả hạn mức thủ công bởi Admin #' . (auth()->id() ?? 'system'),
            ]);
        }

        return back()->with('success', 'Đã giải phóng khoản giữ hạn mức an toàn.');
    }
}
