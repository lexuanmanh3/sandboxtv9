<?php

namespace App\Http\Middleware;

use App\Models\CauHinhApiDaiLy;
use App\Support\B2bSecurityCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPartnerHmacSignature
{
    public const TIMESTAMP_WINDOW_SECONDS = 300; // 5 phút lệch tối đa
    public const NONCE_TTL_SECONDS = 600;        // 10 phút chống phát lại

    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->header('X-Client-Id');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        // 1. Bắt buộc các header xác thực cốt lõi
        if (!$clientId || !$timestamp || !$nonce || !$signature) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_AUTHENTICATION_HEADERS',
                'message' => 'Thiếu các HTTP header xác thực B2B bắt buộc (X-Client-Id, X-Timestamp, X-Nonce, X-Signature).',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Kiểm tra Timestamp: bắt buộc là số nguyên giây và trong cửa sổ cho phép (+- 300s)
        $now = time();
        if (!ctype_digit((string) $timestamp) || abs($now - (int) $timestamp) > self::TIMESTAMP_WINDOW_SECONDS) {
            return response()->json([
                'success' => false,
                'error_code' => 'REQUEST_EXPIRED',
                'message' => 'Timestamp không hợp lệ hoặc đã lệch quá 5 phút so với máy chủ.',
                'server_time' => $now,
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 3. Kiểm tra Nonce: tối thiểu 16 ký tự, chỉ chứa ký tự chữ/số/gạch nối
        if (!preg_match('/^[A-Za-z0-9_\-]{16,64}$/', (string) $nonce)) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_NONCE_FORMAT',
                'message' => 'Header X-Nonce không hợp lệ (yêu cầu từ 16 đến 64 ký tự chữ, số hoặc gạch dưới/ngang).',
            ], Response::HTTP_BAD_REQUEST);
        }

        // 4. Tra cứu cấu hình đại lý theo Client ID
        $cauHinh = CauHinhApiDaiLy::with('daiLyApi')
            ->where('client_id', $clientId)
            ->first();

        if (!$cauHinh || !$cauHinh->daiLyApi) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_CLIENT_ID',
                'message' => 'Client ID không tồn tại trên hệ thống.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $daiLy = $cauHinh->daiLyApi;

        // 5. Kiểm tra trạng thái hoạt động & Key thu hồi
        if ($daiLy->trang_thai !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'error_code' => 'PARTNER_INACTIVE',
                'message' => 'Tài khoản Đại lý API đang bị tạm khóa hoặc ngừng hoạt động.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Khóa bị thu hồi phải vô hiệu ngay lập tức (không có ngoại lệ)
        if ($cauHinh->key_status === 'revoked') {
            return response()->json([
                'success' => false,
                'error_code' => 'KEY_REVOKED',
                'message' => 'API Key của đại lý đã bị thu hồi.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 6. Xử lý Idempotency-Key
        $method = strtoupper($request->method());
        $idempotencyKey = $request->header('Idempotency-Key')
            ?: $request->header('X-Idempotency-Key')
            ?: '';

        // Với các method thay đổi trạng thái (POST, PUT, DELETE), bắt buộc phải có Idempotency-Key dạng UUID
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            if (empty($idempotencyKey)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'MISSING_IDEMPOTENCY_KEY',
                    'message' => 'Header Idempotency-Key là bắt buộc để đảm bảo an toàn giao dịch.',
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!\Illuminate\Support\Str::isUuid($idempotencyKey)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_IDEMPOTENCY_KEY',
                    'message' => 'Header Idempotency-Key bắt buộc phải là định dạng UUID v4.',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // 7. Chuẩn hóa chuỗi ký Canonical String duy nhất:
        // METHOD\nCANONICAL_PATH_WITH_QUERY\nTIMESTAMP\nNONCE\nIDEMPOTENCY_KEY\nBODY_SHA256
        $path = '/' . ltrim($request->path(), '/');
        $queryParams = $request->query();
        if (!empty($queryParams)) {
            ksort($queryParams);
            $canonicalQuery = http_build_query($queryParams);
            $canonicalUri = "{$path}?{$canonicalQuery}";
        } else {
            $canonicalUri = $path;
        }

        $rawBody = in_array($method, ['GET', 'HEAD']) ? '' : (string) $request->getContent();
        $bodyHash = hash('sha256', $rawBody);

        $stringToSign = "{$method}\n{$canonicalUri}\n{$timestamp}\n{$nonce}\n{$idempotencyKey}\n{$bodyHash}";

        // 8. Tính toán và so sánh chữ ký bằng constant-time hash_equals
        $currentSecret = $cauHinh->secret_key_ma_hoa;
        $expectedSignature = hash_hmac('sha256', $stringToSign, $currentSecret);

        $isSignatureValid = hash_equals($expectedSignature, strtolower($signature));

        // Hỗ trợ Grace Period khi xoay key (Key Rotation)
        if (!$isSignatureValid && $cauHinh->key_status === 'rotating' && $cauHinh->previous_secret_ma_hoa) {
            if ($cauHinh->key_rotated_at && $cauHinh->key_rotated_at->diffInHours(now()) <= 48) {
                $previousExpectedSignature = hash_hmac('sha256', $stringToSign, $cauHinh->previous_secret_ma_hoa);
                if (hash_equals($previousExpectedSignature, strtolower($signature))) {
                    $isSignatureValid = true;
                }
            }
        }

        // XÁC MINH CHỮ KÝ TRƯỚC: Nếu sai chữ ký, từ chối ngay và TUYỆT ĐỐI không claim nonce!
        if (!$isSignatureValid) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_SIGNATURE',
                'message' => 'Chữ ký HMAC không khớp hoặc không hợp lệ.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 9. CHỈ CLAIM NONCE SAU KHI CHỮ KÝ ĐÃ HỢP LỆ (Bảo vệ chống kẻ xấu chiếm nonce của đại lý)
        //
        // Nonce phải được ghi vào cache DÙNG CHUNG giữa các app server. Với cache cục bộ
        // theo máy (file/array), một nonce đã dùng ở server A vẫn qua được ở server B.
        // Xem config/b2b.php và `php artisan b2b:health-check`.
        $nonceCacheKey = "b2b_nonce:{$daiLy->id}:{$nonce}";
        if (!B2bSecurityCache::store()->add($nonceCacheKey, 1, self::NONCE_TTL_SECONDS)) {
            return response()->json([
                'success' => false,
                'error_code' => 'REPLAY_DETECTED',
                'message' => 'Nonce đã được sử dụng trước đó (Replay attack detected).',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 9. Gắn thông tin đại lý và Idempotency-Key vào Request attributes
        $request->attributes->set('b2b_partner', $daiLy);
        $request->attributes->set('b2b_config', $cauHinh);
        $request->attributes->set('b2b_idempotency_key', $idempotencyKey);

        return $next($request);
    }
}
