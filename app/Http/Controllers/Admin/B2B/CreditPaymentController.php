<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\DieuChinhCongNo;
use App\Models\SoPhatSinhCongNo;
use App\Models\ThanhToanCongNo;
use App\Services\DaiLyApi\B2bCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditPaymentController extends Controller
{
    public function __construct(
        protected B2bCreditService $creditService
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->input('tab', 'ledger'); // ledger | payments | adjustments

        $partners = DaiLyApi::with('cauHinhApi')->orderBy('ten_dai_ly_api')->get();
        $selectedPartnerId = $request->input('dai_ly_api_id');

        $ledgerQuery = SoPhatSinhCongNo::with(['daiLyApi', 'donHang', 'thanhToan', 'dieuChinh', 'nguoiTao']);
        if ($selectedPartnerId) {
            $ledgerQuery->where('dai_ly_api_id', $selectedPartnerId);
        }
        if ($type = $request->input('loai_phat_sinh')) {
            $ledgerQuery->where('loai_phat_sinh', $type);
        }
        $ledgers = $ledgerQuery->orderByDesc('id')->paginate(20, ['*'], 'ledger_page')->withQueryString();

        $paymentsQuery = ThanhToanCongNo::with(['daiLyApi', 'nguoiTao']);
        if ($selectedPartnerId) {
            $paymentsQuery->where('dai_ly_api_id', $selectedPartnerId);
        }
        $payments = $paymentsQuery->orderByDesc('id')->paginate(20, ['*'], 'pay_page')->withQueryString();

        $adjustmentsQuery = DieuChinhCongNo::with(['daiLyApi', 'nguoiTao']);
        if ($selectedPartnerId) {
            $adjustmentsQuery->where('dai_ly_api_id', $selectedPartnerId);
        }
        $adjustments = $adjustmentsQuery->orderByDesc('id')->paginate(20, ['*'], 'adj_page')->withQueryString();

        $selectedPartner = $selectedPartnerId ? DaiLyApi::find($selectedPartnerId) : null;
        $creditInfo = $selectedPartner ? $this->creditService->tinhHanMucKhaDung($selectedPartner) : null;

        return view('admin.b2b.credit-payments', compact(
            'partners',
            'selectedPartnerId',
            'selectedPartner',
            'creditInfo',
            'ledgers',
            'payments',
            'adjustments',
            'tab'
        ));
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dai_ly_api_id' => 'required|exists:dai_ly_api,id',
            'so_tien' => 'required|numeric|min:1',
            'phuong_thuc' => 'required|string|in:CHUYEN_KHOAN,TIEN_MAT,KHAC',
            'ma_giao_dich_ngan_hang' => 'nullable|string|max:100',
            'ghi_chu' => 'nullable|string|max:500',
        ]);

        $daiLy = DaiLyApi::findOrFail($validated['dai_ly_api_id']);

        $this->creditService->ghiNhanThanhToan(
            $daiLy,
            (float) $validated['so_tien'],
            $validated['phuong_thuc'],
            $validated['ma_giao_dich_ngan_hang'] ?? null,
            $validated['ghi_chu'] ?? null,
            auth()->id()
        );

        return redirect()->route('admin.b2b.credit-payments.index', ['dai_ly_api_id' => $daiLy->id, 'tab' => 'payments'])
            ->with('success', "Đã ghi nhận thanh toán {$validated['so_tien']} VNĐ cho đại lý '{$daiLy->ten_dai_ly_api}'.");
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dai_ly_api_id' => 'required|exists:dai_ly_api,id',
            'loai_dieu_chinh' => 'required|string|in:TANG_NO,GIAM_NO',
            'so_tien' => 'required|numeric|min:1',
            'ly_do' => 'required|string|max:500',
            'ma_tham_chieu_goc' => 'nullable|string|max:100',
        ]);

        $daiLy = DaiLyApi::findOrFail($validated['dai_ly_api_id']);

        $this->creditService->ghiNhanDieuChinh(
            $daiLy,
            $validated['loai_dieu_chinh'],
            (float) $validated['so_tien'],
            $validated['ly_do'],
            $validated['ma_tham_chieu_goc'] ?? null,
            null,
            auth()->id()
        );

        return redirect()->route('admin.b2b.credit-payments.index', ['dai_ly_api_id' => $daiLy->id, 'tab' => 'adjustments'])
            ->with('success', "Đã ghi nhận bút toán điều chỉnh cho đại lý '{$daiLy->ten_dai_ly_api}'.");
    }
}
