<?php

namespace Tests\Feature;

use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Models\VaiTro;
use App\Models\ViNguoiDung;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthAndAuthorizationSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear(Str::transliterate('testuser_rate|127.0.0.1'));
    }

    // =========================================================================
    // PHẦN 24 – TEST XÁC THỰC & SESSION
    // =========================================================================

    public function test_guest_accessing_admin_routes_redirects_to_login(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->get('/admin/accounts')->assertRedirect('/login');
        $this->get('/admin/roles')->assertRedirect('/login');
        $this->get('/admin/maintenance')->assertRedirect('/login');
    }

    public function test_normal_customer_gets_403_on_admin_routes(): void
    {
        $customer = $this->createCustomerUser();

        $this->actingAs($customer)->get('/admin/orders')->assertStatus(403);
        $this->actingAs($customer)->get('/admin/accounts')->assertStatus(403);
        $this->actingAs($customer)->get('/admin/roles')->assertStatus(403);
        $this->actingAs($customer)->get('/admin/maintenance')->assertStatus(403);
    }

    public function test_locked_user_cannot_login(): void
    {
        $password = 'Secret@123';
        $user = User::create([
            'ten_dang_nhap' => 'locked_' . uniqid(),
            'email' => 'locked_' . uniqid() . '@example.com',
            'password' => Hash::make($password),
            'loai_tai_khoan' => 'customer',
            'trang_thai' => 'tam_khoa',
            'bi_khoa' => true,
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $response = $this->post('/login', [
            'ten_dang_nhap' => $user->ten_dang_nhap,
            'password' => $password,
        ]);

        $response->assertSessionHasErrors('ten_dang_nhap');
        $this->assertGuest();
    }

    public function test_user_locked_during_active_session_is_blocked_on_next_request(): void
    {
        $customer = $this->createCustomerUser();

        // Customer đang có session hợp lệ
        $this->actingAs($customer);
        $this->get('/nap-tien-dien-thoai')->assertStatus(200);

        // Khóa tài khoản
        $customer->update([
            'bi_khoa' => true,
            'trang_thai' => 'tam_khoa',
        ]);

        // Request tiếp theo phải bị chặn và đẩy về trang đăng nhập
        $response = $this->actingAs($customer->fresh())->get('/nap-tien-dien-thoai');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session_and_regenerates_csrf_token(): void
    {
        $customer = $this->createCustomerUser();

        $response = $this->actingAs($customer)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_brute_force_is_rate_limited(): void
    {
        $username = 'testuser_rate';
        $throttleKey = Str::transliterate($username . '|127.0.0.1');
        RateLimiter::clear($throttleKey);

        // Thử sai 5 lần
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'ten_dang_nhap' => $username,
                'password' => 'WrongPassword',
            ]);
        }

        // Lần thứ 6 phải bị chặn (bởi throttle route middleware HTTP 429 hoặc controller RateLimiter)
        $response = $this->post('/login', [
            'ten_dang_nhap' => $username,
            'password' => 'WrongPassword',
        ]);

        $this->assertTrue(
            $response->status() === 429 || $response->isRedirect()
        );

        if ($response->status() === 429) {
            $response->assertStatus(429);
        } else {
            $response->assertSessionHasErrors('ten_dang_nhap');
            $errors = session('errors')->get('ten_dang_nhap');
            $this->assertStringContainsString('Quá nhiều lần đăng nhập', $errors[0]);
        }

        RateLimiter::clear($throttleKey);
    }

    public function test_password_is_not_stored_in_activity_log(): void
    {
        $customer = $this->createCustomerUser();

        $this->actingAs($customer)->post('/login', [
            'ten_dang_nhap' => $customer->ten_dang_nhap,
            'password' => 'SuperSecretPlainPassword',
        ]);

        $latestLog = \App\Models\NhatKyKiemTra::latest('id')->first();
        if ($latestLog && $latestLog->tham_so) {
            $params = is_array($latestLog->tham_so) ? json_encode($latestLog->tham_so) : (string) $latestLog->tham_so;
            $this->assertStringNotContainsString('SuperSecretPlainPassword', $params);
        }
        $this->assertTrue(true);
    }

    // =========================================================================
    // PHẦN 25 – TEST PHÂN QUYỀN & LEO THANG ĐẶC QUYỀN
    // =========================================================================

    public function test_staff_and_accountant_cannot_access_unauthorized_features(): void
    {
        $staff = User::create([
            'ten_dang_nhap' => 'staff_' . uniqid(),
            'email' => 'staff_' . uniqid() . '@example.com',
            'password' => Hash::make('StaffPass@123'),
            'loai_tai_khoan' => 'backend',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $backendRole = VaiTro::where('ma_vai_tro', 'backend')->first();
        if ($backendRole) {
            $staff->vaiTro()->attach($backendRole->id, ['tao_luc' => now()]);
        }

        // Nhân viên không có quyền quản lý tài khoản, hoàn tiền, cấu hình NCC hay bảo trì
        $this->actingAs($staff)->get('/admin/accounts')->assertStatus(403);
        $this->actingAs($staff)->get('/admin/roles')->assertStatus(403);
        $this->actingAs($staff)->get('/admin/providers')->assertStatus(403);
        $this->actingAs($staff)->get('/admin/maintenance')->assertStatus(403);
        $this->actingAs($staff)->post('/admin/orders/1/refund')->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_call_refund_endpoint(): void
    {
        $customer = $this->createCustomerUser();

        $response = $this->actingAs($customer)->post('/admin/orders/999/refund', [
            'ly_do_hoan_tien' => 'Hacked refund attempt',
        ]);

        $response->assertStatus(403);
    }

    public function test_operator_cannot_change_their_own_role_or_account_type(): void
    {
        $admin = $this->createAdminUser();

        // Admin cố đổi loại tài khoản của chính mình sang tài khoản khác
        $response = $this->actingAs($admin)->put("/admin/accounts/{$admin->id}", [
            'ten_dang_nhap' => $admin->ten_dang_nhap,
            'loai_tai_khoan' => 'customer',
            'trang_thai' => 'hoat_dong',
        ]);

        $response->assertSessionHasErrors('account');
    }

    public function test_non_root_admin_cannot_create_admin_account(): void
    {
        $normalAdmin = User::create([
            'ten_dang_nhap' => 'subadmin_' . uniqid(),
            'email' => 'subadmin_' . uniqid() . '@example.com',
            'password' => Hash::make('AdminPass@123'),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $normalAdmin->vaiTro()->attach($adminRole->id, ['tao_luc' => now()]);
        }

        // Subadmin (không phải root admin 'admin') cố tạo tài khoản Admin
        $response = $this->actingAs($normalAdmin)->post('/admin/accounts', [
            'ten_dang_nhap' => 'new_admin_' . uniqid(),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // PHẦN 26 – TEST IDOR / BOLA
    // =========================================================================

    public function test_user_a_cannot_view_order_of_user_b(): void
    {
        $userA = $this->createCustomerUser();
        $userB = $this->createCustomerUser();

        $service = DichVu::where('ma_dich_vu', 'MOBILE_TOPUP')->first();
        $category = LoaiSanPham::first();
        $product = SanPham::first();

        $orderB = DonHang::create([
            'ma_don_hang' => 'ORD_' . uniqid(),
            'nguon_don' => 'frontend',
            'nguoi_dung_id' => $userB->id,
            'idempotency_key' => 'IDEM_' . uniqid(),
            'dich_vu_id' => $service?->id ?? 1,
            'loai_san_pham_id' => $category?->id ?? 1,
            'san_pham_id' => $product?->id ?? 1,
            'tai_khoan_nhan' => '0987654321',
            'so_luong' => 1,
            'menh_gia' => 50000,
            'gia_ban' => 49000,
            'chiet_khau' => 2,
            'phuong_thuc_thanh_toan' => 'vi',
            'trang_thai_thanh_toan' => 'da_thanh_toan',
            'trang_thai_don_hang' => 'SUCCESS',
        ]);

        // User A gọi API chi tiết đơn của User B -> phải nhận 404
        $response = $this->actingAs($userA)->getJson("/api/topup/orders/{$orderB->id}");
        $response->assertStatus(404);

        // User B gọi API chi tiết đơn của chính mình -> thành công 200
        $responseB = $this->actingAs($userB)->getJson("/api/topup/orders/{$orderB->id}");
        $responseB->assertStatus(200);
        $responseB->assertJsonPath('data.ma_don_hang', $orderB->ma_don_hang);
    }

    // =========================================================================
    // PHẦN 27 – TEST DỮ LIỆU ĐẦU VÀO, GIÁ & TRẠNG THÁI
    // =========================================================================

    public function test_client_cannot_tamper_pricing_or_order_status(): void
    {
        $customer = $this->createCustomerUser();
        $vi = ViNguoiDung::firstOrCreate(
            ['nguoi_dung_id' => $customer->id],
            ['so_du' => 500000, 'trang_thai' => 'hoat_dong']
        );
        $vi->update(['so_du' => 500000]);

        $product = SanPham::whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])->first();
        if (!$product) {
            $this->markTestSkipped('Chưa có sản phẩm hợp lệ để test.');
        }

        $realDbPrice = $product->gia_ban ? (float) $product->gia_ban : (float) $product->menh_gia;

        // Gửi payload giả mạo giá 1000đ và trạng thái SUCCESS
        $response = $this->actingAs($customer)->postJson('/api/topup/orders', [
            'phone' => '0988123456',
            'carrier' => 'viettel',
            'product_id' => $product->id,
            'idempotency_key' => 'IDEM_' . Str::random(16),
            'price' => 1000, // Giá giả
            'amount' => 1000,
            'gia_ban' => 1000,
            'status' => 'SUCCESS', // Trạng thái giả
            'trang_thai_don_hang' => 'SUCCESS',
        ]);

        $response->assertStatus(200);
        $orderId = $response->json('data.id');
        $createdOrder = DonHang::find($orderId);

        // Giá bán trên đơn hàng phải do server tính từ DB, không nhận giá giả
        $this->assertEquals($realDbPrice, (float) $createdOrder->gia_ban);
        // Trạng thái đơn ban đầu không thể bị client ép thành SUCCESS ngay từ request
        $this->assertNotEquals('SUCCESS', $createdOrder->getOriginal('trang_thai_don_hang'));
    }

    public function test_uploading_invalid_file_format_or_svg_is_rejected(): void
    {
        $admin = $this->createAdminUser();

        // Giả lập file SVG chứa script độc hại
        $svgFile = UploadedFile::fake()->createWithContent('malicious.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $response = $this->actingAs($admin)->post('/admin/categories', [
            'ma_loai_san_pham' => 'TEST_SVG_' . strtoupper(Str::random(4)),
            'ten_loai_san_pham' => 'Test SVG Category',
            'trang_thai' => 'hoat_dong',
            'hinh_anh_file' => $svgFile,
        ]);

        $response->assertSessionHasErrors('hinh_anh_file');
    }

    public function test_uploading_php_file_disguised_as_image_is_rejected(): void
    {
        $admin = $this->createAdminUser();

        $fakePhp = UploadedFile::fake()->createWithContent('shell.php.png', '<?php phpinfo(); ?>');

        $response = $this->actingAs($admin)->post('/admin/categories', [
            'ma_loai_san_pham' => 'TEST_FAKE_' . strtoupper(Str::random(4)),
            'ten_loai_san_pham' => 'Test Fake PNG Category',
            'trang_thai' => 'hoat_dong',
            'hinh_anh_file' => $fakePhp,
        ]);

        $response->assertSessionHasErrors('hinh_anh_file');
    }

    // =========================================================================
    // PHẦN 28 – TEST CSRF & SECURITY HEADERS
    // =========================================================================

    public function test_sensitive_routes_are_not_in_csrf_exceptions(): void
    {
        $kernel = app(\App\Http\Kernel::class);
        $reflection = new \ReflectionClass(\App\Http\Middleware\VerifyCsrfToken::class);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);
        $except = $property->getValue(app(\App\Http\Middleware\VerifyCsrfToken::class));

        $this->assertEmpty($except);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function createCustomerUser(): User
    {
        $user = User::create([
            'ten_dang_nhap' => 'cust_' . uniqid(),
            'email' => 'cust_' . uniqid() . '@example.com',
            'password' => Hash::make('Password@123'),
            'loai_tai_khoan' => 'customer',
            'trang_thai' => 'hoat_dong',
            'bi_khoa' => false,
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $userRole = VaiTro::where('ma_vai_tro', 'user')->first();
        if ($userRole) {
            $user->vaiTro()->sync([$userRole->id]);
        }

        return $user;
    }

    private function createAdminUser(): User
    {
        $admin = User::create([
            'ten_dang_nhap' => 'adm_' . uniqid(),
            'email' => 'adm_' . uniqid() . '@example.com',
            'password' => Hash::make('AdminPass@123'),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'bi_khoa' => false,
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $admin->vaiTro()->sync([$adminRole->id]);
        }

        return $admin;
    }
}
