<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Models\CauHinhDichVu;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use Illuminate\Http\Request;

class RoutingConfigController extends Controller
{
    public function index(Request $request)
    {
        $query = CauHinhDichVu::with(['daiLyApi', 'dichVu', 'sanPham', 'nhaCungCap', 'loaiSanPham']);

        if ($request->filled('dai_ly_api_id')) {
            $query->where('dai_ly_ap_dung_id', $request->dai_ly_api_id);
        }

        if ($request->filled('nha_cung_cap_id')) {
            $query->where('nha_cung_cap_id', $request->nha_cung_cap_id);
        }

        if ($request->filled('trang_thai')) {
            if ($request->trang_thai === 'ACTIVE') {
                $query->where('dang_mo', true);
            } elseif ($request->trang_thai === 'PAUSED') {
                $query->where('dang_mo', false);
            }
        }

        $configs = $query->orderBy('muc_uu_tien', 'asc')->paginate(20)->withQueryString();
        
        $partners = DaiLyApi::where('trang_thai', 'hoat_dong')->get();
        $providers = NhaCungCap::all();
        $services = DichVu::where('trang_thai', 'hoat_dong')->get();
        $categories = LoaiSanPham::all();
        $products = SanPham::where('trang_thai', 'hoat_dong')->get();

        return view('admin.b2b.routing-configs.index', compact('configs', 'partners', 'providers', 'services', 'categories', 'products'));
    }

    public function create()
    {
        return redirect()->route('admin.b2b.routing-configs.index', ['action' => 'create']);
    }

    public function store(\App\Http\Requests\Admin\B2B\StoreRoutingConfigRequest $request)
    {
        $validated = $request->validated();
        $isDangMo = $request->has('dang_mo') ? $request->boolean('dang_mo') : (($request->input('trang_thai') ?? 'ACTIVE') === 'ACTIVE');
        $tenCauHinh = $request->input('ten_cau_hinh') ?: ($request->input('mo_ta') ?: 'Tuyến cấu hình tự động');

        $sanPhamIds = array_values(array_filter(array_map('intval', (array) ($request->input('san_pham_ids') ?? []))));
        $singleSanPhamId = count($sanPhamIds) === 1 ? $sanPhamIds[0] : ($validated['san_pham_id'] ?? null);
        $danhSachSanPhamId = !empty($sanPhamIds) ? $sanPhamIds : ($singleSanPhamId ? [$singleSanPhamId] : null);
        $moTa = $request->input('mo_ta') ?? null;

        CauHinhDichVu::create([
            'dai_ly_ap_dung_id' => $validated['dai_ly_api_id'] ?? null,
            'dich_vu_id' => $validated['dich_vu_id'],
            'loai_san_pham_id' => $validated['loai_san_pham_id'] ?? null,
            'san_pham_id' => $singleSanPhamId,
            'danh_sach_san_pham_id' => $danhSachSanPhamId,
            'nha_cung_cap_id' => $validated['nha_cung_cap_id'],
            'muc_uu_tien' => $validated['muc_uu_tien'] ?? 1,
            'dang_mo' => $isDangMo,
            'ket_thuc_cau_hinh' => $request->boolean('ket_thuc_cau_hinh'),
            'che_do_chay' => $validated['che_do_chay'] ?? 'api',
            'ten_cau_hinh' => $tenCauHinh,
            'mo_ta' => $moTa,
            'timeout_he_thong_giay' => $request->input('timeout_he_thong_giay', 30),
            'timeout_gui_ncc_giay' => $request->input('timeout_gui_ncc_giay', 25),
            'thoi_gian_tra_ket_qua_giay' => $request->input('thoi_gian_tra_ket_qua_giay', 60),
        ]);

        return redirect()->route('admin.b2b.routing-configs.index')->with('success', 'Thêm tuyến dịch vụ thành công.');
    }

    public function edit(CauHinhDichVu $routing_config)
    {
        return redirect()->route('admin.b2b.routing-configs.index', ['edit' => $routing_config->id]);
    }

    public function update(\App\Http\Requests\Admin\B2B\StoreRoutingConfigRequest $request, CauHinhDichVu $routing_config)
    {
        $validated = $request->validated();
        $isDangMo = $request->has('dang_mo') ? $request->boolean('dang_mo') : (($request->input('trang_thai') ?? 'ACTIVE') === 'ACTIVE');
        $tenCauHinh = $request->input('ten_cau_hinh') ?: ($request->input('mo_ta') ?: $routing_config->ten_cau_hinh);

        $sanPhamIds = array_values(array_filter(array_map('intval', (array) ($request->input('san_pham_ids') ?? []))));
        $singleSanPhamId = count($sanPhamIds) === 1 ? $sanPhamIds[0] : ($validated['san_pham_id'] ?? null);
        $danhSachSanPhamId = !empty($sanPhamIds) ? $sanPhamIds : ($singleSanPhamId ? [$singleSanPhamId] : null);
        $moTa = $request->input('mo_ta') ?? null;

        $routing_config->update([
            'dai_ly_ap_dung_id' => $validated['dai_ly_api_id'] ?? null,
            'dich_vu_id' => $validated['dich_vu_id'],
            'loai_san_pham_id' => $validated['loai_san_pham_id'] ?? null,
            'san_pham_id' => $singleSanPhamId,
            'danh_sach_san_pham_id' => $danhSachSanPhamId,
            'nha_cung_cap_id' => $validated['nha_cung_cap_id'],
            'muc_uu_tien' => $validated['muc_uu_tien'] ?? 1,
            'dang_mo' => $isDangMo,
            'ket_thuc_cau_hinh' => $request->boolean('ket_thuc_cau_hinh'),
            'che_do_chay' => $validated['che_do_chay'] ?? 'api',
            'ten_cau_hinh' => $tenCauHinh,
            'mo_ta' => $moTa,
            'timeout_he_thong_giay' => $request->input('timeout_he_thong_giay', 30),
            'timeout_gui_ncc_giay' => $request->input('timeout_gui_ncc_giay', 25),
            'thoi_gian_tra_ket_qua_giay' => $request->input('thoi_gian_tra_ket_qua_giay', 60),
        ]);

        return redirect()->route('admin.b2b.routing-configs.index')->with('success', 'Cập nhật tuyến dịch vụ thành công.');
    }

    public function destroy(CauHinhDichVu $routing_config)
    {
        try {
            $routing_config->delete();
            return redirect()->route('admin.b2b.routing-configs.index')->with('success', 'Xóa tuyến dịch vụ thành công.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.b2b.routing-configs.index')->with('error', 'Không thể xóa tuyến dịch vụ: ' . $e->getMessage());
        }
    }
}
