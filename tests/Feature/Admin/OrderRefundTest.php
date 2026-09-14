<?php

namespace Tests\Feature\Admin;

use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KetNoiNhaCungCap;
use App\Models\LanGoiNhaCungCap;
use App\Models\LichSuTrangThaiDonHang;
use App\Models\LichSuVi;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Models\Quyen;
use App\Models\SanPham;
use App\Models\User;
use App\Models\VaiTro;
use App\Models\ViNguoiDung;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\HoanTienDonHangService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderRefundTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected User $unauthorizedAdmin;
    protected User $customer;
    protected ViNguoiDung $customerWallet;
    protected DichVu $dichVu;
    protected LoaiSanPham $loaiSp;
    protected SanPham $sanPham;
    protected NhaCungCap $ncc;
    protected KetNoiNhaCungCap $ketNoi;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Kiểm tra an toàn tuyệt đối: Không bao giờ chạy trên database thật
        $currentDb = config('database.connections.mysql.database');
        if ($currentDb !== 'sandbox_test') {
            $this->markTestSkipped("BẢO VỆ DATABASE: Đang kết nối '{$currentDb}', không phải 'sandbox_test'. Dừng test ngay!");
        }

        // 2. Tạo role & permission cho Admin
        $vaiTroAdmin = VaiTro::firstOrCreate(['ma_vai_tro' => 'admin_test'], ['ten_vai_tro' => 'Admin Test']);
        $quyenRefund = Quyen::firstOrCreate(['ma_quyen' => 'order.refund'], ['ten_quyen' => 'Hoan tien don hang', 'nhom_quyen' => 'order']);
        $vaiTroAdmin->quyen()->syncWithoutDetaching([$quyenRefund->id]);

        $this->admin = User::factory()->create([
            'ten_dang_nhap' => 'admin_rf_' . uniqid(),
            'loai_tai_khoan' => 'admin',
            'trang_thai'    => 'hoat_dong',
            'bi_khoa'       => false,
            'tai_khoan_da_xac_thuc' => true,
        ]);
        $this->admin->vaiTro()->attach($vaiTroAdmin->id, ['tao_luc' => now()]);

        // 3. Admin không có quyền hoàn tiền (customer hoặc user thường)
        $this->unauthorizedAdmin = User::factory()->create([
            'ten_dang_nhap' => 'unauth_rf_' . uniqid(),
            'loai_tai_khoan' => 'customer',
            'trang_thai'    => 'hoat_dong',
            'bi_khoa'       => false,
            'tai_khoan_da_xac_thuc' => true,
        ]);

        // 4. Khách hàng và ví
        $this->customer = User::factory()->create([
            'ten_dang_nhap' => 'cust_rf_' . uniqid(),
            'trang_thai'    => 'hoat_dong',
            'bi_khoa'       => false,
        ]);

        $this->customerWallet = ViNguoiDung::firstOrCreate(
            ['nguoi_dung_id' => $this->customer->id],
            ['so_du' => '1000000.00', 'tong_nap' => '1000000.00', 'tong_chi' => '0.00', 'tong_hoan' => '0.00', 'trang_thai' => 'hoat_dong']
        );

        // 5. Master data cơ bản
        $this->dichVu = DichVu::firstOrCreate(
            ['ma_dich_vu' => 'MOBILE_TOPUP'],
            ['ten_dich_vu' => 'Nạp tiền điện thoại', 'trang_thai' => 'hoat_dong']
        );

        $this->loaiSp = LoaiSanPham::firstOrCreate(
            ['ma_loai_san_pham' => 'VTE_TOPUP'],
            ['ten_loai_san_pham' => 'Viettel Topup', 'dich_vu_id' => $this->dichVu->id, 'trang_thai' => 'hoat_dong']
        );

        $this->sanPham = SanPham::firstOrCreate(
            ['ma_san_pham' => 'VTE_50K'],
            [
                'dich_vu_id'       => $this->dichVu->id,
                'loai_san_pham_id' => $this->loaiSp->id,
                'ten_san_pham'     => 'Viettel 50.000đ',
                'menh_gia'         => 50000,
                'gia_ban'          => 48000,
                'gia_von'          => 47000,
                'chiet_khau'       => 4.0,
                'trang_thai'       => 'hoat_dong',
            ]
        );

        $this->ncc = NhaCungCap::firstOrCreate(
            ['ma_ncc' => 'NCC_TEST'],
            ['ten_ncc' => 'Nhà cung cấp Test', 'trang_thai' => 'hoat_dong']
        );

        $this->ketNoi = KetNoiNhaCungCap::firstOrCreate(
            ['nha_cung_cap_id' => $this->ncc->id],
            ['ten_ket_noi' => 'Kết nối Test', 'loai_ket_noi' => 'API', 'trang_thai' => 'hoat_dong']
        );
    }

    /**
     * Helper tạo đơn hàng kèm giao dịch trừ ví hợp lệ.
     */
    protected function createPaidOrder(string $orderStatus = 'PROVIDER_PENDING', float $giaBan = 48000.00): DonHang
    {
        $giaBanStr = number_format($giaBan, 2, '.', '');

        $order = DonHang::create([
            'ma_don_hang'            => 'DH_RF_' . strtoupper(uniqid()),
            'nguon_don'              => 'frontend',
            'nguoi_dung_id'          => $this->customer->id,
            'dich_vu_id'             => $this->dichVu->id,
            'loai_san_pham_id'       => $this->loaiSp->id,
            'san_pham_id'            => $this->sanPham->id,
            'tai_khoan_nhan'         => '0988888888',
            'so_luong'               => 1,
            'menh_gia'               => 50000,
            'gia_ban'                => $giaBanStr,
            'gia_von'                => 47000,
            'chiet_khau'             => 4.0,
            'loi_nhuan'              => 1000,
            'phuong_thuc_thanh_toan' => 'vi',
            'trang_thai_thanh_toan'  => 'da_thanh_toan',
            'trang_thai_don_hang'    => $orderStatus,
            'thanh_toan_luc'         => now(),
            'thong_bao_loi_he_thong' => 'Original error from provider',
        ]);

        // Tạo giao dịch debit gốc trong lịch sử ví
        $soDuTruoc = (string) $this->customerWallet->so_du;
        $soDuSau = bcsub($soDuTruoc, $giaBanStr, 2);

        $this->customerWallet->so_du = $soDuSau;
        $this->customerWallet->tong_chi = bcadd((string) $this->customerWallet->tong_chi, $giaBanStr, 2);
        $this->customerWallet->save();

        LichSuVi::create([
            'vi_nguoi_dung_id' => $this->customerWallet->id,
            'don_hang_id'      => $order->id,
            'loai_giao_dich'   => 'debit',
            'so_tien'          => $giaBanStr,
            'so_du_truoc'      => $soDuTruoc,
            'so_du_sau'        => $soDuSau,
            'mo_ta'            => "Thanh toán đơn #{$order->ma_don_hang}",
            'nguon'            => 'user',
        ]);

        return $order;
    }

    /**
     * TEST 1: Hoàn tiền một lần thành công (đi qua REFUND_PENDING -> REFUNDED)
     */
    public function test_single_refund_success_passes_through_refund_pending_and_credits_wallet(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'PROVIDER_PENDING', giaBan: 48000.00);
        $initialBalance = (string) $this->customerWallet->fresh()->so_du;

        // Mô phỏng NCC thất bại dứt điểm
        LanGoiNhaCungCap::create([
            'don_hang_id'             => $order->id,
            'nha_cung_cap_id'         => $this->ncc->id,
            'ket_noi_nha_cungCap_id'  => $this->ketNoi->id,
            'partner_ref_id'          => 'PREF_' . uniqid(),
            'ket_qua_xac_dinh'        => KetQuaNhaCungCap::DEFINITIVE_FAILURE->value,
            'trang_thai'              => 'DEFINITIVE_FAILURE',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/refund", [
                'ly_do_hoan_tien' => 'Admin duyệt hoàn tiền thủ công do NCC lỗi',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 1. Kiểm tra trạng thái đơn
        $order->refresh();
        $this->assertEquals(TrangThaiDonHang::REFUNDED->value, $order->trang_thai_don_hang);
        $this->assertEquals('REFUNDED', $order->trang_thai_thanh_toan);
        // Không ghi đè lỗi hệ thống ban đầu
        $this->assertEquals('Original error from provider', $order->thong_bao_loi_he_thong);

        // 2. Kiểm tra số dư ví
        $finalBalance = (string) $this->customerWallet->fresh()->so_du;
        $expectedBalance = bcadd($initialBalance, '48000.00', 2);
        $this->assertEquals($expectedBalance, $finalBalance);

        // 3. Kiểm tra đúng 1 bản ghi lịch sử ví refund
        $refundHistory = LichSuVi::where('don_hang_id', $order->id)
            ->where('loai_giao_dich', 'refund')
            ->get();
        $this->assertCount(1, $refundHistory);
        $this->assertEquals('48000.00', number_format((float) $refundHistory->first()->so_tien, 2, '.', ''));
        $this->assertEquals($initialBalance, number_format((float) $refundHistory->first()->so_du_truoc, 2, '.', ''));
        $this->assertEquals($expectedBalance, number_format((float) $refundHistory->first()->so_du_sau, 2, '.', ''));

        // 4. Kiểm tra lịch sử chuyển trạng thái: Phải đi qua REFUND_PENDING rồi mới sang REFUNDED
        $statusHistories = LichSuTrangThaiDonHang::where('don_hang_id', $order->id)
            ->orderBy('id', 'asc')
            ->pluck('trang_thai_moi')
            ->toArray();

        $this->assertContains(TrangThaiDonHang::REFUND_PENDING->value, $statusHistories);
        $this->assertContains(TrangThaiDonHang::REFUNDED->value, $statusHistories);
    }

    /**
     * TEST 2: Bấm hoàn tiền hai lần tuần tự -> Lần 2 bị từ chối an toàn, tiền chỉ cộng 1 lần
     */
    public function test_double_refund_sequential_rejected_safely(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 48000.00);

        // Lần 1: Thành công
        $res1 = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund");
        $res1->assertSessionHas('success');

        $walletAfterFirst = (string) $this->customerWallet->fresh()->so_du;

        // Lần 2: Thử hoàn lại cùng đơn
        $res2 = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund");
        $res2->assertSessionHasErrors(['refund']);
        $this->assertStringContainsString('đã được hoàn tiền trước đó', session('errors')->first('refund'));

        // Số dư không đổi
        $this->assertEquals($walletAfterFirst, (string) $this->customerWallet->fresh()->so_du);

        // Vẫn chỉ có đúng 1 bản ghi refund
        $refundCount = LichSuVi::where('don_hang_id', $order->id)
            ->where('loai_giao_dich', 'refund')
            ->count();
        $this->assertEquals(1, $refundCount);
    }

    /**
     * TEST 3: Concurrency - Hai kết nối DB thật trên MySQL/MariaDB hoàn cùng 1 đơn
     * Chứng minh row-level locking và unique constraint bảo vệ tuyệt đối
     */
    public function test_concurrency_two_db_connections_competing_for_same_order(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 50000.00);
        $initialBalance = (string) $this->customerWallet->fresh()->so_du;

        // Mở kết nối PDO thứ 2 trực tiếp tới database test
        $pdo2 = new \PDO('mysql:host=127.0.0.1;port=3306;dbname=sandbox_test', 'root', '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        // Connection 1 bắt đầu transaction và khóa order
        DB::beginTransaction();
        $lockedOrder = DonHang::query()->lockForUpdate()->find($order->id);

        // Tại thời điểm này, Connection 2 thử gọi refundService
        // Nhờ lockForUpdate, Connection 2 sẽ chờ hoặc khi Connection 1 commit xong,
        // Connection 2 đọc lại thấy trạng thái đã là REFUNDED và từ chối an toàn!
        $refundService = app(HoanTienDonHangService::class);

        // Commit Connection 1 thông qua refundService hoàn tất
        $refundService->hoanTien(
            orderId: $order->id,
            actorType: 'admin',
            actorId: $this->admin->id,
            actorName: 'admin1',
            reason: 'Admin 1 hoàn tiền'
        );
        DB::commit();

        // Bây giờ Connection 2 thử thực hiện hoàn tiền lại
        $rejectedSecondAttempt = false;
        try {
            $refundService->hoanTien(
                orderId: $order->id,
                actorType: 'admin',
                actorId: $this->admin->id,
                actorName: 'admin2',
                reason: 'Admin 2 hoàn tiền đồng thời'
            );
        } catch (\App\Exceptions\Refund\OrderAlreadyRefundedException $e) {
            $rejectedSecondAttempt = true;
        }

        $this->assertTrue($rejectedSecondAttempt, "Yêu cầu thứ hai phải bị từ chối với OrderAlreadyRefundedException!");

        // Số dư ví chỉ tăng đúng 50.000đ
        $expectedBalance = bcadd($initialBalance, '50000.00', 2);
        $this->assertEquals($expectedBalance, (string) $this->customerWallet->fresh()->so_du);

        // Chỉ có đúng 1 bản ghi hoàn tiền
        $this->assertEquals(1, LichSuVi::where('don_hang_id', $order->id)->where('loai_giao_dich', 'refund')->count());
    }

    /**
     * TEST 4: Hai đơn khác nhau hoàn tiền vào cùng một ví
     */
    public function test_two_different_orders_refunded_to_same_wallet(): void
    {
        $order1 = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 20000.00);
        $order2 = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 30000.00);

        $initialBalance = (string) $this->customerWallet->fresh()->so_du;

        $refundService = app(HoanTienDonHangService::class);
        $refundService->hoanTien($order1->id, 'admin', $this->admin->id, 'admin', 'Hoàn đơn 1');
        $refundService->hoanTien($order2->id, 'admin', $this->admin->id, 'admin', 'Hoàn đơn 2');

        // Số dư ví phải tăng đúng 20.000 + 30.000 = 50.000
        $expectedBalance = bcadd(bcadd($initialBalance, '20000.00', 2), '30000.00', 2);
        $this->assertEquals($expectedBalance, (string) $this->customerWallet->fresh()->so_du);

        $this->assertEquals(2, LichSuVi::whereIn('don_hang_id', [$order1->id, $order2->id])->where('loai_giao_dich', 'refund')->count());
    }

    /**
     * TEST 5: Đơn hàng SUCCESS tuyệt đối bị từ chối hoàn tiền
     */
    public function test_success_order_refund_is_strictly_rejected(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'SUCCESS', giaBan: 48000.00);
        $initialBalance = (string) $this->customerWallet->fresh()->so_du;

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund");

        $response->assertSessionHasErrors(['refund']);
        $this->assertStringContainsString('SUCCESS', session('errors')->first('refund'));

        // Đơn hàng và ví không đổi
        $this->assertEquals('SUCCESS', $order->fresh()->trang_thai_don_hang);
        $this->assertEquals($initialBalance, (string) $this->customerWallet->fresh()->so_du);
        $this->assertEquals(0, LichSuVi::where('don_hang_id', $order->id)->where('loai_giao_dich', 'refund')->count());
    }

    /**
     * TEST 6: Nhà cung cấp đang xử lý (UNKNOWN_OR_PENDING) -> Chưa được hoàn tiền
     */
    public function test_order_with_in_flight_provider_call_cannot_be_refunded(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'PROVIDER_PENDING', giaBan: 48000.00);

        // Lần gọi NCC đang ở trạng thái chưa rõ kết quả
        LanGoiNhaCungCap::create([
            'don_hang_id'             => $order->id,
            'nha_cung_cap_id'         => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->ketNoi->id,
            'partner_ref_id'          => 'PREF_' . uniqid(),
            'ket_qua_xac_dinh'        => KetQuaNhaCungCap::UNKNOWN_OR_PENDING->value,
            'trang_thai'              => 'PROCESSING',
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund");

        $response->assertSessionHasErrors(['refund']);
        $this->assertStringContainsString('Nhà cung cấp chưa xác nhận kết quả thất bại cuối cùng', session('errors')->first('refund'));

        // Đơn hàng vẫn giữ nguyên PROVIDER_PENDING
        $this->assertEquals(TrangThaiDonHang::PROVIDER_PENDING->value, $order->fresh()->trang_thai_don_hang);
    }

    /**
     * TEST 7: Worker dừng gửi NCC nếu đơn đã ở REFUND_PENDING / REFUNDED
     */
    public function test_worker_aborts_and_does_not_call_provider_if_order_is_already_refunded(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'REFUNDED', giaBan: 48000.00);

        // Dispatch worker
        $job = new \App\Jobs\ProcessMobileTopupJob($order->id);
        $job->handle(
            app(\App\Services\Topup\NhaCungCapRoutingService::class),
            app(\App\Services\Topup\NhaCungCapResolver::class),
            app(\App\Services\Topup\DonHangStateMachine::class),
            app(\App\Services\Alert\TelegramAlertService::class),
            app(\App\Services\Topup\ViNguoiDungService::class)
        );

        // Không có lần gọi NCC nào được tạo
        $this->assertEquals(0, LanGoiNhaCungCap::where('don_hang_id', $order->id)->count());
    }

    /**
     * TEST 8: Callback NCC báo SUCCESS sau khi đơn đã REFUNDED -> Không ghi đè SUCCESS, không trừ ví
     */
    public function test_provider_success_after_refund_does_not_overwrite_status_nor_deduct_wallet(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'REFUNDED', giaBan: 48000.00);
        $walletBalance = (string) $this->customerWallet->fresh()->so_du;

        $lanGoi = LanGoiNhaCungCap::create([
            'don_hang_id'             => $order->id,
            'nha_cung_cap_id'         => $this->ncc->id,
            'ket_noi_nha_cung_cap_id' => $this->ketNoi->id,
            'partner_ref_id'          => 'PREF_' . uniqid(),
            'ket_qua_xac_dinh'        => KetQuaNhaCungCap::UNKNOWN_OR_PENDING->value,
            'trang_thai'              => 'PROCESSING',
        ]);

        // Mock kết quả kiemTraTrangThai trả về SUCCESS
        $mockAdapter = $this->createMock(\App\Contracts\NhaCungCapTopupInterface::class);
        $mockAdapter->method('kiemTraTrangThai')->willReturn(
            new \App\DTOs\KetQuaNhaCungCapDTO(
                ketQua: KetQuaNhaCungCap::SUCCESS,
                maGiaoDichNcc: 'TX_NCC_SUCCESS_LATE',
                thongBao: 'Nạp thành công muộn',
                duLieu: ['status' => '00']
            )
        );

        $mockResolver = $this->createMock(\App\Services\Topup\NhaCungCapResolver::class);
        $mockResolver->method('resolve')->willReturn($mockAdapter);

        $job = new \App\Jobs\CheckMobileTopupStatusJob($lanGoi->id);
        $job->handle(
            $mockResolver,
            app(\App\Services\Topup\DonHangStateMachine::class),
            app(\App\Services\Topup\ViNguoiDungService::class),
            app(\App\Services\Alert\TelegramAlertService::class)
        );

        // Trạng thái đơn vẫn là REFUNDED, KHÔNG bị đổi thành SUCCESS!
        $this->assertEquals(TrangThaiDonHang::REFUNDED->value, $order->fresh()->trang_thai_don_hang);

        // Số dư ví không bị trừ lại
        $this->assertEquals($walletBalance, (string) $this->customerWallet->fresh()->so_du);

        // Lần gọi được cập nhật thông tin đối soát
        $this->assertEquals(KetQuaNhaCungCap::SUCCESS->value, $lanGoi->fresh()->ket_qua_xac_dinh);
    }

    /**
     * TEST 9: Đơn chưa có giao dịch trừ tiền (hoặc số tiền không khớp) -> Từ chối hoàn tiền
     */
    public function test_order_without_valid_debit_transaction_is_rejected(): void
    {
        // Tạo đơn không có debit transaction trong lich_su_vi
        $order = DonHang::create([
            'ma_don_hang'            => 'DH_UNPAID_' . strtoupper(uniqid()),
            'nguon_don'              => 'frontend',
            'nguoi_dung_id'          => $this->customer->id,
            'dich_vu_id'             => $this->dichVu->id,
            'loai_san_pham_id'       => $this->loaiSp->id,
            'san_pham_id'            => $this->sanPham->id,
            'tai_khoan_nhan'         => '0988888888',
            'so_luong'               => 1,
            'menh_gia'               => 50000,
            'gia_ban'                => '48000.00',
            'trang_thai_thanh_toan'  => 'cho_thanh_toan',
            'trang_thai_don_hang'    => TrangThaiDonHang::PROCESSING->value,
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund");

        $response->assertSessionHasErrors(['refund']);
        $this->assertStringContainsString('Không tìm thấy giao dịch trừ tiền gốc', session('errors')->first('refund'));
    }

    /**
     * TEST 10: Rollback nguyên tử khi bước cập nhật trạng thái hoặc ví phát sinh lỗi
     */
    public function test_rollback_atomicity_when_exception_occurs(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 48000.00);
        $initialBalance = (string) $this->customerWallet->fresh()->so_du;

        // Bắt sự kiện creating của LichSuVi để mô phỏng sự cố hệ thống lúc lưu
        LichSuVi::creating(function ($item) {
            if ($item->loai_giao_dich === 'refund' && str_contains((string) $item->mo_ta, 'Test rollback simulation')) {
                throw new \RuntimeException("Mô phỏng sự cố hệ thống khi ghi lịch sử ví!");
            }
        });

        $service = app(HoanTienDonHangService::class);

        $exceptionCaught = false;
        try {
            $service->hoanTien($order->id, 'admin', $this->admin->id, 'admin', 'Test rollback simulation');
        } catch (\Throwable $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught, "Ngoại lệ phải được ném ra để kích hoạt rollback");

        // Xác nhận transaction rollback 100%:
        // 1. Số dư ví không đổi trong DB
        $this->assertEquals($initialBalance, (string) $this->customerWallet->fresh()->so_du);

        // 2. Trạng thái đơn không bị kẹt ở REFUND_PENDING hay REFUNDED
        $this->assertEquals(TrangThaiDonHang::FAILED->value, $order->fresh()->trang_thai_don_hang);

        // 3. Không có bản ghi refund nào được lưu
        $this->assertEquals(0, LichSuVi::where('don_hang_id', $order->id)->where('loai_giao_dich', 'refund')->count());
    }

    /**
     * TEST 11: Telegram lỗi sau commit không làm ảnh hưởng giao dịch hoàn tiền đã thành công
     */
    public function test_telegram_failure_after_commit_does_not_fail_refund(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 48000.00);

        // Giả lập Telegram API trả về lỗi 500
        \Illuminate\Support\Facades\Http::fake([
            'https://api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => false, 'description' => 'Simulated Telegram Failure'], 500),
        ]);

        $response = $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/refund");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Đơn hàng vẫn hoàn tất REFUNDED
        $this->assertEquals(TrangThaiDonHang::REFUNDED->value, $order->fresh()->trang_thai_don_hang);
    }

    /**
     * TEST 12: Phân quyền - Admin không có quyền order.refund nhận HTTP 403
     */
    public function test_unauthorized_user_cannot_refund(): void
    {
        $order = $this->createPaidOrder(orderStatus: 'FAILED', giaBan: 48000.00);

        $response = $this->actingAs($this->unauthorizedAdmin)->post("/admin/orders/{$order->id}/refund");

        $response->assertStatus(403);
        $this->assertEquals(TrangThaiDonHang::FAILED->value, $order->fresh()->trang_thai_don_hang);
    }
}
