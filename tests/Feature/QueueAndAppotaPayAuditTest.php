<?php

namespace Tests\Feature;

use App\DTOs\KetQuaNhaCungCapDTO;
use App\DTOs\YeuCauNapTienDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Jobs\CheckMobileTopupStatusJob;
use App\Jobs\ProcessMobileTopupJob;
use App\Models\DonHang;
use App\Models\KetNoiNhaCungCap;
use App\Models\LanGoiNhaCungCap;
use App\Models\NhaCungCap;
use App\Models\SanPham;
use App\Models\SanPhamNhaCungCap;
use App\Models\User;
use App\Models\ViNguoiDung;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\NhaCungCapResolver;
use App\Services\Topup\NhaCungCapRoutingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class QueueAndAppotaPayAuditTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private NhaCungCap $ncc;
    private KetNoiNhaCungCap $ketNoi;
    private SanPham $sanPham;
    private SanPhamNhaCungCap $mapping;

    protected function setUp(): void
    {
        parent::setUp();

        $currentDb = config('database.connections.mysql.database');
        if ($currentDb !== 'sandbox_test') {
            $this->markTestSkipped("BẢO VỆ DATABASE: Đang kết nối '{$currentDb}', không phải 'sandbox_test'. Dừng test ngay!");
        }

        $this->user = User::factory()->create([
            'ten_dang_nhap' => 'test_user_' . uniqid(),
            'trang_thai'    => 'hoat_dong',
        ]);

        ViNguoiDung::firstOrCreate(
            ['nguoi_dung_id' => $this->user->id],
            ['so_du' => '1000000.00', 'tong_nap' => '1000000.00', 'tong_chi' => '0.00', 'tong_hoan' => '0.00', 'trang_thai' => 'hoat_dong']
        );

        $this->ncc = NhaCungCap::firstOrCreate(
            ['ma_ncc' => 'APPOTAPAY_TEST'],
            ['ten_ncc' => 'AppotaPay Test NCC', 'trang_thai' => 'hoat_dong']
        );

        $this->ketNoi = KetNoiNhaCungCap::firstOrCreate(
            ['nha_cung_cap_id' => $this->ncc->id, 'moi_truong' => 'sandbox'],
            [
                'ten_ket_noi' => 'AppotaPay Sandbox Test',
                'driver' => 'appotapay',
                'partner_code' => 'AP_TEST_01',
                'api_key_ma_hoa' => 'test_key',
                'secret_key_ma_hoa' => 'test_secret',
                'base_url' => 'https://sandbox.appotapay.com',
                'trang_thai' => 'hoat_dong',
                'cau_hinh_dong_tu_dong' => [
                    'so_gd_that_bai_lien_tiep' => 3,
                    'thoi_gian_dong_giay' => 300,
                ],
            ]
        );

        $this->sanPham = SanPham::first() ?: SanPham::create([
            'dich_vu_id' => 1,
            'loai_san_pham_id' => 1,
            'ma_san_pham' => 'VT10K_TEST',
            'ten_san_pham' => 'Viettel 10k Test',
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->mapping = SanPhamNhaCungCap::firstOrCreate(
            [
                'san_pham_id' => $this->sanPham->id,
                'nha_cung_cap_id' => $this->ncc->id,
                'ket_noi_nha_cung_cap_id' => $this->ketNoi->id,
            ],
            [
                'ma_san_pham_ncc' => 'VT10K',
                'ma_nha_mang_ncc' => 'VIETTEL',
                'menh_gia_ncc' => 10000,
                'gia_nhap' => 9500,
                'do_uu_tien' => 1,
                'trang_thai' => 'hoat_dong',
            ]
        );
    }

    private function createOrder(string $prefix, string $status = 'PROVIDER_PENDING', string $phone = '0988888888'): DonHang
    {
        return DonHang::create([
            'ma_don_hang' => $prefix . '_' . uniqid(),
            'nguon_don' => 'frontend',
            'nguoi_dung_id' => $this->user->id,
            'dich_vu_id' => $this->sanPham->dich_vu_id,
            'loai_san_pham_id' => $this->sanPham->loai_san_pham_id,
            'san_pham_id' => $this->sanPham->id,
            'so_luong' => 1,
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'gia_von' => 9500,
            'tong_tien' => 10000,
            'tai_khoan_nhan' => $phone,
            'trang_thai_don_hang' => $status,
            'trang_thai_thanh_toan' => 'da_thanh_toan',
        ]);
    }

    /**
     * Tái hiện & xác nhận sửa lỗi $telegramAlert trong CheckMobileTopupStatusJob:
     * AppotaPay trả SUCCESS, closure cập nhật đơn thành công và gọi cảnh báo Telegram an toàn qua DB::afterCommit.
     */
    public function test_check_mobile_topup_status_job_success_executes_after_commit_without_undefined_variable(): void
    {
        $order = $this->createOrder('ORD_STATUS', TrangThaiDonHang::PROVIDER_PENDING->value);

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $order->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->ketNoi->id,
            'san_pham_nha_cung_cap_id' => $this->mapping->id,
            'lan_thu' => 1,
            'loai_yeu_cau' => 'CHECK_STATUS',
            'partner_ref_id' => 'REF_' . uniqid(),
            'ma_san_pham_ncc' => 'VT10K',
            'endpoint' => '/api/v1/service/topup/transaction',
            'trang_thai' => 'PROCESSING',
            'bat_dau_luc' => now(),
        ]);

        $mockProvider = Mockery::mock(\App\Contracts\NhaCungCapTopupInterface::class);
        $mockProvider->shouldReceive('kiemTraTrangThai')
            ->once()
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::SUCCESS,
                maLoi: '0',
                thongBao: 'Giao dịch thành công',
                maGiaoDichNcc: 'AP_TRANS_99999',
                chuKyHopLe: true
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockProvider);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $mockAlert = Mockery::mock(TelegramAlertService::class);
        $mockAlert->shouldReceive('alertOrderSuccess')->once();
        $this->app->instance(TelegramAlertService::class, $mockAlert);

        // Chạy job
        $job = new CheckMobileTopupStatusJob($lanGoi->id);
        $job->handle(
            $mockResolver,
            app(\App\Services\Topup\DonHangStateMachine::class),
            app(\App\Services\Topup\ViNguoiDungService::class),
            $mockAlert
        );

        $order->refresh();
        $this->assertSame(TrangThaiDonHang::SUCCESS->value, $order->trang_thai_don_hang);
        $this->assertNotNull($order->hoan_thanh_luc);

        $lanGoi->refresh();
        $this->assertSame('SUCCESS', $lanGoi->trang_thai);
        $this->assertSame('AP_TRANS_99999', $lanGoi->ma_giao_dich_ncc);
    }

    /**
     * Kiểm tra khi Telegram alert ném ngoại lệ sau commit, nghiệp vụ đơn hàng vẫn giữ SUCCESS và không bị rollback.
     */
    public function test_telegram_failure_does_not_rollback_order_success(): void
    {
        $order = $this->createOrder('ORD_TELE_FAIL', TrangThaiDonHang::PROVIDER_PENDING->value, '0977777777');

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id' => $order->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->ketNoi->id,
            'san_pham_nha_cung_cap_id' => $this->mapping->id,
            'lan_thu' => 1,
            'loai_yeu_cau' => 'CHECK_STATUS',
            'partner_ref_id' => 'REF_' . uniqid(),
            'ma_san_pham_ncc' => 'VT10K',
            'endpoint' => '/api/v1/service/topup/transaction',
            'trang_thai' => 'PROCESSING',
            'bat_dau_luc' => now(),
        ]);

        $mockProvider = Mockery::mock(\App\Contracts\NhaCungCapTopupInterface::class);
        $mockProvider->shouldReceive('kiemTraTrangThai')
            ->once()
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::SUCCESS,
                maLoi: '0',
                thongBao: 'Success',
                maGiaoDichNcc: 'AP_TRANS_12345',
                chuKyHopLe: true
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockProvider);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        // Telegram giả lập lỗi mạng
        $mockAlert = Mockery::mock(TelegramAlertService::class);
        $mockAlert->shouldReceive('alertOrderSuccess')
            ->once()
            ->andThrow(new \RuntimeException('Telegram API network connection timed out'));
        $this->app->instance(TelegramAlertService::class, $mockAlert);

        $job = new CheckMobileTopupStatusJob($lanGoi->id);
        $job->handle(
            $mockResolver,
            app(\App\Services\Topup\DonHangStateMachine::class),
            app(\App\Services\Topup\ViNguoiDungService::class),
            $mockAlert
        );

        $order->refresh();
        $this->assertSame(TrangThaiDonHang::SUCCESS->value, $order->trang_thai_don_hang);
    }

    /**
     * Chống nạp trùng: Khi đơn đã có một lần gọi CHARGING đang dở dang (PROCESSING) do worker trước chết/timeout,
     * ProcessMobileTopupJob không được sinh partner_ref_id mới, không được gọi nạp tiền lại,
     * mà phải chuyển đơn sang PROVIDER_PENDING và lên lịch tra cứu.
     */
    public function test_process_mobile_topup_job_detects_in_flight_call_and_prevents_duplicate_charge(): void
    {
        \Illuminate\Support\Facades\Queue::fake([CheckMobileTopupStatusJob::class]);

        $order = $this->createOrder('ORD_INFLIGHT', TrangThaiDonHang::PROCESSING->value, '0966666666');

        $initialPartnerRef = 'TP_INFLIGHT_' . uniqid();
        $inFlightCall = LanGoiNhaCungCap::create([
            'don_hang_id' => $order->id,
            'nha_cung_cap_id' => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->ketNoi->id,
            'san_pham_nha_cung_cap_id' => $this->mapping->id,
            'lan_thu' => 1,
            'loai_yeu_cau' => 'CHARGING',
            'partner_ref_id' => $initialPartnerRef,
            'ma_san_pham_ncc' => 'VT10K',
            'endpoint' => '/api/v2/service/topup/charging',
            'trang_thai' => 'PROCESSING',
            'bat_dau_luc' => now(),
        ]);

        // Provider tuyệt đối không được gọi nạp tiền lại!
        $mockProvider = Mockery::mock(\App\Contracts\NhaCungCapTopupInterface::class);
        $mockProvider->shouldNotReceive('napTien');

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->never();
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $mockAlert = Mockery::mock(TelegramAlertService::class);
        $this->app->instance(TelegramAlertService::class, $mockAlert);

        $job = new ProcessMobileTopupJob($order->id);
        $job->handle(
            app(NhaCungCapRoutingService::class),
            $mockResolver,
            app(\App\Services\Topup\DonHangStateMachine::class),
            $mockAlert,
            app(\App\Services\Topup\ViNguoiDungService::class)
        );

        $order->refresh();
        $this->assertSame(TrangThaiDonHang::PROVIDER_PENDING->value, $order->trang_thai_don_hang);

        // Đảm bảo không tạo thêm lần gọi nào khác (vẫn chỉ có 1 lần gọi)
        $this->assertSame(1, LanGoiNhaCungCap::where('don_hang_id', $order->id)->count());

        $inFlightCall->refresh();
        $this->assertSame('UNKNOWN_OR_PENDING', $inFlightCall->trang_thai);

        // Đảm bảo CheckMobileTopupStatusJob được dispatch với ID của lần gọi dở dang
        \Illuminate\Support\Facades\Queue::assertPushed(CheckMobileTopupStatusJob::class, function ($job) use ($inFlightCall) {
            return $job->lanGoiNhaCungCapId === $inFlightCall->id;
        });
    }

    /**
     * Circuit breaker: Khi các lần gọi liên tiếp thất bại vượt ngưỡng (3 lần),
     * kết nối nhà cung cấp được chuyển sang 'tam_dung' và cờ circuit_breaker_tripped được ghi nhận.
     */
    public function test_circuit_breaker_triggers_after_consecutive_failures(): void
    {
        $this->ketNoi->update([
            'cau_hinh_dong_tu_dong' => [
                'so_gd_that_bai_lien_tiep' => 3,
                'thoi_gian_dong_giay' => 300,
            ],
            'trang_thai' => 'hoat_dong',
        ]);

        $cacheKey = "circuit_breaker_fails_{$this->ketNoi->id}";
        Cache::forget($cacheKey);
        Cache::forget("circuit_breaker_tripped_{$this->ketNoi->id}");

        $mockProvider = Mockery::mock(\App\Contracts\NhaCungCapTopupInterface::class);
        $mockProvider->shouldReceive('napTien')
            ->andReturn(new KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::DEFINITIVE_FAILURE,
                maLoi: 'PROVIDER_ERROR_99',
                thongBao: 'Hệ thống nhà cung cấp quá tải'
            ));

        $mockResolver = Mockery::mock(NhaCungCapResolver::class);
        $mockResolver->shouldReceive('resolve')->andReturn($mockProvider);
        $this->app->instance(NhaCungCapResolver::class, $mockResolver);

        $mockAlert = Mockery::mock(TelegramAlertService::class);
        $mockAlert->shouldReceive('alertTransactionFailure')->times(3);
        $mockAlert->shouldReceive('alertCircuitBreakerTriggered')->once();
        $mockAlert->shouldReceive('alertSlowTransaction')->zeroOrMoreTimes();
        $this->app->instance(TelegramAlertService::class, $mockAlert);

        // Thực hiện 3 đơn thất bại liên tiếp (nguon_don = api để không phát sinh hoàn tiền ví)
        for ($i = 1; $i <= 3; $i++) {
            $order = $this->createOrder('ORD_CB_' . $i, TrangThaiDonHang::QUEUED->value, '0912345678');
            $order->update(['nguon_don' => 'api']);

            $job = new ProcessMobileTopupJob($order->id);
            $job->handle(
                app(NhaCungCapRoutingService::class),
                $mockResolver,
                app(\App\Services\Topup\DonHangStateMachine::class),
                $mockAlert,
                app(\App\Services\Topup\ViNguoiDungService::class)
            );
        }

        $this->ketNoi->refresh();
        $this->assertSame('tam_dung', $this->ketNoi->trang_thai);
        $this->assertTrue(Cache::has("circuit_breaker_tripped_{$this->ketNoi->id}"));
    }
}
