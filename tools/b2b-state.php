<?php
/**
 * tools/b2b-state.php — In trạng thái đơn B2B, khoản giữ hạn mức, công nợ, outbox.
 *
 * Chạy:  php tools/b2b-state.php
 * Không in secret. Chỉ đọc, không ghi.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\B2bOrderOutbox;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\LanGoiNhaCungCap;
use App\Models\SoPhatSinhCongNo;
use App\Models\WebhookOutbox;
use Illuminate\Support\Facades\DB;

$dbName = DB::connection()->getDatabaseName();
echo "DB: {$dbName}  |  APP_ENV: " . app()->environment() . "\n";
echo str_repeat('=', 100) . "\n";

$orders = DonHang::whereNotNull('dai_ly_api_id')->orderBy('id')->get();

printf("%-4s %-16s %-13s %-14s %-12s %-9s %-8s\n", 'ID', 'partner_order_id', 'account', 'trang_thai', 'doi_soat', 'gia_ban', 'ncc_calls');
echo str_repeat('-', 100) . "\n";

foreach ($orders as $o) {
    $calls = LanGoiNhaCungCap::where('don_hang_id', $o->id)->where('loai_yeu_cau', 'CHARGING')->count();
    printf(
        "%-4d %-16s %-13s %-14s %-12s %-9s %-8d\n",
        $o->id,
        (string) $o->ma_don_doi_tac,
        (string) $o->tai_khoan_nhan,
        (string) $o->trang_thai_don_hang,
        (string) ($o->trang_thai_doi_soat ?: '-'),
        (string) $o->gia_ban,
        $calls
    );
}

echo "\n--- LanGoiNhaCungCap (CHARGING) ---\n";
printf("%-4s %-6s %-8s %-22s %-20s %-8s\n", 'ID', 'order', 'lan_thu', 'ket_qua_xac_dinh', 'trang_thai', 'ncc_id');
foreach (LanGoiNhaCungCap::where('loai_yeu_cau', 'CHARGING')->orderBy('id')->get() as $c) {
    printf(
        "%-4d %-6d %-8d %-22s %-20s %-8d\n",
        $c->id, $c->don_hang_id, (int) $c->lan_thu,
        (string) ($c->ket_qua_xac_dinh ?: '-'), (string) $c->trang_thai, (int) $c->nha_cung_cap_id
    );
}

echo "\n--- KhoanGiuHanMuc ---\n";
printf("%-4s %-6s %-12s %-12s %-14s\n", 'ID', 'order', 'so_tien', 'trang_thai', 'ma_tham_chieu');
foreach (KhoanGiuHanMuc::orderBy('id')->get() as $k) {
    printf("%-4d %-6s %-12s %-12s %-14s\n", $k->id, (string) $k->don_hang_id, (string) $k->so_tien, (string) $k->trang_thai, (string) ($k->ma_tham_chieu ?? '-'));
}

echo "\n--- SoPhatSinhCongNo ---\n";
printf("%-4s %-6s %-10s %-14s %-16s %-22s\n", 'ID', 'order', 'loai', 'so_tien', 'ma_tham_chieu', 'created_at');
foreach (SoPhatSinhCongNo::orderBy('id')->get() as $s) {
    printf(
        "%-4d %-6s %-10s %-14s %-16s %-22s\n",
        $s->id, (string) $s->don_hang_id, (string) $s->loai_phat_sinh,
        (string) $s->so_tien, (string) ($s->ma_tham_chieu ?? '-'), (string) $s->created_at
    );
}

echo "\n--- B2bOrderOutbox ---\n";
foreach (B2bOrderOutbox::orderBy('id')->get() as $r) {
    printf("#%-4d order=%-5s status=%-12s attempts=%s\n", $r->id, (string) $r->don_hang_id, (string) $r->status, (string) $r->attempts);
}

echo "\n--- WebhookOutbox ---\n";
$wh = WebhookOutbox::orderBy('id')->get();
if ($wh->isEmpty()) {
    echo "(rỗng — chưa cấu hình webhook_url cho đại lý, đúng như thiết kế)\n";
}
foreach ($wh as $r) {
    printf(
        "#%-4d order=%-5s type=%-16s status=%-14s so_lan_thu=%s next=%s\n",
        $r->id, (string) $r->don_hang_id, (string) $r->event_type,
        (string) $r->trang_thai, (string) $r->so_lan_thu, (string) ($r->lan_thu_tiep_theo ?? '-')
    );
}

echo "\n--- Queue jobs / failed_jobs ---\n";
echo 'jobs        : ' . DB::table('jobs')->count() . "\n";
echo 'failed_jobs : ' . DB::table('failed_jobs')->count() . "\n";
