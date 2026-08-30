<?php

namespace Tests\Unit;

use App\Services\Topup\AppotaPay\AppotaPaySignature;
use PHPUnit\Framework\TestCase;

class AppotaPaySignatureTest extends TestCase
{
    public function test_charging_signature_dung_vi_du_chinh_thuc(): void
    {
        $data = ['partnerRefId' => 'AB123', 'telco' => 'viettel', 'telcoServiceType' => 'prepaid',
            'phoneNumber' => '0866123456', 'productCode' => 'viettel_10'];
        $expected = hash_hmac('sha256', 'partnerRefId=AB123&phoneNumber=0866123456&productCode=viettel_10&telco=viettel&telcoServiceType=prepaid', 'SECRET');
        $this->assertSame($expected, (new AppotaPaySignature())->charging($data, 'SECRET'));
    }

    public function test_charging_va_status_xac_minh_theo_hai_tap_field_khac_nhau(): void
    {
        $service = new AppotaPaySignature();
        $transaction = ['amount' => 10000, 'appotapayTransId' => 'APT1', 'topupAmount' => 10000,
            'phoneNumber' => '0866123456', 'productCode' => 'viettel_10', 'time' => '2026-08-12T10:00:00+07:00'];
        $chargingRaw = 'amount=10000&appotapayTransId=APT1&errorCode=0&phoneNumber=0866123456&productCode=viettel_10&time=2026-08-12T10:00:00+07:00&topupAmount=10000';
        $response = ['errorCode' => 0, 'transaction' => $transaction, 'signature' => hash_hmac('sha256', $chargingRaw, 'SECRET')];
        $this->assertTrue($service->verifyChargingResponse($response, 'SECRET'));
        $this->assertFalse($service->verifyStatusResponse($response, 'SECRET'));
    }

    public function test_chu_ky_sai_khong_hop_le(): void
    {
        $this->assertFalse((new AppotaPaySignature())->verifyChargingResponse([
            'errorCode' => 0, 'transaction' => [], 'signature' => 'invalid',
        ], 'SECRET'));
    }
}
