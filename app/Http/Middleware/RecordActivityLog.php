<?php

namespace App\Http\Middleware;

use App\Models\NhatKyKiemTra;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordActivityLog
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token',
        'refresh_token', 'remember_token', 'cookie', 'secret', 'secret_key',
        'api_key', 'api_password', 'pin', 'card_pin', 'otp',
    ];

    /**
     * Tự động ghi lại hoạt động truy cập và thao tác trong Admin & API.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $this->recordLog($request, null, $durationMs, $e);
            throw $e;
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $this->recordLog($request, $response, $durationMs);

        return $response;
    }

    private function recordLog(Request $request, ?Response $response, int $durationMs, ?Throwable $exception = null): void
    {
        // Bỏ qua các file tĩnh hoặc request polling log liên tục
        $path = $request->path();
        if (
            str_starts_with($path, 'backend/') ||
            str_starts_with($path, 'build/') ||
            str_starts_with($path, 'vendor/') ||
            str_contains($path, 'favicon.ico') ||
            $request->is('admin/maintenance/logs/live')
        ) {
            return;
        }

        try {
            $route = $request->route();
            $dichVu = 'UnknownController';
            $hoatDong = $request->method();

            if ($route) {
                $action = $route->getAction();
                if (isset($action['controller'])) {
                    $parts = explode('@', class_basename($action['controller']));
                    $dichVu = $parts[0] ?? 'UnknownController';
                    $hoatDong = ucfirst($parts[1] ?? $request->method());
                } elseif ($route->getName()) {
                    $dichVu = $route->getName();
                }
            }

            $statusCode = $response?->getStatusCode() ?? 500;
            $status = ($statusCode < 400 && !$exception) ? 'thanh_cong' : 'loi';

            $user = Auth::user();
            $userName = $user ? ($user->name ?? $user->email ?? 'backend') : ($request->is('api/*') ? 'api_client' : 'guest');

            $params = $this->sanitize($request->except(['_token']));
            $exceptionTrace = null;

            if ($exception) {
                $exceptionTrace = $exception->getMessage() . "\n\n" . $exception->getTraceAsString();
            } elseif ($statusCode >= 400 && $response) {
                $content = $response->getContent();
                if ($content && strlen($content) < 5000) {
                    $exceptionTrace = "HTTP {$statusCode}: " . $content;
                }
            }

            NhatKyKiemTra::create([
                'nguoi_thuc_hien_id' => $user?->id,
                'hanh_dong' => "{$dichVu}@{$hoatDong}",
                'dich_vu' => $dichVu,
                'hoat_dong' => $hoatDong,
                'thoi_gian_thuc_thi_ms' => $durationMs,
                'ip' => $request->ip() ?: '127.0.0.1',
                'user_agent' => $request->userAgent() ?: 'Unknown User-Agent',
                'khach_hang' => $userName,
                'route' => optional($route)->getName() ?: $path,
                'trang_thai' => $status,
                'tham_so' => !empty($params) ? $params : null,
                'thong_tin' => "{$request->method()} /{$path} - {$statusCode} ({$durationMs}ms)",
                'loi_ngoai_le' => $exceptionTrace,
            ]);
        } catch (Throwable $e) {
            // Không bao giờ để lỗi ghi log làm sập luồng chính
            Log::debug('RecordActivityLog error: ' . $e->getMessage());
        }
    }

    private function sanitize(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitize($value);
            } else {
                $result[$key] = is_string($value) && strlen($value) > 500 ? substr($value, 0, 500) . '...' : $value;
            }
        }

        return $result;
    }
}
