<?php

namespace App\Jobs;

use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi một sự kiện webhook B2B ra ngoài.
 *
 * Job này KHÔNG tự retry ở tầng queue ($tries = 1): toàn bộ chính sách retry
 * (backoff, số lần thử, chuyển xử lý thủ công) nằm ở B2bWebhookService và được
 * điều phối bởi RetryB2bWebhooksCommand. Nhờ vậy backoff khai báo trong service
 * là backoff thực tế được dùng, không bị queue retry chồng lên.
 */
class SendB2bWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public int $webhookOutboxId) {}

    public function middleware(): array
    {
        // Chặn hai job cùng đích chạy song song trên cùng một worker.
        // Lớp bảo vệ thật sự vẫn là atomic claim + lease trong B2bWebhookService.
        return [(new WithoutOverlapping('b2b-webhook-'.$this->webhookOutboxId))
            ->expireAfter(B2bWebhookService::LEASE_SECONDS)
            ->dontRelease()];
    }

    public function handle(B2bWebhookService $webhookService): void
    {
        $outbox = WebhookOutbox::find($this->webhookOutboxId);

        if (!$outbox || in_array($outbox->trang_thai, ['SUCCESS', 'MANUAL_REVIEW'], true)) {
            return;
        }

        // guiWebhook tự nhận quyền sở hữu; trả về false nếu worker khác đang giữ lease.
        $webhookService->guiWebhook($outbox);
    }
}
