<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class B2bThrottleRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->attributes->get('b2b_partner');
        $cauHinh = $request->attributes->get('b2b_config');

        $clientId = $request->header('X-Client-Id') ?? ($partner ? $partner->ma_dai_ly_api : 'global');
        $limit = $cauHinh ? (int) $cauHinh->rate_limit_per_minute : 60;
        if ($limit <= 0) {
            $limit = 60;
        }

        $rateLimitKey = 'b2b_throttle:' . $clientId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

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

        RateLimiter::hit($rateLimitKey, 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($rateLimitKey, $limit));

        return $response;
    }
}
