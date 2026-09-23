<?php
/**
 * Debug: chạy thủ công + kiểm tra mọi thứ trong 1 nơi.
 *
 * Truy cập: /debug-worker.php
 */
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Worker Debug</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.6}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}";
echo ".box{background:#252526;padding:12px;border-radius:4px;margin:10px 0}";
echo "pre{background:#1a1a1a;padding:10px;border-radius:4px;overflow-x:auto;color:#dcdcaa;margin:5px 0}";
echo ".btn{display:inline-block;padding:10px 16px;background:#0e639c;color:white;border-radius:4px;text-decoration:none;margin:5px 5px 5px 0;cursor:pointer}";
echo "</style></head><body>";
echo "<h2 style='color:#569cd6'>🐞 Worker Debug — 1 nơi kiểm tra tất cả</h2>";

$root = realpath(__DIR__);
$logFile = $root . '/storage/logs/queue-worker.log';
$artisanPath = $root . '/artisan';

// =========================================
// TEST 1: PHP binary
// =========================================
echo "<div class='box'><b>TEST 1: PHP binary khả dụng?</b><pre>";
$paths = ['/opt/alt/php83/usr/bin/php', '/opt/alt/php82/usr/bin/php', '/usr/bin/php'];
$workingPHP = null;
foreach ($paths as $p) {
    $ver = @shell_exec("$p -v 2>&1");
    if ($ver && str_contains($ver, 'PHP')) {
        echo "<span class='ok'>✓ $p:</span>\n";
        echo "  " . trim(explode("\n", $ver)[0]) . "\n";
        $workingPHP = $p;
        break;
    } else {
        echo "<span class='bad'>✗ $p (not found)</span>\n";
    }
}
echo "</pre></div>";

// =========================================
// TEST 2: Đường dẫn artisan
// =========================================
echo "<div class='box'><b>TEST 2: File artisan có không?</b><pre>";
echo (file_exists($artisanPath) ? "<span class='ok'>✓ Tồn tại:</span>" : "<span class='bad'>✗ THIẾU:</span>") . " $artisanPath\n";
echo (is_readable($artisanPath) ? "<span class='ok'>✓ Đọc được</span>" : "<span class='bad'>✗ Không đọc được</span>") . "\n";
echo "</pre></div>";

// =========================================
// TEST 3: Permission storage
// =========================================
echo "<div class='box'><b>TEST 3: Thư mục logs có ghi được?</b><pre>";
$logsDir = $root . '/storage/logs';
echo "Dir: $logsDir\n";
echo (is_dir($logsDir) ? "<span class='ok'>✓ Tồn tại</span>" : "<span class='bad'>✗ THIẾU</span>") . "\n";
echo (is_writable($logsDir) ? "<span class='ok'>✓ Writable</span>" : "<span class='bad'>✗ KHÔNG writable</span>") . "\n";

// Test ghi file
try {
    $testFile = $logsDir . '/test-write.txt';
    file_put_contents($testFile, 'test ' . date('H:i:s'));
    @unlink($testFile);
    echo "<span class='ok'>✓ Ghi file OK</span>\n";
} catch (\Throwable $e) {
    echo "<span class='bad'>✗ Lỗi ghi: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}
echo "</pre></div>";

// =========================================
// TEST 4: Test chạy queue:work THỦ CÔNG
// =========================================
echo "<div class='box'>";
echo "<b>TEST 4: Chạy thủ công queue:work (max 30s)</b><br>";
echo "<form method='get'><input type='hidden' name='test' value='4'><button class='btn' type='submit'>▶️ Chạy thử ngay</button></form>";
echo "</div>";

if (isset($_GET['test']) && $_GET['test'] === '4' && $workingPHP) {
    echo "<div class='box'><b>KẾT QUẢ TEST 4:</b><pre>";
    $cmd = "$workingPHP $artisanPath queue:work --stop-when-empty --max-time=10 --tries=1 2>&1";
    echo "Command: $cmd\n\n";
    echo "Output:\n";
    echo htmlspecialchars(shell_exec($cmd) ?: '(không có output)');
    echo "\n\n";
    echo "</pre></div>";

    if (file_exists($logFile)) {
        $stat = stat($logFile);
        $age = time() - $stat['mtime'];
        $color = $age < 60 ? 'ok' : 'warn';
        echo "<div class='box'><b>queue-worker.log sau khi chạy:</b><pre>";
        echo "<span class='$color'>Modified: " . date('Y-m-d H:i:s', $stat['mtime']) . " ($age giây trước)</span>\n";
        echo "Size: " . $stat['size'] . " bytes\n";
        echo "\nNội dung:\n";
        $lines = file($logFile);
        foreach (array_slice($lines, -20) as $l) {
            echo htmlspecialchars($l);
        }
        echo "</pre></div>";
    }
}

// =========================================
// TEST 5: Cron đã setup chưa (read crontab)
// =========================================
echo "<div class='box'><b>TEST 5: Cron job hiện tại (read crontab):</b><pre>";
$crontab = @shell_exec('crontab -l 2>&1');
if (empty(trim($crontab ?? ''))) {
    echo "<span class='warn'>⚠ Không đọc được crontab — có thể hosting không cho phép. Kiểm tra qua hPanel UI.</span>\n";
} else {
    echo htmlspecialchars($crontab);
}
echo "</pre></div>";

// =========================================
// HƯỚNG DẪN
// =========================================
echo "<div class='box' style='background:#0e639c;color:white'>";
echo "<b>📋 Thứ tự debug:</b><br>";
echo "1. Nhấn nút 'Chạy thử ngay' ở TEST 4<br>";
echo "2. Nếu thấy job được xử lý → worker OK, vấn đề là cron Job<br>";
echo "3. Vào hPanel → Cron Jobs → xem cron có trong list không<br>";
echo "4. Nếu có cron → click 'Run now' để trigger ngay<br>";
echo "5. Paste kết quả TEST 5 và bất kỳ error nào về cho mình";
echo "</div>";

echo "</body></html>";
