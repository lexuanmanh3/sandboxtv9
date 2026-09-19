<?php

namespace App\Http\Middleware;

use App\Support\B2bSecurityCache;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Giới hạn tần suất B2B API theo hai tầng:
 *
 *  - `b2b.throttle:ip`      — chạy TRƯỚC xác thực, khóa theo IP thật của client.
 *                             Đây là lớp chặn flood duy nhất hoạt động được khi
 *                             request chưa được xác thực.
 *  - `b2b.throttle:partner` — chạy SAU xác thực, khóa theo ID đại lý đã xác minh.
 *
 * Trước đây chỉ có một tầng khóa theo header X-Client-Id: kẻ tấn công chỉ cần đổi
 * header này mỗi request là tạo ra vô số "đại lý" mới và không bao giờ bị giới hạn.
 */
class B2bThrottleRequests
{
    public const MODE_IP = 'ip';
    public const MODE_PARTNER = 'partner';

    public function handle(Request $request, Closure $next, string $mode = self::MODE_PARTNER): Response
    {
        return $mode === self::MODE_IP
            ? $this->throttleByIp($request, $next)
            : $this->throttleByPartner($request, $next);
    }

    private function throttleByIp(Request $request, Closure $next): Response
    {
        $limit = max(1, (int) config('b2b.ip_rate_limit_per_minute', 300));

        return $this->applyLimit($request, $next, 'b2b_throttle_ip:' . $request->ip(), $limit);
    }

    private function throttleByPartner(Request $request, Closure $next): Response
    {
        $partner = $request->attributes->get('b2b_partner');
        $cauHinh = $request->attributes->get('b2b_config');

        // Khóa theo ID đại lý đã xác thực (không theo header) để không gian khóa luôn hữu hạn.
        $key = $partner ? 'b2b_throttle_partner:' . $partner->id : 'b2b_throttle_anon';

        $limit = $cauHinh ? (int) $cauHinh->rate_limit_per_minute : 60;
        if ($limit <= 0) {
            $limit = 60;
        }

        return $this->applyLimit($request, $next, $key, $limit);
    }

    private function applyLimit(Request $request, Closure $next, string $key, int $limit): Response
    {
        $limiter = new RateLimiter(B2bSecurityCache::store());

        if ($limiter->tooManyAttempts($key, $limit)) {
            $seconds = $limiter->availableIn($key);

            return response()->json([
                'success' => false,
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'message' => "Bạn đã gửi quá giới hạn yêu cầu cho phép ({$limit} req/phút). Vui lòng thử lại sau {$seconds} giây.",
                'retry_after_seconds' => $seconds,
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $limiter->hit($key, 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $limiter->remaining($key, $limit));

        return $response;
    }
}
