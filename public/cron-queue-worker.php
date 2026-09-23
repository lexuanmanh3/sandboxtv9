<?php
/**
 * Trigger queue worker thủ công. Dùng được trên MỌI shared hosting kể cả khi cron bị chặn.
 *
 * CÁCH DÙNG:
 *  1. Thiết lập cron từ bên NGOÀI trỏ vào file này (cron-job.org, uptimerobot.com,...)
 *     URL: https://sandboxtv9.online/cron-queue-worker.php?secret=KEY
 *  2. Hoặc mở trình duyệt tới URL trên để chạy 1 lần thủ công.
 *
 * Có secret key để tránh người khác spam URL này.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

// ===== SECRET KEY - đổi thành chuỗi bất kỳ =====
$SECRET = 'b2b-worker-2026-secure-key-do-not-share';

$secretInput = $_GET['secret'] ?? $_POST['secret'] ?? '';
$isCli = php_sapi_name() === 'cli';

if (!$isCli && $secretInput !== $SECRET) {
    http_response_code(403);
    exit('Forbidden - thiếu secret key đúng.');
}

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$logFile = __DIR__ . '/storage/logs/queue-worker.log';

function logMsg(string $logFile, string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
    echo nl2br(htmlspecialchars($line));
}

$startTime = time();
logMsg($logFile, 'Worker invoked via ' . ($isCli ? 'CLI' : 'HTTP'));

try {
    // --max-time giây rồi tự dừng. max-jobs giới hạn số job chạy mỗi lần
    // (hữu ích cho HTTP-based cron để tránh script chạy quá lâu bị timeout).
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    // Mirror dòng output của artisan queue:work vào file log
    $cmd = 'queue:work --stop-when-empty --max-time=50 --tries=3 --max-jobs=50';
    $exitCode = $kernel->call($cmd);
    $output = $kernel->output();

    logMsg($logFile, 'queue:work exit_code=' . $exitCode);
    if ($output) {
        foreach (explode("\n", trim($output)) as $l) {
            logMsg($logFile, '  ' . $l);
        }
    } else {
        logMsg($logFile, '  (queue rỗng - không có job xử lý)');
    }

    $duration = time() - $startTime;
    logMsg($logFile, "Done in {$duration}s");

    // === Recover outbox cũng đáng chạy mỗi phút ===
    logMsg($logFile, 'Running b2b:recover-outbox...');
    $kernel->call('b2b:recover-outbox');
    $out = trim($kernel->output());
    logMsg($logFile, '  ' . ($out ?: 'no stuck orders'));

} catch (\Throwable $e) {
    logMsg($logFile, 'ERROR: ' . $e->getMessage());
    logMsg($logFile, $e->getTraceAsString());
}

echo '<br><p style="color:#888">© Worker auto-cleanup</p>';
register_shutdown_function(function () {
    // KHÔNG xóa file vì file này cần được gọi lại mỗi phút.
    // Nếu bạn muốn xóa, xóa thủ công qua File Manager.
});
