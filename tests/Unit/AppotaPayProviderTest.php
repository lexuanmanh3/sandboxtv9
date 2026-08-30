<?php

namespace Tests\Unit;

use App\DTOs\YeuCauNapTienDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Models\KetNoiNhaCungCap;
use App\Services\Topup\AppotaPay\AppotaPayErrorMapper;
use App\Services\Topup\AppotaPay\AppotaPayJwt;
use App\Services\Topup\AppotaPay\AppotaPaySignature;
use App\Services\Topup\AppotaPay\AppotaPayTopupProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppotaPayProviderTest extends TestCase
{
    public function test_response_chu_ky_sai_chuyen_pending_va_header_khong_lap_bearer(): void
    {
        Http::fake(['*' => Http::response([
            'errorCode' => 0, 'message' => 'OK', 'transaction' => [
                'amount' => 10000, 'topupAmount' => 10000, 'phoneNumber' => '0866123456',
                'productCode' => 'viettel_10', 'appotapayTransId' => 'APT1', 'time' => '2026-08-12T10:00:00+07:00',
            ], 'signature' => 'sai',
        ])]);
        $connection = new KetNoiNhaCungCap();
        $connection->forceFill(['base_url' => 'https://gateway.dev.appotapay.com', 'partner_code' => 'P',
            'api_key_ma_hoa' => 'K', 'secret_key_ma_hoa' => 'S', 'connect_timeout_seconds' => 1, 'request_timeout_seconds' => 1]);
        $provider = new AppotaPayTopupProvider($connection, new AppotaPayJwt(), new AppotaPaySignature(), new AppotaPayErrorMapper());
        $result = $provider->napTien(new YeuCauNapTienDTO('REF1', '0866123456', 'viettel_10', 'viettel', 'PREPAID'));

        $this->assertSame(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, $result->ketQua);
        $this->assertFalse($result->chuKyHopLe);
        Http::assertSent(fn ($request) => !str_starts_with($request->header('X-APPOTAPAY-AUTH')[0], 'Bearer '));
    }
}
