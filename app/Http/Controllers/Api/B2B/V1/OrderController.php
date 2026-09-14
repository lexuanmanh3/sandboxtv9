<?php

namespace App\Http\Controllers\Api\B2B\V1;

use App\Http\Controllers\Controller;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use App\Services\DaiLyApi\B2bOrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        protected B2bOrderService $orderService
    ) {}

    public function create(Request $request): JsonResponse
    {
        /** @var DaiLyApi $partner */
        $partner = $request->attributes->get('b2b_partner');

        $idempotencyKey = $request->header('Idempotency-Key')
            ?: $request->header('X-Idempotency-Key')
            ?: $request->input('idempotency_key');

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
            $code = $de->getCode() ?: Response::HTTP_UNPROCESSABLE_ENTITY;
            return response()->json([
                'success' => false,
                'error_code' => 'ORDER_CREATION_FAILED',
                'message' => $de->getMessage(),
            ], $code);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("B2B Order Creation Exception: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'SYSTEM_ERROR',
                'message' => 'Lỗi xử lý nội bộ hệ thống khi tiếp nhận đơn.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

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

        // Nếu không có partner_order_id, trả về danh sách phân trang
        $orders = $query->orderByDesc('id')->paginate(20);

        $items = collect($orders->items())->map(fn($d) => $this->formatOrder($d));

        return response()->json([
            'success' => true,
            'current_page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
            'total' => $orders->total(),
            'data' => $items,
        ]);
    }

    protected function formatOrder(DonHang $donHang): array
    {
        return [
            'order_id' => $donHang->id,
            'order_code' => $donHang->ma_don_hang,
            'partner_order_id' => $donHang->ma_don_doi_tac,
            'account' => $donHang->tai_khoan_nhan,
            'product_code' => $donHang->ma_san_pham_snapshot,
            'product_name' => $donHang->ten_san_pham_snapshot,
            'amount' => (float) $donHang->menh_gia,
            'price' => (float) $donHang->gia_ban,
            'status' => $donHang->trang_thai_don_hang,
            'payment_status' => $donHang->trang_thai_thanh_toan,
            'created_at' => $donHang->created_at?->toIso8601String(),
            'completed_at' => $donHang->hoan_thanh_luc?->toIso8601String(),
            'failed_at' => $donHang->that_bai_luc?->toIso8601String(),
        ];
    }
}
