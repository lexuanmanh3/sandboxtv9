<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_security_headers_are_present_in_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_unauthenticated_users_cannot_access_admin_routes(): void
    {
        $this->get('/admin/orders')->assertRedirect('/login');
        $this->get('/admin/products')->assertRedirect('/login');
        $this->get('/admin/providers')->assertRedirect('/login');
        $this->get('/admin/audit-logs')->assertRedirect('/login');
        $this->get('/admin/maintenance')->assertRedirect('/login');
    }

    public function test_customer_without_admin_permissions_gets_403_on_admin_routes(): void
    {
        $customer = User::create([
            'ten_dang_nhap' => 'testuser_' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => Hash::make('Password@123'),
            'loai_tai_khoan' => 'customer',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $this->actingAs($customer)
            ->get('/admin/orders')
            ->assertStatus(403);

        $this->actingAs($customer)
            ->get('/admin/products')
            ->assertStatus(403);
    }

    public function test_admin_change_password_command_updates_password_cleanly(): void
    {
        $user = User::create([
            'ten_dang_nhap' => 'admin_test_' . uniqid(),
            'email' => 'adm_' . uniqid() . '@example.com',
            'password' => Hash::make('OldPassword@123'),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $this->artisan('admin:change-password', ['username' => $user->ten_dang_nhap])
            ->expectsQuestion('Nhập mật khẩu mới (tối thiểu 8 ký tự):', 'NewSecret@2026')
            ->expectsQuestion('Nhập lại mật khẩu để xác nhận:', 'NewSecret@2026')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret@2026', $user->password));
    }
}

