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
        $channels = $config->layDanhSachKenhHopLe();
        $eventChannels = $config->cau_hinh_kenh_su_kien ?? [];

        return view('admin.telegram-settings', compact('config', 'channels', 'eventChannels'));
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
            'danh_sach_kenh' => ['nullable'],
            'cau_hinh_kenh_su_kien' => ['nullable', 'array'],
            'bat_thong_bao_don_hang' => ['nullable', 'boolean'],
            'bat_canh_bao_loi' => ['nullable', 'boolean'],
            'bat_canh_bao_xu_ly_cham' => ['nullable', 'boolean'],
            'bat_canh_bao_circuit_breaker' => ['nullable', 'boolean'],
            'bat_canh_bao_so_du_thap' => ['nullable', 'boolean'],
            'bat_canh_bao_manual_review' => ['nullable', 'boolean'],
            'bat_thong_bao_hoan_tien' => ['nullable', 'boolean'],
        ]);

        $rawChannels = $request->input('danh_sach_kenh');
        $channels = [];
        if (is_string($rawChannels)) {
            $decoded = json_decode($rawChannels, true);
            if (is_array($decoded)) {
                $channels = $decoded;
            }
        } elseif (is_array($rawChannels)) {
            $channels = $rawChannels;
        }

        $cleanChannels = [];
        foreach ($channels as $c) {
            $chatId = trim((string) ($c['chat_id'] ?? ''));
            $tenKenh = trim((string) ($c['ten_kenh'] ?? ''));
            if ($chatId !== '' && $tenKenh !== '') {
                $cleanChannels[] = [
                    'id' => trim((string) ($c['id'] ?? uniqid('chan_'))),
                    'ten_kenh' => $tenKenh,
                    'chat_id' => $chatId,
                    'ghi_chu' => trim((string) ($c['ghi_chu'] ?? '')),
                ];
            }
        }

        $eventChannels = $request->input('cau_hinh_kenh_su_kien', []);
        if (!is_array($eventChannels)) {
            $eventChannels = [];
        }

        $config = CauHinhThongBao::where('loai', 'telegram')->first() ?? new CauHinhThongBao(['loai' => 'telegram']);
        $before = $config->toArray();

        $config->fill([
            'bot_token' => trim((string) ($validated['bot_token'] ?? '')),
            'chat_id_alert' => trim((string) ($validated['chat_id_alert'] ?? '')),
            'chat_id_order' => trim((string) ($validated['chat_id_order'] ?? '')),
            'chat_id_admin' => trim((string) ($validated['chat_id_admin'] ?? '')),
            'danh_sach_kenh' => $cleanChannels,
            'cau_hinh_kenh_su_kien' => $eventChannels,
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
            'bot_token' => ['nullable', 'string', 'max:255'],
            'channel_name' => ['nullable', 'string', 'max:50'],
        ]);

        $chatId = trim($validated['chat_id']);
        $botToken = trim((string) ($validated['bot_token'] ?? ''));
        $channelName = $validated['channel_name'] ?? 'Kênh Chung';

        $setting = CauHinhThongBao::layCauHinhTelegram();
        $token = $botToken ?: ($setting->bot_token ?: config('services.telegram.bot_token'));

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng nhập Telegram Bot Token ở ô trên trước khi bấm Test!',
            ], 422);
        }

        $time = now()->format('d/m/Y H:i:s');
        $operator = auth()->user()?->ten_dang_nhap ?? 'admin';

        $message = "🤖 <b>[KIỂM TRA KẾT NỐI TELEGRAM THÀNH CÔNG]</b>\n"
            . "📡 <b>Kênh nhận:</b> {$channelName}\n"
            . "🆔 <b>Chat ID:</b> <code>{$chatId}</code>\n"
            . "👤 <b>Người test:</b> {$operator}\n"
            . "⏰ <b>Thời gian:</b> {$time}\n"
            . "✅ <i>Hệ thống thông báo Telegram hoạt động hoàn hảo!</i>";

        $success = $this->telegramAlert->sendMessage($chatId, $message, $token);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => "Đã gửi tin nhắn thử nghiệm thành công tới Chat ID: {$chatId}",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Không thể gửi tin nhắn. Vui lòng kiểm tra lại Bot Token, Chat ID và đảm bảo bạn đã bấm /start với Bot hoặc đã thêm Bot vào Nhóm.",
        ], 422);
    }
}

