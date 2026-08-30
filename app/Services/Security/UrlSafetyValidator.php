<?php

namespace App\Services\Security;

use InvalidArgumentException;

/**
 * Validator ngăn chặn SSRF (Server-Side Request Forgery).
 * Chặn kết nối tới các dải IP nội bộ, loopback, cloud metadata và các scheme không an toàn.
 */
class UrlSafetyValidator
{
    /**
     * Dải IP bị cấm (Private IPv4 & Loopback & Link-local).
     */
    private const FORBIDDEN_IPV4_PATTERNS = [
        '/^127\./',                         // 127.0.0.0/8 (Loopback)
        '/^10\./',                          // 10.0.0.0/8 (Private Class A)
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // 172.16.0.0/12 (Private Class B)
        '/^192\.168\./',                     // 192.168.0.0/16 (Private Class C)
        '/^169\.254\./',                     // 169.254.0.0/16 (Link-local / AWS/GCP Metadata 169.254.169.254)
        '/^0\./',                           // 0.0.0.0/8
        '/^100\.(6[4-9]|[7-9][0-9]|1[0-1][0-9]|12[0-7])\./', // 100.64.0.0/10 (CGNAT)
        '/^198\.18\./',                      // 198.18.0.0/15
    ];

    /**
     * Kiểm tra tính an toàn của một URL trước khi gửi HTTP request.
     */
    public static function validate(string $url, bool $allowHttp = false): bool
    {
        $parsed = parse_url(trim($url));
        if ($parsed === false || !isset($parsed['host'])) {
            throw new InvalidArgumentException('URL không hợp lệ hoặc thiếu host.');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        $allowedSchemes = $allowHttp ? ['http', 'https'] : ['https'];
        if (!in_array($scheme, $allowedSchemes, true)) {
            throw new InvalidArgumentException("Giao thức không an toàn: '{$scheme}'. Chỉ chấp nhận HTTPS.");
        }

        $host = strtolower($parsed['host']);

        // Chặn localhost, local domain
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            if (!app()->environment('local', 'testing')) {
                throw new InvalidArgumentException("Không được phép kết nối tới host nội bộ: {$host}");
            }
        }

        // Phân giải IP của host nếu không ở local
        if (!app()->environment('local', 'testing')) {
            $ips = @dns_get_record($host, DNS_A);
            if (!empty($ips)) {
                foreach ($ips as $record) {
                    $ip = $record['ip'] ?? null;
                    if ($ip && self::isPrivateIp($ip)) {
                        throw new InvalidArgumentException("Host '{$host}' phân giải ra IP nội bộ ({$ip}) — Bị chặn vì lý do bảo mật (SSRF).");
                    }
                }
            }
        }

        return true;
    }

    /**
     * Kiểm tra một IP có thuộc dải Private / Loopback / Metadata không.
     */
    public static function isPrivateIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        foreach (self::FORBIDDEN_IPV4_PATTERNS as $pattern) {
            if (preg_match($pattern, $ip)) {
                return true;
            }
        }

        return false;
    }
}
