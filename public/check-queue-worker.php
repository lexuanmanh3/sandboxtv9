<?php
/**
 * Kiểm tra nhanh trạng thái queue worker và các đơn đang chờ xử lý.
 *
 * Truy cập: /check-queue-worker.php
 * Tự xóa sau khi chạy.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Queue Worker Check</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;font-size:13px;line-height:1.6}";
echo ".ok{color:#4ec9b0}.bad{color:#f48771}.warn{color:#dcdcaa}";
echo ".box{background:#252526;padding:12px;border-radius:4px;margin:8px 0}";
echo "table{border-collapse:collapse;margin-top:10px}td,th{padding:6px 12px;border:1px solid #444}";
echo "</style></head><body>";
echo "<h2 style='color:#569cd6'>⚙️ Queue Worker & Order Status Check</h2>";

// ============ 1. SỐ JOB TRONG QUEUE ============
echo "<div class='box'><b>1. Số job đang chờ trong queue (bảng jobs):</b><br>";
try {
    $count = DB::table('jobs')->count();
    $oldest = DB::table('jobs')->orderBy('id')->first();
    echo "Tổng: <b style='color:" . ($count > 0 ? '#dcdcaa' : '#4ec9b0') . "'>$count</b> job";
    if ($oldest) {
        $age = now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($oldest->available_at));
        echo " | Job cũ nhất chờ: <b style='color:#dcdcaa'>$age phút</b>";
    }
    echo "</div>";

    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Queue</th><th>Attempts</th><th>Created (phút trước)</th></tr>";
        foreach (DB::table('jobs')->orderByDesc('id')->limit(10)->get() as $j) {
            $age = now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($j->created_at));
            echo "<tr><td>{$j->id}</td><td>{$j->queue}</td><td>{$j->attempts}</td><td>$age</td></tr>";
        }
        echo "</table>";
    }
} catch (\Throwable $e) {
    echo "<span class='bad'>Bảng jobs không tồn tại: " . $e->getMessage() . "</span>";
}

// ============ 2. ĐƠN HÀNG THEO TRẠNG THÁI ============
echo "<div class='box'><b>2. Đơn hàng theo trạng thái (5 trạng thái chính):</b><br>";
$statuses = DB::table('don_hang')
    ->select('trang_thai_don_hang', DB::raw('COUNT(*) as c'))
    ->where('nguon_don', 'b2b')
    ->groupBy('trang_thai_don_hang')
    ->get();

if ($statuses->isEmpty()) {
    echo "<span class='warn'>Chưa có đơn B2B nào trong DB.</span>";
} else {
    foreach ($statuses as $s) {
        $color = match ($s->trang_thai_don_hang) {
            'QUEUED' => '#dcdcaa',
            'PROCESSING' => '#569cd6',
            'SUCCESS' => '#4ec9b0',
            'FAILED' => '#f48771',
            default => '#888',
        };
        echo "  <span style='color:$color'>● {$s->trang_thai_don_hang}</span>: {$s->c} đơn<br>";
    }
}
echo "</div>";

// ============ 3. ĐƠN QUEUED > 5 PHÚT (CẢNH BÁO) ============
echo "<div class='box'><b>3. Đơn QUEUED lâu (>5 phút) — Worker không xử lý kịp:</b><br>";
$stuck = DB::table('don_hang')
    ->where('nguon_don', 'b2b')
    ->where('trang_thai_don_hang', 'QUEUED')
    ->where('created_at', '<', now()->subMinutes(5))
    ->orderByDesc('id')
    ->limit(10)
    ->get();

if ($stuck->isEmpty()) {
    echo "<span class='ok'>✓ Không có đơn nào bị kẹt >5 phút.</span>";
} else {
    echo "<span class='bad'>✗ Có {$stuck->count()} đơn bị kẹt!</span><br>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Mã đơn</th><th>Partner</th><th>Created (phút trước)</th></tr>";
    foreach ($stuck as $d) {
        $age = now()->diffInMinutes(\Carbon\Carbon::parse($d->created_at));
        echo "<tr><td>{$d->id}</td><td>{$d->ma_don_hang}</td><td>{$d->ma_don_doi_tac}</td><td>$age</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// ============ 4. QUEUE-WORKER.LOCK STATUS ============
echo "<div class='box'><b>4. File queue-worker.lock:</b><br>";
$lockFile = __DIR__ . '/storage/framework/queue-worker.lock';
if (!file_exists($lockFile)) {
    echo "<span class='ok'>Không tồn tại (worker chưa bao giờ chạy - OK để start cron).</span>";
} else {
    $size = filesize($lockFile);
    $mtime = filemtime($lockFile);
    $age = (time() - $mtime);
    echo "Size: $size bytes | Modified: " . date('Y-m-d H:i:s', $mtime) . " ($age giây trước)";
    if ($size === 0 && $age > 120) {
        echo "<br><span class='bad'>⚠ Lock rỗng quá lâu → worker đã chết từ lâu.</span>";
    } elseif ($age < 120) {
        echo "<br><span class='ok'>✓ Worker đang chạy (lock mới được cập nhật).</span>";
    } else {
        echo "<br><span class='warn'>⚠ Worker không hoạt động gần đây.</span>";
    }
}
echo "</div>";

// ============ 5. QUEUE-WORKER.LOG ============
echo "<div class='box'><b>5. Queue worker log (20 dòng cuối):</b><br>";
$logFile = __DIR__ . '/storage/logs/queue-worker.log';
if (file_exists($logFile) && filesize($logFile) > 0) {
    $lines = file($logFile);
    foreach (array_slice($lines, -20) as $l) {
        echo htmlspecialchars($l) . "<br>";
    }
} else {
    echo "<span class='warn'>Chưa có log. Cron chưa chạy hoặc chưa được setup.</span>";
}
echo "</div>";

// ============ 6. KHUYẾN NGHỊ ============
echo "<div class='box'><b>6. KHUYẾN NGHỊ - lệnh chạy nhanh:</b><br>";
echo "<pre style='background:#1a1a1a;padding:10px;border-radius:4px;color:#dcdcaa'>";
echo "cd /home/u662787388/domains/sandboxtv9.online/public_html\n";
echo "php artisan queue:work --once --tries=1 -vvv 2>&1 | tail -50";
echo "</pre>";
echo "Hoặc bấm Perform test run trên cron-job.org.<br>";

echo "<p style='color:#888'>📋 Copy TOÀN BỘ output dán về nếu cần mình phân tích thêm.</p>";
echo "</body></html>";
register_shutdown_function(function () { @unlink(__FILE__); });
