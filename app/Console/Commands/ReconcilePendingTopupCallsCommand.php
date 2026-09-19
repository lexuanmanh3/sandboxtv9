<?php

namespace App\Console\Commands;

use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Jobs\CheckMobileTopupStatusJob;
use App\Models\DonHang;
use App\Models\LanGoiNhaCungCap;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\DonHangStateMachine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePendingTopupCallsCommand extends Command
{
    /**
     * Tên và chữ ký của console command.
     * Tách biệt cấu hình:
     * - limit: Giới hạn tốc độ quét (rate limit) tránh làm nghẽn API NCC
     * - max-hours: Thời hạn tối đa tự động tra cứu nền (mặc định 24h)
     * - min-interval: Khoảng cách giãn cách tối thiểu giữa 2 lần tra cứu (mặc định 180s = 3 phút)
     */
    protected $signature = 'topup:reconcile-pending
                            {--limit=50 : Số lượng lần gọi quét tối đa mỗi lượt}
                            {--max-hours=24 : Thời gian tối đa (giờ) cho phép tra cứu nền tự động}
                            {--min-interval=180 : Khoảng cách tối thiểu (giây) giữa 2 lần tra cứu}
                            {--slow-interval=3600 : Khoảng cách tra cứu (giây) cho đơn đã quá hạn tự động}';

    protected $description = 'Tra cứu nền và phục hồi các đơn hàng nạp tiền chưa rõ kết quả (PROVIDER_PENDING / MANUAL_REVIEW)';

    public function handle(DonHangStateMachine $stateMachine, TelegramAlertService $telegramAlert): int
    {
        $limit = (int) $this->option('limit');
        $maxHours = (int) $this->option('max-hours');
        $minInterval = (int) $this->option('min-interval');
        $slowInterval = max($minInterval, (int) $this->option('slow-interval'));

        $cutoffTime = Carbon::now()->subHours($maxHours);
        $minCheckTime = Carbon::now()->subSeconds($minInterval);

        $this->info("Bắt đầu quét tra cứu nền (Max hours: {$maxHours}h, Min interval: {$minInterval}s, Slow interval: {$slowInterval}s, Limit: {$limit})...");

        // 1. Quét các lần gọi NCC cho đơn hàng chưa hoàn tất (PROVIDER_PENDING, MANUAL_REVIEW hoặc PROCESSING dở dang)
        //
        // THỨ TỰ SẮP XẾP LÀ MỘT PHẦN CỦA TÍNH ĐÚNG ĐẮN: sắp theo kiem_tra_lai_luc tăng dần
        // (NULL lên trước) để luôn ưu tiên lần gọi lâu chưa được kiểm tra nhất.
        //
        // Trước đây sắp theo id tăng dần: với limit nhỏ hơn tổng số lần gọi cần tra cứu,
        // nhóm id nhỏ luôn chiếm hết slot mỗi lượt và các lần gọi phía sau KHÔNG BAO GIỜ
        // được tra cứu (starvation). Tệ hơn, các đơn đã quá hạn nằm trong nhóm id nhỏ
        // không bao giờ rời khỏi tập kết quả nên chặn vĩnh viễn các đơn mới.
        $pendingCalls = LanGoiNhaCungCap::query()
            ->with(['donHang', 'ketNoi'])
            ->whereHas('donHang', function ($q) use ($minCheckTime) {
                $q->whereIn('trang_thai_don_hang', [
                    TrangThaiDonHang::PROVIDER_PENDING->value,
                    TrangThaiDonHang::MANUAL_REVIEW->value,
                ])->orWhere(function ($sub) use ($minCheckTime) {
                    $sub->whereIn('trang_thai_don_hang', [
                        TrangThaiDonHang::PROCESSING->value,
                        'DANG_XU_LY',
                    ])->where('created_at', '<=', $minCheckTime);
                });
            })
            ->where('loai_yeu_cau', 'CHARGING')
            ->whereNotIn('ket_qua_xac_dinh', [
                KetQuaNhaCungCap::SUCCESS->value,
                KetQuaNhaCungCap::DEFINITIVE_FAILURE->value,
            ])
            ->where(function ($q) use ($minCheckTime) {
                $q->whereNull('kiem_tra_lai_luc')
                  ->orWhere('kiem_tra_lai_luc', '<=', $minCheckTime);
            })
            ->orderByRaw('CASE WHEN kiem_tra_lai_luc IS NULL THEN 0 ELSE 1 END ASC')
            ->orderBy('kiem_tra_lai_luc', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($pendingCalls->isEmpty()) {
            $this->line("Không có lần gọi nào cần tra cứu lại.");
            return Command::SUCCESS;
        }

        $dispatchedCount = 0;
        $expiredCount = 0;
        $throttledCount = 0;

        foreach ($pendingCalls as $call) {
            $order = $call->donHang;
            if (!$order) {
                continue;
            }

            $trangThaiDon = strtoupper((string) $order->trang_thai_don_hang);

            // Kiểm tra xem lần gọi đã quá thời hạn tự động tra cứu nền chưa (VD > 24 giờ)
            $createdAt = $call->bat_dau_luc ?: $call->created_at;
            $isExpired = $createdAt && $createdAt->lt($cutoffTime);

            if ($isExpired) {
                $expiredCount++;

                // Đơn đã ở MANUAL_REVIEW: KHÔNG tự động kết luận thất bại, KHÔNG giải phóng
                // hạn mức. Vẫn tiếp tục tra cứu nhưng ở nhịp chậm để bắt kết quả muộn từ NCC
                // mà không đốt quota API. Nhịp chậm được thực hiện bằng cách đẩy
                // kiem_tra_lai_luc lên hiện tại, đưa lần gọi này về cuối hàng đợi công bằng.
                if ($trangThaiDon === TrangThaiDonHang::MANUAL_REVIEW->value) {
                    if ($call->kiem_tra_lai_luc && $call->kiem_tra_lai_luc->gt(Carbon::now()->subSeconds($slowInterval))) {
                        $throttledCount++;
                        continue;
                    }

                    LanGoiNhaCungCap::where('id', $call->id)->update(['kiem_tra_lai_luc' => now()]);
                    CheckMobileTopupStatusJob::dispatch($call->id);
                    $dispatchedCount++;
                    $this->line("Tra cứu nhịp chậm cho lần gọi #{$call->id} (Đơn #{$order->ma_don_hang}, đã đối soát thủ công)");
                    continue;
                }

                if ($trangThaiDon === TrangThaiDonHang::PROVIDER_PENDING->value) {
                    // Khi hết thời hạn tra cứu nền: GIỮ NGUYÊN TIỀN / HẠN MỨC, chuyển đơn sang MANUAL_REVIEW
                    DB::transaction(function () use ($order, $stateMachine, $maxHours, $telegramAlert) {
                        $lockedOrder = DonHang::query()->lockForUpdate()->find($order->id);
                        if ($lockedOrder && $lockedOrder->trang_thai_don_hang === TrangThaiDonHang::PROVIDER_PENDING->value) {
                            $stateMachine->chuyen(
                                $lockedOrder,
                                TrangThaiDonHang::MANUAL_REVIEW,
                                "Quá thời hạn tra cứu nền tự động ({$maxHours}h), chuyển sang đối soát thủ công"
                            );
                            $lockedOrder->update(['trang_thai_doi_soat' => 'cho_doi_soat']);

                            DB::afterCommit(function () use ($lockedOrder, $telegramAlert, $maxHours) {
                                $cacheKey = "alert_expired_polling_{$lockedOrder->id}";
                                if (Cache::add($cacheKey, true, now()->addHours(24))) {
                                    try {
                                        $telegramAlert->alertManualReview(
                                            $lockedOrder->fresh() ?: $lockedOrder,
                                            "Đơn hàng #{$lockedOrder->ma_don_hang} đã quá thời hạn tra cứu tự động ({$maxHours}h). Chuyển Admin đối soát thủ công với NCC, bảo toàn hạn mức/tiền!"
                                        );
                                    } catch (\Throwable) {}
                                }
                            });
                        }
                    });

                    // Đẩy lần gọi về cuối hàng đợi công bằng để không chặn các đơn khác.
                    LanGoiNhaCungCap::where('id', $call->id)->update(['kiem_tra_lai_luc' => now()]);

                    $this->warn("Lần gọi #{$call->id} (Đơn #{$order->ma_don_hang}) quá thời hạn {$maxHours}h -> Đã chuyển đối soát thủ công.");
                }

                continue;
            }

            // Đơn còn trong thời hạn tra cứu: Dispatch job với WithoutOverlapping chống duplicate
            CheckMobileTopupStatusJob::dispatch($call->id);
            $dispatchedCount++;
            $this->line("Đã dispatch CheckMobileTopupStatusJob cho lần gọi #{$call->id} (Đơn #{$order->ma_don_hang})");
        }

        $this->info("Hoàn tất: Đã dispatch {$dispatchedCount} jobs, {$expiredCount} đơn hết hạn tự động chuyển đối soát, {$throttledCount} lần gọi đang chờ nhịp chậm.");
        return Command::SUCCESS;
    }
}
