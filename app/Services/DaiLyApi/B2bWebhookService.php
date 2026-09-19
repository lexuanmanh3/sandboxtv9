<?php

namespace App\Services\DaiLyApi;

use App\Jobs\SendB2bWebhookJob;
use App\Models\DonHang;
use App\Models\LichSuGuiWebhook;
use App\Models\WebhookOutbox;
use App\Services\Security\UrlSafetyValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class B2bWebhookService
{
    /**
     * Số lần thử tối đa trước khi chuyển sang trạng thái cần xử lý thủ công.
     * Phải khớp với số phần tử của BACKOFF_SECONDS + 1 để mọi khoảng chờ
     * được khai báo đều thực sự được sử dụng.
     */
    public const MAX_ATTEMPTS = 6;

    /**
     * Khoảng chờ (giây) sau lần thử thứ 1..5.
     * Lần thử thứ 6 thất bại -> MANUAL_REVIEW (không còn khoảng chờ nào).
     */
    public const BACKOFF_SECONDS = [30, 120, 600, 1800, 7200];

    /** Thời hạn lease sở hữu khi gửi webhook (giây). */
    public const LEASE_SECONDS = 120;

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

        // Trạng thái công khai dùng CHUNG một bộ ánh xạ với API tra cứu.
        // Không rò rỉ trạng thái nội bộ (PROVIDER_PENDING, REFUND_PENDING, ...) ra hợp đồng webhook.
        $publicStatus = B2bStatusMapper::toPublic($donHang->trang_thai_don_hang);

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
                'status' => $publicStatus,
                'result_code' => B2bStatusMapper::resultCode($publicStatus),
                'completed_at' => B2bStatusMapper::isFinalPublicStatus($publicStatus)
                    ? ($donHang->hoan_thanh_luc?->toIso8601String() ?: $donHang->that_bai_luc?->toIso8601String())
                    : null,
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

        // Chỉ dispatch job gửi webhook sau khi transaction commit nếu outbox vừa được tạo mới.
        // Khoảng trống giữa commit và dispatch được RetryB2bWebhooksCommand phủ (quét PENDING đến hạn).
        if ($outbox->wasRecentlyCreated) {
            SendB2bWebhookJob::dispatch($outbox->id)->afterCommit();
        }

        return $outbox;
    }

    /**
     * Nhận quyền sở hữu xử lý một sự kiện webhook bằng atomic claim + lease.
     *
     * Chỉ đúng một tiến trình thắng được UPDATE có điều kiện này; các tiến trình
     * khác nhận về null và phải bỏ qua. Nhờ vậy nhiều worker không cùng sở hữu
     * một lần xử lý.
     *
     * @return string|null Token sở hữu, null nếu không nhận được.
     */
    public function nhanXuLy(int $outboxId): ?string
    {
        $owner = (string) Str::uuid();
        $now = now();

        $affected = DB::table('webhook_outbox')
            ->where('id', $outboxId)
            ->where(function ($q) use ($now) {
                $q->where(function ($sub) use ($now) {
                    $sub->where('trang_thai', 'PENDING')
                        ->where(function ($t) use ($now) {
                            $t->whereNull('lan_thu_tiep_theo')
                              ->orWhere('lan_thu_tiep_theo', '<=', $now);
                        });
                })->orWhere(function ($sub) use ($now) {
                    $sub->where('trang_thai', 'PROCESSING')
                        ->whereNotNull('khoa_den')
                        ->where('khoa_den', '<', $now);
                });
            })
            ->update([
                'trang_thai' => 'PROCESSING',
                'khoa_so_huu' => $owner,
                'khoa_den' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                'updated_at' => $now,
            ]);

        return $affected === 1 ? $owner : null;
    }

    /**
     * Nhận quyền xử lý thủ công (Admin bấm gửi lại) — bỏ qua điều kiện trạng thái.
     */
    public function nhanXuLyThuCong(int $outboxId): ?string
    {
        $owner = (string) Str::uuid();
        $now = now();

        $affected = DB::table('webhook_outbox')
            ->where('id', $outboxId)
            ->update([
                'trang_thai' => 'PROCESSING',
                'khoa_so_huu' => $owner,
                'khoa_den' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                'updated_at' => $now,
            ]);

        return $affected === 1 ? $owner : null;
    }

    /**
     * Thực hiện gửi webhook tới URL đại lý kèm kiểm tra SSRF và chữ ký HMAC.
     *
     * KHÔNG giữ database lock trong lúc gọi HTTP. Mọi cập nhật kết quả đều phải
     * kiểm tra quyền sở hữu (khoa_so_huu) để không ghi đè kết quả của worker khác.
     */
    public function guiWebhook(WebhookOutbox $outbox, ?string $owner = null): bool
    {
        $owner = $owner ?: $this->nhanXuLy($outbox->id);

        if (!$owner) {
            // Không nhận được quyền sở hữu: đã có tiến trình khác xử lý hoặc chưa đến hạn.
            return false;
        }

        $outbox->refresh();

        $daiLy = $outbox->daiLyApi ?: \App\Models\DaiLyApi::with('cauHinhApi')->find($outbox->dai_ly_api_id);
        $cauHinh = $daiLy?->cauHinhApi;

        if (!$cauHinh || empty($cauHinh->webhook_url)) {
            $this->ghiKetQua($outbox, $owner, [
                'trang_thai' => 'MANUAL_REVIEW',
                'khoa_so_huu' => null,
                'khoa_den' => null,
                'lan_thu_tiep_theo' => null,
            ]);

            return false;
        }

        $url = $cauHinh->webhook_url;
        $webhookSecret = $cauHinh->webhook_secret_ma_hoa ?: $cauHinh->secret_key_ma_hoa;

        // 1. Kiểm tra SSRF an toàn
        try {
            $allowHttp = app()->environment('local', 'testing');
            UrlSafetyValidator::validate($url, $allowHttp);
        } catch (\Throwable $ve) {
            Log::warning('B2bWebhookService: URL webhook không an toàn: ' . $ve->getMessage(), [
                'outbox_id' => $outbox->id,
                'url' => $url,
            ]);

            // URL không an toàn là lỗi cấu hình phía đối tác, retry tự động vô nghĩa.
            // Chuyển sang MANUAL_REVIEW để vận hành xử lý, không đốt lượt thử.
            $this->ghiKetQua($outbox, $owner, [
                'trang_thai' => 'MANUAL_REVIEW',
                'khoa_so_huu' => null,
                'khoa_den' => null,
                'lan_thu_tiep_theo' => null,
            ]);

            return false;
        }

        // 2. Ký payload bằng HMAC-SHA256. Payload nghiệp vụ và event_id giữ nguyên
        //    qua mọi lần gửi; chỉ timestamp chữ ký được làm mới.
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

        // 3. Gọi HTTP KHÔNG giữ bất kỳ lock DB nào (lease đã được ghi trước đó bằng 1 câu UPDATE).
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

        // 4. Ghi log lịch sử gửi. Headers KHÔNG chứa secret (chỉ chứa chữ ký HMAC
        //    được sinh từ secret, không thể dùng để suy ngược secret).
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

        // 5. Cập nhật trạng thái outbox — CHỈ khi vẫn còn là chủ sở hữu lease.
        $newAttempts = (int) $outbox->so_lan_thu + 1;

        if ($isSuccess) {
            $updated = $this->ghiKetQua($outbox, $owner, [
                'trang_thai' => 'SUCCESS',
                'so_lan_thu' => $newAttempts,
                'khoa_so_huu' => null,
                'khoa_den' => null,
                'lan_thu_tiep_theo' => null,
            ]);

            if (!$updated) {
                Log::warning("B2bWebhookService: mất quyền sở hữu lease khi ghi kết quả THÀNH CÔNG cho outbox #{$outbox->id}. Kết quả không được ghi để tránh ghi đè worker khác.");
                return false;
            }

            return true;
        }

        // Thất bại: tính khoảng chờ kế tiếp theo cấu hình backoff đã đồng bộ với MAX_ATTEMPTS.
        if ($newAttempts >= self::MAX_ATTEMPTS) {
            // Hết lượt thử tự động -> cần xử lý thủ công. KHÔNG ảnh hưởng trạng thái đơn/công nợ.
            $status = 'MANUAL_REVIEW';
            $nextRun = null;
        } else {
            $status = 'PENDING';
            $nextRun = now()->addSeconds(self::BACKOFF_SECONDS[$newAttempts - 1]);
        }

        $updated = $this->ghiKetQua($outbox, $owner, [
            'trang_thai' => $status,
            'so_lan_thu' => $newAttempts,
            'khoa_so_huu' => null,
            'khoa_den' => null,
            'lan_thu_tiep_theo' => $nextRun,
        ]);

        if (!$updated) {
            Log::warning("B2bWebhookService: mất quyền sở hữu lease khi ghi kết quả THẤT BẠI cho outbox #{$outbox->id}. Kết quả không được ghi để tránh ghi đè worker khác.");
        }

        return false;
    }

    /**
     * Ghi kết quả gửi webhook với điều kiện vẫn đang giữ quyền sở hữu lease.
     *
     * @return bool true nếu ghi được (vẫn là chủ sở hữu), false nếu đã mất quyền.
     */
    protected function ghiKetQua(WebhookOutbox $outbox, string $owner, array $attributes): bool
    {
        $attributes['updated_at'] = now();

        $affected = DB::table('webhook_outbox')
            ->where('id', $outbox->id)
            ->where('khoa_so_huu', $owner)
            ->update($attributes);

        return $affected === 1;
    }

    /**
     * Gửi lại webhook thủ công từ giao diện Admin.
     */
    public function guiLaiThuCong(int $webhookOutboxId): array
    {
        $outbox = WebhookOutbox::findOrFail($webhookOutboxId);

        if ($outbox->trang_thai === 'PROCESSING' && $outbox->khoa_den && $outbox->khoa_den->isFuture()) {
            return [
                'success' => false,
                'outbox' => $outbox,
                'message' => 'Sự kiện đang được một tiến trình khác gửi. Vui lòng thử lại sau.',
            ];
        }

        // Gửi lại thủ công được phép bỏ qua trạng thái (kể cả MANUAL_REVIEW / FAILED)
        $owner = $this->nhanXuLyThuCong($outbox->id);
        if (!$owner) {
            return [
                'success' => false,
                'outbox' => $outbox->fresh(),
                'message' => 'Không nhận được quyền gửi lại sự kiện này.',
            ];
        }

        $ok = $this->guiWebhook($outbox->fresh(), $owner);

        return [
            'success' => $ok,
            'outbox' => $outbox->fresh(),
            'message' => $ok ? 'Gửi webhook thành công.' : 'Gửi webhook thất bại, xem chi tiết trong lịch sử.',
        ];
    }
}
