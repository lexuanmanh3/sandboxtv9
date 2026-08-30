<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thêm các HTTP security headers vào mọi response web.
 * Ngăn clickjacking (X-Frame-Options), MIME sniffing, và thông lộ thông tin server.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Ngăn MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Ngăn clickjacking (cho phép frame cùng origin)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Kiểm soát referer header
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Tắt các API browser không cần thiết
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');

        // HSTS: chỉ bật khi dùng HTTPS (production)
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Ẩn thông tin server technology
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}