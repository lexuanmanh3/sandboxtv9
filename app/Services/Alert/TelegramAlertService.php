<?php

namespace App\Services\Alert;

use App\Models\KetNoiNhaCungCap;
use App\Models\LanGoiNhaCungCap;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramAlertService
{
    /**
     * Gửi tin nhắn văn bản (hỗ trợ HTML / Markdown) đến Telegram ChatID.
     */
    public function sendMessage(string $chatId, string $message, ?string $botToken = null): bool
    {
        $token = $botToken ?: config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (!$token || !$chatId) {
            Log::warning('TelegramAlertService: Thiếu Bot Token hoặc ChatID để gửi cảnh báo.', [
                'has_token' => (bool) $token,
                'chat_id' => $chatId,
            ]);
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $response = Http::timeout(5)
                ->asJson()
                ->post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            if (!$response->successful()) {
                Log::error('TelegramAlertService: Gửi tin nhắn thất bại.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('TelegramAlertService: Lỗi ngoại lệ khi gửi cảnh báo Telegram: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cảnh báo giao dịch nạp tiền thất bại từ nhà cung cấp.
     */
    public function alertTransactionFailure(LanGoiNhaCungCap $lanGoi, string $reason, ?KetNoiNhaCungCap $connection = null): bool
    {
        $connection ??= $lanGoi->ketNoi;
        $config = $connection?->cau_hinh_canh_bao_loi ?? [];

        // Kiểm tra xem NCC có bật cảnh báo lỗi không
        if (empty($config['bat_canh_bao'])) {
            return false;
        }

        $chatId = $config['nhom_canh_bao_chat_id'] ?? null;
        if (!$chatId) {
            return false;
        }

        // Kiểm tra xem mã lỗi có nằm trong danh sách bỏ qua không
        $ignoredCodes = array_filter(array_map('trim', explode(',', (string) ($config['bo_qua_ma_loi_ncc'] ?? ''))));
        if ($lanGoi->ma_loi_ncc && in_array((string) $lanGoi->ma_loi_ncc, $ignoredCodes, true)) {
            return false;
        }

        $nccName = $connection?->nhaCungCap?->ten_ncc ?? 'N/A';
        $time = now()->format('d/m/Y H:i:s');

        $message = "⚠️ <b>[CẢNH BÁO LỖI GIAO DỊCH NCC]</b>\n"
            . "🏢 <b>NCC:</b> {$nccName}\n"
            . "🆔 <b>Mã GD Hệ thống:</b> <code>{$lanGoi->partner_ref_id}</code>\n"
            . "📌 <b>Sản phẩm:</b> {$lanGoi->ma_san_pham_ncc}\n"
            . "❌ <b>Mã lỗi NCC:</b> <code>{$lanGoi->ma_loi_ncc}</code>\n"
            . "📝 <b>Lý do:</b> {$reason}\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Cảnh báo khi thời gian phản hồi giao dịch vượt quá ngưỡng cho phép (GD chậm).
     */
    public function alertSlowTransaction(LanGoiNhaCungCap $lanGoi, float $durationSeconds, int $thresholdSeconds, ?KetNoiNhaCungCap $connection = null): bool
    {
        $connection ??= $lanGoi->ketNoi;
        $config = $connection?->cau_hinh_canh_bao_loi ?? [];

        if (empty($config['bat_canh_bao'])) {
            return false;
        }

        $chatId = $config['nhom_canh_bao_chat_id'] ?? null;
        if (!$chatId) {
            return false;
        }

        $nccName = $connection?->nhaCungCap?->ten_ncc ?? 'N/A';
        $durationFormatted = number_format($durationSeconds, 2);
        $time = now()->format('d/m/Y H:i:s');

        $message = "🐢 <b>[CẢNH BÁO XỬ LÝ GIAO DỊCH CHẬM]</b>\n"
            . "🏢 <b>NCC:</b> {$nccName}\n"
            . "🆔 <b>Mã GD:</b> <code>{$lanGoi->partner_ref_id}</code>\n"
            . "⏱️ <b>Thời gian xử lý:</b> <b>{$durationFormatted}s</b> (Ngưỡng cảnh báo: {$thresholdSeconds}s)\n"
            . "📌 <b>Sản phẩm:</b> {$lanGoi->ma_san_pham_ncc}\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Cảnh báo kích hoạt cơ chế ngắt tự động (Circuit Breaker) khi lỗi liên tiếp.
     */
    public function alertCircuitBreakerTriggered(KetNoiNhaCungCap $connection, int $consecutiveFailures, int $suspendSeconds): bool
    {
        $config = $connection->cau_hinh_canh_bao_loi ?? [];
        $chatId = $config['nhom_canh_bao_chat_id'] ?? null;
        if (!$chatId) {
            return false;
        }

        $nccName = $connection->nhaCungCap?->ten_ncc ?? 'N/A';
        $time = now()->format('d/m/Y H:i:s');
        $suspendText = $suspendSeconds > 0 ? "trong {$suspendSeconds} giây" : "cho đến khi mở lại thủ công";

        $message = "🚨 <b>[KÍCH HOẠT ĐÓNG TỰ ĐỘNG - CIRCUIT BREAKER]</b>\n"
            . "🏢 <b>NCC:</b> {$nccName}\n"
            . "🔌 <b>Kết nối:</b> {$connection->ten_ket_noi} (ID: #{$connection->id})\n"
            . "💥 <b>Số lỗi liên tiếp:</b> <b>{$consecutiveFailures}</b> lần\n"
            . "⏸️ <b>Hành động:</b> Đã tự động chuyển trạng thái kết nối sang <b>TẠM DỪNG ({$suspendText})</b>\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Cảnh báo số dư nhà cung cấp xuống dưới mức tối thiểu.
     */
    public function alertLowBalance(KetNoiNhaCungCap $connection, float $currentBalance, float $threshold): bool
    {
        $config = $connection->cau_hinh_canh_bao_loi ?? [];
        $chatId = $config['nhom_canh_bao_chat_id'] ?? null;
        if (!$chatId) {
            return false;
        }

        $nccName = $connection->nhaCungCap?->ten_ncc ?? 'N/A';
        $currentBalanceFmt = number_format($currentBalance, 0, ',', '.') . ' đ';
        $thresholdFmt = number_format($threshold, 0, ',', '.') . ' đ';
        $time = now()->format('d/m/Y H:i:s');

        $message = "💰 <b>[CẢNH BÁO SỐ DƯ NCC THẤP]</b>\n"
            . "🏢 <b>NCC:</b> {$nccName}\n"
            . "🔌 <b>Kết nối:</b> {$connection->ten_ket_noi}\n"
            . "⚠️ <b>Số dư hiện tại:</b> <b>{$currentBalanceFmt}</b>\n"
            . "📉 <b>Ngưỡng cảnh báo:</b> {$thresholdFmt}\n"
            . "👉 <b>Khuyến nghị:</b> Vui lòng nạp thêm tiền vào tài khoản NCC để tránh gián đoạn dịch vụ.\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }
}
