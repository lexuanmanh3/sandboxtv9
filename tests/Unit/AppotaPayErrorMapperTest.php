<?php

namespace Tests\Unit;

use App\Enums\KetQuaNhaCungCap;
use App\Services\Topup\AppotaPay\AppotaPayErrorMapper;
use PHPUnit\Framework\TestCase;

class AppotaPayErrorMapperTest extends TestCase
{
    public function test_ma_pending_khong_bi_coi_la_that_bai_cuoi(): void
    {
        $mapper = new AppotaPayErrorMapper();
        foreach (['34', '35', '99'] as $code) {
            $this->assertSame(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, $mapper->map($code));
        }
        $this->assertSame(KetQuaNhaCungCap::SUCCESS, $mapper->map('0'));
        $this->assertSame(KetQuaNhaCungCap::DEFINITIVE_FAILURE, $mapper->map('40'));
    }
}
