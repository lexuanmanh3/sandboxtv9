<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kiểm tra hình dạng request B2B trước khi vào nghiệp vụ:
 *  - Content-Type phải là application/json với các method có body;
 *  - body không vượt quá giới hạn cấu hình;
 *  - body phải là JSON object hợp lệ;
 *  - không chấp nhận field lạ (tránh hợp đồng ngầm ngoài tài liệu);
 *  - không chấp nhận gửi đồng thời `account` và `phone_number` với giá trị khác nhau.
 *
 * Chạy SAU xác thực HMAC: đây là ràng buộc trên hợp đồng của đối tác đã xác minh.
 */
class ValidateB2bRequestShape
{
    /** Field hợp lệ của POST /orders theo tài liệu tích hợp. */
    public const ALLOWED_ORDER_FIELDS = [
        'partner_order_id',
        'product_code',
        'account',
        'phone_number',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $method = strtoupper($request->method());

        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $contentType = (string) $request->header('Content-Type', '');
        $mediaType = strtolower(trim(explode(';', $contentType)[0]));

        if ($mediaType !== 'application/json') {
            return $this->loi(
                'UNSUPPORTED_MEDIA_TYPE',
                'Content-Type phải là application/json (nhận được: ' . ($mediaType !== '' ? $mediaType : 'không có') . ').',
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE
            );
        }

        $rawBody = (string) $request->getContent();
        $maxBytes = max(1, (int) config('b2b.max_body_bytes', 65536));

        if (strlen($rawBody) > $maxBytes) {
            return $this->loi(
                'PAYLOAD_TOO_LARGE',
                "Kích thước body vượt quá giới hạn cho phép ({$maxBytes} byte).",
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE
            );
        }

        $decoded = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->loi(
                'INVALID_JSON_BODY',
                'Body không phải là JSON object hợp lệ.',
                Response::HTTP_BAD_REQUEST
            );
        }

        // JSON object phải là map, không phải mảng chỉ số
        if ($decoded !== [] && array_is_list($decoded)) {
            return $this->loi(
                'INVALID_JSON_BODY',
                'Body phải là JSON object, không phải mảng.',
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($this->laTaoDonHang($request)) {
            $unknown = array_diff(array_keys($decoded), self::ALLOWED_ORDER_FIELDS);
            if (!empty($unknown)) {
                return $this->loi(
                    'UNKNOWN_FIELD',
                    'Body chứa field không được hỗ trợ: ' . implode(', ', $unknown) . '. Field hợp lệ: ' . implode(', ', self::ALLOWED_ORDER_FIELDS) . '.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            // `phone_number` là alias legacy của `account`. Gửi cả hai với giá trị khác nhau
            // là mơ hồ: không được tự chọn một giá trị rồi âm thầm bỏ qua giá trị còn lại.
            if (array_key_exists('account', $decoded) && array_key_exists('phone_number', $decoded)) {
                if (trim((string) $decoded['account']) !== trim((string) $decoded['phone_number'])) {
                    return $this->loi(
                        'AMBIGUOUS_ACCOUNT_FIELD',
                        "Gửi đồng thời 'account' và 'phone_number' với giá trị khác nhau là không hợp lệ. Vui lòng chỉ dùng 'account'.",
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }
        }

        return $next($request);
    }

    private function laTaoDonHang(Request $request): bool
    {
        return strtoupper($request->method()) === 'POST'
            && trim($request->path(), '/') === 'api/b2b/v1/orders';
    }

    private function loi(string $errorCode, string $message, int $httpStatus): Response
    {
        return response()->json([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ], $httpStatus);
    }
}
