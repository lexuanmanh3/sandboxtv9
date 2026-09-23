<?php
/**
 * Tạo trực tiếp bảng b2b_order_outbox bằng SQL (không qua artisan).
 * Hoạt động trên mọi shared hosting, không cần CLI.
 *
 * Truy cập: /fix-b2b-outbox-table.php
 * Tự xóa sau khi chạy xong.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Fix Outbox</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.6}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}.step{background:#252526;padding:10px;border-radius:4px;margin:8px 0}";
echo "pre{background:#1a1a1a;padding:10px;border-radius:4px;overflow-x:auto}";
echo "</style></head><body>";
echo "<h2 style='color:#569cd6'>🔧 Fix lỗi thiếu bảng b2b_order_outbox</h2>";

$ok = true;

try {
    // ============ BƯỚC 1: KIỂM TRA ============
    echo "<div class='step'><b>Bước 1:</b> Chẩn đoán nguyên nhân</div><pre>";
    $exists = Schema::hasTable('b2b_order_outbox');
    echo "Bảng b2b_order_outbox tồn tại: " . ($exists ? "<span class='ok'>YES</span>" : "<span class='bad'>NO</span>") . PHP_EOL;

    $file = __DIR__ . '/database/migrations/2026_09_19_020000_create_b2b_order_outbox_table.php';
    $fileExists = file_exists($file);
    echo "File migration tồn tại: " . ($fileExists ? "<span class='ok'>YES (" . basename($file) . ")</span>" : "<span class='bad'>NO</span>") . PHP_EOL;

    // Kiểm tra record migrations
    $recordMigration = null;
    if (Schema::hasTable('migrations')) {
        $recordMigration = DB::table('migrations')->where('migration', '2026_09_19_020000_create_b2b_order_outbox_table')->first();
        if ($recordMigration) {
            echo "Bảng migrations đã đánh dấu: <span class='warn'>ĐÃ CHẠY (batch={$recordMigration->batch})</span>" . PHP_EOL;
            echo "→ Đây có thể là nguyên nhân: file migration bị skip do Laravel nghĩ đã chạy." . PHP_EOL;
        } else {
            echo "Bảng migrations: <span class='ok'>CHƯA có record</span>" . PHP_EOL;
        }
    }

    // ============ BƯỚC 2: XÁC ĐỊNH HÀNH ĐỘNG ============
    if ($exists) {
        echo "</pre><h3 style='color:#4ec9b0'>✅ Bảng đã tồn tại - không cần fix.</h3>";
    } else {
        echo "</pre>";

        // ============ TẠO BẢNG BẰNG SQL ============
        echo "<div class='step'><b>Bước 2:</b> Tạo bảng bằng SQL trực tiếp</div><pre>";
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `b2b_order_outbox` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `don_hang_id` bigint(20) unsigned NOT NULL,
    `dai_ly_api_id` bigint(20) unsigned NOT NULL,
    `partner_order_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `product_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `account` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `payload_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
    `attempts` int(10) unsigned NOT NULL DEFAULT 0,
    `locked_until` timestamp NULL DEFAULT NULL,
    `last_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `processed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `b2b_order_outbox_don_hang_id_index` (`don_hang_id`),
    KEY `b2b_order_outbox_dai_ly_api_id_index` (`dai_ly_api_id`),
    KEY `b2b_order_outbox_partner_order_id_index` (`partner_order_id`),
    KEY `b2b_order_outbox_product_code_index` (`product_code`),
    KEY `b2b_order_outbox_account_index` (`account`),
    KEY `b2b_order_outbox_status_index` (`status`),
    KEY `b2b_order_outbox_locked_until_index` (`locked_until`),
    KEY `idx_b2b_outbox_pending` (`status`, `locked_until`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        try {
            DB::statement($sql);
            echo "<span class='ok'>✓ Đã chạy CREATE TABLE b2b_order_outbox</span>" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "<span class='bad'>✗ Lỗi SQL: " . htmlspecialchars($e->getMessage()) . "</span>" . PHP_EOL;
            $ok = false;
        }

        // ============ BƯỚC 3: GHI RECORD MIGRATIONS ============
        echo "</pre><div class='step'><b>Bước 3:</b> Ghi record vào bảng migrations (để lần sau khỏi chạy lại)</div><pre>";
        try {
            if (!Schema::hasTable('migrations')) {
                echo "<span class='warn'>⚠ Bảng migrations không tồn tại - bỏ qua bước này.</span>" . PHP_EOL;
            } elseif ($recordMigration) {
                echo "<span class='warn'>⚠ Đã có record từ trước - bỏ qua.</span>" . PHP_EOL;
            } else {
                // Tìm batch lớn nhất + 1
                $maxBatch = DB::table('migrations')->max('batch') ?? 0;
                DB::table('migrations')->insert([
                    'migration' => '2026_09_19_020000_create_b2b_order_outbox_table',
                    'batch' => $maxBatch + 1,
                ]);
                echo "<span class='ok'>✓ Đã ghi record migration (batch=" . ($maxBatch + 1) . ")</span>" . PHP_EOL;
            }
        } catch (\Throwable $e) {
            echo "<span class='warn'>⚠ Không ghi được record: " . htmlspecialchars($e->getMessage()) . "</span>" . PHP_EOL;
        }

        // ============ BƯỚC 4: CLEAR CACHE ============
        echo "</pre><div class='step'><b>Bước 4:</b> Clear cache</div><pre>";
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Cache::forget('b2b:schema_check');
            echo "<span class='ok'>✓ Đã clear cache</span>" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "<span class='warn'>⚠ " . htmlspecialchars($e->getMessage()) . "</span>" . PHP_EOL;
        }

        // ============ BƯỚC 5: VERIFY ============
        echo "</pre><div class='step'><b>Bước 5:</b> Verify sau khi fix</div><pre>";
        $nowExists = Schema::hasTable('b2b_order_outbox');
        echo "Bảng b2b_order_outbox: " . ($nowExists ? "<span class='ok'>✓ ĐÃ TẠO</span>" : "<span class='bad'>✗ VẪN THIẾU</span>") . PHP_EOL;

        if ($nowExists) {
            $cols = DB::select("SHOW COLUMNS FROM b2b_order_outbox");
            echo "Số cột: " . count($cols) . PHP_EOL;
            echo "Index: " . count(DB::select("SHOW INDEX FROM b2b_order_outbox")) . PHP_EOL;
        }
        echo "</pre>";

        if ($nowExists) {
            echo "<h3 style='color:#4ec9b0'>✅ XONG! Bây giờ gửi đơn B2B sẽ không còn INTERNAL_ERROR nữa.</h3>";
            echo "<p>Đừng quên setup cron queue:work (xem PHẦN B trong hướng dẫn).</p>";
        } else {
            echo "<h3 style='color:#f48771'>❌ Vẫn lỗi - kiểm tra quyền MySQL user trên host.</h3>";
        }
    }

} catch (\Throwable $e) {
    echo "<pre class='bad'>FATAL: " . htmlspecialchars($e->getMessage()) . PHP_EOL;
    echo $e->getFile() . ":" . $e->getLine() . "</pre>";
}

echo "</body></html>";
register_shutdown_function(function () { @unlink(__FILE__); });
