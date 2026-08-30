<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ServicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Services\StoreDichVuRequest;
use App\Http\Requests\Admin\Services\UpdateDichVuRequest;
use App\Models\DichVu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ServiceController extends Controller
{
    /**
     * Man danh sach dich vu.
     * Bao tri truy van, filter va du lieu dropdown modal tai controller nay
     * (dung dung pattern voi UserAccountController de de bao tri chung).
     */
    public function index(Request $request): View
    {
        $query = $this->buildServiceQuery($request);

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 10;

        $services = $query->orderBy('thu_tu')->orderBy('ten_dich_vu')->paginate($perPage)->withQueryString();

        return view('admin.services', [
            'services' => $services,
            'statuses' => $this->statuses(),
            'filters' => $request->only(['ma_dich_vu', 'ten_dich_vu', 'trang_thai']),
        ]);
    }

    /**
     * Xuat danh sach dich vu theo dung bo loc hien tai tren man danh sach.
     */
    public function exportExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $services = $this->buildServiceQuery($request)
                ->orderBy('thu_tu')
                ->get();

            $fileName = 'danh-sach-dich-vu-' . now()->format('Y-m-d-H-i') . '.xlsx';

            return Excel::download(
                new ServicesExport($services, $this->statuses()),
                $fileName
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.services', $request->only(['ma_dich_vu', 'ten_dich_vu', 'trang_thai']))
                ->withErrors(['export' => 'Không thể xuất file Excel. Vui lòng thử lại sau.']);
        }
    }

    /**
     * Tao dich vu moi tu modal admin.
     */
    public function store(StoreDichVuRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // NOTE: thu_tu de trong thi mac dinh 0 (hien thi truoc nhat) giong default cua migration.
        $validated['thu_tu'] = $validated['thu_tu'] ?? 0;

        DichVu::create($validated);

        return redirect()->route('admin.services')->with('success', 'Tạo dịch vụ thành công.');
    }

    /**
     * Cap nhat thong tin dich vu.
     */
    public function update(UpdateDichVuRequest $request, DichVu $service): RedirectResponse
    {
        $validated = $request->validated();
        $validated['thu_tu'] = $validated['thu_tu'] ?? 0;

        $service->update($validated);

        return back()->with('success', 'Cập nhật dịch vụ thành công.');
    }

    /**
     * Xoa dich vu.
     * Bang dich_vu khong dung SoftDeletes nen day la xoa that; cac bang con
     * (loai_san_pham, san_pham) dang khai bao FK restrictOnDelete nen se
     * tu chan xoa neu dich vu dang co du lieu lien ket — bat loi de bao ro rang
     * thay vi de loi 500 tho ra man hinh.
     */
    public function destroy(DichVu $service): RedirectResponse
    {
        try {
            $service->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'service' => 'Không thể xóa dịch vụ này vì đang có loại sản phẩm/sản phẩm/cấu hình liên kết.',
            ]);
        }

        return back()->with('success', 'Xóa dịch vụ thành công.');
    }

    /**
     * Xóa nhiều dịch vụ cùng lúc.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:dich_vu,id'],
        ]);

        $ids = $validated['ids'];
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $service = DichVu::find($id);
                if ($service) {
                    $service->delete();
                    $deleted++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        if ($failed > 0) {
            return back()->with('success', "Đã xóa thành công {$deleted} dịch vụ. ({$failed} dịch vụ không thể xóa do đang có dữ liệu liên kết).");
        }

        return back()->with('success', "Đã xóa thành công {$deleted} dịch vụ đã chọn.");
    }

    /**
     * Query dung chung cho man danh sach va export de ket qua khong lech bo loc.
     */
    private function buildServiceQuery(Request $request): Builder
    {
        $query = DichVu::query();

        if ($maDichVu = trim((string) $request->query('ma_dich_vu'))) {
            $query->where('ma_dich_vu', 'like', "%{$maDichVu}%");
        }

        if ($tenDichVu = trim((string) $request->query('ten_dich_vu'))) {
            $query->where('ten_dich_vu', 'like', "%{$tenDichVu}%");
        }

        if ($trangThai = $request->query('trang_thai')) {
            $query->where('trang_thai', $trangThai);
        }

        return $query;
    }

    /**
     * Danh sach trang thai dich vu dung cho filter va form.
     */
    private function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_dung' => 'Tạm dừng',
        ];
    }
}
