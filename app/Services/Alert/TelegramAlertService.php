<?php

namespace App\Services\Alert;

use App\Models\CauHinhThongBao;
use App\Models\KetNoiNhaCungCap;

use App\Models\LanGoiNhaCungCap;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramAlertService
{
    /**
     * Gửi tin nhắn văn bản (hỗ trợ HTML / Markdown) đến Telegram ChatID.
     */
    public function sendMessage(string $chatId, string $message, ?string $botToken = null): bool
    {
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        $token = $botToken ?: $dbConfig->bot_token ?: config('services.telegram.bot_token');
        if (!$token || !$chatId) {
            Log::warning('TelegramAlertService: Thiếu Bot Token hoặc ChatID để gửi cảnh báo.', [
                'has_token' => (bool) $token,
                'chat_id' => $chatId,
            ]);
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $response = Http::timeout(10)
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

        // Kiểm tra xem NCC này có bật cảnh báo không (cài đặt độc lập theo từng NCC)
        if (empty($config['bat_canh_bao'])) {
            return false;
        }

        $chatId = $config['nhom_canh_bao_chat_id'] ?? null;
        if (!$chatId) {
            $dbConfig = CauHinhThongBao::layCauHinhTelegram();
            $chatId = $dbConfig->layChatIdChoSuKien('transaction_failure');
        }
        if (!$chatId) {
            return false;
        }

        // Kiểm tra xem mã lỗi có nằm trong danh sách bỏ qua không
        $ignoredCodes = array_filter(array_map('trim', explode(',', (string) ($config['bo_qua_ma_loi_ncc'] ?? ''))));
        if ($lanGoi->ma_loi_ncc !== null && in_array((string) $lanGoi->ma_loi_ncc, $ignoredCodes, true)) {
            return false;
        }

        // Kiểm tra xem nội dung thông điệp lỗi có nằm trong danh sách bỏ qua không
        $ignoredMsg = trim((string) ($config['bo_qua_message_ncc'] ?? ''));
        if ($ignoredMsg !== '' && stripos($reason, $ignoredMsg) !== false) {
            return false;
        }

        $nccName = $connection?->nhaCungCap?->ten_ncc ?? 'N/A';
        $maDonHang = $lanGoi->donHang?->ma_don_hang ?? 'N/A';
        $soNhan = $lanGoi->donHang?->tai_khoan_nhan ?? 'N/A';
        $time = now()->format('d/m/Y H:i:s');
        $errCode = ($lanGoi->ma_loi_ncc !== null && $lanGoi->ma_loi_ncc !== '') 
            ? (string) $lanGoi->ma_loi_ncc 
            : ($lanGoi->http_status ? "HTTP {$lanGoi->http_status}" : 'N/A');

        $message = "⚠️ <b>[CẢNH BÁO LỖI GIAO DỊCH NCC]</b>\n"
            . "🏢 <b>NCC:</b> {$nccName}\n"
            . "🧾 <b>Mã Hóa đơn:</b> <code>{$maDonHang}</code>\n"
            . "📱 <b>Số nạp:</b> <code>{$soNhan}</code>\n"
            . "🆔 <b>Mã GD Hệ thống:</b> <code>{$lanGoi->partner_ref_id}</code>\n"
            . "📌 <b>Sản phẩm:</b> {$lanGoi->ma_san_pham_ncc}\n"
            . "❌ <b>Mã lỗi NCC:</b> <code>{$errCode}</code>\n"
            . "📝 <b>Lý do:</b> {$reason}\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Cảnh báo khi thời gian phản hồi giao dịch vượt quá ngưỡng cho phép (GD chậm).
     */
    public function alertSlowTransaction(LanGoiNhaCungCap $lanGoi, float $durationSeconds, int $thresholdSeconds, ?KetNoiNhaCungCap $connection = null): bool
    {
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        if (!$dbConfig->bat_canh_bao_xu_ly_cham) {
            return false;
        }

        $connection ??= $lanGoi->ketNoi;
        $config = $connection?->cau_hinh_canh_bao_loi ?? [];

        if (isset($config['bat_canh_bao']) && !$config['bat_canh_bao']) {
            return false;
        }

        $chatId = $config['nhom_canh_bao_chat_id'] ?: $dbConfig->layChatIdChoSuKien('slow_transaction');
        if (!$chatId) {
            return false;
        }

        $nccName = $connection?->nhaCungCap?->ten_ncc ?? 'N/A';
        $maDonHang = $lanGoi->donHang?->ma_don_hang ?? 'N/A';
        $soNhan = $lanGoi->donHang?->tai_khoan_nhan ?? 'N/A';
        $durationFormatted = number_format($durationSeconds, 2);
        $time = now()->format('d/m/Y H:i:s');

        $message = "🐢 <b>[CẢNH BÁO XỬ LÝ GIAO DỊCH CHẬM]</b>\n"
            . "🏢 <b>NCC:</b> {$nccName}\n"
            . "🧾 <b>Mã Hóa đơn:</b> <code>{$maDonHang}</code>\n"
            . "📱 <b>Số nạp:</b> <code>{$soNhan}</code>\n"
            . "🆔 <b>Mã GD Hệ thống:</b> <code>{$lanGoi->partner_ref_id}</code>\n"
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
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        if (!$dbConfig->bat_canh_bao_circuit_breaker) {
            return false;
        }

        $config = $connection->cau_hinh_canh_bao_loi ?? [];
        $chatId = $config['nhom_canh_bao_chat_id'] ?: $dbConfig->layChatIdChoSuKien('circuit_breaker');
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
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        if (!$dbConfig->bat_canh_bao_so_du_thap) {
            return false;
        }

        $config = $connection->cau_hinh_canh_bao_loi ?? [];
        $chatId = $config['nhom_canh_bao_chat_id'] ?: $dbConfig->layChatIdChoSuKien('low_balance');
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

    /**
     * Thông báo đơn nạp tiền điện thoại thành công.
     */
    public function alertOrderSuccess(\App\Models\DonHang $donHang, ?LanGoiNhaCungCap $lanGoi = null): bool
    {
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        if (!$dbConfig->bat_thong_bao_don_hang) {
            return false;
        }

        $chatId = $dbConfig->layChatIdChoSuKien('order_success');
        if (!$chatId) {
            return false;
        }

        $time = now()->format('d/m/Y H:i:s');
        $menhGia = number_format((float) $donHang->menh_gia, 0, ',', '.') . 'đ';
        $giaBan = number_format((float) $donHang->gia_ban, 0, ',', '.') . 'đ';
        $nccName = $donHang->nhaCungCapThanhCong?->ten_ncc ?? $lanGoi?->nhaCungCap?->ten_ncc ?? 'N/A';
        $phoneChe = substr((string) $donHang->tai_khoan_nhan, 0, 4) . '***' . substr((string) $donHang->tai_khoan_nhan, -3);

        $message = "🎉 <b>[ĐƠN HÀNG THÀNH CÔNG]</b>\n"
            . "🆔 <b>Mã đơn:</b> <code>{$donHang->ma_don_hang}</code>\n"
            . "📱 <b>Số nạp:</b> <code>{$phoneChe}</code> (" . strtoupper($donHang->nha_mang_thuc_te ?: $donHang->nha_mang_yeu_cau ?: '') . ")\n"
            . "💵 <b>Mệnh giá:</b> {$menhGia} | <b>Giá bán:</b> <b>{$giaBan}</b>\n"
            . "🏢 <b>NCC xử lý:</b> {$nccName}\n"
            . "👤 <b>Khách hàng:</b> " . ($donHang->nguoiDung?->ten_dang_nhap ?? 'Khách lẻ') . "\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Cảnh báo đơn hàng rơi vào trạng thái chờ đối soát thủ công (MANUAL_REVIEW).
     */
    public function alertManualReview(\App\Models\DonHang $donHang, string $reason): bool
    {
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        if (!$dbConfig->bat_canh_bao_manual_review) {
            return false;
        }

        $chatId = $dbConfig->layChatIdChoSuKien('manual_review');
        if (!$chatId) {
            return false;
        }

        $time = now()->format('d/m/Y H:i:s');
        $giaBan = number_format((float) $donHang->gia_ban, 0, ',', '.') . 'đ';

        $message = "🟡 <b>[ĐƠN HÀNG CẦN ĐỐI SOÁT THỦ CÔNG]</b>\n"
            . "🆔 <b>Mã đơn:</b> <code>{$donHang->ma_don_hang}</code>\n"
            . "📱 <b>Số nạp:</b> <code>{$donHang->tai_khoan_nhan}</code>\n"
            . "💵 <b>Giá bán:</b> {$giaBan}\n"
            . "⚠️ <b>Lý do:</b> {$reason}\n"
            . "👉 <b>Khuyến nghị:</b> Admin vui lòng vào hệ thống kiểm tra trạng thái bên NCC và bấm Hoàn tiền hoặc Hoàn tất thủ công.\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Thông báo khi admin hoàn tiền thủ công cho đơn hàng.
     */
    public function alertOrderRefunded(\App\Models\DonHang $donHang, string $reason, string $operator): bool
    {
        $dbConfig = CauHinhThongBao::layCauHinhTelegram();
        if (!$dbConfig->bat_thong_bao_hoan_tien) {
            return false;
        }

        $chatId = $dbConfig->layChatIdChoSuKien('order_refunded');
        if (!$chatId) {
            return false;
        }

        $time = now()->format('d/m/Y H:i:s');
        $soTien = number_format((float) $donHang->gia_ban, 0, ',', '.') . 'đ';

        $message = "🔄 <b>[HOÀN TIỀN ĐƠN HÀNG]</b>\n"
            . "🆔 <b>Mã đơn:</b> <code>{$donHang->ma_don_hang}</code>\n"
            . "👤 <b>Người nhận hoàn:</b> " . ($donHang->nguoiDung?->ten_dang_nhap ?? 'N/A') . "\n"
            . "💰 <b>Số tiền hoàn:</b> <b>{$soTien}</b>\n"
            . "📝 <b>Lý do:</b> {$reason}\n"
            . "👮 <b>Người thực hiện:</b> {$operator}\n"
            . "⏰ <b>Thời gian:</b> {$time}";

        return $this->sendMessage($chatId, $message);
    }
}
