<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookManagementController extends Controller
{
    public function __construct(
        protected B2bWebhookService $webhookService
    ) {}

    public function index(Request $request): View
    {
        $query = WebhookOutbox::with(['daiLyApi', 'donHang', 'lichSuGui']);

        if ($partnerId = $request->input('dai_ly_api_id')) {
            $query->where('dai_ly_api_id', $partnerId);
        }

        if ($status = $request->input('trang_thai')) {
            $query->where('trang_thai', $status);
        }

        if ($eventId = trim((string) $request->input('event_id'))) {
            $query->where('event_id', $eventId);
        }

        $webhooks = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $partners = DaiLyApi::orderBy('ten_dai_ly_api')->get();

        return view('admin.b2b.webhooks', compact('webhooks', 'partners'));
    }

    public function retry(int $id): RedirectResponse
    {
        $res = $this->webhookService->guiLaiThuCong($id);

        if ($res['success']) {
            return back()->with('success', 'Gửi lại webhook thành công.');
        }

        return back()->with('error', 'Gửi lại webhook thất bại. Vui lòng xem chi tiết lịch sử lỗi.');
    }
}
