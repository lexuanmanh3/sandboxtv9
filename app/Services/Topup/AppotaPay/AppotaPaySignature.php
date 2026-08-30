<?php

namespace App\Services\Topup\AppotaPay;

final class AppotaPaySignature
{
    public function charging(array $data, string $secret): string
    {
        return $this->sign($data, ['partnerRefId', 'phoneNumber', 'productCode', 'telco', 'telcoServiceType'], $secret);
    }

    public function verifyChargingResponse(array $response, string $secret): bool
    {
        return $this->verify($response, ['amount', 'appotapayTransId', 'errorCode', 'phoneNumber', 'productCode', 'time', 'topupAmount'], $secret);
    }

    public function verifyStatusResponse(array $response, string $secret): bool
    {
        return $this->verify($response, ['amount', 'appotapayTransId', 'errorCode', 'phoneNumber', 'time', 'topupAmount'], $secret);
    }

    private function verify(array $response, array $fields, string $secret): bool
    {
        $signature = (string) ($response['signature'] ?? '');
        $transaction = (array) ($response['transaction'] ?? []);
        $data = array_merge($transaction, ['errorCode' => $response['errorCode'] ?? null]);
        if ($signature === '' || collect($fields)->contains(fn ($field) => !array_key_exists($field, $data))) {
            return false;
        }

        return hash_equals($this->sign($data, $fields, $secret), $signature);
    }

    private function sign(array $data, array $fields, string $secret): string
    {
        sort($fields, SORT_STRING);
        $raw = implode('&', array_map(fn ($field) => $field.'='.(string) ($data[$field] ?? ''), $fields));

        return hash_hmac('sha256', $raw, $secret);
    }
}
