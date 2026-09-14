<?php

namespace App\Jobs;

use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendB2bWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(public int $webhookOutboxId) {}

    public function handle(B2bWebhookService $webhookService): void
    {
        $outbox = WebhookOutbox::find($this->webhookOutboxId);
        if (!$outbox || $outbox->trang_thai === 'SUCCESS') {
            return;
        }

        $webhookService->guiWebhook($outbox);
    }
}
