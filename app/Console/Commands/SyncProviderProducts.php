<?php

namespace App\Console\Commands;

use App\Models\KetNoiNhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use App\Services\Topup\NhaCungCapResolver;
use Illuminate\Console\Command;

class SyncProviderProducts extends Command
{
    protected $signature = 'provider:sync-products {provider=appotapay} {--connection=}';
    protected $description = 'Đồng bộ mã sản phẩm từ nhà cung cấp, không xóa dữ liệu đã biến mất';

    public function handle(NhaCungCapResolver $resolver): int
    {
        $provider = strtoupper((string) $this->argument('provider'));
        $connections = KetNoiNhaCungCap::with('nhaCungCap')
            ->whereHas('nhaCungCap', fn ($q) => $q->where('ma_ncc', $provider))
            ->whereIn('trang_thai', ['ACTIVE', 'hoat_dong'])
            ->when($this->option('connection'), fn ($q, $id) => $q->whereKey($id))->get();
        if ($connections->isEmpty()) {
            $this->error('Không tìm thấy kết nối đang hoạt động.');
            return self::FAILURE;
        }

        $created = $updated = $skipped = 0;
        foreach ($connections as $connection) {
            foreach ($resolver->resolve($connection)->layDanhSachSanPham() as $item) {
                $code = data_get($item, 'productCode');
                $amount = data_get($item, 'amount') ?? data_get($item, 'value') ?? data_get($item, 'topupAmount');
                $telco = strtolower((string) (data_get($item, 'telco') ?? data_get($item, 'provider')));
                if (!$code || !is_numeric($amount)) { $skipped++; continue; }

                // Chỉ tự nối vào sản phẩm nội bộ đã tồn tại; không đoán và tạo trùng danh mục nghiệp vụ.
                $product = SanPham::where('menh_gia', $amount)
                    ->whereHas('loaiSanPham', fn ($q) => $q->whereRaw('LOWER(ten_loai_san_pham) LIKE ?', ['%'.$telco.'%']))
                    ->first();
                if (!$product) { $skipped++; continue; }

                $mapping = SanPhamNhaCungCap::firstOrNew([
                    'ket_noi_nha_cung_cap_id' => $connection->id, 'ma_san_pham_ncc' => $code,
                ]);
                $wasNew = !$mapping->exists;
                $mapping->fill([
                    'san_pham_id' => $product->id, 'nha_cung_cap_id' => $connection->nha_cung_cap_id,
                    'ma_nha_mang_ncc' => $telco, 'loai_dich_vu_ncc' => data_get($item, 'serviceType'),
                    'loai_thue_bao' => strtoupper((string) data_get($item, 'telcoServiceType', 'PREPAID')),
                    'menh_gia_ncc' => $amount, 'trang_thai' => 'ACTIVE', 'du_lieu_mo_rong_json' => $item,
                    'dong_bo_luc' => now(),
                ])->save();
                $wasNew ? $created++ : $updated++;
            }
        }
        $this->info("Mới: {$created}; cập nhật: {$updated}; bỏ qua chưa có sản phẩm nội bộ: {$skipped}.");
        return self::SUCCESS;
    }
}
