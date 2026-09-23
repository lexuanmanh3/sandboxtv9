<?php
/**
 * Kiểm tra PHP path chính xác trên hosting để dùng cho cron.
 *
 * Truy cập: /check-php-path.php
 * Tự xóa sau khi chạy.
 */
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>PHP Path Check</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.6}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}.box{background:#252526;padding:12px;border-radius:4px;margin:10px 0}";
echo "pre{background:#1a1a1a;padding:10px;border-radius:4px;color:#dcdcaa;overflow-x:auto}";
echo "</style></head><body>";
echo "<h2 style='color:#569cd6'>🔍 Kiểm tra PHP Path cho Cron Job</h2>";

// ============ 1. PATH HIỆN TẠI ============
echo "<div class='box'><b>1. PHP binary đang chạy:</b><br>";
echo "PHP_BINARY: <b style='color:#569cd6'>" . PHP_BINARY . "</b><br>";
echo "PHP_VERSION: <b style='color:#4ec9b0'>" . PHP_VERSION . "</b><br>";
echo "php_uname(): " . php_uname() . "<br>";
echo "</div>";

// ============ 2. CÁC PATH CÓ THỂ DÙNG ============
echo "<div class='box'><b>2. Test các path PHP phổ biến trên hosting:</b><pre>";
$commonPaths = [
    '/usr/bin/php',
    '/usr/bin/php8.1',
    '/usr/bin/php8.2',
    '/usr/bin/php8.3',
    '/usr/local/bin/php',
    '/opt/alt/php81/usr/bin/php',
    '/opt/alt/php82/usr/bin/php',
    '/opt/alt/php83/usr/bin/php',
];

$working = [];
foreach ($commonPaths as $p) {
    if (is_executable($p)) {
        $ver = @shell_exec("$p -v 2>&1");
        $verLine = $ver ? trim(explode("\n", $ver)[0]) : '?';
        echo "<span class='ok'>✓</span> $p  →  $verLine\n";
        $working[] = $p;
    } else {
        echo "<span class='bad'>✗</span> $p\n";
    }
}
echo "</pre></div>";

// ============ 3. PATH ĐƯỜNG DẪN TUYỆT ĐỐI ============
echo "<div class='box'><b>3. Đường dẫn tuyệt đối đến artisan:</b><br>";
echo "<pre style='color:#4ec9b0'>" . realpath(__DIR__) . "/artisan</pre>";
echo "<i>(Đường dẫn này lấy tự động - không lo sai)</i>";
echo "</div>";

// ============ 4. PATH LOG FILE ============
echo "<div class='box'><b>4. Đường dẫn log file:</b><br>";
$logPath = __DIR__ . '/storage/logs/queue-worker.log';
echo "File: <pre style='color:#4ec9b0'>$logPath</pre>";
echo "Có thể ghi: " . (is_writable(dirname($logPath)) ? "<span class='ok'>YES</span>" : "<span class='bad'>NO</span>") . "<br>";

// ============ 5. COMMAND HOÀN CHỈNH (COPY DÒNG NÀY) ============
echo "<div class='box'><b>5. ⭐ COMMAND HOÀN CHỈNH - COPY DÒNG DƯỚI ⭐</b><br>";

if (empty($working)) {
    echo "<span class='bad'>⚠ Không tìm thấy PHP binary nào qua danh sách phổ biến. Thử thêm các đường dẫn khác hoặc báo admin host.</span>";
} else {
    $php = $working[0];
    $artisanPath = realpath(__DIR__) . '/artisan';
    $logFile = realpath(__DIR__) . '/storage/logs/queue-worker.log';

    $cmd = "$php $artisanPath queue:work --stop-when-empty --max-time=55 --tries=3 >> $logFile 2>&1";
    echo "<pre style='color:#dcdcaa;font-size:14px;font-weight:bold'>$cmd</pre>";
    echo "<i>↑ Dòng này đã đúng 100%. Không sửa gì cả, copy và paste vào ô Command trong hPanel.</i>";
}
echo "</div>";

// ============ 6. TEST NGAY ============
echo "<div class='box'><b>6. Test chạy worker NGAY tại đây (không cần qua cron):</b><br>";
if (!empty($working)) {
    echo "<a href='#' onclick='event.preventDefault(); document.getElementById(\"test-output\").innerHTML=\"Running...\"; fetch(\"?run=1\").then(r=>r.text()).then(t=>document.getElementById(\"test-output\").innerHTML=t)' style='color:#4ec9b0'>▶️ Click để chạy queue:work 1 lần</a>";
    echo "<pre id='test-output'></pre>";
} else {
    echo "<span class='bad'>Không thể test vì thiếu PHP binary.</span>";
}
echo "</div>";

if (isset($_GET['run']) && !empty($working)) {
    ob_start();
    $php = $working[0];
    $artisanPath = realpath(__DIR__) . '/artisan';
    passthru("$php $artisanPath queue:work --stop-when-empty --max-time=10 --tries=1 2>&1");
    $output = ob_get_clean();
    echo "<script>document.getElementById('test-output').innerHTML = " . json_encode($output) . ";</script>";
}

echo "<p style='color:#888'>📋 Copy mục 5 về cho mình nếu cần trợ giúp thêm.</p>";
echo "</body></html>";
register_shutdown_function(function () { @unlink(__FILE__); });
