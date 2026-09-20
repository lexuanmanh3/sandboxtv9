<?php

namespace App\Services\Security;

use InvalidArgumentException;

/**
 * Validator ngăn chặn SSRF (Server-Side Request Forgery).
 * Chặn kết nối tới các dải IP nội bộ, loopback, cloud metadata và các scheme không an toàn.
 *
 * Ba lỗ hổng đã được bịt (trước đây chỉ chặn được hostname phân giải qua DNS_A):
 *  1. IP literal (ví dụ https://10.0.0.5/, https://169.254.169.254/) — trước đây lọt vì
 *     dns_get_record() trả về rỗng cho một IP literal, nên vòng kiểm tra IP không bao giờ chạy.
 *  2. IPv6 literal — parse_url() trả host kèm dấu ngoặc vuông ("[::1]") nên không khớp
 *     danh sách đen, và cũng không có bản ghi DNS_A nào để kiểm tra.
 *  3. Hostname chỉ có bản ghi AAAA — trước đây chỉ hỏi DNS_A nên bỏ sót hoàn toàn.
 *
 * RỦI RO CÒN LẠI (đã ghi nhận, chưa xử lý được ở tầng này): DNS rebinding. Giữa lúc
 * validator phân giải tên miền và lúc HTTP client thực sự kết nối, kẻ tấn công có thể
 * đổi bản ghi DNS sang IP nội bộ. Muốn triệt để phải ghim IP đã kiểm tra vào lúc kết nối
 * (ví dụ CURLOPT_RESOLVE / pinning ở tầng HTTP client), không thể giải quyết chỉ bằng
 * kiểm tra trước khi gọi.
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
     * Host nội bộ bị cấm theo tên.
     */
    private const FORBIDDEN_HOSTS = ['localhost', '127.0.0.1', '::1', '0.0.0.0', '::'];

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

        // parse_url() giữ nguyên dấu ngoặc vuông của IPv6 literal ("[::1]") — bỏ ra để
        // so khớp và để filter_var() nhận đúng là địa chỉ IPv6.
        $hostBare = trim($host, '[]');

        // Ở local/testing vẫn phải cho phép loopback để kiểm thử webhook nội bộ.
        // Ngoài local/testing, MỌI host nội bộ đều bị chặn — kể cả IP literal và IPv6.
        $choPhepNoiBo = app()->environment('local', 'testing');

        if (self::laHostNoiBo($hostBare) && !$choPhepNoiBo) {
            throw new InvalidArgumentException("Không được phép kết nối tới host nội bộ: {$hostBare}");
        }

        // IP literal: kiểm tra trực tiếp, KHÔNG phụ thuộc DNS.
        // Đây là lỗ hổng cũ: dns_get_record() trả rỗng cho IP literal nên vòng kiểm tra
        // theo DNS không bao giờ chạy, khiến https://10.0.0.5/ lọt qua ở production.
        if (filter_var($hostBare, FILTER_VALIDATE_IP)) {
            if (!$choPhepNoiBo && self::isPrivateIp($hostBare)) {
                throw new InvalidArgumentException("Không được phép kết nối tới IP nội bộ: {$hostBare} — Bị chặn vì lý do bảo mật (SSRF).");
            }

            return true;
        }

        if ($choPhepNoiBo) {
            return true;
        }

        // Hostname: phải hỏi CẢ bản ghi A (IPv4) và AAAA (IPv6).
        // Trước đây chỉ hỏi DNS_A nên tên miền chỉ có AAAA trỏ về IP nội bộ lọt hoàn toàn.
        $ipDaKiemTra = [];

        foreach ([DNS_A, DNS_AAAA] as $loaiBanGhi) {
            $banGhi = @dns_get_record($hostBare, $loaiBanGhi);
            if (empty($banGhi)) {
                continue;
            }

            foreach ($banGhi as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (!$ip || isset($ipDaKiemTra[$ip])) {
                    continue;
                }
                $ipDaKiemTra[$ip] = true;

                if (self::isPrivateIp($ip)) {
                    throw new InvalidArgumentException("Host '{$hostBare}' phân giải ra IP nội bộ ({$ip}) — Bị chặn vì lý do bảo mật (SSRF).");
                }
            }
        }

        return true;
    }

    /**
     * Host theo TÊN thuộc dạng nội bộ (không xét IP literal — phần đó do isPrivateIp lo).
     */
    private static function laHostNoiBo(string $hostBare): bool
    {
        return in_array($hostBare, self::FORBIDDEN_HOSTS, true)
            || str_ends_with($hostBare, '.local')
            || str_ends_with($hostBare, '.internal')
            || str_ends_with($hostBare, '.localhost');
    }

    /**
     * Kiểm tra một IP (IPv4 hoặc IPv6) có thuộc dải Private / Loopback / Metadata không.
     */
    public static function isPrivateIp(string $ip): bool
    {
        $ip = trim($ip, '[]');

        // Địa chỉ IPv6 dạng IPv4-mapped (::ffff:127.0.0.1) không bị FILTER_FLAG_NO_PRIV_RANGE
        // nhận diện, nên phải tách phần IPv4 ra kiểm tra riêng — nếu không sẽ lọt qua.
        if (preg_match('/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $m)) {
            return self::isPrivateIp($m[1]);
        }

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
