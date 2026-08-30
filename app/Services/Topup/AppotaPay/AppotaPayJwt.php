<?php

namespace App\Services\Topup\AppotaPay;

final class AppotaPayJwt
{
    public function tao(string $partnerCode, string $apiKey, string $secretKey, ?int $now = null, int $ttl = 300): string
    {
        $now ??= time();
        $header = ['typ' => 'JWT', 'alg' => 'HS256', 'cty' => 'appotapay-api;v=1'];
        $payload = ['iss' => $partnerCode, 'jti' => $apiKey.'-'.$now, 'api_key' => $apiKey, 'exp' => $now + $ttl];
        $segments = [$this->encode($header), $this->encode($payload)];
        $segments[] = $this->base64Url(hash_hmac('sha256', implode('.', $segments), $secretKey, true));

        return implode('.', $segments);
    }

    private function encode(array $value): string
    {
        return $this->base64Url(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
