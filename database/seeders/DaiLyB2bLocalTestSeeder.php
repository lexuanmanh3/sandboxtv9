<?php

namespace Database\Seeders;

use App\Models\BangGiaDaiLy;
use App\Models\CauHinhApiDaiLy;
use App\Models\CauHinhDichVu;
use App\Models\DaiLyApi;
use App\Models\DaiLyApiDichVuDuocPhep;
use App\Models\DaiLyApiLoaiSanPhamDuocPhep;
use App\Models\DaiLyApiSanPhamDuocPhep;
use App\Models\DichVu;
use App\Models\KetNoiNhaCungCap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use Illuminate\Database\Seeder;

/**
 * Seeder dành RIÊNG cho kiểm thử tích hợp B2B tại local (DailyB2B -> Laravel).
 *
 * Nguyên tắc:
 *  - Chạy lại được nhiều lần (idempotent): mọi bản ghi dùng updateOrCreate/firstOrCreate
 *    theo khóa tự nhiên, không nhân bản dữ liệu.
 *  - KHÔNG đụng tới đại lý, đơn hàng, sản phẩm hay kết nối NCC đã có. Chỉ tạo/gán
 *    các bản ghi thuộc sở hữu của đại lý test và NCC giả lập.
 *  - Sản phẩm test dùng tiền tố TEST_ và CHỈ được ánh xạ sang NCC giả lập, nên không
 *    thể rơi sang AppotaPay hay bất kỳ nhà cung cấp thật nào.
 *  - Webhook để TRỐNG có chủ đích: giai đoạn đầu kiểm thử bằng tra cứu (polling).
 *    Bật webhook sau bằng cách cập nhật cau_hinh_api_dai_ly.webhook_url.
 *  - Secret lấy từ biến môi trường, KHÔNG ghi cứng vào mã nguồn và KHÔNG in ra log.
 *
 * Cách chạy:
 *   php artisan db:seed --class=DaiLyB2bLocalTestSeeder --force
 */
class DaiLyB2bLocalTestSeeder extends Seeder
{
    /** Mã đại lý test. Tiền tố DAILYB2B_ để phân biệt tuyệt đối với đại lý thật. */
    public const MA_DAI_LY = 'DAILYB2B_LOCAL';

    /**
     * Đại lý thứ hai, dùng DUY NHẤT để kiểm chứng cô lập dữ liệu:
     * đại lý B không được thấy đơn hàng, hạn mức hay công nợ của đại lý A.
     */
    public const MA_DAI_LY_B = 'DAILYB2B_LOCAL_B';

    /** Mã nhà cung cấp giả lập. Resolver chọn driver theo ma_ncc (viết thường) -> 'fake'. */
    public const MA_NCC = 'FAKE';

    /** Tiền tố mã sản phẩm test, tách biệt hoàn toàn khỏi catalog thật. */
    public const TIEN_TO_SAN_PHAM = 'TEST_TOPUP_';

    /**
     * Danh mục sản phẩm test: [mã sản phẩm, loại sản phẩm, tên nhà mạng, mệnh giá, giá nhập NCC].
     * Giá nhập NCC thấp hơn mệnh giá để có chênh lệch; giá bán đại lý lấy từ BangGiaDaiLy.
     */
    private const SAN_PHAM = [
        ['VTE_10K', 'VTE_TOPUP', 'Viettel', 10000],
        ['VTE_20K', 'VTE_TOPUP', 'Viettel', 20000],
        ['VTE_50K', 'VTE_TOPUP', 'Viettel', 50000],
        ['VTE_100K', 'VTE_TOPUP', 'Viettel', 100000],
        ['VMS_10K', 'VMS_TOPUP', 'Mobifone', 10000],
        ['VNA_10K', 'VNA_TOPUP', 'Vinaphone', 10000],
    ];

    /** Chiết khấu đại lý test được hưởng, tính theo phần trăm mệnh giá. */
    private const CHIET_KHAU_PHAN_TRAM = 3.0;

    /** Tỷ lệ giá nhập NCC giả lập so với mệnh giá (0.94 = chiết khấu nhập 6%). */
    private const TY_LE_GIA_NHAP = 0.94;

    public function run(): void
    {
        $clientId = (string) env('B2B_TEST_CLIENT_ID', 'dailyb2b_local_client');
        $clientSecret = (string) env('B2B_TEST_CLIENT_SECRET', 'local_test_secret_change_me');
        $webhookSecret = (string) env('B2B_TEST_WEBHOOK_SECRET', 'local_test_webhook_secret_change_me');

        // 1. Danh mục nền: dịch vụ nạp tiền và các loại sản phẩm nhà mạng.
        $dichVu = DichVu::updateOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại',
            'trang_thai' => 'hoat_dong',
            'thu_tu' => 70,
        ]);

        $loaiSanPham = [];
        foreach (['VTE_TOPUP' => 'Viettel', 'VMS_TOPUP' => 'Mobifone', 'VNA_TOPUP' => 'Vinaphone'] as $ma => $ten) {
            $loaiSanPham[$ma] = LoaiSanPham::updateOrCreate(['ma_loai_san_pham' => $ma], [
                'ten_loai_san_pham' => $ten,
                'dich_vu_id' => $dichVu->id,
                'trang_thai' => 'hoat_dong',
            ]);
        }

        // 2. Nhà cung cấp GIẢ LẬP + kết nối. Không có base_url vì provider không gọi mạng.
        $nhaCungCap = NhaCungCap::updateOrCreate(['ma_ncc' => self::MA_NCC], [
            'ten_ncc' => 'Nhà cung cấp giả lập (chỉ dùng kiểm thử local)',
            'trang_thai' => 'hoat_dong',
        ]);

        $ketNoi = KetNoiNhaCungCap::updateOrCreate([
            'nha_cung_cap_id' => $nhaCungCap->id,
            'ten_ket_noi' => 'Fake Local Test',
        ], [
            'moi_truong' => 'LOCAL',
            'trang_thai' => 'hoat_dong',
            'auth_type' => 'NONE',
            'base_url' => null,
            'api_url' => null,
            'connect_timeout_seconds' => 5,
            'request_timeout_seconds' => 10,
        ]);

        // 3. Sản phẩm test + ánh xạ sang NCC giả lập.
        $sanPhamIds = [];
        foreach (self::SAN_PHAM as [$hauTo, $maLoai, $tenNhaMang, $menhGia]) {
            $maSanPham = self::TIEN_TO_SAN_PHAM.$hauTo;
            $giaNhap = (int) round($menhGia * self::TY_LE_GIA_NHAP);

            $sanPham = SanPham::updateOrCreate(['ma_san_pham' => $maSanPham], [
                'dich_vu_id' => $dichVu->id,
                'loai_san_pham_id' => $loaiSanPham[$maLoai]->id,
                'ten_san_pham' => '[TEST] '.$tenNhaMang.' '.number_format($menhGia, 0, ',', '.').'đ',
                'menh_gia' => $menhGia,
                'gia_ban' => $menhGia - (int) round($menhGia * self::CHIET_KHAU_PHAN_TRAM / 100),
                'don_vi' => 'VND',
                'trang_thai' => 'hoat_dong',
                'hien_thi_web_app' => false,
            ]);
            $sanPhamIds[] = $sanPham->id;

            SanPhamNhaCungCap::updateOrCreate([
                'ket_noi_nha_cung_cap_id' => $ketNoi->id,
                'ma_san_pham_ncc' => 'FAKE_'.$hauTo,
            ], [
                'san_pham_id' => $sanPham->id,
                'nha_cung_cap_id' => $nhaCungCap->id,
                'ma_nha_mang_ncc' => strtolower($tenNhaMang),
                'loai_dich_vu_ncc' => 'MOBILE_TOPUP',
                'loai_thue_bao' => 'PREPAID',
                'menh_gia_ncc' => $menhGia,
                'gia_nhap' => $giaNhap,
                'muc_uu_tien' => 1,
                'trang_thai' => 'ACTIVE',
            ]);
        }

        // 4. Đại lý API test (A) — đại lý chính dùng cho mọi kiểm thử nghiệp vụ.
        $daiLy = $this->taoDaiLy(
            ma: self::MA_DAI_LY,
            ten: 'Đại lý DailyB2B (kiểm thử local)',
            hoTen: ['DailyB2B', 'Local Test'],
            email: 'dailyb2b.local',
            clientId: $clientId,
            clientSecret: $clientSecret,
            webhookSecret: $webhookSecret,
            dichVu: $dichVu,
            loaiSanPham: $loaiSanPham,
            sanPhamIds: $sanPhamIds,
            nhaCungCap: $nhaCungCap,
            tenTuyen: 'Tuyến DailyB2B local -> NCC giả lập'
        );

        // 5. Đại lý API test (B) — CHỈ để kiểm chứng cô lập dữ liệu giữa hai đại lý.
        //    Dùng chung danh mục sản phẩm nhưng có tuyến dịch vụ và hạn mức riêng.
        $clientIdB = (string) env('B2B_TEST_CLIENT_ID_B', '');
        $daiLyB = null;
        if ($clientIdB !== '') {
            $daiLyB = $this->taoDaiLy(
                ma: self::MA_DAI_LY_B,
                ten: 'Đại lý DailyB2B B (kiểm thử cô lập)',
                hoTen: ['DailyB2B', 'Dealer B'],
                email: 'dailyb2b-b.local',
                clientId: $clientIdB,
                clientSecret: (string) env('B2B_TEST_CLIENT_SECRET_B', ''),
                webhookSecret: (string) env('B2B_TEST_WEBHOOK_SECRET_B', ''),
                dichVu: $dichVu,
                loaiSanPham: $loaiSanPham,
                sanPhamIds: $sanPhamIds,
                nhaCungCap: $nhaCungCap,
                tenTuyen: 'Tuyến DailyB2B local B -> NCC giả lập'
            );
        }

        // Chỉ in định danh, KHÔNG in secret.
        $this->command?->info('Đại lý test: '.self::MA_DAI_LY." (client_id: {$clientId})");
        $this->command?->info('NCC giả lập: '.self::MA_NCC.' | kết nối #'.$ketNoi->id);
        $this->command?->info('Sản phẩm test: '.count($sanPhamIds).' | webhook_url: (để trống, kiểm thử bằng tra cứu)');
        if ($daiLyB) {
            $this->command?->info('Đại lý cô lập: '.self::MA_DAI_LY_B." (client_id: {$clientIdB})");
        } else {
            $this->command?->info('Bỏ qua đại lý B: chưa cấu hình B2B_TEST_CLIENT_ID_B trong .env');
        }
    }

    /**
     * Tạo (hoặc cập nhật) một đại lý test cùng toàn bộ cấu hình, quyền, bảng giá và tuyến dịch vụ.
     *
     * Idempotent: mọi bản ghi dùng updateOrCreate/firstOrCreate theo khóa tự nhiên nên chạy lại
     * nhiều lần không nhân bản dữ liệu và không đụng tới đại lý khác.
     *
     * @param array{0:string,1:string} $hoTen
     * @param array<string,\App\Models\LoaiSanPham> $loaiSanPham
     * @param array<int,int> $sanPhamIds
     */
    private function taoDaiLy(
        string $ma,
        string $ten,
        array $hoTen,
        string $email,
        string $clientId,
        string $clientSecret,
        string $webhookSecret,
        DichVu $dichVu,
        array $loaiSanPham,
        array $sanPhamIds,
        NhaCungCap $nhaCungCap,
        string $tenTuyen
    ): DaiLyApi {
        $daiLy = DaiLyApi::updateOrCreate(['ma_dai_ly_api' => $ma], [
            'ten_dai_ly_api' => $ten,
            'so_dien_thoai' => '0900000000',
            'ho' => $hoTen[0],
            'ten' => $hoTen[1],
            'email_ky_thuat' => 'tech@'.$email,
            'email_doi_soat' => 'reconcile@'.$email,
            'ky_doi_soat' => 30,
            'trang_thai' => 'hoat_dong',
        ]);

        // Cấu hình API: client_id/secret, hạn mức công nợ, IP whitelist.
        //
        // TÁCH LÀM HAI BƯỚC có chủ đích: giá trị mặc định chỉ được ghi khi bản ghi
        // CHƯA tồn tại, còn các trường cấu hình tĩnh mới được cập nhật mỗi lần chạy.
        // Nhờ vậy chạy lại seeder giữa chừng KHÔNG xóa webhook_url đã bật và KHÔNG
        // reset cong_no_hien_tai về 0 — việc reset sẽ phá vỡ bất biến cân đối
        // giữa số dư tổng hợp và sổ phát sinh công nợ của các đơn đã chạy.
        $cauHinh = CauHinhApiDaiLy::firstOrCreate(['dai_ly_api_id' => $daiLy->id], [
            'client_id' => $clientId,
            'secret_key_ma_hoa' => $clientSecret,
            // webhook_url để TRỐNG có chủ đích (giai đoạn 1 kiểm thử bằng tra cứu).
            'webhook_url' => null,
            'cong_no_hien_tai' => 0,
            'webhook_secret_ma_hoa' => $webhookSecret,
        ]);

        $cauHinh->update([
            'client_id' => $clientId,
            'secret_key_ma_hoa' => $clientSecret,
            'allowed_grant_types' => 'client_credentials',
            'allowed_scopes' => 'order:create,order:query,credit:query,service:query',
            'su_dung_chu_ky_dien_tu' => true,
            // IPv4 loopback, IPv6 loopback và toàn dải 127.0.0.0/8: backend có thể thấy
            // ::1 hoặc 127.0.0.1 tùy cách Apache phân giải "localhost" trên Windows.
            'danh_sach_ip_ket_noi' => '127.0.0.1; ::1; 127.0.0.0/8',
            'so_luong_kenh_toi_da' => 5,
            'rate_limit_per_minute' => 120,
            'han_muc_mua_the' => 10000000,
            'han_muc_cong_no' => 50000000,
            'nguong_canh_bao_han_muc' => 40000000,
            'cho_phep_nhan_don' => true,
            'ap_dung_nap_cham' => false,
            'check_phone_khi_nap_cham' => false,
            'key_status' => 'active',
        ]);

        // Quyền 3 tầng.
        DaiLyApiDichVuDuocPhep::firstOrCreate([
            'dai_ly_api_id' => $daiLy->id,
            'dich_vu_id' => $dichVu->id,
        ]);

        foreach ($loaiSanPham as $loai) {
            DaiLyApiLoaiSanPhamDuocPhep::firstOrCreate([
                'dai_ly_api_id' => $daiLy->id,
                'loai_san_pham_id' => $loai->id,
            ]);
        }

        foreach ($sanPhamIds as $sanPhamId) {
            DaiLyApiSanPhamDuocPhep::firstOrCreate([
                'dai_ly_api_id' => $daiLy->id,
                'san_pham_id' => $sanPhamId,
            ]);

            BangGiaDaiLy::updateOrCreate([
                'dai_ly_api_id' => $daiLy->id,
                'san_pham_id' => $sanPhamId,
            ], [
                'loai_chiet_khau' => 'PERCENT',
                'gia_tri_chiet_khau' => self::CHIET_KHAU_PHAN_TRAM,
                'trang_thai' => 'hoat_dong',
            ]);
        }

        // Tuyến dịch vụ RIÊNG cho từng đại lý test, trỏ duy nhất về NCC giả lập.
        // ket_thuc_cau_hinh = true: khi NCC giả lập từ chối dứt khoát thì DỪNG,
        // tuyệt đối không fallback sang AppotaPay hay nhà cung cấp thật khác.
        // dai_ly_ap_dung_id được xếp ưu tiên cao nhất trong NhaCungCapRoutingService.
        CauHinhDichVu::updateOrCreate([
            'ten_cau_hinh' => $tenTuyen,
            'dich_vu_id' => $dichVu->id,
            'dai_ly_ap_dung_id' => $daiLy->id,
        ], [
            'nha_cung_cap_id' => $nhaCungCap->id,
            'muc_uu_tien' => 1,
            'dang_mo' => true,
            'ket_thuc_cau_hinh' => true,
            'che_do_chay' => 'api',
        ]);

        return $daiLy;
    }
}
