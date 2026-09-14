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

class ProductController extends Controller
{
    /**
     * Danh sách sản phẩm kèm thông tin dịch vụ, loại sản phẩm và ánh xạ NCC.
     */
    public function index(Request $request): View
    {
        $query = $this->buildQuery($request);

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 10;

        $products = $query->with([
            'dichVu',
            'loaiSanPham',
            'nhaCungCapMappings.nhaCungCap',
            'nhaCungCapMappings.ketNoi',
        ])
        ->orderBy('thu_tu')
        ->orderBy('menh_gia')
        ->paginate($perPage)
        ->withQueryString();

        return view('admin.products', [
            'products' => $products,
            'services' => DichVu::orderBy('thu_tu')->get(),
            'categories' => LoaiSanPham::orderBy('thu_tu')->get(),
            'providers' => NhaCungCap::with('ketNoi')->orderBy('ten_ncc')->get(),
            'filters' => $request->only(['q', 'dich_vu_id', 'loai_san_pham_id', 'trang_thai']),
            'statuses' => $this->statuses(),
        ]);
    }

    /**
     * Thêm mới sản phẩm.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ma_san_pham' => ['required', 'string', 'max:50', 'unique:san_pham,ma_san_pham'],
            'ten_san_pham' => ['required', 'string', 'max:150'],
            'dich_vu_id' => ['nullable', 'exists:dich_vu,id'],
            'loai_san_pham_id' => ['required', 'exists:loai_san_pham,id'],
            'menh_gia' => ['required', 'numeric', 'min:0'],
            'don_vi' => ['nullable', 'string', 'max:20'],
            'thu_tu' => ['nullable', 'integer', 'min:0'],
            'trang_thai' => ['required', 'in:ACTIVE,INACTIVE,hoat_dong,tam_dung'],
            'hinh_anh' => ['nullable', 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['ma_san_pham'] = strtoupper(trim($validated['ma_san_pham']));
        $validated['thu_tu'] = $validated['thu_tu'] ?? 0;
        $validated['don_vi'] = $validated['don_vi'] ?? 'VND';

        if (empty($validated['dich_vu_id']) && !empty($validated['loai_san_pham_id'])) {
            $cat = LoaiSanPham::find($validated['loai_san_pham_id']);
            if ($cat && $cat->dich_vu_id) {
                $validated['dich_vu_id'] = $cat->dich_vu_id;
            }
        }

        SanPham::create($validated);

        return redirect()->route('admin.products')->with('success', 'Thêm mới sản phẩm thành công.');
    }

    /**
     * Cập nhật thông tin sản phẩm.
     */
    public function update(Request $request, SanPham $product): RedirectResponse
    {
        $validated = $request->validate([
            'ma_san_pham' => ['required', 'string', 'max:50', 'unique:san_pham,ma_san_pham,' . $product->id],
            'ten_san_pham' => ['required', 'string', 'max:150'],
            'dich_vu_id' => ['nullable', 'exists:dich_vu,id'],
            'loai_san_pham_id' => ['required', 'exists:loai_san_pham,id'],
            'menh_gia' => ['required', 'numeric', 'min:0'],
            'don_vi' => ['nullable', 'string', 'max:20'],
            'thu_tu' => ['nullable', 'integer', 'min:0'],
            'trang_thai' => ['required', 'in:ACTIVE,INACTIVE,hoat_dong,tam_dung'],
            'hinh_anh' => ['nullable', 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['ma_san_pham'] = strtoupper(trim($validated['ma_san_pham']));

        if (empty($validated['dich_vu_id']) && !empty($validated['loai_san_pham_id'])) {
            $cat = LoaiSanPham::find($validated['loai_san_pham_id']);
            if ($cat && $cat->dich_vu_id) {
                $validated['dich_vu_id'] = $cat->dich_vu_id;
            }
        }

        $product->update($validated);

        return redirect()->route('admin.products')->with('success', 'Cập nhật sản phẩm thành công.');
    }

    /**
     * Xóa sản phẩm.
     */
    public function destroy(SanPham $product): RedirectResponse
    {
        try {
            $product->nhaCungCapMappings()->delete();
            $product->delete();
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors([
                'product' => 'Không thể xóa sản phẩm này do đã phát sinh đơn hàng hoặc giao dịch liên quan.',
            ]);
        }

        return redirect()->route('admin.products')->with('success', 'Xóa sản phẩm thành công.');
    }

    /**
     * Xóa nhiều sản phẩm cùng lúc.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:san_pham,id'],
        ]);

        $ids = $validated['ids'];
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $product = SanPham::find($id);
                if ($product) {
                    $product->nhaCungCapMappings()->delete();
                    $product->delete();
                    $deleted++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        if ($failed > 0) {
            return back()->with('success', "Đã xóa thành công {$deleted} sản phẩm. ({$failed} sản phẩm không thể xóa do có ràng buộc dữ liệu).");
        }

        return back()->with('success', "Đã xóa thành công {$deleted} sản phẩm đã chọn.");
    }

    /**
     * Gán / Cập nhật mã ánh xạ Nhà cung cấp cho 1 sản phẩm cụ thể.
     */
    public function saveProviderMapping(Request $request, SanPham $product): RedirectResponse
    {
        $validated = $request->validate([
            'nha_cung_cap_id' => ['required', 'exists:nha_cung_cap,id'],
            'ma_san_pham_ncc' => ['required', 'string', 'max:100'],
            'gia_nhap' => ['nullable', 'numeric', 'min:0'],
            'ty_le_chiet_khau' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'muc_uu_tien' => ['nullable', 'integer', 'min:1', 'max:999'],
            'trang_thai' => ['required', 'in:ACTIVE,INACTIVE,hoat_dong,tam_dung'],
        ]);

        $provider = NhaCungCap::findOrFail($validated['nha_cung_cap_id']);
        $connection = $provider->ketNoi()->first();

        if (!$connection) {
            return back()->withErrors([
                'provider' => "Nhà cung cấp {$provider->ten_ncc} chưa có kết nối API.",
            ]);
        }

        SanPhamNhaCungCap::updateOrCreate(
            [
                'san_pham_id' => $product->id,
                'ket_noi_nha_cung_cap_id' => $connection->id,
            ],
            [
                'nha_cung_cap_id' => $provider->id,
                'ma_san_pham_ncc' => trim($validated['ma_san_pham_ncc']),
                'menh_gia_ncc' => $product->menh_gia,
                'gia_nhap' => $validated['gia_nhap'] ?? ($product->menh_gia * (1 - (($validated['ty_le_chiet_khau'] ?? 0) / 100))),
                'ty_le_chiet_khau' => $validated['ty_le_chiet_khau'] ?? 0,
                'muc_uu_tien' => $validated['muc_uu_tien'] ?? 100,
                'trang_thai' => $validated['trang_thai'],
                'dong_bo_luc' => now(),
            ]
        );

        return back()->with('success', "Đã lưu mã NCC '{$validated['ma_san_pham_ncc']}' cho sản phẩm {$product->ten_san_pham}.");
    }

    /**
     * Xóa 1 mã ánh xạ Nhà cung cấp.
     */
    public function deleteProviderMapping(SanPhamNhaCungCap $mapping): RedirectResponse
    {
        $mapping->delete();
        return back()->with('success', 'Đã xóa cấu hình mã nhà cung cấp.');
    }

    /**
     * Tự động sinh & Đồng bộ toàn bộ mã sản phẩm cho Nhà Cung Cấp.
     * (Hỗ trợ AppotaPay, HPZ và các quy tắc chuẩn).
     */
    public function autoGenerateMappings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nha_cung_cap_id' => ['required', 'exists:nha_cung_cap,id'],
            'telco_group' => ['nullable', 'string'],
        ]);

        $provider = NhaCungCap::findOrFail($validated['nha_cung_cap_id']);
        $connection = $provider->ketNoi()->first();

        if (!$connection) {
            return back()->withErrors(['provider' => 'Nhà cung cấp chưa có kết nối API.']);
        }

        $providerCode = strtoupper($provider->ma_ncc);
        $products = SanPham::with(['loaiSanPham', 'dichVu'])->get();

        $count = 0;
        DB::transaction(function () use ($products, $provider, $connection, $providerCode, &$count) {
            foreach ($products as $p) {
                if ($p->menh_gia <= 0) continue;

                $cat = $p->loaiSanPham;
                $catCode = strtoupper($cat?->ma_loai_san_pham ?? '');
                $catName = strtolower($cat?->ten_loai_san_pham ?? '');
                $serviceCode = strtoupper($p->dichVu?->ma_dich_vu ?? '');

                // Xác định tên nhà mạng chuẩn
                $telcoKey = 'viettel';
                if (str_contains($catCode, 'VTE') || str_contains($catName, 'viettel')) {
                    $telcoKey = 'viettel';
                } elseif (str_contains($catCode, 'VMS') || str_contains($catName, 'mobi')) {
                    $telcoKey = 'mobifone';
                } elseif (str_contains($catCode, 'VNA') || str_contains($catName, 'vina')) {
                    $telcoKey = 'vinaphone';
                } elseif (str_contains($catCode, 'VNM') || str_contains($catName, 'vietnamobile')) {
                    $telcoKey = 'vietnamobile';
                } elseif (str_contains($catCode, 'GMOBILE') || str_contains($catName, 'gmobile')) {
                    $telcoKey = 'gmobile';
                } elseif (str_contains($catCode, 'WT') || str_contains($catName, 'wintel')) {
                    $telcoKey = 'wintel';
                } elseif (str_contains($catCode, 'GARENA') || str_contains($catName, 'garena')) {
                    $telcoKey = 'garena';
                } elseif (str_contains($catCode, 'ZING') || str_contains($catName, 'zing')) {
                    $telcoKey = 'zing';
                } elseif (str_contains($catCode, 'VTC') || str_contains($catName, 'vtc')) {
                    $telcoKey = 'vtc';
                } else {
                    continue;
                }

                $menhGiaK = (int) ($p->menh_gia / 1000);
                $isCard = str_contains($serviceCode, 'PIN') || str_contains($catCode, 'PIN');
                $isData = str_contains($serviceCode, 'DATA') || str_contains($catCode, 'DATA');

                // Sinh mã tương ứng theo NCC và loại Dịch vụ
                if ($providerCode === 'APPOTAPAY') {
                    if ($isData) {
                        $maNcc = "data_{$telcoKey}_{$menhGiaK}";
                    } elseif ($isCard) {
                        $maNcc = "card_{$telcoKey}_{$menhGiaK}";
                    } else {
                        // Nạp tiền điện thoại trực tiếp: viettel_10, viettel_20, viettel_50...
                        $maNcc = "{$telcoKey}_{$menhGiaK}";
                    }
                } elseif ($providerCode === 'HPZ') {
                    $hpzTelco = match($telcoKey) {
                        'viettel' => 'VT',
                        'mobifone' => 'MB',
                        'vinaphone' => 'VN',
                        'vietnamobile' => 'VNM',
                        default => strtoupper($telcoKey),
                    };
                    $prefix = $isCard ? 'PIN_' : ($isData ? 'DATA_' : '');
                    $maNcc = "{$prefix}{$hpzTelco}{$menhGiaK}";
                } else {
                    $prefix = $isCard ? 'PIN_' : ($isData ? 'DATA_' : 'TOPUP_');
                    $maNcc = "{$prefix}" . strtoupper($telcoKey) . "_{$p->menh_gia}";
                }

                // Xử lý chống trùng lặp uq_sp_ncc_ket_noi_ma trên cùng kết nối
                $existingMapping = SanPhamNhaCungCap::where('ket_noi_nha_cung_cap_id', $connection->id)
                    ->where('ma_san_pham_ncc', $maNcc)
                    ->first();

                if ($existingMapping && $existingMapping->san_pham_id != $p->id) {
                    if (str_contains($p->ma_san_pham, 'MOBILE_TOPUP') || $p->id > $existingMapping->san_pham_id) {
                        $existingMapping->update([
                            'san_pham_id' => $p->id,
                            'nha_cung_cap_id' => $provider->id,
                            'ma_nha_mang_ncc' => $telcoKey,
                            'menh_gia_ncc' => $p->menh_gia,
                            'dong_bo_luc' => now(),
                        ]);
                        $count++;
                    }
                    continue;
                }

                SanPhamNhaCungCap::updateOrCreate(
                    [
                        'san_pham_id' => $p->id,
                        'ket_noi_nha_cung_cap_id' => $connection->id,
                    ],
                    [
                        'nha_cung_cap_id' => $provider->id,
                        'ma_san_pham_ncc' => $maNcc,
                        'ma_nha_mang_ncc' => $telcoKey,
                        'menh_gia_ncc' => $p->menh_gia,
                        'muc_uu_tien' => 100,
                        'trang_thai' => 'hoat_dong',
                        'dong_bo_luc' => now(),
                    ]
                );

                $count++;
            }
        });

        return back()->with('success', "Đã tự động tạo & đồng bộ {$count} mã sản phẩm cho nhà cung cấp {$provider->ten_ncc}!");
    }

    /**
     * Lấy và đồng bộ sản phẩm trực tiếp từ API của Nhà Cung Cấp theo link/môi trường lựa chọn.
     */
    public function syncFromProvider(Request $request, NhaCungCapResolver $resolver): RedirectResponse
    {
        $validated = $request->validate([
            'nha_cung_cap_id' => ['required', 'exists:nha_cung_cap,id'],
            'api_url' => ['nullable', 'string', 'max:255'],
            'auto_create_category' => ['nullable', 'boolean'],
            'auto_create_product' => ['nullable', 'boolean'],
        ]);

        $provider = NhaCungCap::findOrFail($validated['nha_cung_cap_id']);
        $connection = $provider->ketNoi()->first();

        if (!$connection) {
            return back()->withErrors(['provider' => "Nhà cung cấp {$provider->ten_ncc} chưa có cấu hình kết nối API."]);
        }

        // Nếu người dùng chọn / nhập link API endpoint tùy chỉnh thì gán vào kết nối
        if (!empty($validated['api_url'])) {
            $customUrl = trim($validated['api_url']);
            $connection->api_url = $customUrl;
            $connection->base_url = $customUrl;
        }

        $autoCreateCat = $request->boolean('auto_create_category', true);
        $autoCreateProd = $request->boolean('auto_create_product', true);

        try {
            $adapter = $resolver->resolve($connection);
            $items = $adapter->layDanhSachSanPham($validated['api_url'] ?? null);
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors([
                'sync' => "Không thể lấy danh sách sản phẩm từ API của {$provider->ten_ncc}: " . $e->getMessage(),
            ]);
        }

        if ($items->isEmpty()) {
            return back()->withErrors([
                'sync' => "Nhà cung cấp {$provider->ten_ncc} không trả về danh sách sản phẩm nào từ link API đã chọn.",
            ]);
        }

        $service = DichVu::whereIn('ma_dich_vu', ['MOBILE_TOPUP', 'TOPUP'])->first()
            ?? DichVu::firstOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], ['ten_dich_vu' => 'Nạp tiền điện thoại', 'trang_thai' => 'hoat_dong', 'thu_tu' => 1]);

        $createdProd = 0;
        $createdCat = 0;
        $createdMapping = 0;
        $updatedMapping = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $items, $provider, $connection, $service, $autoCreateCat, $autoCreateProd,
            &$createdProd, &$createdCat, &$createdMapping, &$updatedMapping, &$skipped
        ) {
            foreach ($items as $item) {
                $code = (string) (data_get($item, 'productCode') ?? data_get($item, 'code'));
                $amount = data_get($item, 'amount') ?? data_get($item, 'value') ?? data_get($item, 'topupAmount');
                $telcoRaw = data_get($item, 'telco') ?? data_get($item, 'provider') ?? data_get($item, 'telco_code') ?? 'viettel';
                $telco = strtolower((string) $telcoRaw);
                $serviceType = data_get($item, 'serviceType', 'MOBILE_TOPUP');
                $telcoServiceType = strtoupper((string) data_get($item, 'telcoServiceType', 'PREPAID'));

                if (!$code || !is_numeric($amount) || (float) $amount <= 0) {
                    $skipped++;
                    continue;
                }

                $amount = (float) $amount;

                // Chuẩn hóa tên & mã nhà mạng
                $telcoName = match(true) {
                    str_contains($telco, 'viettel') => 'Viettel',
                    str_contains($telco, 'mobi') => 'Mobifone',
                    str_contains($telco, 'vina') => 'Vinaphone',
                    str_contains($telco, 'vnm') || str_contains($telco, 'vietnamobile') => 'Vietnamobile',
                    str_contains($telco, 'gmobile') || str_contains($telco, 'beeline') => 'Gmobile',
                    str_contains($telco, 'wintel') || str_contains($telco, 'wt') => 'Wintel',
                    str_contains($telco, 'garena') => 'Garena',
                    str_contains($telco, 'zing') => 'Zing',
                    str_contains($telco, 'gate') => 'Gate',
                    default => ucfirst($telco),
                };

                $telcoCode = match(true) {
                    str_contains($telco, 'viettel') => 'VTE_TOPUP',
                    str_contains($telco, 'mobi') => 'VMS_TOPUP',
                    str_contains($telco, 'vina') => 'VNA_TOPUP',
                    str_contains($telco, 'vnm') || str_contains($telco, 'vietnamobile') => 'VNM_TOPUP',
                    str_contains($telco, 'gmobile') || str_contains($telco, 'beeline') => 'GMOBILE_TOPUP',
                    str_contains($telco, 'wintel') || str_contains($telco, 'wt') => 'WT_TOPUP',
                    default => strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $telco)) . '_TOPUP',
                };

                // 1. Tìm hoặc tạo Loại sản phẩm (Category)
                $category = LoaiSanPham::where('ma_loai_san_pham', $telcoCode)
                    ->orWhere('ten_loai_san_pham', $telcoName)
                    ->first();

                if (!$category && $autoCreateCat) {
                    $category = LoaiSanPham::create([
                        'dich_vu_id' => $service->id,
                        'ma_loai_san_pham' => $telcoCode,
                        'ten_loai_san_pham' => $telcoName,
                        'trang_thai' => 'hoat_dong',
                        'thu_tu' => 10,
                    ]);
                    $createdCat++;
                }

                if (!$category) {
                    $skipped++;
                    continue;
                }

                // 2. Tìm hoặc tạo Mapping & SanPham (Bảo vệ ràng buộc UNIQUE ket_noi_nha_cung_cap_id + san_pham_id)
                $mapping = SanPhamNhaCungCap::where('ket_noi_nha_cung_cap_id', $connection->id)
                    ->where('ma_san_pham_ncc', $code)
                    ->first();

                if ($mapping) {
                    $product = SanPham::find($mapping->san_pham_id);
                } else {
                    $prodCode = 'TOPUP_' . $category->ma_loai_san_pham . '_' . strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $code));
                    $product = SanPham::where('ma_san_pham', $prodCode)->first();

                    if (!$product) {
                        // Tìm SP cùng loại và mệnh giá chưa gắn mapping cho kết nối này
                        $product = SanPham::where('loai_san_pham_id', $category->id)
                            ->where('menh_gia', $amount)
                            ->whereDoesntHave('nhaCungCapMappings', function ($mq) use ($connection) {
                                $mq->where('ket_noi_nha_cung_cap_id', $connection->id);
                            })
                            ->first();
                    }

                    if (!$product && $autoCreateProd) {
                        $product = SanPham::create([
                            'ma_san_pham' => $prodCode,
                            'dich_vu_id' => $service->id,
                            'loai_san_pham_id' => $category->id,
                            'ten_san_pham' => $telcoName . ' ' . number_format($amount, 0, ',', '.') . 'đ',
                            'menh_gia' => $amount,
                            'don_vi' => 'VND',
                            'trang_thai' => 'hoat_dong',
                            'thu_tu' => (int) ($amount / 1000),
                        ]);
                        $createdProd++;
                    }

                    if (!$product) {
                        $skipped++;
                        continue;
                    }

                    $mapping = new SanPhamNhaCungCap([
                        'ket_noi_nha_cung_cap_id' => $connection->id,
                        'ma_san_pham_ncc' => $code,
                    ]);
                }

                $wasNew = !$mapping->exists;
                $mapping->fill([
                    'san_pham_id' => $product->id,
                    'nha_cung_cap_id' => $provider->id,
                    'ma_nha_mang_ncc' => $telco,
                    'loai_dich_vu_ncc' => $serviceType,
                    'loai_thue_bao' => $telcoServiceType,
                    'menh_gia_ncc' => $amount,
                    'muc_uu_tien' => 100,
                    'trang_thai' => 'hoat_dong',
                    'du_lieu_mo_rong_json' => $item,
                    'dong_bo_luc' => now(),
                ])->save();

                if ($wasNew) {
                    $createdMapping++;
                } else {
                    $updatedMapping++;
                }
            }
        });

        $msg = "Đã lấy từ API {$provider->ten_ncc} thành công! Mới tạo: {$createdProd} sản phẩm, {$createdCat} loại SP, {$createdMapping} mã ánh xạ NCC (Cập nhật: {$updatedMapping}).";
        return back()->with('success', $msg);
    }

    /**
     * Bật / tắt nhanh trạng thái sản phẩm.
     */
    public function toggleStatus(SanPham $product): RedirectResponse
    {
        $newStatus = in_array($product->trang_thai, ['hoat_dong', 'ACTIVE']) ? 'tam_dung' : 'hoat_dong';
        $product->update(['trang_thai' => $newStatus]);
        return back()->with('success', "Đã đổi trạng thái sản phẩm '{$product->ten_san_pham}'.");
    }

    /**
     * Bộ lọc tìm kiếm sản phẩm.
     */
    private function buildQuery(Request $request): Builder
    {
        $query = SanPham::query();

        if ($keyword = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('ma_san_pham', 'like', "%{$keyword}%")
                  ->orWhere('ten_san_pham', 'like', "%{$keyword}%");
            });
        }

        if ($dichVuId = $request->query('dich_vu_id')) {
            $query->where('dich_vu_id', $dichVuId);
        }

        if ($loaiSpId = $request->query('loai_san_pham_id')) {
            $query->where('loai_san_pham_id', $loaiSpId);
        }

        if ($trangThai = $request->query('trang_thai')) {
            if (in_array($trangThai, ['hoat_dong', 'ACTIVE'])) {
                $query->whereIn('trang_thai', ['hoat_dong', 'ACTIVE']);
            } elseif (in_array($trangThai, ['tam_dung', 'INACTIVE'])) {
                $query->whereIn('trang_thai', ['tam_dung', 'INACTIVE']);
            } else {
                $query->where('trang_thai', $trangThai);
            }
        }

        return $query;
    }

    /**
     * Trạng thái sản phẩm.
     */
    private function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_dung' => 'Tạm dừng',
            'khoa' => 'Khóa',
            'ACTIVE' => 'Hoạt động',
            'INACTIVE' => 'Tạm dừng',
        ];
    }
}
