<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Middleware\RecordActivityLog;
use App\Http\Middleware\TrustProxies;
use App\Models\KetNoiNhaCungCap;
use App\Models\NhaCungCap;
use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ConfigCacheLogAuditTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private KetNoiNhaCungCap $ketNoi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'ten_dang_nhap' => 'adm_audit_' . uniqid(),
            'email' => 'adm_audit_' . uniqid() . '@example.com',
            'password' => Hash::make('AdminPass@123'),
            'loai_tai_khoan' => 'admin',
            'trang_thai' => 'hoat_dong',
            'tai_khoan_da_xac_thuc' => true,
        ]);

        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $this->admin->vaiTro()->attach($adminRole->id, ['tao_luc' => now()]);
        }

        $ncc = NhaCungCap::firstOrCreate(
            ['ma_ncc' => 'AUDIT_NCC'],
            ['ten_ncc' => 'Audit NCC', 'trang_thai' => 'hoat_dong']
        );

        $this->ketNoi = KetNoiNhaCungCap::firstOrCreate(
            ['nha_cung_cap_id' => $ncc->id, 'moi_truong' => 'sandbox'],
            [
                'ten_ket_noi' => 'Audit Conn Test',
                'driver' => 'appotapay',
                'trang_thai' => 'hoat_dong',
            ]
        );
    }

    /**
     * Kiểm tra clearSpecificCache('providers') xóa chính xác cache circuit breaker theo ID kết nối,
     * khắc phục lỗi wildcard Cache::forget('circuit_breaker_*').
     */
    public function test_clear_provider_cache_clears_connection_specific_keys(): void
    {
        $failKey = "circuit_breaker_fails_{$this->ketNoi->id}";
        $tripKey = "circuit_breaker_tripped_{$this->ketNoi->id}";

        Cache::put($failKey, 5, 3600);
        Cache::put($tripKey, true, 3600);

        $this->assertTrue(Cache::has($failKey));
        $this->assertTrue(Cache::has($tripKey));

        $controller = new MaintenanceController();
        $response = $controller->clearSpecificCache('providers');

        $this->assertFalse(Cache::has($failKey));
        $this->assertFalse(Cache::has($tripKey));
    }

    /**
     * Kiểm tra parseLogFile đọc an toàn theo tail buffer và trả về mảng có cấu trúc
     * mà không gây OOM khi gặp log dung lượng lớn.
     */
    public function test_parse_log_file_safely_reads_tail_buffer(): void
    {
        $logPath = storage_path('logs/laravel.log');
        $originalContent = File::exists($logPath) ? File::get($logPath) : '';

        try {
            // Ghi file log giả lập gồm 200 dòng với định dạng timestamp hợp lệ HH:MM:SS
            $sampleLines = [];
            for ($i = 1; $i <= 200; $i++) {
                $min = str_pad((string) intdiv($i, 60), 2, '0', STR_PAD_LEFT);
                $sec = str_pad((string) ($i % 60), 2, '0', STR_PAD_LEFT);
                $sampleLines[] = "[2026-09-05 12:{$min}:{$sec}] local.INFO: Test log entry number {$i}";
            }
            File::put($logPath, implode("\n", $sampleLines));

            $controller = new MaintenanceController();
            $method = new \ReflectionMethod(MaintenanceController::class, 'parseLogFile');
            $method->setAccessible(true);
            $parsed = $method->invoke($controller, 50);

            $this->assertIsArray($parsed);
            $this->assertNotEmpty($parsed);
            // Sẽ lấy tối đa 50 dòng mới nhất
            $this->assertLessThanOrEqual(50, count($parsed));
            $this->assertSame('INFO', $parsed[0]['level']);
        } finally {
            if (!empty($originalContent)) {
                File::put($logPath, $originalContent);
            } else {
                @unlink($logPath);
            }
        }
    }

    /**
     * Kiểm tra RecordActivityLog che giấu (masking) số điện thoại của khách hàng
     * cùng các thông tin nhạy cảm (token, mật khẩu).
     */
    public function test_record_activity_log_masks_phone_and_sensitive_keys(): void
    {
        $middleware = new RecordActivityLog();
        $method = new \ReflectionMethod(RecordActivityLog::class, 'sanitize');
        $method->setAccessible(true);

        $data = [
            'so_dien_thoai' => '0988123456',
            'tai_khoan_nhan' => '0912345678',
            'phone' => '0977888999',
            'password' => 'secret_password_123',
            'token' => 'bearer_token_abc',
            'product_code' => 'VT10K',
        ];

        $sanitized = $method->invoke($middleware, $data);

        // Mật khẩu và token phải được che hoàn toàn thành [REDACTED]
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['token']);

        // Số điện thoại phải được mask (giữ đầu và cuối, ẩn giữa)
        $this->assertNotSame('0988123456', $sanitized['so_dien_thoai']);
        $this->assertStringStartsWith('098', $sanitized['so_dien_thoai']);
        $this->assertStringEndsWith('56', $sanitized['so_dien_thoai']);
        $this->assertStringContainsString('*', $sanitized['so_dien_thoai']);

        $this->assertNotSame('0912345678', $sanitized['tai_khoan_nhan']);
        $this->assertStringContainsString('*', $sanitized['tai_khoan_nhan']);

        // Mã sản phẩm không nhạy cảm được giữ nguyên
        $this->assertSame('VT10K', $sanitized['product_code']);
    }

    /**
     * Kiểm tra cấu hình TrustProxies để hỗ trợ Nginx/Cloudflare reverse proxy.
     */
    public function test_trust_proxies_is_configured_for_reverse_proxies(): void
    {
        $reflection = new \ReflectionProperty(TrustProxies::class, 'proxies');
        $reflection->setAccessible(true);
        $proxies = $reflection->getValue(new TrustProxies(app()));

        $this->assertNotNull($proxies);
        $this->assertSame('*', $proxies);
    }
}
