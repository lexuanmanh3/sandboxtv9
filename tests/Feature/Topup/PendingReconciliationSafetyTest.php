<?php

namespace Tests\Feature\Topup;

use App\Contracts\NhaCungCapTopupInterface;
use App\DTOs\KetQuaNhaCungCapDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Jobs\CheckMobileTopupStatusJob;
use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KetNoiNhaCungCap;
use App\Models\KhoanGiuHanMuc;
use App\Models\LanGoiNhaCungCap;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SoPhatSinhCongNo;
use App\Models\User;
use App\Models\ViNguoiDung;
use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bCreditService;
use App\Services\DaiLyApi\B2bWebhookService;
use App\Services\Topup\NhaCungCapResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PendingReconciliationSafetyTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected ViNguoiDung $wallet;
    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $partnerConfig;
    protected DichVu $dichVu;
    protected LoaiSanPham $loaiSp;
    protected SanPham $product;
    protected NhaCungCap $ncc;
    protected KetNoiNhaCungCap $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $currentDb = config('database.connections.mysql.database');
        if ($currentDb !== 'sandbox_test') {
            $this->markTestSkipped("BẢO VỆ DATABASE: Đang kết nối '{$currentDb}', không phải 'sandbox_test'. Dừng test ngay!");
        }

        // Admin user & wallet
        $this->user = User::factory()->create([
            'ten_dang_nhap' => 'b2c_safe_user_' . uniqid(),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
        ]);
        $this->wallet = ViNguoiDung::firstOrCreate(
            ['nguoi_dung_id' => $this->user->id],
            [
                'so_du' => 500000.00,
                'tong_nap' => 500000.00,
                'tong_chi' => 0.00,
                'tong_hoan' => 0.00,
                'trang_thai' => 'hoat_dong',
            ]
        );

        // Service & Product
        $this->dichVu = DichVu::firstOrCreate(
            ['ma_dich_vu' => 'MOBILE_TOPUP'],
            ['ten_dich_vu' => 'Nạp tiền điện thoại', 'trang_thai' => 'hoat_dong']
        );
        $this->loaiSp = LoaiSanPham::firstOrCreate(
            ['ma_loai_san_pham' => 'VTE_TOPUP'],
            ['ten_loai_san_pham' => 'Viettel Topup', 'dich_vu_id' => $this->dichVu->id, 'trang_thai' => 'hoat_dong']
        );
        $this->product = SanPham::firstOrCreate(
            ['ma_san_pham' => 'SAFE_VT_20K'],
            [
                'ten_san_pham' => 'Viettel 20K Test',
                'dich_vu_id' => $this->dichVu->id,
                'loai_san_pham_id' => $this->loaiSp->id,
                'menh_gia' => 20000,
                'gia_ban' => 19500,
                'gia_nhap' => 19000,
                'chiet_khau' => 500,
                'trang_thai' => 'hoat_dong',
            ]
        );

        // B2B Partner
        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'PARTNER_SAFE_' . strtoupper(Str::random(5)),
            'ten_dai_ly_api' => 'Đại lý Test An Toàn',
            'trang_thai' => 'hoat_dong',
        ]);
        $this->partner->dichVu()->attach($this->dichVu->id);
        $this->partner->loaiSanPham()->attach($this->loaiSp->id);
        $this->partner->sanPham()->attach($this->product->id);

        $this->partnerConfig = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'client_safe_' . strtolower(Str::random(8)),
            'secret_key_ma_hoa' => 'secret_safe_key_123',
            'webhook_url' => 'https://partner.test/webhook',
            'webhook_secret' => 'webhook_sec_123',
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => 1000000,
            'cong_no_hien_tai' => 0,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
            'dich_vu_duoc_phep' => ['MOBILE_TOPUP'],
            'san_pham_loai_tru' => [],
        ]);

        // Provider & Connection
        $this->ncc = NhaCungCap::firstOrCreate(
            ['ma_ncc' => 'SAFE_NCC_TEST'],
            ['ten_ncc' => 'Safe NCC Mock Test', 'trang_thai' => 'hoat_dong']
        );
        $this->connection = KetNoiNhaCungCap::firstOrCreate(
            ['nha_cung_cap_id' => $this->ncc->id, 'moi_truong' => 'sandbox'],
            [
                'ten_ket_noi' => 'Safe Connection Mock',
                'driver' => 'appotapay',
                'partner_code' => 'SAFE_TEST_01',
                'api_key_ma_hoa' => 'test_key',
                'secret_key_ma_hoa' => 'test_secret',
                'base_url' => 'https://sandbox.safe.test',
                'trang_thai' => 'hoat_dong',
                'cau_hinh_dong_tu_dong' => [
                    'so_lan_kiem_tra_lai' => 3,
                    'hanh_dong_khi_het_gio' => 'MANUAL_REVIEW',
                ],
            ]
        );
    }

    /**
     * 1. Hết số lần tra cứu SLA nhưng NCC vẫn UNKNOWN_OR_PENDING:
     * - Đơn phải chuyển sang MANUAL_REVIEW
     * - Tiền ví B2C KHÔNG được hoàn
     * - Khoản giữ hạn mức B2B KHÔNG được nhả
     * - KHÔNG tạo webhook order.failed
     */
    public function test_sla_timeout_unknown_status_transitions_to_manual_review_without_refund_or_releasing_hold(): void
    {
        // Tạo đơn B2B
        $donHangB2b = DonHang::create([
            'ma_don_hang' => 'ORD_B2B_TIMEOUT_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_B2B_TIMEOUT_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988111222',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'PROVIDER_PENDING',
        ]);

        app(B2bCreditService::class)->kiemTraVaGiuHanMuc($this->partner, $donHangB2b, 19500);

        $hold = KhoanGiuHanMuc::where('don_hang_id', $donHangB2b->id)->first();
        $this->assertEquals('HOLDING', $hold->trang_thai);

        // Tạo lần gọi charging
        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHangB2b->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_TIMEOUT_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
        ]);

        // Mock NCC driver trả về UNKNOWN_OR_PENDING
        $mockDriver = Mockery::mock(NhaCungCapTopupInterface::class);
        $mockDriver->shouldReceive('kiemTraTrangThai')
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
                maLoi: 'PENDING_AT_PROVIDER',
                thongBao: 'Đang xử lý tại nhà mạng',
                maGiaoDichNcc: 'TX_PENDING_123',
                chuKyHopLe: true,
                duLieu: ['status' => 'PENDING'],
                httpStatus: 200
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockDriver);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        // Chạy job với businessAttempt = 3 (đúng maxAttempts = 3)
        $job = new CheckMobileTopupStatusJob($lanGoi->id, 3);
        app()->call([$job, 'handle']);

        // Xác minh kết quả:
        $donHangFresh = $donHangB2b->fresh();
        $this->assertEquals(TrangThaiDonHang::MANUAL_REVIEW->value, $donHangFresh->trang_thai_don_hang);
        $this->assertEquals('cho_doi_soat', $donHangFresh->trang_thai_doi_soat);

        // Khoản giữ B2B vẫn phải là HOLDING, KHÔNG được nhả
        $holdFresh = $hold->fresh();
        $this->assertEquals('HOLDING', $holdFresh->trang_thai);

        // Công nợ vẫn giữ nguyên 0, không có bút toán phát sinh
        $this->assertEquals(0, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);
        $this->assertEquals(0, SoPhatSinhCongNo::where('don_hang_id', $donHangB2b->id)->count());

        // Tuyệt đối không có webhook failed
        $this->assertDatabaseMissing('webhook_outbox', [
            'don_hang_id' => $donHangB2b->id,
            'event_type' => 'order.failed',
        ]);
    }

    /**
     * 2. Kể cả khi DB cũ còn lưu TU_DONG_HOAN_TIEN, job vẫn phải vô hiệu hóa và chuyển sang MANUAL_REVIEW
     */
    public function test_legacy_config_tu_dong_hoan_tien_is_neutralized_when_provider_is_unknown(): void
    {
        $this->connection->update([
            'cau_hinh_dong_tu_dong' => [
                'so_lan_kiem_tra_lai' => 2,
                'hanh_dong_khi_het_gio' => 'TU_DONG_HOAN_TIEN', // Cấu hình cũ nguy hiểm
            ],
        ]);

        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_LEGACY_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_LEGACY_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988222333',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'PROVIDER_PENDING',
        ]);

        app(B2bCreditService::class)->kiemTraVaGiuHanMuc($this->partner, $donHang, 19500);

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHang->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_LEGACY_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
        ]);

        $mockDriver = Mockery::mock(NhaCungCapTopupInterface::class);
        $mockDriver->shouldReceive('kiemTraTrangThai')
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
                maLoi: 'TIMEOUT',
                thongBao: 'Timeout từ NCC',
                maGiaoDichNcc: 'TX_LEGACY_999',
                chuKyHopLe: false,
                duLieu: [],
                httpStatus: 504
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockDriver);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $job = new CheckMobileTopupStatusJob($lanGoi->id, 2);
        app()->call([$job, 'handle']);

        // Kết quả PHẢI LÀ MANUAL_REVIEW, KHÔNG PHẢI FAILED!
        $this->assertEquals(TrangThaiDonHang::MANUAL_REVIEW->value, $donHang->fresh()->trang_thai_don_hang);
        $this->assertEquals('HOLDING', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));
    }

    /**
     * 3. Late SUCCESS cho đơn đang ở MANUAL_REVIEW:
     * - Chuyển sang SUCCESS
     * - Chuyển hold sang COMMITTED và ghi nợ B2B đúng 1 lần
     * - Chạy lại lần 2 không sinh thêm bút toán (Idempotent)
     */
    public function test_late_success_on_manual_review_order_commits_b2b_debt_exactly_once(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_MANUAL_SUCC_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_MANUAL_SUCC_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988333444',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'MANUAL_REVIEW', // Đang chờ đối soát
        ]);

        app(B2bCreditService::class)->kiemTraVaGiuHanMuc($this->partner, $donHang, 19500);

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHang->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_MANUAL_SUCC_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
        ]);

        $mockDriver = Mockery::mock(NhaCungCapTopupInterface::class);
        $mockDriver->shouldReceive('kiemTraTrangThai')
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::SUCCESS,
                maLoi: '00',
                thongBao: 'Thành công',
                maGiaoDichNcc: 'NCC_TX_SUCCESS_LATE',
                chuKyHopLe: true,
                duLieu: ['status' => 'SUCCESS'],
                httpStatus: 200
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockDriver);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $job = new CheckMobileTopupStatusJob($lanGoi->id, 1);
        app()->call([$job, 'handle']);

        // 1. Đơn chuyển SUCCESS
        $this->assertEquals(TrangThaiDonHang::SUCCESS->value, $donHang->fresh()->trang_thai_don_hang);

        // 2. Khoản giữ chuyển sang COMMITTED
        $this->assertEquals('COMMITTED', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));

        // 3. Công nợ ghi tăng đúng 19500
        $this->assertEquals(19500, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);

        // 4. Sổ cái phát sinh đúng 1 bản ghi
        $this->assertEquals(1, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());

        // 5. Webhook order.success được tạo
        $this->assertDatabaseHas('webhook_outbox', [
            'don_hang_id' => $donHang->id,
            'event_type' => 'order.success',
        ]);

        // Chạy lại một lần nữa (mô phỏng worker chạy trùng):
        app(B2bCreditService::class)->chotCongNoThanhCong($donHang->fresh());
        $this->assertEquals(19500, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);
        $this->assertEquals(1, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());
    }

    /**
     * 4. Bất thường nghiêm trọng: Đơn đã REFUNDED nhưng NCC báo SUCCESS muộn:
     * - Không tự động trừ lại tiền ví hoặc ghi công nợ
     * - Không âm thầm ghi đè trạng thái SUCCESS
     */
    public function test_late_success_on_already_refunded_order_does_not_recharge_or_overwrite_status(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_ALREADY_REF_' . time(),
            'nguon_don' => 'frontend',
            'nguoi_dung_id' => $this->user->id,
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988444555',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'vi_tien',
            'trang_thai_thanh_toan' => 'REFUNDED',
            'trang_thai_don_hang' => 'REFUNDED',
        ]);

        $initialWalletBalance = (float) $this->wallet->fresh()->so_du;

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHang->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_ANOMALY_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
        ]);

        $mockDriver = Mockery::mock(NhaCungCapTopupInterface::class);
        $mockDriver->shouldReceive('kiemTraTrangThai')
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::SUCCESS,
                maLoi: '00',
                thongBao: 'Giao dịch thành công muộn',
                maGiaoDichNcc: 'TX_ANOMALY_SUCCESS',
                chuKyHopLe: true,
                duLieu: [],
                httpStatus: 200
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockDriver);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $job = new CheckMobileTopupStatusJob($lanGoi->id, 1);
        app()->call([$job, 'handle']);

        // Đơn hàng PHẢI giữ nguyên REFUNDED
        $this->assertEquals(TrangThaiDonHang::REFUNDED->value, $donHang->fresh()->trang_thai_don_hang);

        // Số dư ví người dùng KHÔNG bị trừ lại
        $this->assertEquals($initialWalletBalance, (float) $this->wallet->fresh()->so_du);
    }

    /**
     * 5. Late DEFINITIVE_FAILURE cho đơn ở MANUAL_REVIEW:
     * - Chuyển sang FAILED
     * - B2B: Nhả khoản giữ sang RELEASED đúng một lần
     * - Webhook: order.failed
     */
    public function test_late_definitive_failure_on_manual_review_releases_hold(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_MANUAL_FAIL_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_MANUAL_FAIL_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988555666',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'MANUAL_REVIEW',
        ]);

        app(B2bCreditService::class)->kiemTraVaGiuHanMuc($this->partner, $donHang, 19500);

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHang->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_MANUAL_FAIL_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
        ]);

        $mockDriver = Mockery::mock(NhaCungCapTopupInterface::class);
        $mockDriver->shouldReceive('kiemTraTrangThai')
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::DEFINITIVE_FAILURE,
                maLoi: 'SUB_DOES_NOT_EXIST',
                thongBao: 'Thuê bao không tồn tại',
                maGiaoDichNcc: 'TX_DEFINITIVE_FAIL',
                chuKyHopLe: true,
                duLieu: [],
                httpStatus: 200
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockDriver);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $job = new CheckMobileTopupStatusJob($lanGoi->id, 1);
        app()->call([$job, 'handle']);

        // Đơn chuyển FAILED
        $this->assertEquals(TrangThaiDonHang::FAILED->value, $donHang->fresh()->trang_thai_don_hang);

        // Khoản giữ giải phóng sang RELEASED
        $this->assertEquals('RELEASED', KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->value('trang_thai'));

        // Công nợ không tăng
        $this->assertEquals(0, (float) $this->partnerConfig->fresh()->cong_no_hien_tai);

        // Webhook order.failed được sinh ra
        $this->assertDatabaseHas('webhook_outbox', [
            'don_hang_id' => $donHang->id,
            'event_type' => 'order.failed',
        ]);
    }

    /**
     * 6. Webhook event_id là tất định (deterministic UUIDv5) và outbox là idempotent
     */
    public function test_webhook_event_id_is_deterministic_and_idempotent(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_WEBHOOK_IDEM_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_WH_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988777888',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'SUCCESS',
        ]);

        $service = app(B2bWebhookService::class);

        $outbox1 = $service->taoSuKien($donHang, 'order.success');
        $this->assertNotNull($outbox1);

        $outbox2 = $service->taoSuKien($donHang, 'order.success');
        $this->assertNotNull($outbox2);

        // ID outbox và event_id phải trùng khớp, không tạo bản ghi mới
        $this->assertEquals($outbox1->id, $outbox2->id);
        $this->assertEquals($outbox1->event_id, $outbox2->event_id);

        $count = WebhookOutbox::where('don_hang_id', $donHang->id)->where('event_type', 'order.success')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * 7. Chặn Admin nhả hạn mức thủ công khi đơn đang PROVIDER_PENDING
     */
    public function test_system_operation_controller_cannot_release_hold_while_order_is_provider_pending(): void
    {
        $this->actingAs($this->user);

        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_BLOCK_RELEASE_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_BLOCK_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988999000',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'chiet_khau' => 500,
            'loi_nhuan' => 500,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'PROVIDER_PENDING',
        ]);

        app(B2bCreditService::class)->kiemTraVaGiuHanMuc($this->partner, $donHang, 19500);
        $hold = KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->first();

        // Gửi POST release hold qua route chính xác admin.b2b.operations.credit-holds.release
        $response = $this->post(route('admin.b2b.operations.credit-holds.release', $hold->id));

        // Bị chặn vì đơn đang PROVIDER_PENDING
        $response->assertSessionHas('error');
        $this->assertEquals('HOLDING', $hold->fresh()->trang_thai);
    }

    /**
     * 8. Kiểm thử Artisan Command topup:reconcile-pending
     */
    public function test_reconcile_pending_command_dispatches_jobs_and_expires_old_orders(): void
    {
        Queue::fake();

        // 1 đơn còn hạn tra cứu (< 24h)
        $donHangActive = DonHang::create([
            'ma_don_hang' => 'ORD_REC_ACT_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_REC_ACT_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988111333',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'PROVIDER_PENDING',
        ]);

        $callActive = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHangActive->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_ACT_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
            'created_at' => now()->subMinutes(10),
            'bat_dau_luc' => now()->subMinutes(10),
        ]);

        // 1 đơn đã quá hạn (> 24h)
        $donHangExpired = DonHang::create([
            'ma_don_hang' => 'ORD_REC_EXP_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'REF_REC_EXP_' . time(),
            'dich_vu_id' => $this->dichVu->id,
            'loai_san_pham_id' => $this->loaiSp->id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988111444',
            'menh_gia' => 20000,
            'gia_ban' => 19500,
            'gia_von' => 19000,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'PROVIDER_PENDING',
        ]);

        $callExpired = LanGoiNhaCungCap::create([
            'don_hang_id' => $donHangExpired->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->connection->id,
            'partner_ref_id' => 'PREF_EXP_' . time(),
            'loai_yeu_cau' => 'CHARGING',
            'trang_thai' => 'UNKNOWN_OR_PENDING',
            'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
            'created_at' => now()->subHours(25),
            'bat_dau_luc' => now()->subHours(25),
        ]);

        // Chạy command
        Artisan::call('topup:reconcile-pending', ['--max-hours' => 24, '--min-interval' => 60]);

        // Đơn active phải được dispatch CheckMobileTopupStatusJob
        Queue::assertPushed(CheckMobileTopupStatusJob::class, function ($job) use ($callActive) {
            return $job->lanGoiNhaCungCapId === $callActive->id;
        });

        // Đơn hết hạn phải chuyển sang MANUAL_REVIEW
        $this->assertEquals(TrangThaiDonHang::MANUAL_REVIEW->value, $donHangExpired->fresh()->trang_thai_don_hang);
        $this->assertEquals('cho_doi_soat', $donHangExpired->fresh()->trang_thai_doi_soat);
    }

    /**
     * Chống "chết đói" (starvation) trong hàng đợi tra cứu nền.
     *
     * Trước đây command sắp xếp theo id tăng dần và các lần gọi của đơn đã quá hạn
     * (đã ở MANUAL_REVIEW) không bao giờ rời khỏi tập kết quả: mỗi lượt chúng chiếm
     * hết slot của limit, nên các đơn phía sau KHÔNG BAO GIỜ được tra cứu.
     *
     * Nay mỗi lần gọi đã xử lý đều được đẩy kiem_tra_lai_luc lên hiện tại, đưa nó về
     * cuối hàng đợi công bằng, nên lượt quét sau sẽ phục vụ được các đơn còn lại.
     */
    public function test_reconcile_pending_does_not_starve_orders_behind_expired_manual_review_calls(): void
    {
        Queue::fake();

        $taoDon = function (string $trangThai, string $hauTo) {
            return DonHang::create([
                'ma_don_hang' => 'ORD_STARVE_' . $hauTo . '_' . Str::random(6),
                'nguon_don' => 'b2b',
                'dai_ly_api_id' => $this->partner->id,
                'ma_don_doi_tac' => 'REF_STARVE_' . $hauTo . '_' . Str::random(6),
                'dich_vu_id' => $this->dichVu->id,
                'loai_san_pham_id' => $this->loaiSp->id,
                'san_pham_id' => $this->product->id,
                'tai_khoan_nhan' => '0988' . random_int(100000, 999999),
                'menh_gia' => 20000,
                'gia_ban' => 19500,
                'gia_von' => 19000,
                'phuong_thuc_thanh_toan' => 'cong_no',
                'trang_thai_thanh_toan' => 'chua_thanh_toan',
                'trang_thai_don_hang' => $trangThai,
            ]);
        };

        $taoCall = function (DonHang $don, string $hauTo, ?Carbon $batDauLuc) {
            return LanGoiNhaCungCap::create([
                'don_hang_id' => $don->id,
                'nha_cung_cap_id' => $this->ncc->id,
                'ket_noi_nha_cung_cap_id' => $this->connection->id,
                'partner_ref_id' => 'PREF_STARVE_' . $hauTo . '_' . Str::random(6),
                'loai_yeu_cau' => 'CHARGING',
                'trang_thai' => 'UNKNOWN_OR_PENDING',
                'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
                'bat_dau_luc' => $batDauLuc,
                'kiem_tra_lai_luc' => null,
            ]);
        };

        // Hai lần gọi của đơn ĐÃ quá hạn và đã ở MANUAL_REVIEW, id nhỏ (đứng đầu hàng đợi)
        $callManual1 = $taoCall($taoDon(TrangThaiDonHang::MANUAL_REVIEW->value, 'M1'), 'M1', now()->subHours(30));
        $callManual2 = $taoCall($taoDon(TrangThaiDonHang::MANUAL_REVIEW->value, 'M2'), 'M2', now()->subHours(30));

        // Một lần gọi của đơn CÒN trong thời hạn tra cứu, id lớn (đứng sau hàng đợi)
        $callPending = $taoCall($taoDon(TrangThaiDonHang::PROVIDER_PENDING->value, 'P1'), 'P1', now()->subMinutes(10));

        $options = ['--limit' => 2, '--max-hours' => 24, '--min-interval' => 60, '--slow-interval' => 3600];

        // Lượt 1: hai lần gọi MANUAL_REVIEW quá hạn chiếm hết 2 slot của limit
        Artisan::call('topup:reconcile-pending', $options);

        Queue::assertPushed(CheckMobileTopupStatusJob::class, fn($job) => $job->lanGoiNhaCungCapId === $callManual1->id);
        Queue::assertPushed(CheckMobileTopupStatusJob::class, fn($job) => $job->lanGoiNhaCungCapId === $callManual2->id);

        // Lượt 2: đơn PROVIDER_PENDING phải được phục vụ, không bị chặn vĩnh viễn
        Queue::fake();

        Artisan::call('topup:reconcile-pending', $options);

        Queue::assertPushed(
            CheckMobileTopupStatusJob::class,
            fn($job) => $job->lanGoiNhaCungCapId === $callPending->id
        );
    }
}
