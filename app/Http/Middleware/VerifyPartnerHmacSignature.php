<?php

namespace App\Http\Middleware;

use App\Models\CauHinhApiDaiLy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyPartnerHmacSignature
{
    public const TIMESTAMP_WINDOW_SECONDS = 300; // 5 phút
    public const NONCE_TTL_SECONDS = 600; // 10 phút

    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->header('X-Client-Id');
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if (!$clientId || !$timestamp || !$nonce || !$signature) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_AUTHENTICATION_HEADERS',
                'message' => 'Thiếu các HTTP header xác thực B2B bắt buộc (X-Client-Id, X-Timestamp, X-Nonce, X-Signature).',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 1. Kiểm tra timestamp trong cửa sổ cho phép (+- 300s)
        $now = time();
        if (!is_numeric($timestamp) || abs($now - (int) $timestamp) > self::TIMESTAMP_WINDOW_SECONDS) {
            return response()->json([
                'success' => false,
                'error_code' => 'TIMESTAMP_EXPIRED_OR_INVALID',
                'message' => 'Timestamp không hợp lệ hoặc đã lệch quá 5 phút so với máy chủ.',
                'server_time' => $now,
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Tra cứu cấu hình đối tác theo Client ID
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

        // 3. Kiểm tra trạng thái hoạt động của đối tác & Key
        if ($daiLy->trang_thai !== 'hoat_dong') {
            return response()->json([
                'success' => false,
                'error_code' => 'PARTNER_INACTIVE',
                'message' => 'Tài khoản Đại lý API đang bị tạm khóa hoặc ngừng hoạt động.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($cauHinh->key_status === 'revoked') {
            return response()->json([
                'success' => false,
                'error_code' => 'KEY_REVOKED',
                'message' => 'API Key của đại lý đã bị thu hồi.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 4. Kiểm tra Nonce nguyên tử chống tấn công Replay
        $nonceCacheKey = "b2b_nonce:{$daiLy->id}:{$nonce}";
        if (!Cache::add($nonceCacheKey, 1, self::NONCE_TTL_SECONDS)) {
            return response()->json([
                'success' => false,
                'error_code' => 'DUPLICATE_NONCE',
                'message' => 'Nonce đã được sử dụng trước đó (Replay attack detected).',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 5. Chuẩn hóa chuỗi ký Canonical String
        // METHOD\nPATH_WITH_QUERY\nTIMESTAMP\nNONCE\nBODY_SHA256
        $method = strtoupper($request->method());
        
        // Chuẩn hóa path và query
        $path = '/' . ltrim($request->path(), '/');
        $queryString = $request->getQueryString();
        $canonicalUri = $queryString ? "{$path}?{$queryString}" : $path;

        $rawBody = in_array($method, ['GET', 'HEAD']) ? '' : (string) $request->getContent();
        $bodyHash = hash('sha256', $rawBody);

        $stringToSign = "{$method}\n{$canonicalUri}\n{$timestamp}\n{$nonce}\n{$bodyHash}";

        // 6. Tính toán và so sánh chữ ký bằng constant-time hash_equals
        $currentSecret = $cauHinh->secret_key_ma_hoa;
        $expectedSignature = hash_hmac('sha256', $stringToSign, $currentSecret);

        $isSignatureValid = hash_equals($expectedSignature, strtolower($signature));

        // Hỗ trợ Grace Period khi xoay key (Key Rotation)
        if (!$isSignatureValid && $cauHinh->key_status === 'rotating' && $cauHinh->previous_secret_ma_hoa) {
            // Kiểm tra xem thời gian xoay có trong vòng 48h không
            if ($cauHinh->key_rotated_at && $cauHinh->key_rotated_at->diffInHours(now()) <= 48) {
                $previousExpectedSignature = hash_hmac('sha256', $stringToSign, $cauHinh->previous_secret_ma_hoa);
                if (hash_equals($previousExpectedSignature, strtolower($signature))) {
                    $isSignatureValid = true;
                }
            }
        }

        if (!$isSignatureValid) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_SIGNATURE',
                'message' => 'Chữ ký HMAC không khớp hoặc không hợp lệ.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 7. Gắn thông tin đại lý vào Request attributes để controller sử dụng
        $request->attributes->set('b2b_partner', $daiLy);
        $request->attributes->set('b2b_config', $cauHinh);

        return $next($request);
    }
}
