<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DichVu;
use App\Models\KetNoiNhaCungCap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use App\Services\Topup\NhaCungCapResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProviderProductController extends Controller
{
    /**
     * Danh sách Sản phẩm Nhà Cung Cấp (CPT riêng, tách biệt hoàn toàn với Sản phẩm Web).
     */
    public function index(Request $request): View
    {
        $query = $this->buildQuery($request);

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 20;

        $providerProducts = $query->with([
            'nhaCungCap',
            'ketNoi',
            'sanPham.loaiSanPham',
            'sanPham.dichVu',
        ])
        ->orderByDesc('id')
        ->paginate($perPage)
        ->withQueryString();

        // Thống kê nhanh
        $stats = [
            'total' => SanPhamNhaCungCap::count(),
            'mapped' => SanPhamNhaCungCap::whereNotNull('san_pham_id')->count(),
            'unmapped' => SanPhamNhaCungCap::whereNull('san_pham_id')->count(),
            'active' => SanPhamNhaCungCap::whereIn('trang_thai', ['ACTIVE', 'hoat_dong'])->count(),
        ];

        // Danh sách sản phẩm web để phục vụ modal Ánh xạ (Map)
        $webProducts = SanPham::with(['loaiSanPham', 'dichVu'])
            ->whereIn('trang_thai', ['ACTIVE', 'hoat_dong'])
            ->orderBy('menh_gia')
            ->get();

        return view('admin.provider-products', [
            'providerProducts' => $providerProducts,
            'providers' => NhaCungCap::with('ketNoi')->orderBy('ten_ncc')->get(),
            'services' => DichVu::where('trang_thai', 'hoat_dong')->orderBy('thu_tu')->get(),
            'webProducts' => $webProducts,
            'filters' => $request->only(['q', 'nha_cung_cap_id', 'loai_dich_vu_ncc', 'ma_nha_mang_ncc', 'trang_thai_map', 'trang_thai']),
            'stats' => $stats,
        ]);
    }

    /**
     * Thêm thủ công mã sản phẩm NCC mới.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nha_cung_cap_id' => ['required', 'exists:nha_cung_cap,id'],
            'ma_san_pham_ncc' => ['required', 'string', 'max:100'],
            'ma_nha_mang_ncc' => ['nullable', 'string', 'max:50'],
            'loai_dich_vu_ncc' => ['nullable', 'string', 'max:50'],
            'loai_thue_bao' => ['nullable', 'string', 'max:30'],
            'menh_gia_ncc' => ['required', 'numeric', 'min:0'],
            'gia_nhap' => ['nullable', 'numeric', 'min:0'],
            'ty_le_chiet_khau' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'muc_uu_tien' => ['nullable', 'integer', 'min:1', 'max:999'],
            'san_pham_id' => ['nullable', 'exists:san_pham,id'],
            'trang_thai' => ['required', 'in:ACTIVE,INACTIVE,hoat_dong,tam_dung'],
        ]);

        $provider = NhaCungCap::findOrFail($validated['nha_cung_cap_id']);
        $connection = $provider->ketNoi()->first();

        if (!$connection) {
            return back()->withErrors(['provider' => "Nhà cung cấp {$provider->ten_ncc} chưa có cấu hình kết nối API."]);
        }

        // Tự động tính giá nhập nếu chưa nhập nhưng có chiết khấu
        $menhGia = (float) $validated['menh_gia_ncc'];
        $chietKhau = isset($validated['ty_le_chiet_khau']) ? (float) $validated['ty_le_chiet_khau'] : 0;
        $giaNhap = isset($validated['gia_nhap']) && $validated['gia_nhap'] !== '' ? (float) $validated['gia_nhap'] : ($menhGia * (1 - ($chietKhau / 100)));

        SanPhamNhaCungCap::updateOrCreate(
            [
                'ket_noi_nha_cung_cap_id' => $connection->id,
                'ma_san_pham_ncc' => trim($validated['ma_san_pham_ncc']),
            ],
            [
                'san_pham_id' => $validated['san_pham_id'] ?? null,
                'nha_cung_cap_id' => $provider->id,
                'ma_nha_mang_ncc' => strtolower(trim((string) ($validated['ma_nha_mang_ncc'] ?? ''))),
                'loai_dich_vu_ncc' => strtoupper(trim((string) ($validated['loai_dich_vu_ncc'] ?? 'MOBILE_TOPUP'))),
                'loai_thue_bao' => strtoupper(trim((string) ($validated['loai_thue_bao'] ?? 'PREPAID'))),
                'menh_gia_ncc' => $menhGia,
                'gia_nhap' => $giaNhap,
                'ty_le_chiet_khau' => $chietKhau,
                'muc_uu_tien' => $validated['muc_uu_tien'] ?? 100,
                'trang_thai' => $validated['trang_thai'],
                'dong_bo_luc' => now(),
            ]
        );

        return redirect()->route('admin.provider-products')->with('success', 'Thêm mới mã sản phẩm Nhà Cung Cấp thành công.');
    }

    /**
     * Cập nhật thông tin mã sản phẩm NCC và liên kết Sản phẩm Web.
     */
    public function update(Request $request, SanPhamNhaCungCap $providerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'ma_san_pham_ncc' => ['required', 'string', 'max:100'],
            'ma_nha_mang_ncc' => ['nullable', 'string', 'max:50'],
            'loai_dich_vu_ncc' => ['nullable', 'string', 'max:50'],
            'loai_thue_bao' => ['nullable', 'string', 'max:30'],
            'menh_gia_ncc' => ['required', 'numeric', 'min:0'],
            'gia_nhap' => ['nullable', 'numeric', 'min:0'],
            'ty_le_chiet_khau' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'muc_uu_tien' => ['nullable', 'integer', 'min:1', 'max:999'],
            'san_pham_id' => ['nullable', 'exists:san_pham,id'],
            'trang_thai' => ['required', 'in:ACTIVE,INACTIVE,hoat_dong,tam_dung'],
        ]);

        $menhGia = (float) $validated['menh_gia_ncc'];
        $chietKhau = isset($validated['ty_le_chiet_khau']) ? (float) $validated['ty_le_chiet_khau'] : 0;
        $giaNhap = isset($validated['gia_nhap']) && $validated['gia_nhap'] !== '' ? (float) $validated['gia_nhap'] : ($menhGia * (1 - ($chietKhau / 100)));

        $providerProduct->update([
            'ma_san_pham_ncc' => trim($validated['ma_san_pham_ncc']),
            'ma_nha_mang_ncc' => strtolower(trim((string) ($validated['ma_nha_mang_ncc'] ?? ''))),
            'loai_dich_vu_ncc' => strtoupper(trim((string) ($validated['loai_dich_vu_ncc'] ?? 'MOBILE_TOPUP'))),
            'loai_thue_bao' => strtoupper(trim((string) ($validated['loai_thue_bao'] ?? 'PREPAID'))),
            'menh_gia_ncc' => $menhGia,
            'gia_nhap' => $giaNhap,
            'ty_le_chiet_khau' => $chietKhau,
            'muc_uu_tien' => $validated['muc_uu_tien'] ?? 100,
            'san_pham_id' => $validated['san_pham_id'] ?: null,
            'trang_thai' => $validated['trang_thai'],
        ]);

        return redirect()->route('admin.provider-products')->with('success', 'Cập nhật mã sản phẩm Nhà Cung Cấp thành công.');
    }

    /**
     * Ánh xạ nhanh (Map / Unmap) sang Sản phẩm Web.
     */
    public function mapProduct(Request $request, SanPhamNhaCungCap $providerProduct): RedirectResponse
    {
        $validated = $request->validate([
            'san_pham_id' => ['nullable', 'exists:san_pham,id'],
        ]);

        $providerProduct->update([
            'san_pham_id' => $validated['san_pham_id'] ?: null,
        ]);

        $msg = $validated['san_pham_id']
            ? "Đã ánh xạ mã '{$providerProduct->ma_san_pham_ncc}' sang Sản phẩm Web thành công."
            : "Đã gỡ bỏ ánh xạ của mã '{$providerProduct->ma_san_pham_ncc}'.";

        return back()->with('success', $msg);
    }

    /**
     * Bật / Tắt trạng thái hoạt động.
     */
    public function toggleStatus(SanPhamNhaCungCap $providerProduct): RedirectResponse
    {
        $newStatus = in_array($providerProduct->trang_thai, ['ACTIVE', 'hoat_dong']) ? 'tam_dung' : 'hoat_dong';
        $providerProduct->update(['trang_thai' => $newStatus]);
        $statusText = $newStatus === 'hoat_dong' ? 'Hoạt động' : 'Tạm dừng';

        return back()->with('success', "Đã chuyển trạng thái mã '{$providerProduct->ma_san_pham_ncc}' sang {$statusText}.");
    }

    /**
     * Xóa 1 mã NCC.
     */
    public function destroy(SanPhamNhaCungCap $providerProduct): RedirectResponse
    {
        $providerProduct->delete();
        return redirect()->route('admin.provider-products')->with('success', 'Đã xóa mã sản phẩm Nhà Cung Cấp.');
    }

    /**
     * Xóa hàng loạt mã NCC đã chọn.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:san_pham_nha_cung_cap,id'],
        ]);

        $count = SanPhamNhaCungCap::whereIn('id', $validated['ids'])->delete();

        return back()->with('success', "Đã xóa thành công {$count} mã sản phẩm NCC đã chọn.");
    }

    /**
     * Đồng bộ trực tiếp từ API của Nhà Cung Cấp (Lưu thẳng vào bảng san_pham_nha_cung_cap mà không làm ô nhiễm danh mục web).
     */
    public function syncFromProvider(Request $request, NhaCungCapResolver $resolver): RedirectResponse
    {
        $validated = $request->validate([
            'nha_cung_cap_id' => ['required', 'exists:nha_cung_cap,id'],
        ]);

        $provider = NhaCungCap::with('ketNoi')->findOrFail($validated['nha_cung_cap_id']);
        $connections = $provider->ketNoi()->whereIn('trang_thai', ['ACTIVE', 'hoat_dong'])->get();

        if ($connections->isEmpty()) {
            return back()->withErrors(['provider' => "Nhà cung cấp {$provider->ten_ncc} không có kết nối API nào đang hoạt động."]);
        }

        $created = 0;
        $updated = 0;
        $mappedCount = 0;

        try {
            foreach ($connections as $connection) {
                $providerClient = $resolver->resolve($connection);
                $rawProducts = $providerClient->layDanhSachSanPham();

                foreach ($rawProducts as $item) {
                    $code = data_get($item, 'productCode');
                    $amount = data_get($item, 'amount') ?? data_get($item, 'value') ?? data_get($item, 'topupAmount');
                    $telco = strtolower((string) (data_get($item, 'telco') ?? data_get($item, 'provider')));
                    $serviceType = strtoupper((string) (data_get($item, 'serviceType') ?? ''));

                    if (!$code || !is_numeric($amount)) continue;

                    // Nhận diện phân loại Dịch vụ NCC: Nạp tiền (MOBILE_TOPUP) vs Nạp Data (TOPUP_DATA)
                    $loaiDichVuNcc = 'MOBILE_TOPUP';
                    if (str_contains(strtoupper($code), 'DATA') || str_contains($serviceType, 'DATA')) {
                        $loaiDichVuNcc = 'TOPUP_DATA';
                    } elseif (str_contains(strtoupper($code), 'CARD') || str_contains(strtoupper($code), 'PIN') || str_contains($serviceType, 'PIN')) {
                        $loaiDichVuNcc = 'PIN_CODE';
                    }

                    // Tự động tìm sản phẩm Web phù hợp để ghép nối (nếu có)
                    $matchedProduct = null;
                    if ($loaiDichVuNcc === 'TOPUP_DATA') {
                        $matchedProduct = SanPham::where('menh_gia', $amount)
                            ->whereHas('dichVu', fn ($q) => $q->where('ma_dich_vu', 'TOPUP_DATA'))
                            ->whereHas('loaiSanPham', fn ($q) => $q->whereRaw('LOWER(ten_loai_san_pham) LIKE ?', ['%' . $telco . '%']))
                            ->first();
                    } else {
                        $matchedProduct = SanPham::where('menh_gia', $amount)
                            ->whereHas('dichVu', fn ($q) => $q->where('ma_dich_vu', 'MOBILE_TOPUP'))
                            ->whereHas('loaiSanPham', fn ($q) => $q->whereRaw('LOWER(ten_loai_san_pham) LIKE ?', ['%' . $telco . '%']))
                            ->first();
                    }

                    $mapping = SanPhamNhaCungCap::firstOrNew([
                        'ket_noi_nha_cung_cap_id' => $connection->id,
                        'ma_san_pham_ncc' => $code,
                    ]);

                    $isNew = !$mapping->exists;

                    $mapping->fill([
                        'san_pham_id' => $mapping->san_pham_id ?: ($matchedProduct?->id),
                        'nha_cung_cap_id' => $provider->id,
                        'ma_nha_mang_ncc' => $telco,
                        'loai_dich_vu_ncc' => $loaiDichVuNcc,
                        'loai_thue_bao' => strtoupper((string) data_get($item, 'telcoServiceType', 'PREPAID')),
                        'menh_gia_ncc' => (float) $amount,
                        'gia_nhap' => data_get($item, 'price') ? (float) data_get($item, 'price') : ($mapping->gia_nhap ?: (float) $amount),
                        'ty_le_chiet_khau' => data_get($item, 'discount') ? (float) data_get($item, 'discount') : ($mapping->ty_le_chiet_khau ?: 0),
                        'trang_thai' => 'hoat_dong',
                        'du_lieu_mo_rong_json' => is_array($item) ? $item : [],
                        'dong_bo_luc' => now(),
                    ]);

                    $mapping->save();

                    if ($matchedProduct) {
                        $mappedCount++;
                    }

                    $isNew ? $created++ : $updated++;
                }
            }

            return back()->with('success', "Đồng bộ từ {$provider->ten_ncc} thành công: {$created} mã mới, {$updated} mã cập nhật, tự động ánh xạ {$mappedCount} mã.");
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['sync' => "Lỗi khi gọi API đồng bộ: " . $e->getMessage()]);
        }
    }



    /**
     * Tự động quét và Ánh xạ toàn bộ mã NCC chưa map vào Sản phẩm Web phù hợp.
     */
    public function autoMap(): RedirectResponse
    {
        $unmapped = SanPhamNhaCungCap::whereNull('san_pham_id')->get();
        $mappedCount = 0;

        foreach ($unmapped as $item) {
            $telco = strtolower((string) $item->ma_nha_mang_ncc);
            $amount = (float) $item->menh_gia_ncc;
            $loaiDichVu = $item->loai_dich_vu_ncc ?: 'MOBILE_TOPUP';

            $product = SanPham::where('menh_gia', $amount)
                ->whereHas('dichVu', fn ($q) => $q->where('ma_dich_vu', $loaiDichVu))
                ->whereHas('loaiSanPham', fn ($q) => $q->whereRaw('LOWER(ten_loai_san_pham) LIKE ?', ['%' . $telco . '%']))
                ->first();

            // Nếu không tìm thấy theo dịch vụ chuẩn, thử tìm theo mệnh giá + nhà mạng
            if (!$product) {
                $product = SanPham::where('menh_gia', $amount)
                    ->whereHas('loaiSanPham', fn ($q) => $q->whereRaw('LOWER(ten_loai_san_pham) LIKE ?', ['%' . $telco . '%']))
                    ->first();
            }

            if ($product) {
                $item->update(['san_pham_id' => $product->id]);
                $mappedCount++;
            }
        }

        return back()->with('success', "Đã tự động ánh xạ thành công {$mappedCount} mã sản phẩm NCC vào sản phẩm Web.");
    }

    /**
     * Xây dựng query tìm kiếm và bộ lọc.
     */
    private function buildQuery(Request $request): Builder
    {
        $query = SanPhamNhaCungCap::query();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('ma_san_pham_ncc', 'like', "%{$q}%")
                    ->orWhere('ma_nha_mang_ncc', 'like', "%{$q}%")
                    ->orWhereHas('san_pham', fn ($sq) => $sq->where('ten_san_pham', 'like', "%{$q}%"));
            });
        }

        if ($nccId = $request->query('nha_cung_cap_id')) {
            $query->where('nha_cung_cap_id', $nccId);
        }

        if ($dichVu = $request->query('loai_dich_vu_ncc')) {
            $query->where('loai_dich_vu_ncc', $dichVu);
        }

        if ($nhaMang = $request->query('ma_nha_mang_ncc')) {
            $query->where('ma_nha_mang_ncc', $nhaMang);
        }

        if ($mapStatus = $request->query('trang_thai_map')) {
            if ($mapStatus === 'da_map') {
                $query->whereNotNull('san_pham_id');
            } elseif ($mapStatus === 'chua_map') {
                $query->whereNull('san_pham_id');
            }
        }

        if ($trangThai = $request->query('trang_thai')) {
            $query->where('trang_thai', $trangThai);
        }

        return $query;
    }
}
