<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CauHinhThongBao;
use App\Services\Alert\TelegramAlertService;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TelegramSettingController extends Controller
{
    public function __construct(
        private readonly TelegramAlertService $telegramAlert,
        private readonly AuditService $auditService
    ) {}

    /**
     * Màn hình quản lý Cấu hình Thông báo Telegram.
     */
    public function index(): View
    {
        $config = CauHinhThongBao::layCauHinhTelegram();

        return view('admin.telegram-settings', compact('config'));
    }

    /**
     * Cập nhật Cấu hình Thông báo Telegram (Chỉ Admin có quyền).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id_alert' => ['nullable', 'string', 'max:100'],
            'chat_id_order' => ['nullable', 'string', 'max:100'],
            'chat_id_admin' => ['nullable', 'string', 'max:100'],
            'bat_thong_bao_don_hang' => ['nullable', 'boolean'],
            'bat_canh_bao_loi' => ['nullable', 'boolean'],
            'bat_canh_bao_xu_ly_cham' => ['nullable', 'boolean'],
            'bat_canh_bao_circuit_breaker' => ['nullable', 'boolean'],
            'bat_canh_bao_so_du_thap' => ['nullable', 'boolean'],
            'bat_canh_bao_manual_review' => ['nullable', 'boolean'],
            'bat_thong_bao_hoan_tien' => ['nullable', 'boolean'],
        ]);

        $config = CauHinhThongBao::where('loai', 'telegram')->first() ?? new CauHinhThongBao(['loai' => 'telegram']);
        $before = $config->toArray();

        $config->fill([
            'bot_token' => trim((string) ($validated['bot_token'] ?? '')),
            'chat_id_alert' => trim((string) ($validated['chat_id_alert'] ?? '')),
            'chat_id_order' => trim((string) ($validated['chat_id_order'] ?? '')),
            'chat_id_admin' => trim((string) ($validated['chat_id_admin'] ?? '')),
            'bat_thong_bao_don_hang' => $request->boolean('bat_thong_bao_don_hang'),
            'bat_canh_bao_loi' => $request->boolean('bat_canh_bao_loi'),
            'bat_canh_bao_xu_ly_cham' => $request->boolean('bat_canh_bao_xu_ly_cham'),
            'bat_canh_bao_circuit_breaker' => $request->boolean('bat_canh_bao_circuit_breaker'),
            'bat_canh_bao_so_du_thap' => $request->boolean('bat_canh_bao_so_du_thap'),
            'bat_canh_bao_manual_review' => $request->boolean('bat_canh_bao_manual_review'),
            'bat_thong_bao_hoan_tien' => $request->boolean('bat_thong_bao_hoan_tien'),
        ]);

        $config->save();
        CauHinhThongBao::xoaCache();

        $this->auditService->record(
            'telegram_setting.updated',
            $config,
            $before,
            $config->toArray()
        );

        return redirect()->route('admin.telegram-settings')
            ->with('success', 'Đã lưu cấu hình thông báo Telegram thành công!');
    }

    /**
     * Gửi tin nhắn kiểm tra kết nối Telegram trực tiếp.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => ['required', 'string', 'max:100'],
            'channel_name' => ['nullable', 'string', 'max:50'],
        ]);

        $chatId = trim($validated['chat_id']);
        $channelName = $validated['channel_name'] ?? 'Kênh Chung';
        $time = now()->format('d/m/Y H:i:s');
        $operator = auth()->user()?->ten_dang_nhap ?? 'admin';

        $message = "🤖 <b>[KIỂM TRA KẾT NỐI TELEGRAM THÀNH CÔNG]</b>\n"
            . "📡 <b>Kênh nhận:</b> {$channelName}\n"
            . "🆔 <b>Chat ID:</b> <code>{$chatId}</code>\n"
            . "👤 <b>Người test:</b> {$operator}\n"
            . "⏰ <b>Thời gian:</b> {$time}\n"
            . "✅ <i>Hệ thống thông báo Telegram hoạt động hoàn hảo!</i>";

        $success = $this->telegramAlert->sendMessage($chatId, $message);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => "Đã gửi tin nhắn thử nghiệm thành công tới Chat ID: {$chatId}",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Không thể gửi tin nhắn. Vui lòng kiểm tra Bot Token và đảm bảo bạn đã bấm /start với Bot hoặc thêm Bot vào Nhóm.",
        ], 422);
    }
}

