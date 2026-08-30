<?php

namespace App\Services\Topup;

final class DuLieuNhayCam
{
    private const KEYS = ['authorization', 'x-appotapay-auth', 'api_key', 'apikey', 'secret_key', 'secretkey', 'jwt', 'password', 'private_key'];

    public static function che(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::KEYS, true) || str_contains(strtolower((string) $key), 'secret')) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = self::che($value);
            }
        }

        return $data;
    }
}
