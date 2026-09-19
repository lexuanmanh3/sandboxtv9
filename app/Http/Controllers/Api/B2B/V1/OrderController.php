<?php

namespace App\Http\Controllers\Api\B2B\V1;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use App\Services\DaiLyApi\B2bOrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected B2bOrderService $orderService
    ) {}

    /**
     * Tạo đơn nạp tiền B2B chuẩn hóa.
     * Endpoint: POST /api/b2b/v1/orders
     */
    public function create(Request $request): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');

        $idempotencyKey = $request->attributes->get('b2b_idempotency_key')
            ?: $request->header('Idempotency-Key')
            ?: $request->header('X-Idempotency-Key');

        if (!$idempotencyKey) {
            return response()->json([
                'success' => false,
                'error_code' => 'MISSING_IDEMPOTENCY_KEY',
                'message' => 'Header Idempotency-Key là bắt buộc để đảm bảo an toàn giao dịch.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->orderService->taoDonHang(
                $partner,
                $request->all(),
                (string) $idempotencyKey,
                (string) $request->getContent()
            );

            $headers = [];
            if (!empty($result['is_replay'])) {
                $headers['X-Cache'] = 'HIT';
            }

            return response()->json($result['data'], $result['http_code'], $headers);
        } catch (DomainException $de) {
            $httpCode = $de->getCode() ?: Response::HTTP_UNPROCESSABLE_ENTITY;
            $msg = $de->getMessage();

            // Phân loại mã lỗi API công khai chính xác
            $errorCode = match (true) {
                $httpCode === 409 && str_contains($msg, 'Idempotency-Key') => 'IDEMPOTENCY_CONFLICT',
                $httpCode === 409 && str_contains($msg, 'Mã đơn đối tác') => 'DUPLICATE_ORDER',
                $httpCode === 404 => 'INVALID_PRODUCT',
                $httpCode === 403 && str_contains($msg, 'loại trừ') => 'PRODUCT_EXCLUDED',
                $httpCode === 403 => 'PRODUCT_UNAUTHORIZED',
                $httpCode === 422 && str_contains($msg, 'hạn mức') => 'INSUFFICIENT_CREDIT',
                $httpCode === 422 && str_contains($msg, 'định dạng') => 'INVALID_REQUEST',
                default => 'ORDER_CREATION_FAILED',
            };

            return response()->json([
                'success' => false,
                'error_code' => $errorCode,
                'message' => $msg,
            ], $httpCode);
        } catch (\Throwable $e) {
            $requestId = (string) Str::uuid();

            Log::error("B2B Order Creation Exception [Req: {$requestId}]: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'partner_id' => $partner->id,
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Lỗi xử lý nội bộ hệ thống khi tiếp nhận đơn.',
                'request_id' => $requestId,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Tra cứu chi tiết đơn hàng theo ID nội bộ (Scope cứng theo đại lý).
     * Endpoint: GET /api/b2b/v1/orders/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');

        $donHang = DonHang::where('id', $id)
            ->where('dai_ly_api_id', $partner->id)
            ->first();

        if (!$donHang) {
            return response()->json([
                'success' => false,
                'error_code' => 'ORDER_NOT_FOUND',
                'message' => "Không tìm thấy đơn hàng #{$id}.",
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatOrder($donHang),
        ]);
    }

    /**
     * Tra cứu danh sách đơn hàng hoặc theo mã đơn đối tác (Scope cứng theo đại lý).
     * Endpoint: GET /api/b2b/v1/orders
     */
    public function query(Request $request): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');

        $partnerOrderId = $request->query('partner_order_id');

        $query = DonHang::where('dai_ly_api_id', $partner->id);

        if ($partnerOrderId) {
            $donHang = $query->where('ma_don_doi_tac', $partnerOrderId)->first();
            if (!$donHang) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'ORDER_NOT_FOUND',
                    'message' => "Không tìm thấy đơn hàng với partner_order_id '{$partnerOrderId}'.",
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatOrder($donHang),
            ]);
        }

        // Xác thực và lọc theo khoảng thời gian nếu có.
        // Bắt buộc định dạng ngày tường minh: strtotime() một mình chấp nhận cả
        // 'now', 'tomorrow', '+1 week'... khiến hợp đồng API không xác định.
        $fromDate = $this->docNgay($request, 'from_date');
        if ($fromDate instanceof JsonResponse) {
            return $fromDate;
        }

        $toDate = $this->docNgay($request, 'to_date');
        if ($toDate instanceof JsonResponse) {
            return $toDate;
        }

        if ($fromDate && $toDate && $fromDate->gt($toDate)) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_DATE_RANGE',
                'message' => "Tham số 'from_date' không được lớn hơn 'to_date'.",
            ], Response::HTTP_BAD_REQUEST);
        }

        // Chặn truy vấn quét toàn bộ bảng (from_date=1970-01-01) làm nghẽn hệ thống
        $maxRangeDays = max(1, (int) config('b2b.max_query_range_days', 31));
        if ($fromDate && $toDate && $fromDate->diffInDays($toDate) > $maxRangeDays) {
            return response()->json([
                'success' => false,
                'error_code' => 'DATE_RANGE_TOO_WIDE',
                'message' => "Khoảng thời gian tra cứu tối đa là {$maxRangeDays} ngày. Vui lòng thu hẹp khoảng tra cứu.",
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate->copy()->startOfDay());
        }

        if ($toDate) {
            // Bao trọn ngày kết thúc, nếu không đơn tạo lúc 14:00 ngày to_date sẽ bị loại.
            $query->where('created_at', '<=', $toDate->copy()->endOfDay());
        }

        if ($request->filled('status')) {
            $statusInput = strtolower(trim((string) $request->query('status')));
            if (!in_array($statusInput, \App\Services\DaiLyApi\B2bStatusMapper::ALL_PUBLIC_STATUSES, true)) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_STATUS_FILTER',
                    'message' => "Trạng thái lọc '{$statusInput}' không hợp lệ. Các trạng thái hợp lệ: " . implode(', ', \App\Services\DaiLyApi\B2bStatusMapper::ALL_PUBLIC_STATUSES),
                ], Response::HTTP_BAD_REQUEST);
            }

            $internalStatuses = \App\Services\DaiLyApi\B2bStatusMapper::toInternalFilter($statusInput);
            $query->whereIn('trang_thai_don_hang', $internalStatuses);
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $orders = $query->orderByDesc('id')->paginate($perPage);

        $items = collect($orders->items())->map(fn($d) => $this->formatOrder($d));

        return response()->json([
            'success' => true,
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'data' => $items,
        ]);
    }

    /**
     * Đọc và xác thực một tham số ngày.
     *
     * Chỉ chấp nhận đúng hai định dạng tường minh: Y-m-d và ISO-8601. Không dùng
     * Carbon::parse() trực tiếp vì nó chấp nhận cả 'now', 'tomorrow', '+1 week'...
     * khiến hợp đồng API không xác định và kết quả tra cứu phụ thuộc thời điểm gọi.
     *
     * @return \Carbon\Carbon|JsonResponse|null Carbon khi hợp lệ, null khi không truyền,
     *                                           JsonResponse lỗi 400 khi sai định dạng.
     */
    protected function docNgay(Request $request, string $tenThamSo)
    {
        if (!$request->filled($tenThamSo)) {
            return null;
        }

        $giaTri = trim((string) $request->query($tenThamSo));

        $laNgayThuan = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $giaTri);
        $laIso8601 = (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?$/', $giaTri);

        if (!$laNgayThuan && !$laIso8601) {
            return $this->loiNgay($tenThamSo);
        }

        try {
            $ngay = \Carbon\Carbon::parse($giaTri);
        } catch (\Throwable $e) {
            return $this->loiNgay($tenThamSo);
        }

        // PHP tự cuộn ngày không hợp lệ (2026-02-31 -> 2026-03-03). Kiểm tra vòng lại
        // để từ chối thay vì âm thầm tra cứu sai khoảng thời gian.
        if ($laNgayThuan && $ngay->format('Y-m-d') !== $giaTri) {
            return $this->loiNgay($tenThamSo);
        }

        return $ngay;
    }

    protected function loiNgay(string $tenThamSo): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'INVALID_DATE_FORMAT',
            'message' => "Tham số '{$tenThamSo}' không đúng định dạng. Yêu cầu định dạng Y-m-d (ví dụ: 2026-09-20) hoặc ISO-8601.",
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Chuẩn hóa cấu trúc đơn hàng trả về theo chuẩn public API.
     */
    protected function formatOrder(DonHang $donHang): array
    {
        $publicStatus = \App\Services\DaiLyApi\B2bStatusMapper::toPublic($donHang->trang_thai_don_hang);

        // completed_at chỉ có khi đơn đã có kết quả cuối cùng (success hoặc failed)
        $completedAt = \App\Services\DaiLyApi\B2bStatusMapper::isFinalPublicStatus($publicStatus)
            ? ($donHang->hoan_thanh_luc?->toIso8601String() ?: $donHang->that_bai_luc?->toIso8601String() ?: $donHang->updated_at?->toIso8601String())
            : null;

        return [
            'order_id' => $donHang->id,
            'order_code' => $donHang->ma_don_hang,
            'partner_order_id' => $donHang->ma_don_doi_tac,
            'account' => $donHang->tai_khoan_nhan,
            'product_code' => $donHang->ma_san_pham_snapshot,
            'product_name' => $donHang->ten_san_pham_snapshot,
            'amount' => (int) round((float) $donHang->menh_gia),
            'price' => (int) round((float) $donHang->gia_ban),
            'status' => $publicStatus,
            'payment_status' => $donHang->trang_thai_thanh_toan,
            'created_at' => $donHang->created_at?->toIso8601String(),
            'completed_at' => $completedAt,
        ];
    }
}
