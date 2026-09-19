<?php
// §14: Kiểm tra dữ liệu trùng TRƯỚC khi cân nhắc thêm unique index.
// CHỈ ĐỌC - không sửa dữ liệu.
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$checks = [
  'don_hang: trùng (dai_ly_api_id, ma_don_doi_tac)' =>
    "SELECT dai_ly_api_id, ma_don_doi_tac, COUNT(*) c FROM don_hang
     WHERE ma_don_doi_tac IS NOT NULL AND dai_ly_api_id IS NOT NULL
     GROUP BY dai_ly_api_id, ma_don_doi_tac HAVING c > 1",
  'khoan_giu_han_muc: trùng don_hang_id' =>
    "SELECT don_hang_id, COUNT(*) c FROM khoan_giu_han_muc
     WHERE don_hang_id IS NOT NULL GROUP BY don_hang_id HAVING c > 1",
  'so_phat_sinh_cong_no: trùng (loai_phat_sinh, ma_tham_chieu)' =>
    "SELECT loai_phat_sinh, ma_tham_chieu, COUNT(*) c FROM so_phat_sinh_cong_no
     WHERE ma_tham_chieu IS NOT NULL GROUP BY loai_phat_sinh, ma_tham_chieu HAVING c > 1",
  'so_phat_sinh_cong_no: trùng don_hang_id theo loai' =>
    "SELECT don_hang_id, loai_phat_sinh, COUNT(*) c FROM so_phat_sinh_cong_no
     WHERE don_hang_id IS NOT NULL GROUP BY don_hang_id, loai_phat_sinh HAVING c > 1",
  'webhook_outbox: trùng event_id' =>
    "SELECT event_id, COUNT(*) c FROM webhook_outbox
     WHERE event_id IS NOT NULL GROUP BY event_id HAVING c > 1",
  'b2b_order_outbox: trùng don_hang_id' =>
    "SELECT don_hang_id, COUNT(*) c FROM b2b_order_outbox
     WHERE don_hang_id IS NOT NULL GROUP BY don_hang_id HAVING c > 1",
];

foreach ($checks as $label => $sql) {
    try {
        $rows = DB::select($sql);
        printf("%-58s : %s\n", $label, count($rows) === 0 ? 'SẠCH (0 nhóm trùng)' : count($rows).' NHÓM TRÙNG');
        foreach (array_slice($rows,0,5) as $r) { echo "    -> ".json_encode($r, JSON_UNESCAPED_UNICODE)."\n"; }
    } catch (\Throwable $e) {
        printf("%-58s : BỎ QUA (%s)\n", $label, substr($e->getMessage(),0,60));
    }
}
