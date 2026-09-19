<?php
// §14: Xuất DANH SÁCH ĐỐI SOÁT các giao dịch tài chính bất định / lệch sổ.
// CHỈ ĐỌC - TUYỆT ĐỐI không tự động backfill hay sửa lịch sử công nợ đã chốt.
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\DaiLyApi;
use App\Services\DaiLyApi\B2bCreditService;
use Illuminate\Support\Facades\DB;

$svc = app(B2bCreditService::class);
$drift = [];
DaiLyApi::query()->chunkById(200, function ($ds) use ($svc, &$drift) {
    foreach ($ds as $dl) {
        $cfg = $dl->cauHinhApi;
        if (!$cfg) continue;
        try { $kq = $svc->kiemTraCanDoiCongNo($dl); } catch (\Throwable $e) { continue; }
        if (empty($kq['can_doi'])) {
            $drift[] = [
                'ma_dai_ly_api' => $dl->ma_dai_ly_api,
                'cong_no_tong_hop' => (float) $cfg->cong_no_hien_tai,
                'so_du_theo_so' => $kq['so_du_tong_hop'] ?? null,
                'chenh_lech' => round((float)$cfg->cong_no_hien_tai - (float)($kq['so_du_tong_hop'] ?? 0), 2),
            ];
        }
    }
});

echo "=== DANH SÁCH ĐỐI SOÁT CÔNG NỢ LỆCH SỔ ===\n";
echo count($drift) === 0 ? "Không có đại lý nào lệch sổ.\n" : json_encode($drift, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";

echo "\n=== GIAO DỊCH TÀI CHÍNH BẤT ĐỊNH CẦN ĐỐI SOÁT TAY ===\n";
$rows = DB::select("
  SELECT d.ma_don_hang, d.trang_thai_don_hang, d.gia_ban, d.created_at,
         k.trang_thai AS trang_thai_giu, k.so_tien_giu
  FROM don_hang d
  LEFT JOIN khoan_giu_han_muc k ON k.don_hang_id = d.id
  WHERE d.nguon_don = 'b2b'
    AND d.trang_thai_don_hang IN ('MANUAL_REVIEW','PROVIDER_PENDING','PROCESSING')
  ORDER BY d.created_at ASC
  LIMIT 200
");
echo "Số đơn cần đối soát tay: ".count($rows)."\n";
foreach (array_slice($rows,0,20) as $r) {
    printf("  %s | %s | gia_ban=%s | giu=%s/%s | %s\n",
      $r->ma_don_hang, $r->trang_thai_don_hang, $r->gia_ban, $r->trang_thai_giu ?? '-', $r->so_tien_giu ?? '-', $r->created_at);
}
