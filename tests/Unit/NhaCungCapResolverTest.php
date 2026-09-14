<?php

namespace Tests\Unit;

use App\Contracts\NhaCungCapTopupInterface;
use App\Models\KetNoiNhaCungCap;
use App\Models\NhaCungCap;
use App\Services\Topup\AppotaPay\AppotaPayTopupProvider;
use App\Services\Topup\NhaCungCapResolver;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class NhaCungCapResolverTest extends TestCase
{
    public function test_resolves_appotapay_via_config(): void
    {
        $ncc = new NhaCungCap(['ma_ncc' => 'APPOTAPAY', 'ten_ncc' => 'AppotaPay']);
        $ketNoi = new KetNoiNhaCungCap([
            'driver' => 'appotapay',
            'ten_ket_noi' => 'AppotaPay Conn',
        ]);
        $ketNoi->setRelation('nhaCungCap', $ncc);

        $resolver = app(NhaCungCapResolver::class);
        $provider = $resolver->resolve($ketNoi);

        $this->assertInstanceOf(AppotaPayTopupProvider::class, $provider);
    }

    public function test_can_extend_new_provider_dynamically(): void
    {
        $ncc = new NhaCungCap(['ma_ncc' => 'VNPAY', 'ten_ncc' => 'VNPay Test']);
        $ketNoi = new KetNoiNhaCungCap([
            'driver' => 'vnpay',
            'ten_ket_noi' => 'VNPay Conn',
        ]);
        $ketNoi->setRelation('nhaCungCap', $ncc);

        $mockCustomProvider = Mockery::mock(NhaCungCapTopupInterface::class);

        $resolver = new NhaCungCapResolver();
        $resolver->extend('vnpay', function ($conn, $app) use ($mockCustomProvider) {
            return $mockCustomProvider;
        });

        $resolved = $resolver->resolve($ketNoi);
        $this->assertSame($mockCustomProvider, $resolved);
    }

    public function test_throws_exception_with_clear_guidance_for_unregistered_driver(): void
    {
        $ncc = new NhaCungCap(['ma_ncc' => 'UNKNOWN_PROVIDER']);
        $ketNoi = new KetNoiNhaCungCap([
            'driver' => 'unknown_provider',
            'ten_ket_noi' => 'Unknown Conn',
        ]);
        $ketNoi->setRelation('nhaCungCap', $ncc);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Chưa có adapter cho nhà cung cấp/driver 'unknown_provider'");

        $resolver = new NhaCungCapResolver();
        $resolver->resolve($ketNoi);
    }
}
