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

        // Lọc theo khoảng thời gian nếu có
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->query('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->query('to_date'));
        }
        if ($request->filled('status')) {
            $status = strtoupper((string) $request->query('status'));
            $mappedStatus = match ($status) {
                'PENDING' => 'QUEUED',
                'PROCESSING' => 'DANG_XU_LY',
                'SUCCESS' => 'HOAN_THANH',
                'FAILED' => 'THAT_BAI',
                default => $status,
            };
            $query->where('trang_thai_don_hang', $mappedStatus);
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
     * Chuẩn hóa cấu trúc đơn hàng trả về theo chuẩn public API.
     */
    protected function formatOrder(DonHang $donHang): array
    {
        // Ánh xạ trạng thái nội bộ sang trạng thái công khai chuẩn
        $publicStatus = match ($donHang->trang_thai_don_hang) {
            'QUEUED', 'CHO_XU_LY' => 'pending',
            'DANG_XU_LY', 'PROCESSING' => 'processing',
            'HOAN_THANH', 'SUCCESS' => 'success',
            'THAT_BAI', 'FAILED' => 'failed',
            'MANUAL_REVIEW' => 'manual_review',
            default => strtolower($donHang->trang_thai_don_hang),
        };

        // completed_at chỉ điền khi đơn có kết quả cuối cùng (thành công hoặc thất bại)
        $completedAt = in_array($publicStatus, ['success', 'failed'], true)
            ? ($donHang->hoan_thanh_luc?->toIso8601String() ?: $donHang->that_bai_luc?->toIso8601String() ?: $donHang->updated_at?->toIso8601String())
            : null;

        return [
            'order_id' => $donHang->id,
            'order_code' => $donHang->ma_don_hang,
            'partner_order_id' => $donHang->ma_don_doi_tac,
            'account' => $donHang->tai_khoan_nhan,
            'product_code' => $donHang->ma_san_pham_snapshot,
            'product_name' => $donHang->ten_san_pham_snapshot,
            'amount' => (float) $donHang->menh_gia,
            'price' => (float) $donHang->gia_ban,
            'status' => $publicStatus,
            'payment_status' => $donHang->trang_thai_thanh_toan,
            'created_at' => $donHang->created_at?->toIso8601String(),
            'completed_at' => $completedAt,
        ];
    }
}
