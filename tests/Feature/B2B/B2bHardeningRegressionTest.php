<?php

namespace Tests\Feature\B2B;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\SoPhatSinhCongNo;
use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bCreditService;
use App\Services\DaiLyApi\B2bOrderRecoveryService;
use App\Services\DaiLyApi\B2bWebhookService;
use App\Services\Security\UrlSafetyValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Test hồi quy cho bốn lỗi đã được xác nhận và sửa.
 *
 * Mỗi test ở đây phải THẤT BẠI nếu bản sửa bị gỡ bỏ. Vì vậy chúng cố tình đi
 * thẳng vào đúng nhánh mã đã sửa, không dựa vào các kiểm tra khác che chắn:
 *  - Test SSRF dùng URL https:// nên không bị phép kiểm tra scheme che mất
 *    (bộ test cũ chỉ dùng http:// nên luôn ném lỗi ở bước scheme, vô tình
 *    "đạt" mà không hề kiểm tra việc chặn IP nội bộ).
 *  - Test phục hồi dựng đúng trạng thái "đơn đã kết luận nhưng sổ sách chưa ghi".
 *  - Test lease thủ công kiểm tra đúng câu UPDATE có điều kiện.
 */
class B2bHardeningRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $partnerConfig;
    protected SanPham $product;
    protected B2bCreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->creditService = app(B2bCreditService::class);

        $dichVu = DichVu::firstOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại',
            'trang_thai' => 'hoat_dong',
        ]);

        $loaiSp = LoaiSanPham::firstOrCreate(['ma_loai_san_pham' => 'VTE_TOPUP'], [
            'ten_loai_san_pham' => 'Viettel Topup',
            'dich_vu_id' => $dichVu->id,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->product = SanPham::firstOrCreate(['ma_san_pham' => 'TEST_HARDEN_VT_10K'], [
            'ten_san_pham' => 'Viettel 10.000đ Hardening Test',
            'dich_vu_id' => $dichVu->id,
            'loai_san_pham_id' => $loaiSp->id,
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'HARDEN_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý Hardening Test',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partnerConfig = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'client_' . strtolower(Str::random(10)),
            'secret_key_ma_hoa' => 'sec_' . Str::random(16),
            'webhook_url' => 'https://partner.example.com/api/webhook',
            'webhook_secret_ma_hoa' => 'whsec_' . Str::random(16),
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => 5000000,
            'cong_no_hien_tai' => 0,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
        ]);
    }

    /** Dựng một đơn B2B kèm khoản giữ HOLDING, KHÔNG ghi sổ cái (mô phỏng tiến trình chết giữa chừng). */
    protected function dungDonVoiKhoanGiuChuaChot(string $trangThaiDon): DonHang
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_HARDEN_' . strtoupper(Str::random(8)),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_HARDEN_' . strtoupper(Str::random(8)),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988000111',
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai_don_hang' => $trangThaiDon,
            'hoan_thanh_luc' => $trangThaiDon === 'SUCCESS' ? now() : null,
            'that_bai_luc' => $trangThaiDon === 'FAILED' ? now() : null,
        ]);

        $this->creditService->kiemTraVaGiuHanMuc($this->partner, $donHang, 9700);

        return $donHang;
    }

    // =================================================================
    // 1. SSRF — ba lỗ hổng đã bịt
    // =================================================================

    public function test_ssrf_chan_ip_literal_noi_bo_bang_https_o_moi_truong_that(): void
    {
        $this->app['env'] = 'production';

        try {
            // Dùng https:// để phép kiểm tra scheme KHÔNG che mất phép kiểm tra IP.
            $urlNoiBo = [
                'https://10.0.0.5/hook',            // IP literal riêng tư — trước đây lọt vì dns_get_record rỗng
                'https://192.168.1.10/hook',
                'https://172.16.0.1/hook',
                'https://169.254.169.254/latest/meta-data',
                'https://127.0.0.1/hook',
                'https://[::1]/hook',               // IPv6 literal — parse_url trả host kèm ngoặc vuông
                'https://[::ffff:127.0.0.1]/hook',  // IPv6 dạng IPv4-mapped — trước đây không bị nhận diện
                'https://[fd00::1]/hook',           // IPv6 unique-local
            ];

            foreach ($urlNoiBo as $url) {
                $threw = false;
                try {
                    UrlSafetyValidator::validate($url);
                } catch (InvalidArgumentException $e) {
                    $threw = true;
                }

                $this->assertTrue($threw, "URL nội bộ '{$url}' phải bị chặn ở môi trường thật.");
            }
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_ssrf_cho_phep_loopback_chi_o_local_va_testing(): void
    {
        // Ở local/testing phải cho phép loopback để kiểm thử webhook nội bộ.
        $this->assertTrue(UrlSafetyValidator::validate('https://127.0.0.1/hook'));
        $this->assertTrue(UrlSafetyValidator::validate('http://127.0.0.1:8080/hook', true));

        // Ngoài local/testing thì TUYỆT ĐỐI không.
        $this->app['env'] = 'production';

        try {
            $threw = false;
            try {
                UrlSafetyValidator::validate('https://127.0.0.1/hook');
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }

            $this->assertTrue($threw, 'Loopback phải bị chặn ngoài môi trường kiểm thử.');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_is_private_ip_phan_loai_dung_ipv4_va_ipv6(): void
    {
        $noiBo = [
            '127.0.0.1', '10.1.2.3', '172.16.0.1', '172.31.255.255',
            '192.168.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0',
            '::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1',
        ];

        foreach ($noiBo as $ip) {
            $this->assertTrue(
                UrlSafetyValidator::isPrivateIp($ip),
                "IP '{$ip}' phải được coi là nội bộ/không hợp lệ."
            );
        }

        $congKhai = ['8.8.8.8', '1.1.1.1', '203.0.113.10', '172.32.0.1', '2606:4700::1111'];

        foreach ($congKhai as $ip) {
            $this->assertFalse(
                UrlSafetyValidator::isPrivateIp($ip),
                "IP '{$ip}' là công khai, không được chặn."
            );
        }
    }

    // =================================================================
    // 2. Phục hồi — tự chữa lành khi đơn đã kết luận nhưng sổ sách chưa ghi
    // =================================================================

    public function test_phuc_hoi_tu_chua_lanh_don_success_bi_khoan_giu_ket_o_holding(): void
    {
        // Đúng trạng thái do tiến trình chết giữa chừng để lại:
        // đơn đã SUCCESS, khoản giữ còn HOLDING, chưa có bút toán công nợ nào.
        $donHang = $this->dungDonVoiKhoanGiuChuaChot('SUCCESS');

        $this->assertEquals('HOLDING', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));
        $this->assertEquals(0, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());

        $hanhDong = app(B2bOrderRecoveryService::class)->phucHoi($donHang);

        $this->assertEquals(B2bOrderRecoveryService::ACTION_ALREADY_FINAL, $hanhDong);

        // Khoản giữ phải được chốt thay vì kẹt vĩnh viễn ở HOLDING.
        $this->assertEquals('COMMITTED', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));

        // Đúng MỘT bút toán, đúng số tiền.
        $this->assertEquals(1, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());
        $this->assertEquals(9700, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);
        $this->assertDatabaseHas('so_phat_sinh_cong_no', [
            'don_hang_id' => $donHang->id,
            'ma_tham_chieu' => 'TX-ORD-' . $donHang->id,
        ]);
    }

    public function test_phuc_hoi_tu_chua_lanh_la_idempotent(): void
    {
        $donHang = $this->dungDonVoiKhoanGiuChuaChot('SUCCESS');
        $service = app(B2bOrderRecoveryService::class);

        $service->phucHoi($donHang);
        $sauLanMot = (float) $this->partnerConfig->fresh()->cong_no_hien_tai;

        // Chạy thêm ba lượt nữa như nhịp quét mỗi phút của scheduler.
        $service->phucHoi($donHang->fresh());
        $service->phucHoi($donHang->fresh());
        $service->phucHoi($donHang->fresh());

        $this->assertEquals($sauLanMot, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);
        $this->assertEquals(1, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());
    }

    public function test_phuc_hoi_tu_chua_lanh_don_failed_giai_phong_khoan_giu_khong_ghi_no(): void
    {
        $donHang = $this->dungDonVoiKhoanGiuChuaChot('FAILED');

        $hanhDong = app(B2bOrderRecoveryService::class)->phucHoi($donHang);

        $this->assertEquals(B2bOrderRecoveryService::ACTION_ALREADY_FINAL, $hanhDong);
        $this->assertEquals('RELEASED', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));
        $this->assertEquals(0, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());
        $this->assertEquals(0, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);
    }

    public function test_phuc_hoi_khong_dung_toi_khoan_giu_cua_don_dang_doi_soat_thu_cong(): void
    {
        // Quy tắc nghiệp vụ: đối soát thủ công KHÔNG tự kết luận, KHÔNG tự hoàn,
        // KHÔNG tự giải phóng hạn mức. Bản sửa tự chữa lành không được phá quy tắc này.
        $donHang = $this->dungDonVoiKhoanGiuChuaChot('MANUAL_REVIEW');

        app(B2bOrderRecoveryService::class)->phucHoi($donHang);

        $this->assertEquals('HOLDING', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));
        $this->assertEquals(0, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());
        $this->assertEquals(0, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);
        $this->assertEquals('MANUAL_REVIEW', $donHang->fresh()->trang_thai_don_hang);
    }

    // =================================================================
    // 3. Gửi lại webhook thủ công — không được cướp lease đang sống
    // =================================================================

    public function test_gui_lai_webhook_thu_cong_khong_cuop_duoc_lease_dang_song(): void
    {
        $outbox = WebhookOutbox::create([
            'event_id' => (string) Str::uuid(),
            'dai_ly_api_id' => $this->partner->id,
            'event_type' => 'order.success',
            'payload_json' => json_encode(['event_type' => 'order.success']),
            'trang_thai' => 'PROCESSING',
            'so_lan_thu' => 1,
            'khoa_so_huu' => 'worker-dang-gui',
            'khoa_den' => now()->addMinutes(5), // lease còn sống
        ]);

        $token = app(B2bWebhookService::class)->nhanXuLyThuCong($outbox->id);

        $this->assertNull($token, 'Không được cướp lease của worker đang gửi dở.');

        // Chủ sở hữu cũ phải được giữ nguyên, không bị ghi đè.
        $moiNhat = $outbox->fresh();
        $this->assertEquals('worker-dang-gui', $moiNhat->khoa_so_huu);
        $this->assertEquals('PROCESSING', $moiNhat->trang_thai);
    }

    public function test_gui_lai_webhook_thu_cong_giành_duoc_quyen_khi_lease_da_het_han(): void
    {
        $outbox = WebhookOutbox::create([
            'event_id' => (string) Str::uuid(),
            'dai_ly_api_id' => $this->partner->id,
            'event_type' => 'order.success',
            'payload_json' => json_encode(['event_type' => 'order.success']),
            'trang_thai' => 'PROCESSING',
            'so_lan_thu' => 1,
            'khoa_so_huu' => 'worker-da-chet',
            'khoa_den' => now()->subMinute(), // lease đã hết hạn
        ]);

        $token = app(B2bWebhookService::class)->nhanXuLyThuCong($outbox->id);

        $this->assertNotNull($token, 'Lease đã hết hạn thì phải giành được quyền gửi lại.');
        $this->assertEquals($token, $outbox->fresh()->khoa_so_huu);
    }

    public function test_gui_lai_webhook_thu_cong_giành_duoc_quyen_voi_su_kien_dang_cho(): void
    {
        $outbox = WebhookOutbox::create([
            'event_id' => (string) Str::uuid(),
            'dai_ly_api_id' => $this->partner->id,
            'event_type' => 'order.failed',
            'payload_json' => json_encode(['event_type' => 'order.failed']),
            'trang_thai' => 'MANUAL_REVIEW',
            'so_lan_thu' => 6,
            'lan_thu_tiep_theo' => null,
        ]);

        $token = app(B2bWebhookService::class)->nhanXuLyThuCong($outbox->id);

        $this->assertNotNull($token);
        $this->assertEquals('PROCESSING', $outbox->fresh()->trang_thai);
    }
}
