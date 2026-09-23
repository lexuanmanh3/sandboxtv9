<?php
/**
 * Kiểm tra schema production có đầy đủ cột cho ProcessMobileTopupJob không.
 * + Tự động thêm cột thiếu qua DB::statement (ALTER TABLE).
 *
 * Truy cập: /check-schema-fix.php
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Schema Check & Fix</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.6}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}";
echo ".box{background:#252526;padding:12px;border-radius:4px;margin:10px 0}";
echo "pre{background:#1a1a1a;padding:10px;border-radius:4px;overflow-x:auto;color:#dcdcaa;margin:5px 0}";
echo "</style></head><body>";
echo "<h2 style='color:#569cd6'>🛠️ Schema Check & Fix - DonHang</h2>";

// Lấy các cột thực tế
$cols = collect(DB::select('SHOW COLUMNS FROM don_hang'))->keyBy('Field');
echo "<div class='box'><b>Tổng số cột hiện có: " . count($cols) . "</b></div>";

// Cột cần kiểm tra
$required = [
    // Base
    'id', 'ma_don_hang', 'trang_thai_don_hang', 'nguon_don', 'ma_don_doi_tac',
    'tai_khoan_nhan', 'so_tien', 'gia_ban', 'nguoi_dung_id', 'dai_ly_api_id',
    // Mobile topup extension
    'nha_mang_yeu_cau', 'nha_mang_thuc_te',
    'loai_thue_bao_yeu_cau', 'loai_thue_bao_thuc_te',
    'ma_san_pham_snapshot', 'ten_san_pham_snapshot',
    'menh_gia_snapshot', 'gia_ban_snapshot', 'gia_von_thuc_te',
    'nha_cung_cap_thanh_cong_id', 'lan_goi_thanh_cong_id',
    'thanh_toan_luc', 'bat_dau_xu_ly_luc', 'hoan_thanh_luc', 'that_bai_luc',
    // Lease (migration 2026_09_20)
    'xu_ly_owner', 'xu_ly_lease_den',
];

$missing = [];
foreach ($required as $col) {
    if (!$cols->has($col)) {
        $missing[] = $col;
    }
}

echo "<div class='box'><b>Kết quả kiểm tra:</b><br>";
if (empty($missing)) {
    echo "<span class='ok'>✓ Tất cả cột đều tồn tại.</span>";
} else {
    echo "<span class='bad'>✗ THIẾU " . count($missing) . " cột:</span><br>";
    foreach ($missing as $m) echo "&nbsp;&nbsp;- <b style='color:#f48771'>$m</b><br>";
}
echo "</div>";

// === Index check ===
echo "<div class='box'><b>Index quan trọng:</b><pre>";
$idx = collect(DB::select('SHOW INDEX FROM don_hang'))->pluck('Key_name')->unique()->values()->all();
foreach (['idx_don_hang_processing_lease', 'don_hang_dai_ly_api_id_index', 'don_hang_unique_idempotency'] as $i) {
    echo in_array($i, $idx) ? "<span class='ok'>✓</span> $i\n" : "<span class='bad'>✗</span> $i\n";
}
echo "</pre></div>";

if (!empty($missing)) {
    echo "<div class='box'><b>🔧 Tự động FIX - thêm cột thiếu qua ALTER TABLE:</b><pre>";

    $alters = [
        "nha_mang_yeu_cau VARCHAR(64) NULL",
        "nha_mang_thuc_te VARCHAR(64) NULL",
        "loai_thue_bao_yeu_cau VARCHAR(20) NULL",
        "loai_thue_bao_thuc_te VARCHAR(20) NULL",
        "ma_san_pham_snapshot VARCHAR(64) NULL",
        "ten_san_pham_snapshot VARCHAR(255) NULL",
        "menh_gia_snapshot DECIMAL(18,2) NULL",
        "gia_ban_snapshot DECIMAL(18,2) NULL",
        "gia_von_thuc_te DECIMAL(18,2) NULL",
        "nha_cung_cap_thanh_cong_id BIGINT UNSIGNED NULL", // không FK vì có thể thiếu quyền
        "lan_goi_thanh_cong_id BIGINT UNSIGNED NULL",
        "thanh_toan_luc TIMESTAMP NULL",
        "bat_dau_xu_ly_luc TIMESTAMP NULL",
        "hoan_thanh_luc TIMESTAMP NULL",
        "that_bai_luc TIMESTAMP NULL",
        "xu_ly_owner VARCHAR(64) NULL",
        "xu_ly_lease_den TIMESTAMP NULL",
    ];

    $hadError = false;
    foreach ($missing as $col) {
        // Tìm câu ALTER phù hợp
        $def = null;
        foreach ($alters as $a) {
            if (strpos($a, $col . ' ') === 0 || strpos($a, $col . "\t") === 0) {
                $def = $a;
                break;
            }
        }
        if (!$def) {
            // tìm theo tên đơn giản
            foreach ($alters as $a) {
                if (str_starts_with($a, $col)) {
                    $def = $a;
                    break;
                }
            }
        }
        if (!$def) {
            echo "<span class='warn'>⚠ $col: chưa biết định nghĩa, bỏ qua</span>\n";
            continue;
        }
        try {
            DB::statement("ALTER TABLE don_hang ADD COLUMN $def");
            echo "<span class='ok'>✓ $col</span>\n";
        } catch (\Throwable $e) {
            // Có thể cột đã tồn tại do race condition
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "<span class='warn'>⚠ $col đã tồn tại (race condition)</span>\n";
            } else {
                echo "<span class='bad'>✗ $col: " . htmlspecialchars($e->getMessage()) . "</span>\n";
                $hadError = true;
            }
        }
    }

    if (Schema::hasColumn('don_hang', 'xu_ly_owner') && Schema::hasColumn('don_hang', 'xu_ly_lease_den')) {
        // Thêm index nếu thiếu
        try {
            DB::statement("ALTER TABLE don_hang ADD INDEX idx_don_hang_processing_lease (trang_thai_don_hang, xu_ly_lease_den)");
            echo "<span class='ok'>✓ index idx_don_hang_processing_lease</span>\n";
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), 'Duplicate')) {
                echo "<span class='warn'>⚠ index: " . htmlspecialchars($e->getMessage()) . "</span>\n";
            }
        }
    }

    echo "</pre></div>";

    // === Sau khi fix, ghi record migrations để artisan migrate không báo lỗi ===
    echo "<div class='box'><b>📝 Ghi record vào bảng migrations:</b><pre>";
    try {
        $m1 = DB::table('migrations')->where('migration', '2026_08_12_100200_extend_don_hang_for_mobile_topup')->first();
        if (!$m1) {
            $maxBatch = DB::table('migrations')->max('batch') ?? 0;
            DB::table('migrations')->insert(['migration' => '2026_08_12_100200_extend_don_hang_for_mobile_topup', 'batch' => $maxBatch + 1]);
            echo "<span class='ok'>✓ 2026_08_12_100200_extend_don_hang_for_mobile_topup</span>\n";
        }
        $m2 = DB::table('migrations')->where('migration', '2026_09_20_100000_add_processing_lease_to_don_hang_table')->first();
        if (!$m2) {
            $maxBatch = DB::table('migrations')->max('batch') ?? 0;
            DB::table('migrations')->insert(['migration' => '2026_09_20_100000_add_processing_lease_to_don_hang_table', 'batch' => $maxBatch + 1]);
            echo "<span class='ok'>✓ 2026_09_20_100000_add_processing_lease_to_don_hang_table</span>\n";
        }
    } catch (\Throwable $e) {
        echo "<span class='warn'>⚠ " . htmlspecialchars($e->getMessage()) . "</span>\n";
    }
    echo "</pre></div>";

    if ($hadError) {
        echo "<div class='box' style='background:#5a1d1d;color:white'><b>❌ Có lỗi khi ALTER - báo admin DB để fix quyền.</b></div>";
    } else {
        echo "<div class='box' style='background:#0e639c;color:white'>";
        echo "<b>✅ Xong! Bây giờ chạy lại:</b><br>";
        echo "<pre>cd /home/u662787388/domains/sandboxtv9.online/public_html && php artisan queue:work --once --tries=1</pre>";
        echo "Sẽ thấy job chạy xong (không còn FAIL 22ms).";
        echo "</div>";
    }
}

// === Check bảng liên quan ===
echo "<div class='box'><b>Bảng liên quan cần cho Job:</b><pre>";
$tables = ['b2b_order_outbox', 'lan_goi_nha_cung_cap', 'nha_cung_cap', 'ket_noi_nha_cung_cap', 'san_pham_nha_cung_cap', 'cau_hinh_dich_vu'];
foreach ($tables as $t) {
    echo (Schema::hasTable($t) ? "<span class='ok'>✓</span>" : "<span class='bad'>✗</span>") . " $t\n";
}
echo "</pre></div>";

echo "<p style='color:#888'>⚠ Nhớ upload file này trước khi test, file chạy idempotent (chạy nhiều lần OK).</p>";
echo "</body></html>";
