<?php
/**
 * Script chạy migration trên shared hosting (không cần artisan).
 * Tạo bảng b2b_order_outbox + các bảng B2B còn thiếu.
 *
 * Truy cập: /run-b2b-migrate.php
 * Tự xóa sau khi chạy.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>B2B Migrate</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.6}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}.step{background:#252526;padding:10px;border-radius:4px;margin:8px 0;border-left:3px solid #569cd6}";
echo "</style></head><body>";
echo "<h2 style='color:#569cd6'>🔧 Tạo bảng B2B trên Production</h2>";

$requiredTables = [
    'b2b_order_outbox' => 'CREATE TABLE b2b_order_outbox (... xem migrations)',
];

try {
    echo "<div class='step'><b>Bước 1:</b> Clear cache (đảm bảo không chạy config cũ)</div>";
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "<span class='ok'>✓ Cache đã sạch</span><br><br>";

    echo "<div class='step'><b>Bước 2:</b> Liệt kê bảng B2B còn thiếu</div><pre>";
    $b2bTables = [
        'b2b_idempotency_records',
        'webhook_outbox',
        'lich_su_gui_webhook',
        'b2b_order_outbox',
        'khoan_giu_han_muc',
        'so_phat_sinh_cong_no',
    ];
    foreach ($b2bTables as $t) {
        $exists = \Illuminate\Support\Facades\Schema::hasTable($t);
        echo ($exists ? "<span class='ok'>✓ $t đã có</span>" : "<span class='bad'>✗ $t THIẾU</span>") . PHP_EOL;
    }
    echo "</pre>";

    echo "<div class='step'><b>Bước 3:</b> Chạy migration chưa chạy</div>";
    $ran = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $output = \Illuminate\Support\Facades\Artisan::output();
    echo "<pre>" . htmlspecialchars($output) . "</pre>";

    echo "<div class='step'><b>Bước 4:</b> Kiểm tra lại sau migration</div><pre>";
    foreach ($b2bTables as $t) {
        $exists = \Illuminate\Support\Facades\Schema::hasTable($t);
        echo ($exists ? "<span class='ok'>✓</span>" : "<span class='bad'>✗</span>") . " $t" . PHP_EOL;
    }
    echo "</pre>";

    if (\Illuminate\Support\Facades\Schema::hasTable('b2b_order_outbox')) {
        echo "<h3 style='color:#4ec9b0'>✅ ĐÃ TẠO XONG BẢNG b2b_order_outbox. Bây giờ thử gửi đơn B2B lại xem còn INTERNAL_ERROR không.</h3>";
        echo "<p>Lưu ý: Vẫn còn vấn đề queue worker không chạy - đơn sẽ vào QUEUED nhưng NCC không được gọi. Xem hướng dẫn setup worker bên dưới.</p>";
    } else {
        echo "<h3 style='color:#f48771'>❌ Vẫn thiếu - xem chi tiết ở trên.</h3>";
    }

} catch (\Throwable $e) {
    echo "<pre class='bad'>LỖI: " . htmlspecialchars($e->getMessage()) . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "</pre>";
}

echo "</body></html>";
register_shutdown_function(function () { @unlink(__FILE__); });
