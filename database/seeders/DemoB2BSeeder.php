<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CauHinhDichVu;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\NhaCungCap;
use App\Models\LanGoiNhaCungCap;
use App\Models\KhoanGiuHanMuc;
use App\Models\DonHang;

class DemoB2BSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tạo dữ liệu demo Cấu hình tuyến dịch vụ
        $dichVu = DichVu::first();
        $ncc1 = NhaCungCap::first();
        $ncc2 = NhaCungCap::skip(1)->first();
        
        if ($dichVu && $ncc1) {
            CauHinhDichVu::firstOrCreate([
                'ten_cau_hinh' => 'Tuyến mặc định qua ' . $ncc1->ten_nha_cung_cap,
                'dich_vu_id' => $dichVu->id,
                'nha_cung_cap_id' => $ncc1->id,
                'muc_uu_tien' => 1,
                'dang_mo' => true,
                'ket_thuc_cau_hinh' => false,
            ]);
        }
        
        if ($dichVu && $ncc2) {
            CauHinhDichVu::firstOrCreate([
                'ten_cau_hinh' => 'Tuyến dự phòng qua ' . $ncc2->ten_nha_cung_cap,
                'dich_vu_id' => $dichVu->id,
                'nha_cung_cap_id' => $ncc2->id,
                'muc_uu_tien' => 2,
                'dang_mo' => true,
                'ket_thuc_cau_hinh' => true,
            ]);
        }

        // 2. Tạo dữ liệu demo Vận hành (Nhật ký gọi NCC & Khoản giữ)
        $daiLy = DaiLyApi::first();
        $donHang1 = DonHang::first();
        $donHang2 = DonHang::skip(1)->first();

        if ($donHang1 && $ncc1) {
            LanGoiNhaCungCap::firstOrCreate([
                'don_hang_id' => $donHang1->id,
                'nha_cung_cap_id' => $ncc1->id,
                'ket_noi_nha_cung_cap_id' => null,
                'endpoint' => 'https://api.ncc1.com/v1/topup',
                'request_json' => ['amount' => 50000],
                'response_json' => ['status' => 'success', 'ref_id' => 'NCC123456'],
                'http_status' => 200,
                'thoi_gian_xu_ly_ms' => 350,
                'trang_thai' => 'SUCCESS',
                'partner_ref_id' => 'NCC123456',
            ]);
        }

        if ($donHang2 && $ncc2) {
            LanGoiNhaCungCap::firstOrCreate([
                'don_hang_id' => $donHang2->id,
                'nha_cung_cap_id' => $ncc2->id,
                'ket_noi_nha_cung_cap_id' => null,
                'endpoint' => 'https://api.ncc2.com/v1/topup',
                'request_json' => ['amount' => 100000],
                'response_json' => ['status' => 'timeout'],
                'http_status' => 504,
                'thoi_gian_xu_ly_ms' => 15000,
                'trang_thai' => 'FAILED',
                'partner_ref_id' => null,
            ]);
        }

        if ($daiLy && $donHang2) {
            // Trạng thái khoản giữ CHỈ có ba giá trị hợp lệ: HOLDING | COMMITTED | RELEASED.
            // Trước đây seeder ghi 'DANG_GIU' — giá trị không tồn tại trong nghiệp vụ:
            // B2bCreditService tính hạn mức khả dụng bằng WHERE trang_thai = 'HOLDING' nên
            // khoản giữ này KHÔNG BAO GIỜ được trừ vào hạn mức, cũng không thể chốt (COMMITTED)
            // hay giải phóng (RELEASED) — đơn treo vĩnh viễn và hạn mức bị báo cao hơn thực tế.
            KhoanGiuHanMuc::firstOrCreate([
                'dai_ly_api_id' => $daiLy->id,
                'don_hang_id' => $donHang2->id,
                'so_tien_giu' => 100000,
                'trang_thai' => 'HOLDING',
                'chot_cong_no_luc' => null,
                'giai_phong_luc' => null,
                'ly_do_giai_phong' => 'Giao dịch timeout tại NCC, chờ đối soát.',
            ]);
        }
    }
}
