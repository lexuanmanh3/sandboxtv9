<?php

namespace App\Console\Commands;

use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bWebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Quét và tự động gửi lại các webhook B2B đến hạn hoặc bị treo do worker đột tử.
 *
 * Đây là tiến trình duy nhất chịu trách nhiệm retry webhook. Nó phủ hai khoảng trống:
 *  1. Sự kiện PENDING đến hạn gửi lại (backoff).
 *  2. Sự kiện PROCESSING đã hết hạn lease (worker chết giữa chừng).
 *  3. Sự kiện PENDING chưa từng được dispatch (rơi giữa commit và dispatch queue).
 */
class RetryB2bWebhooksCommand extends Command
{
    protected $signature = 'b2b:retry-webhooks {--limit=50 : Số lượng webhook tối đa xử lý mỗi lượt}';
    protected $description = 'Quét và tự động gửi lại các webhook B2B đến hạn hoặc bị treo do worker đột tử';

    public function handle(B2bWebhookService $webhookService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $now = now();

        // 1. Quét các sự kiện đến hạn: PENDING có lan_thu_tiep_theo <= now (hoặc null),
        //    HOẶC PROCESSING đã hết hạn lease (worker đột tử).
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
        ->orderBy('lan_thu_tiep_theo', 'asc')
        ->orderBy('id', 'asc')
        ->limit($limit)
        ->get();

        if ($dueWebhooks->isEmpty()) {
            return Command::SUCCESS;
        }

        $processedCount = 0;
        $successCount = 0;
        $skippedCount = 0;

        foreach ($dueWebhooks as $outbox) {
            // Nhận xử lý nguyên tử: chỉ tiến trình thắng cuộc đua mới có owner token.
            $owner = $webhookService->nhanXuLy($outbox->id);

            if (!$owner) {
                // Đã được tiến trình khác nhận
                $skippedCount++;
                continue;
            }

            $processedCount++;

            try {
                if ($webhookService->guiWebhook($outbox->fresh(), $owner)) {
                    $successCount++;
                }
            } catch (\Throwable $e) {
                // Không để một sự kiện lỗi làm hỏng cả lượt quét.
                // Trả lease về PENDING để lượt quét sau thử lại thay vì kẹt ở PROCESSING.
                Log::error("RetryB2bWebhooksCommand: Ngoại lệ khi gửi webhook outbox #{$outbox->id}: " . $e->getMessage(), [
                    'outbox_id' => $outbox->id,
                    'event_id' => $outbox->event_id,
                ]);

                WebhookOutbox::where('id', $outbox->id)
                    ->where('khoa_so_huu', $owner)
                    ->update([
                        'trang_thai' => 'PENDING',
                        'khoa_so_huu' => null,
                        'khoa_den' => null,
                        'lan_thu_tiep_theo' => now()->addSeconds(60),
                    ]);
            }
        }

        if ($processedCount > 0 || $skippedCount > 0) {
            $this->info("Webhook B2B: xử lý {$processedCount} sự kiện (thành công: {$successCount}), bỏ qua {$skippedCount} do tiến trình khác đang giữ.");
        }

        return Command::SUCCESS;
    }
}
