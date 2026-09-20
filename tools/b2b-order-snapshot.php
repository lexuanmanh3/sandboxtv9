<?php
/**
 * tools/b2b-order-snapshot.php — Chụp trạng thái ĐÚNG của một đơn B2B để làm bằng chứng.
 *
 * Chạy:  php tools/b2b-order-snapshot.php <order_id>
 *
 * In ra: trạng thái đơn, khoản giữ hạn mức, sổ phát sinh công nợ, số dư công nợ tổng hợp,
 * số lần gọi NCC (CHARGING) và số bản ghi webhook outbox. Chỉ đọc, không ghi, không in secret.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CauHinhApiDaiLy;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\LanGoiNhaCungCap;
use App\Models\SoPhatSinhCongNo;
use App\Models\WebhookOutbox;

$orderId = (int) ($argv[1] ?? 0);
if ($orderId <= 0) {
    fwrite(STDERR, "Cách dùng: php tools/b2b-order-snapshot.php <order_id>\n");
    exit(1);
}

$order = DonHang::find($orderId);
if (!$order) {
    fwrite(STDERR, "Không tìm thấy đơn #{$orderId}\n");
    exit(1);
}

$tien = static fn ($v): string => number_format((float) $v, 0, ',', '.');

echo "=== SNAPSHOT ĐƠN #{$orderId} — {$order->ma_don_hang} ===\n";
echo 'DB                 : ' . \Illuminate\Support\Facades\DB::connection()->getDatabaseName() . "\n";
echo 'ma_don_doi_tac     : ' . ($order->ma_don_doi_tac ?: '-') . "\n";
echo 'tai_khoan_nhan     : ' . ($order->tai_khoan_nhan ?: '-') . "\n";
echo 'trang_thai_don_hang: ' . $order->trang_thai_don_hang . "\n";
echo 'trang_thai_doi_soat: ' . ($order->trang_thai_doi_soat ?: '-') . "\n";
echo 'gia_ban            : ' . $tien($order->gia_ban) . "\n";
echo 'hoan_thanh_luc     : ' . ($order->hoan_thanh_luc ?: '-') . "\n";
echo 'that_bai_luc       : ' . ($order->that_bai_luc ?: '-') . "\n";

$holds = KhoanGiuHanMuc::where('don_hang_id', $orderId)->get();
echo "\n-- KhoanGiuHanMuc (" . $holds->count() . " bản ghi) --\n";
foreach ($holds as $h) {
    $cot = array_keys($h->getAttributes());
    $soTienCol = null;
    foreach (['so_tien_giu', 'so_tien', 'gia_tri', 'so_tien_han_muc'] as $c) {
        if (in_array($c, $cot, true)) { $soTienCol = $c; break; }
    }
    echo "  #{$h->id} trang_thai={$h->trang_thai}"
        . ($soTienCol ? ' ' . $soTienCol . '=' . $tien($h->{$soTienCol}) : '')
        . ' ma_tham_chieu=' . ($h->ma_tham_chieu ?: '-') . "\n";
}

$ledger = SoPhatSinhCongNo::where('don_hang_id', $orderId)->get();
echo "\n-- SoPhatSinhCongNo (" . $ledger->count() . " bản ghi) --\n";
foreach ($ledger as $s) {
    echo "  #{$s->id} loai={$s->loai_phat_sinh} so_tien=" . $tien($s->so_tien)
        . ' ma_tham_chieu=' . ($s->ma_tham_chieu ?: '-') . ' luc=' . $s->created_at . "\n";
}

$cfg = CauHinhApiDaiLy::where('dai_ly_api_id', $order->dai_ly_api_id)->first();
if ($cfg) {
    echo "\n-- CauHinhApiDaiLy (đại lý #{$order->dai_ly_api_id}) --\n";
    echo '  cong_no_hien_tai   : ' . $tien($cfg->cong_no_hien_tai) . "\n";
    echo '  han_muc_cong_no    : ' . $tien($cfg->han_muc_cong_no) . "\n";
}

$calls = LanGoiNhaCungCap::where('don_hang_id', $orderId)->where('loai_yeu_cau', 'CHARGING')->get();
echo "\n-- LanGoiNhaCungCap CHARGING (" . $calls->count() . " lần nạp) --\n";
foreach ($calls as $c) {
    echo "  #{$c->id} lan_thu={$c->lan_thu} ket_qua={$c->ket_qua_xac_dinh} trang_thai={$c->trang_thai}"
        . ' partner_ref_id=' . $c->partner_ref_id . ' ncc_id=' . $c->nha_cung_cap_id . "\n";
}

$wh = WebhookOutbox::where('don_hang_id', $orderId)->get();
echo "\n-- WebhookOutbox (" . $wh->count() . " bản ghi) --\n";
foreach ($wh as $r) {
    echo "  #{$r->id} type={$r->event_type} trang_thai={$r->trang_thai} so_lan_thu={$r->so_lan_thu}\n";
}
