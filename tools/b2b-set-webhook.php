<?php
/**
 * tools/b2b-set-webhook.php — Cấu hình webhook_url + webhook secret cho đại lý test.
 *
 * Chạy:
 *   php tools/b2b-set-webhook.php http://localhost:8080/dailyb2b/api/webhook.php
 *   php tools/b2b-set-webhook.php --off        (xóa webhook_url, quay lại kiểm thử bằng tra cứu)
 *
 * Secret đọc từ C:/tmp/dailyb2b_secrets.env (B2B_WEBHOOK_SECRET) — KHÔNG in ra màn hình.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;

$maDaiLy = 'DAILYB2B_LOCAL';

$daiLy = DaiLyApi::where('ma_dai_ly_api', $maDaiLy)->first();
if (!$daiLy) {
    fwrite(STDERR, "Không tìm thấy đại lý {$maDaiLy}\n");
    exit(1);
}

$cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)->firstOrFail();

$arg = $argv[1] ?? '';

if ($arg === '--off' || $arg === '') {
    $cauHinh->update(['webhook_url' => null]);
    echo "Đã XÓA webhook_url của {$maDaiLy} (kiểm thử bằng tra cứu).\n";
    exit(0);
}

$secretPath = 'C:/tmp/dailyb2b_secrets.env';
$secret = null;
if (is_readable($secretPath)) {
    foreach (file($secretPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), 'B2B_WEBHOOK_SECRET=')) {
            $secret = trim(substr(trim($line), strlen('B2B_WEBHOOK_SECRET=')));
        }
    }
}

if (!$secret) {
    fwrite(STDERR, "Không đọc được B2B_WEBHOOK_SECRET từ {$secretPath}\n");
    exit(1);
}

$cauHinh->update([
    'webhook_url' => $arg,
    'webhook_secret_ma_hoa' => $secret,
]);

echo "Đã cấu hình webhook cho {$maDaiLy}:\n";
echo '  webhook_url        : ' . $arg . "\n";
echo '  webhook_secret     : •••••••• (' . strlen($secret) . " ký tự)\n";
