<?php

namespace App\Console\Commands;

use App\Models\B2bOrderOutbox;
use App\Models\DonHang;
use App\Services\Alert\TelegramAlertService;
use App\Services\DaiLyApi\B2bOrderRecoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverB2bOrderOutboxCommand extends Command
{
    protected $signature = 'b2b:recover-outbox
                            {--limit=50 : Số lượng tối đa bản ghi xử lý trong một lần quét}
                            {--max-attempts=8 : Số lần phục hồi tối đa trước khi chuyển đối soát thủ công}';

    protected $description = 'Quét và phục hồi các công việc Outbox tạo đơn B2B bị treo hoặc rơi rớt sau transaction commit';

    public function handle(B2bOrderRecoveryService $recovery, TelegramAlertService $telegramAlert): int
    {
        $limit = (int) $this->option('limit');
        $maxAttempts = (int) $this->option('max-attempts');
        $now = now();
        $stalePendingTime = $now->copy()->subSeconds(30);

        // Tìm các bản ghi PENDING quá 30s (khoảng trống giữa commit và dispatch queue)
        // hoặc PROCESSING đã hết hạn lease (worker đột tử).
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
        $held = 0;
        $manual = 0;

        foreach ($staleRecords as $record) {
            // Nhận xử lý nguyên tử: chỉ tiến trình thắng cuộc đua mới nhận được bản ghi.
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

            // Hết lượt phục hồi: KHÔNG được biến đơn thành thất bại hay nhả hạn mức.
            // Chỉ dừng tự động phục hồi và chuyển sang đối soát thủ công.
            if ($record->attempts > $maxAttempts) {
                $record->update([
                    'status' => 'MANUAL_REVIEW',
                    'last_error' => "Vượt quá {$maxAttempts} lần phục hồi tự động. Cần đối soát thủ công.",
                ]);

                $donHang = DonHang::find($record->don_hang_id);
                if ($donHang) {
                    try {
                        $telegramAlert->alertManualReview(
                            $donHang,
                            "OUTBOX B2B #{$record->id}: đơn #{$donHang->ma_don_hang} vượt quá {$maxAttempts} lần phục hồi tự động. Cần đối soát thủ công (giữ nguyên hạn mức/công nợ)."
                        );
                    } catch (\Throwable) {
                    }
                }

                Log::error("B2B Order Outbox #{$record->id} vượt quá {$maxAttempts} lần phục hồi, chuyển đối soát thủ công.");
                $manual++;
                continue;
            }

            $donHang = DonHang::find($record->don_hang_id);
            if (!$donHang) {
                $record->update([
                    'status' => 'FAILED',
                    'last_error' => "Không tìm thấy đơn hàng ID #{$record->don_hang_id}.",
                ]);
                continue;
            }

            try {
                $action = $recovery->phucHoi($donHang, $record);

                match ($action) {
                    B2bOrderRecoveryService::ACTION_HOLD_FOR_LEASE => $held++,
                    B2bOrderRecoveryService::ACTION_MANUAL_REVIEW => $manual++,
                    B2bOrderRecoveryService::ACTION_ALREADY_FINAL => null,
                    default => $recovered++,
                };

                $this->line("Outbox #{$record->id} (Đơn #{$donHang->ma_don_hang}): {$action}");
            } catch (\Throwable $e) {
                // Không để lỗi phục hồi một đơn làm hỏng cả lượt quét.
                // Trả lease để lượt quét sau thử lại thay vì kẹt cứng ở PROCESSING.
                $record->update([
                    'status' => 'PENDING',
                    'locked_until' => null,
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                ]);

                Log::error("B2B Order Outbox #{$record->id}: lỗi khi phục hồi: " . $e->getMessage());
            }
        }

        $this->info("Phục hồi Outbox B2B: {$recovered} đơn xử lý, {$held} đơn đang có worker sở hữu, {$manual} đơn chuyển đối soát.");

        return Command::SUCCESS;
    }
}
