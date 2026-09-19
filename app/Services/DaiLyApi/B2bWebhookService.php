<?php

namespace App\Services\DaiLyApi;

use App\Jobs\SendB2bWebhookJob;
use App\Models\DonHang;
use App\Models\LichSuGuiWebhook;
use App\Models\WebhookOutbox;
use App\Services\Security\UrlSafetyValidator;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class B2bWebhookService
{
    /**
     * Tạo bản ghi outbox cho webhook khi đơn hàng cập nhật trạng thái.
     * Thường được gọi trong DB transaction sau khi đơn đổi trạng thái.
     */
    public function taoSuKien(DonHang $donHang, string $eventType): ?WebhookOutbox
    {
        if (!$donHang->dai_ly_api_id) {
            return null;
        }

        $daiLy = $donHang->daiLyApi ?: \App\Models\DaiLyApi::find($donHang->dai_ly_api_id);
        $cauHinh = $daiLy?->cauHinhApi;

        if (!$cauHinh || empty($cauHinh->webhook_url)) {
            return null;
        }

        // Sử dụng UUIDv5 tất định dựa trên don_hang_id và event_type để định danh sự kiện ổn định
        $eventId = \Ramsey\Uuid\Uuid::uuid5(\Ramsey\Uuid\Uuid::NAMESPACE_OID, "b2b_webhook:{$donHang->id}:{$eventType}")->toString();

        $payload = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => time(),
            'data' => [
                'order_id' => $donHang->id,
                'order_code' => $donHang->ma_don_hang,
                'partner_order_id' => $donHang->ma_don_doi_tac,
                'account' => $donHang->tai_khoan_nhan,
                'amount' => (float) $donHang->menh_gia,
                'price' => (float) $donHang->gia_ban,
                'status' => $donHang->trang_thai_don_hang,
                'result_code' => $donHang->trang_thai_don_hang === 'SUCCESS' ? '00' : '99',
                'completed_at' => $donHang->hoan_thanh_luc?->toIso8601String(),
                'failed_at' => $donHang->that_bai_luc?->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ];

        $outbox = WebhookOutbox::firstOrCreate(
            ['event_id' => $eventId],
            [
                'dai_ly_api_id' => $daiLy->id,
                'don_hang_id' => $donHang->id,
                'event_type' => $eventType,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'trang_thai' => 'PENDING',
                'so_lan_thu' => 0,
                'lan_thu_tiep_theo' => now(),
            ]
        );

        // Chỉ dispatch job gửi webhook sau khi transaction commit nếu outbox vừa được tạo mới
        if ($outbox->wasRecentlyCreated) {
            SendB2bWebhookJob::dispatch($outbox->id)->afterCommit();
        }

        return $outbox;
    }

    /**
     * Thực hiện gửi webhook tới URL đại lý kèm kiểm tra SSRF và chữ ký HMAC.
     */
    public function guiWebhook(WebhookOutbox $outbox): bool
    {
        $daiLy = $outbox->daiLyApi ?: \App\Models\DaiLyApi::with('cauHinhApi')->find($outbox->dai_ly_api_id);
        $cauHinh = $daiLy?->cauHinhApi;

        if (!$cauHinh || empty($cauHinh->webhook_url)) {
            $outbox->update(['trang_thai' => 'FAILED']);
            return false;
        }

        $url = $cauHinh->webhook_url;
        $webhookSecret = $cauHinh->webhook_secret_ma_hoa ?: $cauHinh->secret_key_ma_hoa;

        // 1. Kiểm tra SSRF an toàn
        try {
            $allowHttp = app()->environment('local', 'testing');
            UrlSafetyValidator::validate($url, $allowHttp);
        } catch (\Throwable $ve) {
            Log::warning("B2bWebhookService: URL webhook không an toàn: " . $ve->getMessage(), [
                'outbox_id' => $outbox->id,
                'url' => $url,
            ]);

            $outbox->update([
                'trang_thai' => 'FAILED',
                'so_lan_thu' => $outbox->so_lan_thu + 1,
            ]);

            LichSuGuiWebhook::create([
                'webhook_outbox_id' => $outbox->id,
                'url' => $url,
                'http_status' => null,
                'loi' => 'SSRF check failed: ' . $ve->getMessage(),
                'created_at' => now(),
            ]);

            return false;
        }

        // 2. Ký payload bằng HMAC-SHA256
        $payloadString = $outbox->payload_json;
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}\n{$payloadString}", $webhookSecret);

        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-Event-Id' => $outbox->event_id,
            'X-Webhook-Timestamp' => (string) $timestamp,
            'X-Webhook-Signature' => $signature,
            'User-Agent' => 'tv9tech-B2B-Webhook/1.0',
        ];

        // Đặt lease khóa 2 phút chống worker khác tranh chấp
        $outbox->update([
            'trang_thai' => 'PROCESSING',
            'khoa_den' => now()->addMinutes(2),
        ]);

        $startTime = microtime(true);
        $httpStatus = null;
        $responseBody = null;
        $errorMessage = null;
        $isSuccess = false;

        try {
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->withHeaders($headers)
                ->withBody($payloadString, 'application/json')
                ->post($url);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $httpStatus = $response->status();
            $responseBody = mb_substr($response->body(), 0, 4000);

            if ($response->successful()) {
                $isSuccess = true;
            } else {
                $errorMessage = "HTTP Error: {$httpStatus}";
            }
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $errorMessage = $e->getMessage();
        }

        // 3. Ghi log lịch sử gửi (không lộ secret trong headers)
        LichSuGuiWebhook::create([
            'webhook_outbox_id' => $outbox->id,
            'url' => $url,
            'http_status' => $httpStatus,
            'request_headers_json' => json_encode($headers),
            'request_body' => $payloadString,
            'response_body' => $responseBody,
            'thoi_gian_ms' => $durationMs,
            'loi' => $errorMessage,
            'created_at' => now(),
        ]);

        // 4. Cập nhật trạng thái outbox và lên lịch retry nếu lỗi
        $newAttempts = $outbox->so_lan_thu + 1;
        if ($isSuccess) {
            $outbox->update([
                'trang_thai' => 'SUCCESS',
                'so_lan_thu' => $newAttempts,
                'khoa_den' => null,
            ]);
            return true;
        } else {
            // Backoff đồng bộ: lần 1: 30s, lần 2: 120s, lần 3: 600s, lần 4: 1800s, lần 5: 7200s
            $backoffDelays = [30, 120, 600, 1800, 7200];
            $nextDelay = $backoffDelays[min($newAttempts - 1, count($backoffDelays) - 1)];

            $status = ($newAttempts >= 5) ? 'FAILED' : 'PENDING';
            $nextRun = ($status === 'PENDING') ? now()->addSeconds($nextDelay) : null;

            $outbox->update([
                'trang_thai' => $status,
                'so_lan_thu' => $newAttempts,
                'lan_thu_tiep_theo' => $nextRun,
                'khoa_den' => null,
            ]);

            return false;
        }
    }

    /**
     * Gửi lại webhook thủ công từ giao diện Admin.
     */
    public function guiLaiThuCong(int $webhookOutboxId): array
    {
        $outbox = WebhookOutbox::findOrFail($webhookOutboxId);
        $ok = $this->guiWebhook($outbox);

        return [
            'success' => $ok,
            'outbox' => $outbox->fresh(),
            'message' => $ok ? 'Gửi webhook thành công.' : 'Gửi webhook thất bại, xem chi tiết trong lịch sử.',
        ];
    }
}
