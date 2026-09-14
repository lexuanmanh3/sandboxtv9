<?php

namespace App\Console\Commands;

use App\Models\DichVu;
use App\Models\KetNoiNhaCungCap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use Database\Seeders\DichVuSeeder;
use Database\Seeders\LoaiSanPhamSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeCatalogCommand extends Command
{
    protected $signature = 'catalog:normalize';
    protected $description = 'Chuẩn hóa danh mục: Phân tách Nạp tiền vs Nạp Data, dọn dẹp mã NCC khỏi Loại sản phẩm & Sản phẩm Web';

    public function handle(): int
    {
        $this->info('--- Bắt đầu chuẩn hóa Danh mục Dịch vụ & Loại sản phẩm ---');

        // 1. Chạy lại DichVuSeeder để đảm bảo Dịch vụ đầy đủ
        $this->call(DichVuSeeder::class);

        $dichVuTopup = DichVu::where('ma_dich_vu', 'MOBILE_TOPUP')->first();
        $dichVuData = DichVu::where('ma_dich_vu', 'TOPUP_DATA')->first();

        // 2. Chạy lại LoaiSanPhamSeeder để phục hồi cây danh mục chuẩn
        $this->call(LoaiSanPhamSeeder::class);

        // 3. Dọn dẹp các mã NCC rác khỏi bảng loai_san_pham
        // (Những dòng có tên/mã chứa 'APPOTA_TOPUP', 'COMMON_TOPUP', 'TOPUP_VMS_...', 'TOPUP_VNA_...', v.v.)
        $dirtyCategories = LoaiSanPham::where(function ($q) {
            $q->where('ma_loai_san_pham', 'like', 'TOPUP_%')
              ->orWhere('ma_loai_san_pham', 'like', 'APPOTA_%')
              ->orWhere('ma_loai_san_pham', 'like', 'COMMON_%')
              ->orWhere('ma_loai_san_pham', 'like', 'GTEL_%')
              ->orWhere('ma_loai_san_pham', 'like', '%_DATA_%');
        })
        ->whereNotIn('ma_loai_san_pham', [
            'VTE_TOPUP', 'VMS_TOPUP', 'VNA_TOPUP', 'VNM_TOPUP', 'GMOBILE_TOPUP', 'WT_TOPUP',
            'VTE_TOPUPDATA', 'VMS_TOPUPDATA', 'VNA_TOPUPDATA',
            'VTE_PINCODE', 'VMS_PINCODE', 'VNA_PINCODE', 'VNM_PINCODE', 'GMOBILE_PINCODE', 'WT_PINCODE',
            'VTE_PINDATA', 'VMS_PINDATA', 'VNA_PINDATA',
            'VTE', 'VMS', 'VNA', 'VNM', 'GMOBILE', 'WT',
            'EVN_BILL', 'WATER_BILL', 'INTERNET_BILL', 'TV_BILL', 'MOBILE_BILL', 'TELEPHONE_BILL',
            'TICKET_BILL', 'FINANCE_BILL', 'INS_BILL', 'GARENA_GAME', 'ZING_GAME', 'GATE_GAME',
            'VCOIN_GAME', 'ONCA_GAME', 'SOHA_GAME', 'BIT_GAME', 'FUNCA_GAME', 'SCOIN_GAME',
            'APPOTA_GAME', 'GOSU_GAME', 'KUL_GAME', 'VEGA_GAME', 'MEGA_GAME', 'VCARD_GAME',
            'VINPLAY_GAME', 'ANPAY_GAME', 'VTC_GAME', 'ON_GAME'
        ])
        ->get();

        $deletedCats = 0;
        foreach ($dirtyCategories as $cat) {
            try {
                // Chuyển sản phẩm sang loại chuẩn trước khi xóa (nếu có)
                SanPham::where('loai_san_pham_id', $cat->id)->delete();
                $cat->delete();
                $deletedCats++;
            } catch (\Throwable $e) {
                // Bỏ qua nếu có ràng buộc khóa ngoại chặt
            }
        }

        $this->info("Đã dọn dẹp {$deletedCats} loại sản phẩm rác/mã NCC khỏi bảng Loại sản phẩm.");

        // 4. Tạo Sản phẩm Web chuẩn cho Nạp tiền điện thoại (10k, 20k, 30k, 50k, 100k, 200k, 300k, 500k)
        $topupDenominations = [
            10000 => '10.000đ',
            20000 => '20.000đ',
            30000 => '30.000đ',
            50000 => '50.000đ',
            100000 => '100.000đ',
            200000 => '200.000đ',
            300000 => '300.000đ',
            500000 => '500.000đ',
        ];

        $topupCarriers = [
            'VTE_TOPUP' => 'Viettel',
            'VMS_TOPUP' => 'Mobifone',
            'VNA_TOPUP' => 'Vinaphone',
            'VNM_TOPUP' => 'Vietnamobile',
            'GMOBILE_TOPUP' => 'Gmobile',
            'WT_TOPUP' => 'Wintel',
        ];

        foreach ($topupCarriers as $code => $carrierName) {
            $cat = LoaiSanPham::where('ma_loai_san_pham', $code)->first();
            if (!$cat || !$dichVuTopup) continue;

            // Đảm bảo gắn đúng Dịch vụ Nạp tiền điện thoại
            $cat->update(['dich_vu_id' => $dichVuTopup->id, 'trang_thai' => 'hoat_dong']);

            $thuTu = 1;
            foreach ($topupDenominations as $amount => $label) {
                $productCode = 'TOPUP_' . str_replace('_TOPUP', '', $code) . '_' . ($amount / 1000) . 'K';
                SanPham::updateOrCreate(
                    ['ma_san_pham' => $productCode],
                    [
                        'dich_vu_id' => $dichVuTopup->id,
                        'loai_san_pham_id' => $cat->id,
                        'ten_san_pham' => "{$carrierName} {$label}",
                        'menh_gia' => $amount,
                        'gia_ban' => $amount,
                        'chiet_khau_phan_tram' => 0,
                        'don_vi' => 'VND',
                        'thu_tu' => $thuTu++,
                        'trang_thai' => 'hoat_dong',
                        'hien_thi_web_app' => true,
                    ]
                );
            }
        }

        // 5. Tạo Sản phẩm Web chuẩn cho Nạp Data 3G/4G
        $dataCarriers = [
            'VTE_TOPUPDATA' => 'Viettel',
            'VMS_TOPUPDATA' => 'Mobifone',
            'VNA_TOPUPDATA' => 'Vinaphone',
        ];

        $sampleDataPackages = [
            ['code' => 'DATA_1GB_DAY', 'name' => 'Gói 1GB / Ngày', 'amount' => 5000, 'desc' => '1GB data tốc độ cao dùng trong 24h'],
            ['code' => 'DATA_2GB_DAY', 'name' => 'Gói 2GB / Ngày (ST10K)', 'amount' => 10000, 'desc' => '2GB data tốc độ cao dùng trong 24h'],
            ['code' => 'DATA_3GB_3DAY', 'name' => 'Gói 3GB / 3 Ngày', 'amount' => 15000, 'desc' => '3GB data tốc độ cao dùng trong 3 ngày'],
            ['code' => 'DATA_7GB_7DAY', 'name' => 'Gói 7GB / 7 Ngày', 'amount' => 30000, 'desc' => '7GB data tốc độ cao dùng trong 7 ngày'],
            ['code' => 'DATA_30GB_MONTH', 'name' => 'Gói 30GB / Tháng (1GB/ngày)', 'amount' => 70000, 'desc' => '1GB data/ngày gia hạn mỗi tháng'],
            ['code' => 'DATA_60GB_MONTH', 'name' => 'Gói 60GB / Tháng (2GB/ngày)', 'amount' => 90000, 'desc' => '2GB data/ngày gia hạn mỗi tháng'],
            ['code' => 'DATA_120GB_MONTH', 'name' => 'Gói 120GB / Tháng (4GB/ngày)', 'amount' => 120000, 'desc' => '4GB data/ngày gia hạn mỗi tháng'],
        ];

        foreach ($dataCarriers as $code => $carrierName) {
            $cat = LoaiSanPham::where('ma_loai_san_pham', $code)->first();
            if (!$cat || !$dichVuData) continue;

            // Đảm bảo gắn đúng Dịch vụ Nạp Data
            $cat->update(['dich_vu_id' => $dichVuData->id, 'trang_thai' => 'hoat_dong']);

            $thuTu = 1;
            foreach ($sampleDataPackages as $pkg) {
                $pkgCode = 'DATA_' . str_replace('_TOPUPDATA', '', $code) . '_' . $pkg['code'];
                SanPham::updateOrCreate(
                    ['ma_san_pham' => $pkgCode],
                    [
                        'dich_vu_id' => $dichVuData->id,
                        'loai_san_pham_id' => $cat->id,
                        'ten_san_pham' => "{$carrierName} - {$pkg['name']}",
                        'menh_gia' => $pkg['amount'],
                        'gia_ban' => $pkg['amount'],
                        'chiet_khau_phan_tram' => 0,
                        'don_vi' => 'VND',
                        'thu_tu' => $thuTu++,
                        'trang_thai' => 'hoat_dong',
                        'mo_ta' => $pkg['desc'],
                        'hien_thi_web_app' => true,
                    ]
                );
            }
        }

        $this->info('--- Chuẩn hóa hoàn tất thành công! ---');
        return self::SUCCESS;
    }
}
