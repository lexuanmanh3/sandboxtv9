<?php

namespace Tests\Feature\B2B;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class B2bAdminViewsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_b2b_test@sandbox.local'],
            [
                'ten_dang_nhap' => 'admin_b2b_test',
                'name' => 'Admin Test',
                'password' => bcrypt('Admin@123456'),
                'loai_tai_khoan' => 'admin',
                'trang_thai' => 'hoat_dong',
            ]
        );
    }

    public function test_admin_can_view_partners_page_without_decrypt_exception(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/b2b/partners');

        $response->assertStatus(200);
        $response->assertSee('Quản lý Đại lý API (B2B)');
        $response->assertDontSee('The MAC is invalid');
    }

    public function test_admin_can_view_orders_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/b2b/orders');

        $response->assertStatus(200);
        $response->assertSee('Đơn hàng B2B');
    }

    public function test_admin_can_view_credit_payments_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/b2b/credit-payments');

        $response->assertStatus(200);
        $response->assertSee('Sổ công nợ');
    }

    public function test_admin_can_view_reconciliations_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/b2b/reconciliations');

        $response->assertStatus(200);
        $response->assertSee('Quản lý kỳ đối soát công nợ');
    }

    public function test_admin_can_view_webhooks_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/b2b/webhooks');

        $response->assertStatus(200);
        $response->assertSee('Lịch sử Webhook Outbox');
    }

    public function test_admin_can_view_order_detail_if_exists(): void
    {
        $order = \App\Models\DonHang::whereNotNull('dai_ly_api_id')->first();
        if ($order) {
            $response = $this->actingAs($this->admin)->get('/admin/b2b/orders/' . $order->id);
            $response->assertStatus(200);
            $response->assertSee('Đơn hàng B2B:');
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_admin_can_view_reconciliation_detail_if_exists(): void
    {
        $recon = \App\Models\KyDoiSoat::first();
        if ($recon) {
            $response = $this->actingAs($this->admin)->get('/admin/b2b/reconciliations/' . $recon->id);
            $response->assertStatus(200);
            $response->assertSee('Kỳ đối soát:');
        } else {
            $this->assertTrue(true);
        }
    }
}

