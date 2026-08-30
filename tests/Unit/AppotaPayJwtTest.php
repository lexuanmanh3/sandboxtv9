<?php

namespace Tests\Unit;

use App\Services\Topup\AppotaPay\AppotaPayJwt;
use Tests\TestCase;

class AppotaPayJwtTest extends TestCase
{
    public function test_tao_jwt_dung_header_payload_va_chu_ky(): void
    {
        $token = app(AppotaPayJwt::class)->tao('PARTNER', 'APIKEY', 'SECRET', 1700000000, 300);
        [$header, $payload, $signature] = explode('.', $token);
        $decode = fn ($value) => json_decode(base64_decode(strtr($value, '-_', '+/')), true);

        $this->assertSame(['typ' => 'JWT', 'alg' => 'HS256', 'cty' => 'appotapay-api;v=1'], $decode($header));
        $this->assertSame('PARTNER', $decode($payload)['iss']);
        $this->assertSame('APIKEY-1700000000', $decode($payload)['jti']);
        $this->assertSame(1700000300, $decode($payload)['exp']);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $header.'.'.$payload, 'SECRET', true)), '+/', '-_'), '=');
        $this->assertSame($expected, $signature);
    }
}
