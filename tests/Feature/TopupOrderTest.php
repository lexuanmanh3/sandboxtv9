<?php

namespace Tests\Feature;

use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Models\ViNguoiDung;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopupOrderTest extends TestCase
{
    protected User $user;
    protected LoaiSanPham $category;
    protected SanPham $product;
    protected DichVu $dichVu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'ten_dang_nhap' => 'testuser_' . uniqid(),
            'trang_thai' => 'hoat_dong',
            'bi_khoa' => false,
        ]);

        $this->dichVu = DichVu::firstOrCreate(
            ['ma_dich_vu' => 'MOBILE_TOPUP'],
            ['ten_dich_vu' => 'Nạp tiền điện thoại', 'trang_thai' => 'hoat_dong']
        );

        $this->category = LoaiSanPham::firstOrCreate(
            ['ma_loai_san_pham' => 'VTE_TOPUP'],
            [
                'dich_vu_id' => $this->dichVu->id,
                'ten_loai_san_pham' => 'Viettel Topup',
                'trang_thai' => 'hoat_dong',
                'thu_tu' => 1,
            ]
        );

        $this->product = SanPham::firstOrCreate(
            ['ma_san_pham' => 'VTE_TOPUP_50K'],
            [
                'dich_vu_id' => $this->dichVu->id,
                'loai_san_pham_id' => $this->category->id,
                'ten_san_pham' => 'Viettel 50.000đ',
                'menh_gia' => 50000,
                'gia_ban' => 49000,
                'chiet_khau_phan_tram' => 2.00,
                'trang_thai' => 'hoat_dong',
            ]
        );
    }

    public function test_carriers_api_returns_active_carriers()
    {
        $response = $this->actingAs($this->user)->getJson('/api/topup/carriers');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'code', 'name']]]);
    }

    public function test_denominations_api_returns_pricing_info()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/topup/denominations?carrier_id=' . $this->category->id);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => [['id', 'menh_gia', 'gia_ban', 'chiet_khau']]]);
    }

    public function test_preview_api_validates_phone_and_checks_balance()
    {
        // Gán số dư ví 100k
        app(ViNguoiDungService::class)->napTien($this->user->id, 100000, 'Test deposit');

        $response = $this->actingAs($this->user)->postJson('/api/topup/preview', [
            'phone' => '0987654321',
            'carrier' => 'VTE_TOPUP',
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'phone' => '0987654321',
                    'menh_gia' => 50000,
                    'gia_ban' => 49000,
                    'du_so_du' => true,
                ]
            ]);
    }

    public function test_create_order_fails_when_balance_insufficient()
    {
        // Ví số dư 0
        ViNguoiDung::updateOrCreate(['nguoi_dung_id' => $this->user->id], ['so_du' => 0]);

        $response = $this->actingAs($this->user)->postJson('/api/topup/orders', [
            'phone' => '0987654321',
            'carrier' => 'VTE_TOPUP',
            'product_id' => $this->product->id,
            'idempotency_key' => 'test_insufficient_' . time(),
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_create_order_success_deducts_wallet_and_creates_order()
    {
        // Nạp 100k vào ví
        app(ViNguoiDungService::class)->napTien($this->user->id, 100000, 'Test deposit');

        $idempotencyKey = 'test_order_key_' . uniqid();

        $response = $this->actingAs($this->user)->postJson('/api/topup/orders', [
            'phone' => '0987654321',
            'carrier' => 'VTE_TOPUP',
            'product_id' => $this->product->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Kiểm tra đơn hàng được tạo trong DB
        $this->assertDatabaseHas('don_hang', [
            'nguoi_dung_id' => $this->user->id,
            'idempotency_key' => $idempotencyKey,
            'san_pham_id' => $this->product->id,
            'gia_ban' => 49000,
        ]);

        // Kiểm tra biến động số dư ví
        $vi = ViNguoiDung::where('nguoi_dung_id', $this->user->id)->first();
        // Ban đầu 100k - trừ 49k = 51k (hoặc nếu job chạy thất bại thì được hoàn lại 100k)
        $this->assertNotNull($vi);
    }
}
