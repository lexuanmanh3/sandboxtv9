<?php
/**
 * Script so sánh môi trường local vs production để tìm lỗi INTERNAL_ERROR khi deploy.
 *
 * Truy cập: /b2b-deploy-check.php
 * Tự xóa sau khi chạy xong.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>B2B Deploy Check</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.5}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}h2{color:#569cd6;margin-top:18px;border-bottom:1px solid #444;padding-bottom:4px}";
echo "pre{background:#252526;padding:10px;border-radius:4px;overflow-x:auto;white-space:pre-wrap}";
echo "</style></head><body>";
echo "<h2>🔍 B2B Deploy Health Check</h2>";

$row = fn($ok, $msg) => $ok
    ? "<span class='ok'>✓</span> $msg"
    : "<span class='bad'>✗</span> $msg";

try {
    // 1. APP_KEY - nguyên nhân #1 của lỗi khi deploy
    echo "<h2>1. APP_KEY (rất quan trọng - secret_key_ma_hoa được mã hóa bằng key này)</h2><pre>";
    $appKey = config('app.key');
    $localKeyFile = 'C:/Users/Admin/.../.env'; // không cần, chỉ in ra
    echo "APP_KEY hiện tại: " . substr((string) $appKey, 0, 30) . "..." . PHP_EOL;

    if (!$appKey) {
        echo "<span class='bad'>✗ APP_KEY TRỐNG → .env chưa có APP_KEY, hoặc chưa chạy php artisan key:generate.</span>" . PHP_EOL;
    } else {
        try {
            \Illuminate\Support\Facades\Crypt::decryptString('invalid_string_to_test_mac');
            echo "<span class='ok'>✓ APP_KEY hoạt động.</span>" . PHP_EOL;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            echo "<span class='bad'>✗ APP_KEY đã thay đổi so với lúc mã hóa secret → tất cả secret_key_ma_hoa trong DB sẽ ĐỌC SAI.</span>" . PHP_EOL;
        }
    }

    // Thử giải mã secret của đối tác đầu tiên
    $cfg = DB::table('cau_hinh_api_dai_ly')->first();
    if ($cfg) {
        try {
            $s = \Illuminate\Support\Facades\Crypt::decryptString($cfg->secret_key_ma_hoa);
            echo "✓ Decrypt secret_key_ma_hoa (id={$cfg->id}) thành công: " . substr($s, 0, 12) . "... (len=" . strlen($s) . ")" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "<span class='bad'>✗ KHÔNG THỂ giải mã secret_key_ma_hoa (id={$cfg->id}) - ĐÂY CHÍNH LÀ NGUYÊN NHÂN GÂY INTERNAL_ERROR!</span>" . PHP_EOL;
            echo "  Lỗi: " . $e->getMessage() . PHP_EOL;
            echo "  APP_KEY trên server KHÁC với local → khi VerifyPartnerHmacSignature đọc secret để verify chữ ký sẽ bị DecryptException → INTERNAL_ERROR." . PHP_EOL;
        }
    } else {
        echo "<span class='bad'>✗ Bảng cau_hinh_api_dai_ly TRỐNG - đại lý trên host không có cấu hình API!</span>" . PHP_EOL;
    }
    echo "</pre>";

    // 2. DB Connection
    echo "<h2>2. Database Connection</h2><pre>";
    try {
        DB::connection()->getPdo();
        echo "<span class='ok'>✓ Kết nối DB thành công.</span>" . PHP_EOL;
        echo "  Driver: " . DB::connection()->getDriverName() . PHP_EOL;
        echo "  Database: " . DB::connection()->getDatabaseName() . PHP_EOL;

        // Lock timeout
        $row = DB::selectOne("SHOW VARIABLES LIKE 'innodb_lock_wait_timeout'");
        echo "  innodb_lock_wait_timeout: " . ($row->Value ?? 'N/A') . "s (nếu &lt; 50 có thể gây lỗi 500 khi concurrent)" . PHP_EOL;
    } catch (\Throwable $e) {
        echo "<span class='bad'>✗ KHÔNG kết nối được DB: " . $e->getMessage() . "</span>" . PHP_EOL;
    }
    echo "</pre>";

    // 3. Schema check - thiếu bảng gây QueryException
    echo "<h2>3. Schema check (bảng B2B bắt buộc)</h2><pre>";
    $required = ['dai_ly_api', 'cau_hinh_api_dai_ly', 'san_pham', 'don_hang', 'b2b_idempotency_records', 'b2b_order_outbox', 'khoan_giu_han_muc', 'so_phat_sinh_cong_no', 'loai_san_pham', 'dich_vu', 'dai_ly_api_dich_vu_duoc_phep', 'dai_ly_api_loai_san_pham_duoc_phep', 'dai_ly_api_san_pham_duoc_phep'];
    foreach ($required as $t) {
        $exists = Schema::hasTable($t);
        echo ($exists ? "<span class='ok'>✓</span>" : "<span class='bad'>✗</span>") . " $t" . PHP_EOL;
    }
    echo "</pre>";

    // 4. Đại lý và cấu hình
    echo "<h2>4. Đối tác B2B</h2><pre>";
    foreach (DB::table('dai_ly_api')->get() as $p) {
        $cfg = DB::table('cau_hinh_api_dai_ly')->where('dai_ly_api_id', $p->id)->first();
        echo "id={$p->id} ma={$p->ma_dai_ly_api} ten=\"{$p->ten_dai_ly_api}\" trang_thai={$p->trang_thai}" .
            ($cfg ? " ✓ có cấu hình" : " <span class='bad'>✗ KHÔNG CÓ cấu hình - gây firstOrFail → INTERNAL_ERROR</span>") . PHP_EOL;
    }
    echo "</pre>";

    // 5. Storage permissions (logs, queue, framework)
    echo "<h2>5. Storage permissions</h2><pre>";
    $dirs = ['storage/logs', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/testing', 'storage/app', 'bootstrap/cache'];
    foreach ($dirs as $d) {
        $path = __DIR__ . "/$d";
        if (!is_dir($path)) {
            echo "<span class='bad'>✗ MISSING: $d</span>" . PHP_EOL;
            continue;
        }
        $w = is_writable($path);
        echo ($w ? "<span class='ok'>✓</span>" : "<span class='bad'>✗</span>") . " $d " . ($w ? 'writable' : 'NOT writable') . PHP_EOL;
    }

    // queue-worker.lock
    $lockFile = __DIR__ . '/storage/framework/queue-worker.lock';
    if (file_exists($lockFile)) {
        $size = filesize($lockFile);
        echo "<span class='warn'>⚠</span> queue-worker.lock tồn tại (size=$size bytes) - worker có thể đang chạy hoặc đã crash không dọn." . PHP_EOL;
    }
    echo "</pre>";

    // 6. PHP version & extensions
    echo "<h2>6. PHP runtime</h2><pre>";
    echo "PHP version: " . PHP_VERSION . PHP_EOL;
    $ext = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl'];
    foreach ($ext as $e) {
        echo (extension_loaded($e) ? "<span class='ok'>✓</span>" : "<span class='bad'>✗</span>") . " ext: $e" . PHP_EOL;
    }
    echo "</pre>";

    // 7. APP_DEBUG & APP_ENV
    echo "<h2>7. APP_DEBUG / APP_ENV</h2><pre>";
    echo "APP_ENV: " . app()->environment() . PHP_EOL;
    echo "APP_DEBUG: " . (config('app.debug') ? 'true' : 'false') . PHP_EOL;
    if (!app()->environment('local') && !config('app.debug')) {
        echo "<span class='warn'>⚠ Production không bật debug - stack trace bị ẩn khi có lỗi 500.</span>" . PHP_EOL;
    }
    echo "</pre>";

    // 8. Cache & config cleared?
    echo "<h2>8. Cached config (có thể chứa config cũ)</h2><pre>";
    $cachedConfig = __DIR__ . '/bootstrap/cache/config.php';
    $cachedRoutes = __DIR__ . '/bootstrap/cache/routes-v7.php';
    echo (file_exists($cachedConfig) ? "<span class='warn'>⚠</span>" : "<span class='ok'>✓</span>") .
        " bootstrap/cache/config.php " . (file_exists($cachedConfig) ? "EXISTS - có thể chứa APP_KEY cũ. Nên xóa + chạy php artisan config:clear" : "OK") . PHP_EOL;
    echo (file_exists($cachedRoutes) ? "<span class='warn'>⚠</span>" : "<span class='ok'>✓</span>") .
        " bootstrap/cache/routes-v7.php " . (file_exists($cachedRoutes) ? "EXISTS - có thể routes cũ. Nên chạy php artisan route:clear" : "OK") . PHP_EOL;
    echo "</pre>";

} catch (\Throwable $e) {
    echo "<pre class='bad'>FATAL: " . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . "</pre>";
}

echo "<p style='color:#888'>📋 <strong>Copy TOÀN BỘ output dán về để mình so sánh local vs host.</strong></p>";
echo "</body></html>";
register_shutdown_function(function () { @unlink(__FILE__); });
