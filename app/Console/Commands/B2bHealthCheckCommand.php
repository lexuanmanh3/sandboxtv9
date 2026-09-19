<?php

namespace App\Console\Commands;

use App\Models\B2bOrderOutbox;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bCreditService;
use App\Support\B2bSecurityCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Kiểm tra sức khỏe toàn diện hệ thống tiền B2B.
 *
 * Đây là lưới an toàn vận hành: mọi bất biến nghiệp vụ quan trọng đều được kiểm tra
 * định kỳ và báo động, thay vì chỉ phát hiện khi kế toán đối chiếu tay.
 *
 * Trả về mã thoát khác 0 khi có vấn đề nghiêm trọng, để cron/monitoring có thể bắt.
 *
 *   php artisan b2b:health-check
 *   php artisan b2b:health-check --stale-hours=2 --json
 */
class B2bHealthCheckCommand extends Command
{
    protected $signature = 'b2b:health-check
                            {--stale-hours=2 : Ngưỡng (giờ) coi một đơn chưa có kết quả cuối là "treo"}
                            {--json : Xuất kết quả dạng JSON để đưa vào hệ thống giám sát}';

    protected $description = 'Kiểm tra sức khỏe hệ thống B2B: cân đối công nợ, khoản giữ treo, đơn treo, outbox/webhook tồn đọng';

    public function handle(B2bCreditService $creditService): int
    {
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $staleCutoff = now()->subHours($staleHours);

        $baoDong = [];
        $canhBao = [];

        // ------------------------------------------------------------------
        // 0. Cache bảo mật phải dùng chung giữa các app server
        // ------------------------------------------------------------------
        $securityCache = [
            'store' => B2bSecurityCache::storeName() ?: config('cache.default'),
            'driver' => B2bSecurityCache::driver(),
            'is_shared' => B2bSecurityCache::isShared(),
        ];

        if (!B2bSecurityCache::isShared()) {
            $canhBao[] = [
                'ma' => 'SECURITY_CACHE_NOT_SHARED',
                'mo_ta' => "Cache bảo mật B2B đang dùng driver '{$securityCache['driver']}' (chỉ có phạm vi một máy). "
                    . 'Khi chạy nhiều app server, nonce đã dùng ở server này vẫn qua được ở server khác và rate limit bị nhân theo số server. '
                    . 'Đặt B2B_SECURITY_CACHE_STORE=redis trong production.',
            ];
        }

        // ------------------------------------------------------------------
        // 1. Cân đối công nợ: số dư tổng hợp phải khớp sổ phát sinh bất biến
        // ------------------------------------------------------------------
        $lechCongNo = [];
        $soDuAm = [];

        DaiLyApi::query()->with('cauHinhApi')->chunkById(200, function ($dsDaiLy) use ($creditService, &$lechCongNo, &$soDuAm) {
            foreach ($dsDaiLy as $daiLy) {
                $kq = $creditService->kiemTraCanDoiCongNo($daiLy);

                if (!$kq['can_doi']) {
                    $lechCongNo[] = [
                        'dai_ly' => $daiLy->ma_dai_ly_api,
                        'so_du_tong_hop' => $kq['so_du_tong_hop'],
                        'so_du_theo_so_cai' => $kq['so_du_theo_so_cai'],
                        'chenh_lech' => $kq['chenh_lech'],
                        'chenh_lech_but_toan_cuoi' => $kq['chenh_lech_but_toan_cuoi'],
                    ];
                }

                if ($kq['so_du_tong_hop'] < -0.01) {
                    $soDuAm[] = [
                        'dai_ly' => $daiLy->ma_dai_ly_api,
                        'so_du_tong_hop' => $kq['so_du_tong_hop'],
                    ];
                }
            }
        });

        if (!empty($lechCongNo)) {
            $baoDong[] = [
                'ma' => 'DEBT_LEDGER_DRIFT',
                'mo_ta' => count($lechCongNo) . ' đại lý có số dư công nợ LỆCH so với sổ phát sinh. Không tự động sửa — cần kế toán đối soát.',
                'chi_tiet' => $lechCongNo,
            ];
        }

        if (!empty($soDuAm)) {
            $canhBao[] = [
                'ma' => 'NEGATIVE_DEBT_BALANCE',
                'mo_ta' => count($soDuAm) . ' đại lý đang có dư nợ âm (số dư có). Kiểm tra chính sách thanh toán vượt/hoàn tiền.',
                'chi_tiet' => $soDuAm,
            ];
        }

        // ------------------------------------------------------------------
        // 2. Khoản giữ hạn mức treo: đơn đã kết thúc nhưng khoản giữ vẫn HOLDING
        // ------------------------------------------------------------------
        $giuTreo = KhoanGiuHanMuc::query()
            ->join('don_hang', 'don_hang.id', '=', 'khoan_giu_han_muc.don_hang_id')
            ->where('khoan_giu_han_muc.trang_thai', 'HOLDING')
            ->whereIn('don_hang.trang_thai_don_hang', ['SUCCESS', 'FAILED', 'REFUNDED'])
            ->where('khoan_giu_han_muc.created_at', '<=', $staleCutoff)
            ->select([
                'khoan_giu_han_muc.id',
                'khoan_giu_han_muc.so_tien_giu',
                'don_hang.ma_don_hang',
                'don_hang.trang_thai_don_hang',
            ])
            ->limit(100)
            ->get()
            ->map(fn($r) => [
                'khoan_giu_id' => $r->id,
                'ma_don_hang' => $r->ma_don_hang,
                'trang_thai_don_hang' => $r->trang_thai_don_hang,
                'so_tien_giu' => (float) $r->so_tien_giu,
            ])
            ->all();

        if (!empty($giuTreo)) {
            $baoDong[] = [
                'ma' => 'STUCK_CREDIT_HOLD',
                'mo_ta' => count($giuTreo) . ' khoản giữ hạn mức vẫn ở trạng thái HOLDING trong khi đơn đã kết thúc. '
                    . 'Hạn mức của đại lý đang bị chiếm dụng sai.',
                'chi_tiet' => $giuTreo,
            ];
        }

        // ------------------------------------------------------------------
        // 3. Đơn treo: chưa có kết quả cuối quá lâu
        // ------------------------------------------------------------------
        $donTreo = DonHang::query()
            ->whereNotNull('dai_ly_api_id')
            ->whereIn('trang_thai_don_hang', ['QUEUED', 'PROCESSING', 'PROVIDER_PENDING', 'MANUAL_REVIEW', 'REFUND_PENDING', 'DANG_XU_LY'])
            ->where('updated_at', '<=', $staleCutoff)
            ->select(['id', 'ma_don_hang', 'trang_thai_don_hang', 'trang_thai_doi_soat', 'updated_at'])
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'ma_don_hang' => $d->ma_don_hang,
                'trang_thai' => $d->trang_thai_don_hang,
                'trang_thai_doi_soat' => $d->trang_thai_doi_soat,
                'cap_nhat_luc' => $d->updated_at?->toIso8601String(),
            ])
            ->all();

        if (!empty($donTreo)) {
            $canhBao[] = [
                'ma' => 'STALE_ORDERS',
                'mo_ta' => count($donTreo) . " đơn B2B chưa có kết quả cuối và không được cập nhật trong {$staleHours} giờ.",
                'chi_tiet' => $donTreo,
            ];
        }

        // ------------------------------------------------------------------
        // 4. Outbox đơn hàng tồn đọng
        // ------------------------------------------------------------------
        $outboxTreo = B2bOrderOutbox::query()
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->where('updated_at', '<=', $staleCutoff)
            ->select(['id', 'don_hang_id', 'status', 'attempts', 'updated_at'])
            ->orderBy('updated_at')
            ->limit(100)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'don_hang_id' => $o->don_hang_id,
                'status' => $o->status,
                'attempts' => $o->attempts,
                'cap_nhat_luc' => $o->updated_at?->toIso8601String(),
            ])
            ->all();

        if (!empty($outboxTreo)) {
            $baoDong[] = [
                'ma' => 'ORDER_OUTBOX_BACKLOG',
                'mo_ta' => count($outboxTreo) . ' bản ghi outbox đơn hàng tồn đọng. Kiểm tra worker queue và cron b2b:recover-outbox.',
                'chi_tiet' => $outboxTreo,
            ];
        }

        // ------------------------------------------------------------------
        // 5. Webhook tồn đọng / thất bại / cần xử lý thủ công
        // ------------------------------------------------------------------
        $webhookDenHan = WebhookOutbox::query()
            ->where('trang_thai', 'PENDING')
            ->where(function ($q) {
                $q->whereNull('lan_thu_tiep_theo')->orWhere('lan_thu_tiep_theo', '<=', now());
            })
            ->where('created_at', '<=', $staleCutoff)
            ->count();

        $webhookLeaseHetHan = WebhookOutbox::query()
            ->where('trang_thai', 'PROCESSING')
            ->whereNotNull('khoa_den')
            ->where('khoa_den', '<', now())
            ->count();

        $webhookThuCong = WebhookOutbox::query()
            ->where('trang_thai', 'MANUAL_REVIEW')
            ->count();

        if ($webhookDenHan > 0 || $webhookLeaseHetHan > 0) {
            $baoDong[] = [
                'ma' => 'WEBHOOK_OUTBOX_BACKLOG',
                'mo_ta' => "Webhook tồn đọng: {$webhookDenHan} sự kiện quá hạn gửi lại, {$webhookLeaseHetHan} sự kiện hết hạn lease (worker đột tử). "
                    . 'Kiểm tra worker queue và cron b2b:retry-webhooks.',
                'chi_tiet' => [
                    'qua_han_gui_lai' => $webhookDenHan,
                    'het_han_lease' => $webhookLeaseHetHan,
                ],
            ];
        }

        if ($webhookThuCong > 0) {
            $canhBao[] = [
                'ma' => 'WEBHOOK_MANUAL_REVIEW',
                'mo_ta' => "{$webhookThuCong} sự kiện webhook đã cạn số lần thử và đang chờ xử lý thủ công. Đối tác chưa nhận được thông báo kết quả.",
            ];
        }

        // ------------------------------------------------------------------
        // Kết xuất
        // ------------------------------------------------------------------
        $ketQua = [
            'kiem_tra_luc' => now()->toIso8601String(),
            'security_cache' => $securityCache,
            'bao_dong' => $baoDong,
            'canh_bao' => $canhBao,
            'so_bao_dong' => count($baoDong),
            'so_canh_bao' => count($canhBao),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($ketQua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('=== KIỂM TRA SỨC KHỎE HỆ THỐNG B2B ===');

            $this->line("Cache bảo mật: store={$securityCache['store']} driver={$securityCache['driver']} "
                . 'dùng chung=' . ($securityCache['is_shared'] ? 'CÓ' : 'KHÔNG'));

            if (empty($baoDong) && empty($canhBao)) {
                $this->info('Không phát hiện bất thường.');
            }

            foreach ($baoDong as $item) {
                $this->error("[BÁO ĐỘNG] {$item['ma']}: {$item['mo_ta']}");
            }

            foreach ($canhBao as $item) {
                $this->warn("[CẢNH BÁO] {$item['ma']}: {$item['mo_ta']}");
            }

            $this->line('Tổng: ' . count($baoDong) . ' báo động, ' . count($canhBao) . ' cảnh báo.');
        }

        return empty($baoDong) ? Command::SUCCESS : Command::FAILURE;
    }
}
