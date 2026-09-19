<?php

namespace App\Console\Commands;

use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryB2bWebhooksCommand extends Command
{
    protected $signature = 'b2b:retry-webhooks {--limit=50 : Số lượng webhook tối đa xử lý mỗi lượt}';
    protected $description = 'Quét và tự động gửi lại các webhook B2B đến hạn hoặc bị treo do worker đột tử';

    public function handle(B2bWebhookService $webhookService): int
    {
        $limit = (int) $this->option('limit');
        $now = now();

        // 1. Quét các sự kiện Webhook đến hạn gửi lại (PENDING & lan_thu_tiep_theo <= now)
        // HOẶC bị treo lease khóa (PROCESSING & khoa_den < now)
        $dueWebhooks = WebhookOutbox::where(function ($q) use ($now) {
            $q->where(function ($sub) use ($now) {
                $sub->where('trang_thai', 'PENDING')
                    ->where(function ($timeQ) use ($now) {
                        $timeQ->whereNull('lan_thu_tiep_theo')
                              ->orWhere('lan_thu_tiep_theo', '<=', $now);
                    });
            })->orWhere(function ($sub) use ($now) {
                $sub->where('trang_thai', 'PROCESSING')
                    ->whereNotNull('khoa_den')
                    ->where('khoa_den', '<', $now);
            });
        })
        ->orderBy('id', 'asc')
        ->limit($limit)
        ->get();

        if ($dueWebhooks->isEmpty()) {
            return Command::SUCCESS;
        }

        $processedCount = 0;
        $successCount = 0;

        foreach ($dueWebhooks as $outbox) {
            // Nhận xử lý nguyên tử bằng atomic lease update
            $affected = DB::table('webhook_outbox')
                ->where('id', $outbox->id)
                ->where(function ($q) use ($now) {
                    $q->where('trang_thai', 'PENDING')
                      ->orWhere(function ($sub) use ($now) {
                          $sub->where('trang_thai', 'PROCESSING')
                              ->where('khoa_den', '<', $now);
                      });
                })
                ->update([
                    'trang_thai' => 'PROCESSING',
                    'khoa_den' => $now->copy()->addMinutes(2),
                    'updated_at' => $now,
                ]);

            if ($affected === 0) {
                // Đã được tiến trình khác nhận
                continue;
            }

            $outbox->refresh();
            $processedCount++;

            try {
                $ok = $webhookService->guiWebhook($outbox);
                if ($ok) {
                    $successCount++;
                }
            } catch (\Throwable $e) {
                Log::error("RetryB2bWebhooksCommand: Ngoại lệ khi gửi webhook outbox #{$outbox->id}: " . $e->getMessage(), [
                    'outbox_id' => $outbox->id,
                    'event_id' => $outbox->event_id,
                ]);
            }
        }

        if ($processedCount > 0) {
            $this->info("Đã quét và xử lý {$processedCount} webhook outbox (Thành công: {$successCount}).");
        }

        return Command::SUCCESS;
    }
}
