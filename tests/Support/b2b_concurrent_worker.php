<?php

/**
 * Worker cho kiểm thử tương tranh thật (§13).
 *
 * Chạy như một TIẾN TRÌNH RIÊNG BIỆT với kết nối DB riêng, để các transaction
 * thực sự chồng lấn nhau ở tầng MySQL — điều mà DatabaseTransactions trong
 * một tiến trình PHPUnit duy nhất không thể mô phỏng được.
 *
 * Dùng: php tests/Support/b2b_concurrent_worker.php <client_id> <idem_key> <partner_order_id> <account> <barrier_file>
 *
 * KHÔNG dùng credential production. Chỉ chạy trên DB test.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CauHinhApiDaiLy;
use App\Services\DaiLyApi\B2bOrderService;

[$script, $clientId, $idemKey, $partnerOrderId, $account, $barrierFile, $tenDbChiDinh] = $argv + [null, null, null, null, null, null, null];

// Cô lập pha TẠO ĐƠN khỏi pha XỬ LÝ BẤT ĐỒNG BỘ: với queue 'sync' (mặc định của
// phpunit.xml), job nạp tiền chạy ngay trong tiến trình và có thể THẤT BẠI rồi
// GIẢI PHÓNG khoản giữ, mở lại hạn mức cho tiến trình khác. Khi đó số đơn tạo được
// không còn là bất biến quan sát được. Dùng queue 'null' để khoản giữ giữ nguyên
// trạng thái HOLDING, làm bất biến "tổng giữ <= hạn mức" trở nên xác định.
config(['queue.default' => 'null']);

// Đặt DB TƯỜNG MINH thay vì phụ thuộc vào việc kế thừa biến môi trường: tiến trình con
// có thể không thừa hưởng env của PHPUnit (php.ini variables_order), và khi đó nó sẽ
// âm thầm nối vào DB trong .env. Chỉ định rõ ràng để hành vi luôn xác định.
if ($tenDbChiDinh) {
    config(['database.connections.mysql.database' => $tenDbChiDinh]);
    Illuminate\Support\Facades\DB::purge('mysql');
}

// CHỐT AN TOÀN DỮ LIỆU: từ chối chạy nếu không phải DB test.
$tenDb = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
if (!str_ends_with($tenDb, '_test')) {
    fwrite(STDERR, "TỪ CHỐI CHẠY: worker chỉ được chạy trên DB test, đang nối vào '{$tenDb}'.\n");
    exit(4);
}

$config = CauHinhApiDaiLy::where('client_id', $clientId)->firstOrFail();
$daiLy = $config->daiLyApi;

// Chờ hiệu lệnh xuất phát để tất cả tiến trình lao vào cùng lúc
if ($barrierFile) {
    $deadline = microtime(true) + 10.0;
    while (!file_exists($barrierFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "barrier timeout\n");
            exit(3);
        }
        usleep(2000);
    }
}

$payload = [
    'partner_order_id' => $partnerOrderId,
    'product_code' => 'TOPUP_VTE_10K',
    'account' => $account,
];

$raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$startedAt = microtime(true);

try {
    $ketQua = app(B2bOrderService::class)->taoDonHang($daiLy, $payload, $idemKey, $raw);
    $out = [
        'ok' => true,
        'db' => $tenDb,
        'is_replay' => $ketQua['is_replay'] ?? false,
        'order_id' => $ketQua['data']['order_id'] ?? null,
        'http_code' => $ketQua['http_code'] ?? null,
        'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ];
} catch (\Throwable $e) {
    $out = [
        'ok' => false,
        'db' => $tenDb,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE) . "\n";
