<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CategoriesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Categories\StoreLoaiSanPhamRequest;
use App\Http\Requests\Admin\Categories\UpdateLoaiSanPhamRequest;
use App\Models\DichVu;
use App\Models\LoaiSanPham;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CategoryController extends Controller
{
    /**
     * Man danh sach loai san pham.
     * Bao tri truy van, filter va du lieu dropdown modal tai controller nay
     * (dung dung pattern voi ServiceController de de bao tri chung).
     */
    public function index(Request $request): View
    {
        $query = $this->buildCategoryQuery($request);

        // NOTE: Eager-load dichVu va loaiCha de tranh N+1 khi render ten dich vu/loai cha tren bang.
        $categories = $query->with(['dichVu', 'loaiCha'])
            ->orderBy('thu_tu')
            ->orderBy('ten_loai_san_pham')
            ->paginate(10)
            ->withQueryString();

        return view('admin.categories', [
            'categories' => $categories,
            'services' => DichVu::where('trang_thai', 'hoat_dong')->orderBy('ten_dich_vu')->get(),
            // NOTE: Danh sach loai san pham dung lam tuy chon "Loai cha" trong form tao/sua.
            'parentCategories' => LoaiSanPham::where('trang_thai', 'hoat_dong')->orderBy('ten_loai_san_pham')->get(),
            'statuses' => $this->statuses(),
            'filters' => $request->only(['ma_loai_san_pham', 'ten_loai_san_pham', 'dich_vu_id', 'trang_thai']),
        ]);
    }

    /**
     * Xuat danh sach loai san pham theo dung bo loc hien tai tren man danh sach.
     */
    public function exportExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $categories = $this->buildCategoryQuery($request)
                ->with(['dichVu', 'loaiCha'])
                ->orderBy('thu_tu')
                ->get();

            $fileName = 'danh-sach-loai-san-pham-' . now()->format('Y-m-d-H-i') . '.xlsx';

            return Excel::download(
                new CategoriesExport($categories, $this->statuses()),
                $fileName
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.categories', $request->only(['ma_loai_san_pham', 'ten_loai_san_pham', 'dich_vu_id', 'trang_thai']))
                ->withErrors(['export' => 'Không thể xuất file Excel. Vui lòng thử lại sau.']);
        }
    }

    /**
     * Tao loai san pham moi tu modal admin.
     */
    public function store(StoreLoaiSanPhamRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['thu_tu'] = $validated['thu_tu'] ?? 0;

        LoaiSanPham::create($validated);

        return redirect()->route('admin.categories')->with('success', 'Tạo loại sản phẩm thành công.');
    }

    /**
     * Cap nhat thong tin loai san pham.
     */
    public function update(UpdateLoaiSanPhamRequest $request, LoaiSanPham $category): RedirectResponse
    {
        $validated = $request->validated();
        $validated['thu_tu'] = $validated['thu_tu'] ?? 0;

        $category->update($validated);

        return back()->with('success', 'Cập nhật loại sản phẩm thành công.');
    }

    /**
     * Xoa loai san pham.
     * Bang loai_san_pham khong dung SoftDeletes nen day la xoa that; bang san_pham dang khai bao
     * FK restrictOnDelete nen se tu chan xoa neu loai san pham dang co san pham lien ket — bat loi
     * de bao ro rang thay vi de loi 500 tho ra man hinh (dung pattern voi ServiceController::destroy).
     */
    public function destroy(LoaiSanPham $category): RedirectResponse
    {
        try {
            $category->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'category' => 'Không thể xóa loại sản phẩm này vì đang có sản phẩm/loại sản phẩm con liên kết.',
            ]);
        }

        return back()->with('success', 'Xóa loại sản phẩm thành công.');
    }

    /**
     * Query dung chung cho man danh sach va export de ket qua khong lech bo loc.
     */
    private function buildCategoryQuery(Request $request): Builder
    {
        $query = LoaiSanPham::query();

        if ($ma = trim((string) $request->query('ma_loai_san_pham'))) {
            $query->where('ma_loai_san_pham', 'like', "%{$ma}%");
        }

        if ($ten = trim((string) $request->query('ten_loai_san_pham'))) {
            $query->where('ten_loai_san_pham', 'like', "%{$ten}%");
        }

        if ($dichVuId = $request->query('dich_vu_id')) {
            $query->where('dich_vu_id', $dichVuId);
        }

        if ($trangThai = $request->query('trang_thai')) {
            $query->where('trang_thai', $trangThai);
        }

        return $query;
    }

    /**
     * Danh sach trang thai loai san pham dung cho filter va form.
     */
    private function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_dung' => 'Tạm dừng',
            'khoa' => 'Khóa',
        ];
    }
}
