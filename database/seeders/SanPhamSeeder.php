<?php

namespace Database\Seeders;

use App\Models\DichVu;
use App\Models\KetNoiNhaCungCap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use Illuminate\Database\Seeder;

class SanPhamSeeder extends Seeder
{
    public function run(): void
    {
        $dichVuTopup = DichVu::where('ma_dich_vu', 'MOBILE_TOPUP')->first();
        $dichVuData  = DichVu::where('ma_dich_vu', 'TOPUP_DATA')->first();

        // 1. TẠO SẢN PHẨM NẠP TIỀN ĐIỆN THOẠI (BÁN GIÁ GỐC, CHIẾT KHẤU 0%)
        $topupDenominations = [10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000];
        $topupCarriers = [
            'VTE_TOPUP'     => ['name' => 'Viettel',      'telco' => 'viettel'],
            'VMS_TOPUP'     => ['name' => 'Mobifone',     'telco' => 'mobifone'],
            'VNA_TOPUP'     => ['name' => 'Vinaphone',    'telco' => 'vinaphone'],
            'VNM_TOPUP'     => ['name' => 'Vietnamobile', 'telco' => 'vietnamobile'],
            'GMOBILE_TOPUP' => ['name' => 'Gmobile',      'telco' => 'gmobile'],
            'WT_TOPUP'      => ['name' => 'Wintel',       'telco' => 'wintel'],
        ];

        foreach ($topupCarriers as $catCode => $c) {
            $cat = LoaiSanPham::where('ma_loai_san_pham', $catCode)->first();
            if (!$cat || !$dichVuTopup) continue;

            $cat->update(['dich_vu_id' => $dichVuTopup->id, 'trang_thai' => 'hoat_dong']);

            $thuTu = 1;
            foreach ($topupDenominations as $amount) {
                $maSP = 'TOPUP_' . str_replace('_TOPUP', '', $catCode) . '_' . ($amount / 1000) . 'K';
                SanPham::updateOrCreate(
                    ['ma_san_pham' => $maSP],
                    [
                        'dich_vu_id' => $dichVuTopup->id,
                        'loai_san_pham_id' => $cat->id,
                        'ten_san_pham' => "{$c['name']} " . number_format($amount, 0, ',', '.') . 'đ',
                        'menh_gia' => $amount,
                        'gia_ban' => $amount, // Bán đúng giá gốc
                        'chiet_khau_phan_tram' => 0, // Chưa cấu hình chiết khấu
                        'don_vi' => 'VND',
                        'thu_tu' => $thuTu++,
                        'trang_thai' => 'ACTIVE',
                        'hien_thi_web_app' => true,
                    ]
                );
            }
        }

        // 2. TẠO SẢN PHẨM NẠP DATA 3G/4G (BÁN GIÁ GỐC)
        $dataPackages = [
            'VTE_TOPUPDATA' => [
                'name' => 'Viettel', 'telco' => 'viettel',
                'packages' => [
                    ['code' => 'ST5K',  'name' => 'Gói ST5K (500MB / Ngày)',    'amount' => 5000],
                    ['code' => 'ST10K', 'name' => 'Gói ST10K (2GB / Ngày)',     'amount' => 10000],
                    ['code' => 'ST15K', 'name' => 'Gói ST15K (3GB / 3 Ngày)',   'amount' => 15000],
                    ['code' => 'ST30K', 'name' => 'Gói ST30K (7GB / 7 Ngày)',   'amount' => 30000],
                    ['code' => 'ST70K', 'name' => 'Gói ST70K (15GB / 30 Ngày)', 'amount' => 70000],
                    ['code' => 'ST90',  'name' => 'Gói ST90 (30GB / 30 Ngày)',  'amount' => 90000],
                    ['code' => 'ST120', 'name' => 'Gói ST120 (60GB / 30 Ngày)', 'amount' => 120000],
                ],
            ],
            'VMS_TOPUPDATA' => [
                'name' => 'Mobifone', 'telco' => 'mobifone',
                'packages' => [
                    ['code' => 'D5',   'name' => 'Gói D5 (1GB / Ngày)',       'amount' => 5000],
                    ['code' => 'D10',  'name' => 'Gói D10 (1.5GB / Ngày)',    'amount' => 10000],
                    ['code' => 'D15',  'name' => 'Gói D15 (3GB / 3 Ngày)',    'amount' => 15000],
                    ['code' => 'D30',  'name' => 'Gói D30 (7GB / 7 Ngày)',    'amount' => 30000],
                    ['code' => 'HD70', 'name' => 'Gói HD70 (6GB / 30 Ngày)',  'amount' => 70000],
                    ['code' => 'HD90', 'name' => 'Gói HD90 (8GB / 30 Ngày)',  'amount' => 90000],
                ],
            ],
            'VNA_TOPUPDATA' => [
                'name' => 'Vinaphone', 'telco' => 'vinaphone',
                'packages' => [
                    ['code' => 'D2',    'name' => 'Gói D2 (2GB / Ngày)',       'amount' => 10000],
                    ['code' => 'D3',    'name' => 'Gói D3 (3GB / 3 Ngày)',     'amount' => 15000],
                    ['code' => 'DT30',  'name' => 'Gói DT30 (7GB / 7 Ngày)',   'amount' => 30000],
                    ['code' => 'MAX',   'name' => 'Gói MAX (3.8GB / 30 Ngày)', 'amount' => 70000],
                    ['code' => 'BIG90', 'name' => 'Gói BIG90 (30GB / 30 Ngày)','amount' => 90000],
                ],
            ],
        ];

        foreach ($dataPackages as $catCode => $group) {
            $cat = LoaiSanPham::where('ma_loai_san_pham', $catCode)->first();
            if (!$cat || !$dichVuData) continue;

            $cat->update(['dich_vu_id' => $dichVuData->id, 'trang_thai' => 'hoat_dong']);

            $thuTu = 1;
            foreach ($group['packages'] as $pkg) {
                $maSP = 'DATA_' . strtoupper($group['telco']) . '_' . $pkg['code'];
                SanPham::updateOrCreate(
                    ['ma_san_pham' => $maSP],
                    [
                        'dich_vu_id' => $dichVuData->id,
                        'loai_san_pham_id' => $cat->id,
                        'ten_san_pham' => "{$group['name']} - {$pkg['name']}",
                        'menh_gia' => $pkg['amount'],
                        'gia_ban' => $pkg['amount'], // Giá gốc
                        'chiet_khau_phan_tram' => 0,
                        'don_vi' => 'VND',
                        'thu_tu' => $thuTu++,
                        'trang_thai' => 'ACTIVE',
                        'hien_thi_web_app' => true,
                    ]
                );
            }
        }

        // 3. TẠO MÃ NHÀ CUNG CẤP Ở TRẠNG THÁI "CHƯA ÁNH XẠ" (san_pham_id = NULL) ĐỂ TEST ÁNH XẠ
        $appotaProvider = NhaCungCap::firstOrCreate(['ma_ncc' => 'APPOTAPAY'], [
            'ten_ncc' => 'Cổng AppotaPay',
            'trang_thai' => 'hoat_dong',
        ]);

        $conn = KetNoiNhaCungCap::firstOrCreate([
            'nha_cung_cap_id' => $appotaProvider->id,
        ], [
            'ten_ket_noi' => 'Kết nối Cổng AppotaPay',
            'moi_truong' => 'SANDBOX',
            'base_url' => 'https://gateway.dev.appotapay.com',
            'api_url' => 'https://gateway.dev.appotapay.com',
            'partner_code' => 'quyducphan96',
            'api_user' => 'quyducphan96',
            'username' => 'quyducphan96',
            'auth_type' => 'JWT_HS256',
            'trang_thai' => 'hoat_dong',
        ]);

        if ($conn) {
            // Sinh danh sách mã NCC chưa map để Admin thử nghiệm tính năng Ánh xạ
            $mockNccCodes = [
                // Nạp tiền Viettel
                ['code' => 'viettel_10', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 10000],
                ['code' => 'viettel_20', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 20000],
                ['code' => 'viettel_30', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 30000],
                ['code' => 'viettel_50', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 50000],
                ['code' => 'viettel_100', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 100000],
                ['code' => 'viettel_200', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 200000],
                ['code' => 'viettel_300', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 300000],
                ['code' => 'viettel_500', 'telco' => 'viettel', 'service' => 'MOBILE_TOPUP', 'amount' => 500000],

                // Nạp tiền Mobifone
                ['code' => 'mobifone_10', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 10000],
                ['code' => 'mobifone_20', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 20000],
                ['code' => 'mobifone_30', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 30000],
                ['code' => 'mobifone_50', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 50000],
                ['code' => 'mobifone_100', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 100000],
                ['code' => 'mobifone_200', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 200000],
                ['code' => 'mobifone_300', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 300000],
                ['code' => 'mobifone_500', 'telco' => 'mobifone', 'service' => 'MOBILE_TOPUP', 'amount' => 500000],

                // Nạp tiền Vinaphone
                ['code' => 'vinaphone_10', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 10000],
                ['code' => 'vinaphone_20', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 20000],
                ['code' => 'vinaphone_30', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 30000],
                ['code' => 'vinaphone_50', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 50000],
                ['code' => 'vinaphone_100', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 100000],
                ['code' => 'vinaphone_200', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 200000],
                ['code' => 'vinaphone_300', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 300000],
                ['code' => 'vinaphone_500', 'telco' => 'vinaphone', 'service' => 'MOBILE_TOPUP', 'amount' => 500000],

                // Nạp Data
                ['code' => 'data_viettel_5', 'telco' => 'viettel', 'service' => 'TOPUP_DATA', 'amount' => 5000],
                ['code' => 'data_viettel_10', 'telco' => 'viettel', 'service' => 'TOPUP_DATA', 'amount' => 10000],
                ['code' => 'data_viettel_15', 'telco' => 'viettel', 'service' => 'TOPUP_DATA', 'amount' => 15000],
                ['code' => 'data_viettel_30', 'telco' => 'viettel', 'service' => 'TOPUP_DATA', 'amount' => 30000],
                ['code' => 'data_viettel_70', 'telco' => 'viettel', 'service' => 'TOPUP_DATA', 'amount' => 70000],
                ['code' => 'data_viettel_90', 'telco' => 'viettel', 'service' => 'TOPUP_DATA', 'amount' => 90000],
                ['code' => 'data_mobifone_10', 'telco' => 'mobifone', 'service' => 'TOPUP_DATA', 'amount' => 10000],
                ['code' => 'data_vinaphone_10', 'telco' => 'vinaphone', 'service' => 'TOPUP_DATA', 'amount' => 10000],
            ];

            foreach ($mockNccCodes as $item) {
                SanPhamNhaCungCap::updateOrCreate(
                    [
                        'ket_noi_nha_cung_cap_id' => $conn->id,
                        'ma_san_pham_ncc' => $item['code'],
                    ],
                    [
                        'san_pham_id' => null, // CHƯA ÁNH XẠ để người dùng tự test chức năng ánh xạ
                        'nha_cung_cap_id' => $appotaProvider->id,
                        'ma_nha_mang_ncc' => $item['telco'],
                        'loai_dich_vu_ncc' => $item['service'],
                        'loai_thue_bao' => 'PREPAID',
                        'menh_gia_ncc' => $item['amount'],
                        'gia_nhap' => $item['amount'],
                        'ty_le_chiet_khau' => 0,
                        'muc_uu_tien' => 1,
                        'trang_thai' => 'ACTIVE',
                        'dong_bo_luc' => now(),
                    ]
                );
            }
        }
    }
}
