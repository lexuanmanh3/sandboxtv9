<?php

namespace Database\Seeders;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DaiLyApiDichVuDuocPhep;
use App\Models\DaiLyApiLoaiSanPhamDuocPhep;
use App\Models\DaiLyApiSanPhamDuocPhep;
use App\Models\DichVu;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Database\Seeder;

class DaiLyApiSeeder extends Seeder
{
    /**
     * Seeder này tạo dữ liệu test cho Đại lý API.
     * Mục đích là sau này reset database vẫn có sẵn partner để test API, quyền dịch vụ,
     * quyền loại sản phẩm và quyền sản phẩm.
     */
    public function run(): void
    {
        // Xóa dữ liệu partner test cũ nếu có key không khớp APP_KEY
        \Illuminate\Support\Facades\DB::table('cau_hinh_api_dai_ly')->where('client_id', 'partner_test_client')->delete();
        \Illuminate\Support\Facades\DB::table('dai_ly_api')->where('ma_dai_ly_api', 'PARTNER_TEST')->delete();

        /**
         * Tạo hoặc lấy Đại lý API test.
         */
        $daiLyApi = DaiLyApi::firstOrCreate(
            ['ma_dai_ly_api' => 'PARTNER_TEST'],
            [
                'ten_dai_ly_api' => 'Đại lý API Test',
                'so_dien_thoai' => '0900000001',
                'ho' => 'API',
                'ten' => 'Partner',
                'email_ky_thuat' => 'tech@partner.test',
                'email_doi_soat' => 'reconcile@partner.test',
                'ky_doi_soat' => 30,
                'trang_thai' => 'hoat_dong',
                'tinh_thanh' => 'Hà Nội',
                'quan_huyen' => 'Cầu Giấy',
                'phuong_xa' => 'Dịch Vọng Hậu',
                'dia_chi_chi_tiet' => 'Tầng 12, Tòa nhà Công nghệ',
                'thong_tin_lien_he' => [
                    'giam_doc' => ['ho_ten' => 'Nguyễn Văn A', 'so_dien_thoai' => '0900000001', 'email' => 'director@partner.test'],
                    'ky_thuat' => ['ho_ten' => 'Trần Văn Tech', 'so_dien_thoai' => '0900000002', 'email' => 'tech@partner.test'],
                    'doi_soat' => ['ho_ten' => 'Lê Thị Reconcile', 'so_dien_thoai' => '0900000003', 'email' => 'reconcile@partner.test'],
                    'ke_toan' => ['ho_ten' => 'Phạm Thị Kế Toán', 'so_dien_thoai' => '0900000004', 'email' => 'accounting@partner.test'],
                ],
            ]
        );

        /**
         * Tạo cấu hình API cho Đại lý API.
         * secret_key_ma_hoa sẽ được mã hóa nếu model CauHinhApiDaiLy đã dùng encrypted cast.
         */
        CauHinhApiDaiLy::updateOrCreate(
            ['dai_ly_api_id' => $daiLyApi->id],
            [
                'client_id' => 'partner_test_client',
                'secret_key_ma_hoa' => 'partner_test_secret',
                'allowed_grant_types' => 'client_credentials',
                'allowed_scopes' => 'order:create,order:query,credit:query,service:query',
                'su_dung_chu_ky_dien_tu' => true,
                'danh_sach_ip_ket_noi' => '127.0.0.1; 127.0.0.2',
                'so_luong_kenh_toi_da' => 5,
                'rate_limit_per_minute' => 60,
                'han_muc_mua_the' => 10000000,
                'han_muc_cong_no' => 50000000,
                'cong_no_hien_tai' => 0,
                'nguong_canh_bao_han_muc' => 40000000,
                'cho_phep_nhan_don' => true,
                'webhook_url' => 'https://partner-test.local/api/webhook',
                'webhook_secret_ma_hoa' => 'partner_webhook_secret',
                'key_status' => 'active',
                'ap_dung_nap_cham' => true,
                'check_phone_khi_nap_cham' => false,
            ]
        );

        /**
         * Lấy dữ liệu danh mục đầu tiên để cấp quyền test.
         * Nếu chưa có dịch vụ / loại sản phẩm / sản phẩm thì dừng lại.
         */
        $dichVu = DichVu::first();
        $loaiSanPham = LoaiSanPham::first();
        $sanPham = SanPham::first();

        /**
         * Nếu thiếu dữ liệu danh mục thì không cấp quyền.
         * Khi Seeder không ra quyền, kiểm tra lại bảng dich_vu, loai_san_pham, san_pham.
         */
        if (!$dichVu || !$loaiSanPham || !$sanPham) {
            return;
        }

        /**
         * Cấp quyền tầng 1: Đại lý API được phép dùng dịch vụ.
         */
        DaiLyApiDichVuDuocPhep::firstOrCreate([
            'dai_ly_api_id' => $daiLyApi->id,
            'dich_vu_id' => $dichVu->id,
        ]);

        /**
         * Cấp quyền tầng 2: Đại lý API được phép dùng loại sản phẩm.
         */
        DaiLyApiLoaiSanPhamDuocPhep::firstOrCreate([
            'dai_ly_api_id' => $daiLyApi->id,
            'loai_san_pham_id' => $loaiSanPham->id,
        ]);

        /**
         * Cấp quyền tầng 3: Đại lý API được phép dùng sản phẩm cụ thể.
         */
        DaiLyApiSanPhamDuocPhep::firstOrCreate([
            'dai_ly_api_id' => $daiLyApi->id,
            'san_pham_id' => $sanPham->id,
        ]);
    }
}