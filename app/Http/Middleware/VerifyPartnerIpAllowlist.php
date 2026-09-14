<?php

namespace App\Http\Middleware;

use App\Models\CauHinhApiDaiLy;
use Closure;
use Illuminate\Http\Request;
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

        if ($cauHinh && !empty($cauHinh->danh_sach_ip_ket_noi)) {
            $allowedIps = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string) $cauHinh->danh_sach_ip_ket_noi)));

            if (!empty($allowedIps)) {
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
                    return response()->json([
                        'success' => false,
                        'error_code' => 'IP_NOT_ALLOWED',
                        'message' => "Địa chỉ IP ({$clientIp}) của bạn không nằm trong danh sách IP được phép kết nối.",
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int) $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
