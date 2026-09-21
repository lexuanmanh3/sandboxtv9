<?php

namespace App\Http\Middleware;

use App\Models\B2bIpRejection;
use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Services\Alert\TelegramAlertService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPartnerIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $cauHinh = $request->attributes->get('b2b_config');

        if (!$cauHinh) {
            $clientId = $request->header('X-Client-Id');
            if ($clientId) {
                $cauHinh = CauHinhApiDaiLy::where('client_id', $clientId)->first();
            }
        }

        $allowedIps = ($cauHinh && !empty($cauHinh->danh_sach_ip_ket_noi))
            ? array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string) $cauHinh->danh_sach_ip_ket_noi)))
            : [];

        // Chính sách an toàn: Trong môi trường Production, danh sách IP rỗng tuyệt đối không được mở cửa tự do
        if (empty($allowedIps)) {
            if (app()->environment('production')) {
                $this->logRejection($request, 'IP_ALLOWLIST_NOT_CONFIGURED', $cauHinh);

                return response()->json([
                    'success' => false,
                    'error_code' => 'IP_ALLOWLIST_NOT_CONFIGURED',
                    'message' => 'Đại lý chưa được cấu hình IP Whitelist. Yêu cầu liên hệ TV9Tech để đăng ký IP kết nối.',
                ], Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        }

        $clientIp = $request->ip();

        // Kiểm tra xem IP client có nằm trong danh sách được phép không
        $matched = false;
        foreach ($allowedIps as $allowedIp) {
            if ($clientIp === $allowedIp) {
                $matched = true;
                break;
            }

            // Hỗ trợ dải CIDR (nếu có dấu /)
            if (str_contains($allowedIp, '/') && $this->ipInCidr($clientIp, $allowedIp)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $this->logRejection($request, 'IP_NOT_ALLOWED', $cauHinh);

            return response()->json([
                'success' => false,
                'error_code' => 'IP_NOT_ALLOWED',
                'message' => "Địa chỉ IP ({$clientIp}) của bạn không nằm trong danh sách IP được phép kết nối.",
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($subnet, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int) $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Ghi audit log khi reseller bị IP allowlist từ chối.
     *
     * Ba tác vụ (mỗi cái đều best-effort — lỗi log không được làm hỏng response 403):
     *   1. Insert bản ghi vào b2b_ip_rejections để admin tra cứu.
     *   2. Ghi Log::warning để có audit trail trong storage/logs.
     *   3. Gửi Telegram cảnh báo cho admin (kèm link trang xử lý).
     */
    private function logRejection(Request $request, string $errorCode, ?CauHinhApiDaiLy $cauHinh): void
    {
        try {
            // Trường hợp IP_ALLOWLIST_NOT_CONFIGURED có thể chưa lookup được partner
            // vì middleware chạy trước b2b.hmac. Tự lookup lại theo X-Client-Id.
            $daiLyApiId = $cauHinh?->dai_ly_api_id;
            if (!$daiLyApiId) {
                $clientId = $request->header('X-Client-Id');
                if ($clientId) {
                    $resolved = CauHinhApiDaiLy::where('client_id', $clientId)->first();
                    $daiLyApiId = $resolved?->dai_ly_api_id;
                    if ($daiLyApiId) {
                        $cauHinh = $resolved;
                    }
                }
            }
            if (!$daiLyApiId) {
                // Không xác định được partner — bỏ qua insert để tránh row rác.
                return;
            }

            $clientIp = $request->ip();
            $endpoint = $request->method() . ' ' . '/' . ltrim($request->path(), '/');

            B2bIpRejection::create([
                'dai_ly_api_id' => $daiLyApiId,
                'client_id' => $request->header('X-Client-Id'),
                'ip_address' => $clientIp,
                'endpoint' => substr($endpoint, 0, 255),
                'method' => $request->method(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'error_code' => $errorCode,
                'request_headers_json' => [
                    'X-Client-Id' => $request->header('X-Client-Id'),
                    'X-Timestamp' => $request->header('X-Timestamp'),
                ],
                'attempted_at' => now(),
            ]);

            Log::warning('B2B IP allowlist rejected', [
                'dai_ly_api_id' => $daiLyApiId,
                'client_id' => $request->header('X-Client-Id'),
                'ip' => $clientIp,
                'error_code' => $errorCode,
                'endpoint' => $endpoint,
            ]);

            // Telegram cảnh báo — bọc try riêng để lỗi alert không làm hỏng middleware.
            try {
                $partner = DaiLyApi::find($daiLyApiId);
                if ($partner) {
                    app(TelegramAlertService::class)->alertIpRejected(
                        $partner,
                        $clientIp,
                        $request->method(),
                        $endpoint
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('VerifyPartnerIpAllowlist: Telegram alert failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            // Tuyệt đối không để logging làm hỏng response 403.
            Log::error('VerifyPartnerIpAllowlist: failed to log rejection: ' . $e->getMessage());
        }
    }
}
