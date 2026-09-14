<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Exports\ReconciliationExport;
use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\KyDoiSoat;
use App\Services\DaiLyApi\B2bReconciliationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReconciliationController extends Controller
{
    public function __construct(
        protected B2bReconciliationService $reconciliationService
    ) {}

    public function index(Request $request): View
    {
        $query = KyDoiSoat::with(['daiLyApi', 'nguoiKhoa']);

        if ($partnerId = $request->input('dai_ly_api_id')) {
            $query->where('dai_ly_api_id', $partnerId);
        }

        if ($status = $request->input('trang_thai')) {
            $query->where('trang_thai', $status);
        }

        $periods = $query->orderByDesc('id')->paginate(15)->withQueryString();
        $partners = DaiLyApi::orderBy('ten_dai_ly_api')->get();

        return view('admin.b2b.reconciliations', compact('periods', 'partners'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dai_ly_api_id' => 'required|exists:dai_ly_api,id',
            'tu_ngay' => 'required|date',
            'den_ngay' => 'required|date|after_or_equal:tu_ngay',
        ]);

        $daiLy = DaiLyApi::findOrFail($validated['dai_ly_api_id']);

        try {
            $ky = $this->reconciliationService->taoKyDoiSoat(
                $daiLy,
                $validated['tu_ngay'],
                $validated['den_ngay'],
                auth()->id()
            );

            return redirect()->route('admin.b2b.reconciliations.show', $ky->id)
                ->with('success', "Đã tạo kỳ đối soát '{$ky->ma_ky}' thành công.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(int $id): View
    {
        $period = KyDoiSoat::with(['daiLyApi.cauHinhApi', 'chiTiet.donHang', 'soPhatSinh', 'nguoiKhoa'])
            ->findOrFail($id);

        return view('admin.b2b.reconciliation-detail', compact('period'));
    }

    public function lock(int $id): RedirectResponse
    {
        $period = KyDoiSoat::findOrFail($id);

        $this->reconciliationService->khoaKy($period, (int) auth()->id());

        return redirect()->route('admin.b2b.reconciliations.show', $period->id)
            ->with('success', "Đã khóa và chốt sổ kỳ đối soát '{$period->ma_ky}'.");
    }

    public function recalculate(int $id): RedirectResponse
    {
        $period = KyDoiSoat::findOrFail($id);

        try {
            $this->reconciliationService->tinhToanLaiKy($period);
            return back()->with('success', "Đã cập nhật lại số liệu kỳ đối soát '{$period->ma_ky}'.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportExcel(int $id)
    {
        $period = KyDoiSoat::findOrFail($id);
        $fileName = 'doi_soat_' . $period->ma_ky . '.xlsx';

        return Excel::download(new ReconciliationExport($period), $fileName);
    }
}
