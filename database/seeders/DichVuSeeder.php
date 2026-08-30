<?php

namespace Database\Seeders;

use App\Models\DichVu;
use Illuminate\Database\Seeder;

class DichVuSeeder extends Seeder
{
    /**
     * Seeder này tạo dữ liệu demo cho module Quản lý dịch vụ.
     * Dùng updateOrCreate theo ma_dich_vu để chạy lại nhiều lần không bị trùng dữ liệu.
     */
    public function run(): void
    {
        $dichVuList = [
            ['ma_dich_vu' => 'QUERY_BILL', 'ten_dich_vu' => 'Truy vấn thông tin hóa đơn', 'thu_tu' => 10],
            ['ma_dich_vu' => 'PIN_GAME', 'ten_dich_vu' => 'Mua thẻ game', 'thu_tu' => 20],
            ['ma_dich_vu' => 'TOPUP_DATA', 'ten_dich_vu' => 'Nạp Data', 'thu_tu' => 30],
            ['ma_dich_vu' => 'PAY_BILL', 'ten_dich_vu' => 'Thanh toán hóa đơn', 'thu_tu' => 40],
            ['ma_dich_vu' => 'PIN_DATA', 'ten_dich_vu' => 'Mua thẻ Data', 'thu_tu' => 50],
            ['ma_dich_vu' => 'MOBILE_TOPUP', 'ten_dich_vu' => 'Nạp tiền điện thoại', 'thu_tu' => 60],
            ['ma_dich_vu' => 'PIN_CODE', 'ten_dich_vu' => 'Mua mã thẻ', 'thu_tu' => 70],
        ];

        foreach ($dichVuList as $dichVu) {
            DichVu::updateOrCreate(
                ['ma_dich_vu' => $dichVu['ma_dich_vu']],
                [
                    'ten_dich_vu' => $dichVu['ten_dich_vu'],
                    'thu_tu' => $dichVu['thu_tu'],
                    'trang_thai' => 'hoat_dong',
                ]
            );
        }
    }
}
