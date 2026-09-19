<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMobileTopupJob;
use App\Models\B2bOrderOutbox;
use App\Models\DonHang;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverB2bOrderOutboxCommand extends Command
{
    protected $signature = 'b2b:recover-outbox {--limit=50 : Số lượng tối đa bản ghi xử lý trong một lần quét}';
    protected $description = 'Quét và phục hồi các công việc Outbox tạo đơn B2B bị treo hoặc rơi rớt sau transaction commit';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $now = now();
        $stalePendingTime = $now->copy()->subSeconds(30);

        // Tìm các bản ghi PENDING quá 30s hoặc PROCESSING bị quá hạn lock
        $staleRecords = B2bOrderOutbox::where(function ($q) use ($stalePendingTime, $now) {
            $q->where(function ($sub) use ($stalePendingTime) {
                $sub->where('status', 'PENDING')
                    ->where('created_at', '<=', $stalePendingTime);
            })->orWhere(function ($sub) use ($now) {
                $sub->where('status', 'PROCESSING')
                    ->whereNotNull('locked_until')
                    ->where('locked_until', '<', $now);
            });
        })
        ->orderBy('id', 'asc')
        ->limit($limit)
        ->get();

        if ($staleRecords->isEmpty()) {
            return Command::SUCCESS;
        }

        $recovered = 0;

        foreach ($staleRecords as $record) {
            // Atomic lock trên từng bản ghi
            $affected = DB::table('b2b_order_outbox')
                ->where('id', $record->id)
                ->where(function ($q) use ($now) {
                    $q->where('status', 'PENDING')
                      ->orWhere(function ($sub) use ($now) {
                          $sub->where('status', 'PROCESSING')
                              ->where('locked_until', '<', $now);
                      });
                })
                ->update([
                    'status' => 'PROCESSING',
                    'locked_until' => $now->copy()->addMinutes(5),
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => $now,
                ]);

            if ($affected === 0) {
                // Tiến trình khác đã nhận xử lý
                continue;
            }

            $record->refresh();

            // Kiểm tra số lần thử tối đa
            if ($record->attempts > 5) {
                $record->update([
                    'status' => 'FAILED',
                    'last_error' => 'Vượt quá 5 lần phục hồi Outbox mà không hoàn tất.',
                ]);
                Log::error("B2B Order Outbox ID #{$record->id} failed after {$record->attempts} recovery attempts.");
                continue;
            }

            // Kiểm tra trạng thái đơn hàng tương ứng
            $donHang = DonHang::find($record->don_hang_id);
            if (!$donHang) {
                $record->update([
                    'status' => 'FAILED',
                    'last_error' => "Không tìm thấy đơn hàng ID #{$record->don_hang_id}.",
                ]);
                continue;
            }

            // Nếu đơn đã ở trạng thái cuối cùng (HOAN_THANH / THAT_BAI)
            if (in_array($donHang->trang_thai_don_hang, ['HOAN_THANH', 'SUCCESS', 'THAT_BAI', 'FAILED'], true)) {
                $record->update([
                    'status' => 'PROCESSED',
                    'processed_at' => now(),
                ]);
                continue;
            }

            // Nếu đơn vẫn ở trạng thái QUEUED -> dispatch job
            if ($donHang->trang_thai_don_hang === 'QUEUED') {
                ProcessMobileTopupJob::dispatch($donHang->id);
                $recovered++;
                $this->info("Đã dispatch lại ProcessMobileTopupJob cho đơn #{$donHang->ma_don_hang} (Outbox ID #{$record->id})");
            }
        }

        if ($recovered > 0) {
            $this->info("Đã phục hồi thành công {$recovered} đơn hàng B2B từ Outbox.");
        }

        return Command::SUCCESS;
    }
}
