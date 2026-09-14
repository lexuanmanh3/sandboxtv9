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

    public function test_customer_without_permission_cannot_access_or_update_telegram_settings(): void
    {
        $customer = User::create([
            'ten_dang_nhap' => 'cust_' . uniqid(),
            'email' => 'cust_' . uniqid() . '@example.com',
            'password' => Hash::make('Password@123'),
            'loai_tai_khoan' => 'customer',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $this->actingAs($customer)
            ->get('/admin/telegram-settings')
            ->assertStatus(403);

        $this->actingAs($customer)
            ->put('/admin/telegram-settings', ['bot_token' => 'hacked_token'])
            ->assertStatus(403);
    }

    public function test_admin_can_view_and_update_telegram_settings(): void
    {
        $admin = User::create([
            'ten_dang_nhap' => 'adm_tele_' . uniqid(),
            'email' => 'adm_tele_' . uniqid() . '@example.com',
            'password' => Hash::make('AdminPass@123'),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $admin->vaiTro()->attach($adminRole->id, ['tao_luc' => now()]);
        }

        $this->actingAs($admin)
            ->get('/admin/telegram-settings')
            ->assertStatus(200);

        $this->actingAs($admin)
            ->put('/admin/telegram-settings', [
                'bot_token' => 'TEST_TELEGRAM_BOT_TOKEN_1',
                'chat_id_alert' => '-1001111111111',
                'chat_id_order' => '-1002222222222',
                'chat_id_admin' => '-1003333333333',
                'bat_thong_bao_don_hang' => '1',
                'bat_canh_bao_loi' => '1',
            ])
            ->assertRedirect('/admin/telegram-settings')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('cau_hinh_thong_bao', [
            'loai' => 'telegram',
            'chat_id_order' => '-1002222222222',
        ]);
    }

    public function test_admin_can_save_dynamic_channels_and_event_mappings(): void
    {
        $admin = User::create([
            'ten_dang_nhap' => 'adm_dyn_' . uniqid(),
            'email' => 'adm_dyn_' . uniqid() . '@example.com',
            'password' => Hash::make('AdminPass@123'),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $admin->vaiTro()->attach($adminRole->id, ['tao_luc' => now()]);
        }

        $channels = [
            ['id' => 'chan_1', 'ten_kenh' => 'Nhóm Cầu Dao', 'chat_id' => '-1005555555555', 'ghi_chu' => 'Kỹ thuật'],
            ['id' => 'chan_2', 'ten_kenh' => 'Nhóm Đơn Lỗi', 'chat_id' => '-1006666666666', 'ghi_chu' => 'Vận hành'],
        ];

        $eventMappings = [
            'circuit_breaker' => '-1005555555555',
            'transaction_failure' => '-1006666666666',
        ];

        $this->actingAs($admin)
            ->put('/admin/telegram-settings', [
                'bot_token' => 'TEST_TELEGRAM_BOT_TOKEN_2',
                'danh_sach_kenh' => $channels,
                'cau_hinh_kenh_su_kien' => $eventMappings,
                'bat_canh_bao_circuit_breaker' => '1',
                'bat_canh_bao_loi' => '1',
            ])
            ->assertRedirect('/admin/telegram-settings')
            ->assertSessionHas('success');

        $cfg = \App\Models\CauHinhThongBao::layCauHinhTelegram();
        $this->assertEquals('-1005555555555', $cfg->layChatIdChoSuKien('circuit_breaker'));
        $this->assertEquals('-1006666666666', $cfg->layChatIdChoSuKien('transaction_failure'));
        $this->assertCount(2, $cfg->layDanhSachKenhHopLe());
    }
}


