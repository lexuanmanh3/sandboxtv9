<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\TrangThaiDonHang;
use App\Exceptions\IdempotencyKeyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\CreateTopupOrderRequest;
use App\Http\Requests\Frontend\PreviewTopupRequest;
use App\Jobs\ProcessMobileTopupJob;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\ViNguoiDung;
use App\Services\Topup\DonHangStateMachine;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TopupApiController — Xử lý toàn bộ API nội bộ cho tính năng Nạp tiền điện thoại.
 *
 * Bảo mật:
 * - user_id lấy từ auth()->id(), KHÔNG từ request.
 * - Giá, chiết khấu lấy từ DB, KHÔNG từ frontend.
 * - idempotency_key có unique index, chống tạo đơn trùng.
 */
class TopupApiController extends Controller
{
    public function __construct(
        private ViNguoiDungService $viService,
        private DonHangStateMachine $stateMachine,
    ) {}

    /**
     * GET /api/topup/carriers
     * Trả về danh sách nhà mạng (LoaiSanPham) đang hoạt động thuộc dịch vụ MOBILE_TOPUP.
     */
    public function carriers(): JsonResponse
    {
        $carriers = LoaiSanPham::whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->where(function ($q) {
                $q->whereHas('dichVu', fn ($dq) => $dq->where('ma_dich_vu', 'MOBILE_TOPUP'))
                  ->orWhereIn('ma_loai_san_pham', [
                      'VTE_TOPUP', 'VMS_TOPUP', 'VNA_TOPUP',
                      'VNM_TOPUP', 'WT_TOPUP', 'GMOBILE_TOPUP',
                  ]);
            })
            ->orderBy('thu_tu')
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'code'        => $c->ma_loai_san_pham,
                'name'        => $c->ten_loai_san_pham,
                'logo_url'    => $c->hinh_anh_url,
            ]);

        return response()->json(['success' => true, 'data' => $carriers]);
    }

    /**
     * GET /api/topup/denominations?carrier_id=1
     * Trả về mệnh giá, giá bán, chiết khấu của một nhà mạng.
     */
    public function denominations(Request $request): JsonResponse
    {
        $carrierId = $request->integer('carrier_id');
        if (!$carrierId) {
            return response()->json(['success' => false, 'message' => 'Thiếu carrier_id.'], 422);
        }

        // Tìm category tương ứng và gom cả ID cha/con (ví dụ VTE cha và VTE_TOPUP con)
        $category = LoaiSanPham::find($carrierId);
        $categoryIds = [$carrierId];
        if ($category) {
            $childIds = LoaiSanPham::where('loai_san_pham_cha_id', $category->id)->pluck('id')->toArray();
            $categoryIds = array_merge($categoryIds, $childIds);
            if ($category->loai_san_pham_cha_id) {
                $categoryIds[] = $category->loai_san_pham_cha_id;
            }
        }

        $products = SanPham::whereIn('loai_san_pham_id', $categoryIds)
            ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->orderBy('menh_gia')
            ->get()
            ->map(function ($p) {
                $menhGia     = (float) $p->menh_gia;
                $giaBan      = $p->gia_ban ? (float) $p->gia_ban : $menhGia;
                $chietKhau   = (float) ($p->chiet_khau_phan_tram ?? 0);
                return [
                    'id'              => $p->id,
                    'menh_gia'        => $menhGia,
                    'gia_ban'         => $giaBan,
                    'chiet_khau'      => $chietKhau,
                    'menh_gia_label'  => number_format($menhGia, 0, ',', '.') . 'đ',
                    'gia_ban_label'   => number_format($giaBan, 0, ',', '.') . 'đ',
                    'trang_thai'      => $p->trang_thai,
                ];
            });

        return response()->json(['success' => true, 'data' => $products]);
    }

    /**
     * POST /api/topup/preview
     * Kiểm tra dữ liệu và trả về thông tin giao dịch dự kiến.
     * KHÔNG tạo đơn hàng, KHÔNG trừ tiền.
     */
    public function preview(PreviewTopupRequest $request): JsonResponse
    {
        $userId    = auth()->id();
        $productId = $request->integer('product_id');

        $product = SanPham::where('id', $productId)
            ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Mệnh giá không tồn tại hoặc đã ngừng cung cấp.'], 422);
        }

        // Lấy số dư ví (không lock — chỉ xem)
        $vi      = $this->viService->layHoacTao($userId);
        $giaBan  = $product->gia_ban ? (float) $product->gia_ban : (float) $product->menh_gia;
        $chietKhau = (float) ($product->chiet_khau_phan_tram ?? 0);

        return response()->json([
            'success' => true,
            'data'    => [
                'phone'             => $request->phone,
                'carrier'           => $request->carrier,
                'product_id'        => $product->id,
                'menh_gia'          => (float) $product->menh_gia,
                'menh_gia_label'    => number_format((float) $product->menh_gia, 0, ',', '.') . 'đ',
                'gia_ban'           => $giaBan,
                'gia_ban_label'     => number_format($giaBan, 0, ',', '.') . 'đ',
                'chiet_khau'        => $chietKhau,
                'chiet_khau_label'  => $chietKhau > 0 ? "-{$chietKhau}%" : '0%',
                'so_du_hien_tai'    => (float) $vi->so_du,
                'so_du_hien_tai_label' => number_format((float) $vi->so_du, 0, ',', '.') . 'đ',
                'so_du_sau'         => max(0, (float) $vi->so_du - $giaBan),
                'so_du_sau_label'   => number_format(max(0, (float) $vi->so_du - $giaBan), 0, ',', '.') . 'đ',
                'du_so_du'          => $vi->duSoDu($giaBan),
            ],
        ]);
    }

    /**
     * POST /api/topup/orders
     * Tạo đơn hàng và thực hiện nạp tiền.
     *
     * Luồng:
     * 1. Validate → 2. idempotency check → 3. Lấy giá từ DB
     * → 4. Kiểm tra số dư → 5. Tạo đơn + trừ tiền (1 transaction)
     * → 6. Dispatch Job (sync) → 7. Hoàn tiền nếu thất bại
     */
    public function createOrder(CreateTopupOrderRequest $request): JsonResponse
    {
        $userId          = auth()->id(); // KHÔNG lấy từ request
        $productId       = $request->integer('product_id');
        $phone           = $request->phone;
        $idempotencyKey  = $request->idempotency_key;

        // ── Lấy sản phẩm và giá từ DB ── (không tin dữ liệu frontend)
        $product = SanPham::where('id', $productId)
            ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Mệnh giá không tồn tại hoặc đã ngừng cung cấp.'], 422);
        }

        // Lấy dịch vụ MOBILE_TOPUP
        $dichVu = DichVu::where('ma_dich_vu', 'MOBILE_TOPUP')->first();
        if (!$dichVu) {
            return response()->json(['success' => false, 'message' => 'Dịch vụ nạp tiền chưa được cấu hình.'], 500);
        }

        $giaBan = $product->gia_ban ? (float) $product->gia_ban : (float) $product->menh_gia;
        $chietKhau = (float) ($product->chiet_khau_phan_tram ?? 0);

        // ── Nhận diện nhà mạng từ số điện thoại ──
        $nhaMangThucTe = $this->nhanDienNhaMang($phone);

        // ── Tạo đơn hàng + trừ tiền trong 1 transaction ──
        try {
            $donHang = DB::transaction(function () use (
                $userId, $product, $dichVu, $phone, $giaBan, $chietKhau,
                $nhaMangThucTe, $idempotencyKey, $request
            ) {
                // ── Kiểm tra idempotency BÊN TRONG transaction với lockForUpdate (chống race condition) ──
                $existing = DonHang::lockForUpdate()
                    ->where('idempotency_key', $idempotencyKey)
                    ->where('nguoi_dung_id', $userId)
                    ->first();

                if ($existing) {
                    throw new IdempotencyKeyExistsException($existing);
                }

                $maDonHang = 'TU' . now()->format('ymdHis') . strtoupper(Str::random(4));

                $donHang = DonHang::create([
                    'ma_don_hang'        => $maDonHang,
                    'nguon_don'          => 'frontend',
                    'nguoi_dung_id'      => $userId,
                    'idempotency_key'    => $idempotencyKey,
                    'dich_vu_id'         => $dichVu->id,
                    'loai_san_pham_id'   => $product->loai_san_pham_id,
                    'san_pham_id'        => $product->id,
                    'tai_khoan_nhan'     => $phone,
                    'so_luong'           => 1,
                    'menh_gia'           => $product->menh_gia,
                    'gia_ban'            => $giaBan,
                    'chiet_khau'         => $chietKhau,
                    'phuong_thuc_thanh_toan' => 'vi',
                    'trang_thai_thanh_toan'  => 'cho_thanh_toan',
                    'trang_thai_don_hang'    => TrangThaiDonHang::CREATED->value,
                    'nha_mang_yeu_cau'       => $request->carrier,
                    'nha_mang_thuc_te'       => $nhaMangThucTe,
                    'ma_san_pham_snapshot'   => $product->ma_san_pham,
                    'ten_san_pham_snapshot'  => $product->ten_san_pham,
                    'menh_gia_snapshot'      => $product->menh_gia,
                    'gia_ban_snapshot'       => $giaBan,
                ]);

                // Trừ tiền ví (lockForUpdate bên trong)
                $this->viService->truTienTrongTransaction(
                    $userId,
                    $giaBan,
                    $donHang,
                    "Nạp tiền {$phone} - " . number_format($giaBan, 0, ',', '.') . 'đ'
                );

                // Cập nhật trạng thái đơn: CREATED → PAID → QUEUED
                $this->stateMachine->chuyen($donHang, TrangThaiDonHang::WAITING_PAYMENT, 'Khởi tạo đơn hàng');
                $this->stateMachine->chuyen($donHang, TrangThaiDonHang::PAID, 'Trừ ví thành công', 'vi_service');
                $donHang->update([
                    'trang_thai_thanh_toan' => 'da_thanh_toan',
                    'thanh_toan_luc'        => now(),
                ]);
                $this->stateMachine->chuyen($donHang, TrangThaiDonHang::QUEUED, 'Đưa vào hàng đợi');

                return $donHang;
            });
        } catch (IdempotencyKeyExistsException $e) {
            // Request trùng lập hợp lệ — trả về đơn đã tồn tại
            return $this->formatOrderResponse($e->getOrder(), true);
        } catch (\Illuminate\Database\QueryException $e) {
            // Bắt duplicate key từ unique constraint DB (lớp bảo vệ thứ 2)
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'uq_don_hang_user_idempotency')) {
                $existing = DonHang::where('idempotency_key', $idempotencyKey)
                    ->where('nguoi_dung_id', $userId)
                    ->first();
                if ($existing) {
                    return $this->formatOrderResponse($existing, true);
                }
            }
            Log::error('Topup order create DB error', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau.'], 500);
        } catch (\RuntimeException $e) {
            // Lỗi nghiệp vụ: số dư không đủ, ví bị khóa...
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Topup order create failed', [
                'user_id'   => $userId,
                'phone'     => substr($phone, 0, 4) . '***' . substr($phone, -2), // Che SĐT trong log
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại sau.'], 500);
        }

        // ── Gọi Job xử lý nạp tiền (sync trong local, async khi production) ──
        try {
            ProcessMobileTopupJob::dispatch($donHang->id);
            $donHang->refresh();
        } catch (\Throwable $e) {
            Log::error('ProcessMobileTopupJob dispatch failed', [
                'don_hang_id' => $donHang->id,
                'error'       => $e->getMessage(),
            ]);
            // Đơn đã tạo, đã trừ tiền — không rollback ở đây, để admin xử lý thủ công
        }

        return $this->formatOrderResponse($donHang);
    }

    /**
     * GET /api/topup/orders/{id}
     * Lấy trạng thái đơn hàng của chính mình.
     */
    public function getOrder(int $id): JsonResponse
    {
        $donHang = DonHang::where('id', $id)
            ->where('nguoi_dung_id', auth()->id()) // Bảo vệ: chỉ xem đơn của mình
            ->first();

        if (!$donHang) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng.'], 404);
        }

        return $this->formatOrderResponse($donHang);
    }

    /**
     * GET /api/topup/orders
     * Lịch sử đơn hàng của user hiện tại.
     */
    public function orderHistory(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $orders = DonHang::where('nguoi_dung_id', $userId)
            ->where('nguon_don', 'frontend')
            ->orderByDesc('created_at')
            ->paginate(20);

        $data = collect($orders->items())->map(fn ($o) => $this->orderToArray($o));

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    // ────────────────────── Private Helpers ──────────────────────

    /**
     * Nhận diện nhà mạng theo đầu số Việt Nam.
     */
    private function nhanDienNhaMang(string $phone): ?string
    {
        $viettel    = ['032', '033', '034', '035', '036', '037', '038', '039', '086', '096', '097', '098'];
        $mobifone   = ['070', '076', '077', '078', '079', '089', '090', '093'];
        $vinaphone  = ['081', '082', '083', '084', '085', '088', '091', '094'];
        $vietnamobile = ['052', '056', '058', '092'];
        $gmobile    = ['059', '099'];
        $reddi      = ['055', '056']; // iTel / Reddi

        $prefix3 = substr($phone, 0, 3);
        $prefix4 = substr($phone, 0, 4);

        if (in_array($prefix3, $viettel, true))     return 'viettel';
        if (in_array($prefix3, $mobifone, true))    return 'mobifone';
        if (in_array($prefix3, $vinaphone, true))   return 'vinaphone';
        if (in_array($prefix3, $vietnamobile, true)) return 'vietnamobile';
        if (in_array($prefix3, $gmobile, true))     return 'gmobile';
        if (in_array($prefix3, $reddi, true))       return 'reddi';

        return null;
    }

    private function formatOrderResponse(DonHang $donHang, bool $isDuplicate = false): JsonResponse
    {
        $status   = strtoupper((string) $donHang->trang_thai_don_hang);
        $isSuccess = in_array($status, ['SUCCESS'], true);
        $isFailed  = in_array($status, ['FAILED', 'REFUNDED', 'REFUND_PENDING'], true);
        $isPending = in_array($status, ['CREATED', 'WAITING_PAYMENT', 'PAID', 'QUEUED', 'PROCESSING', 'PROVIDER_PENDING', 'MANUAL_REVIEW'], true);

        $statusLabel = match ($status) {
            'CREATED', 'WAITING_PAYMENT', 'PAID', 'QUEUED' => 'Đang xử lý',
            'PROCESSING'       => 'Đang nạp tiền',
            'PROVIDER_PENDING' => 'Chờ kết quả từ nhà mạng',
            'SUCCESS'          => 'Nạp tiền thành công!',
            'FAILED'           => 'Nạp tiền thất bại',
            'REFUND_PENDING'   => 'Đang hoàn tiền',
            'REFUNDED'         => 'Đã hoàn tiền',
            'MANUAL_REVIEW'    => 'Đang chờ đối soát',
            default            => 'Không xác định',
        };

        return response()->json([
            'success'      => true,
            'is_duplicate' => $isDuplicate,
            'data'         => $this->orderToArray($donHang) + [
                'status_label' => $statusLabel,
                'is_success'   => $isSuccess,
                'is_failed'    => $isFailed,
                'is_pending'   => $isPending,
            ],
        ]);
    }

    private function orderToArray(DonHang $o): array
    {
        return [
            'id'            => $o->id,
            'ma_don_hang'   => $o->ma_don_hang,
            'tai_khoan_nhan' => $this->cheSDT((string) $o->tai_khoan_nhan),
            'menh_gia'      => (float) $o->menh_gia,
            'gia_ban'       => (float) $o->gia_ban,
            'chiet_khau'    => (float) $o->chiet_khau,
            'gia_ban_label' => number_format((float) $o->gia_ban, 0, ',', '.') . 'đ',
            'menh_gia_label' => number_format((float) $o->menh_gia, 0, ',', '.') . 'đ',
            'trang_thai'    => $o->trang_thai_don_hang,
            'nha_mang'      => $o->nha_mang_yeu_cau,
            'created_at'    => $o->created_at?->format('d/m/Y H:i:s'),
            'hoan_thanh_luc' => $o->hoan_thanh_luc?->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * Che bớt số điện thoại trong response (bảo mật).
     * 0987654321 → 0987***321
     */
    private function cheSDT(string $phone): string
    {
        if (strlen($phone) < 7) return $phone;
        return substr($phone, 0, 4) . '***' . substr($phone, -3);
    }
}
