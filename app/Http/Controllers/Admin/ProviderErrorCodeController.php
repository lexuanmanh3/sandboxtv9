<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProviderErrorCodes\StoreErrorCodeRequest;
use App\Http\Requests\Admin\ProviderErrorCodes\UpdateErrorCodeRequest;
use App\Models\MaLoiNhaCungCap;
use App\Models\NhaCungCap;
use Database\Seeders\MaLoiNhaCungCapSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProviderErrorCodeController extends Controller
{
    /**
     * Danh sách mã lỗi nhà cung cấp kèm bộ lọc và thống kê.
     */
    public function index(Request $request): View
    {
        $query = $this->buildQuery($request);

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 20;

        $errorCodes = $query->with('nhaCungCap')
            ->orderBy('nha_cung_cap_id')
            ->orderBy('ma_loi')
            ->paginate($perPage)
            ->withQueryString();

        // Thống kê nhanh
        $stats = [
            'total' => MaLoiNhaCungCap::count(),
            'success' => MaLoiNhaCungCap::where('loai_ket_qua', MaLoiNhaCungCap::RESULT_SUCCESS)->count(),
            'pending' => MaLoiNhaCungCap::where('loai_ket_qua', MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING)->count(),
            'failure' => MaLoiNhaCungCap::where('loai_ket_qua', MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE)->count(),
        ];

        return view('admin.provider-error-codes', [
            'errorCodes' => $errorCodes,
            'providers' => NhaCungCap::orderBy('ten_ncc')->get(),
            'stats' => $stats,
            'resultTypes' => $this->resultTypes(),
            'actionTypes' => $this->actionTypes(),
            'filters' => $request->only(['q', 'nha_cung_cap_id', 'loai_ket_qua', 'hanh_dong_he_thong', 'trang_thai']),
        ]);
    }

    /**
     * Thêm mới mã lỗi.
     */
    public function store(StoreErrorCodeRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $errorCode = MaLoiNhaCungCap::create($request->validated());

            $this->clearErrorCodeCache();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Đã thêm mã lỗi '{$errorCode->ma_loi}' thành công.",
                    'data' => $errorCode,
                ]);
            }

            return redirect()->route('admin.provider-error-codes')
                ->with('success', "Đã thêm mã lỗi '{$errorCode->ma_loi}' thành công.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi lưu mã lỗi: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Lỗi khi lưu mã lỗi: ' . $e->getMessage()]);
        }
    }

    /**
     * Cập nhật thông tin mã lỗi.
     */
    public function update(UpdateErrorCodeRequest $request, MaLoiNhaCungCap $errorCode): RedirectResponse|JsonResponse
    {
        try {
            $errorCode->update($request->validated());

            $this->clearErrorCodeCache();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Đã cập nhật mã lỗi '{$errorCode->ma_loi}' thành công.",
                    'data' => $errorCode,
                ]);
            }

            return redirect()->route('admin.provider-error-codes')
                ->with('success', "Đã cập nhật mã lỗi '{$errorCode->ma_loi}' thành công.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi cập nhật mã lỗi: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Lỗi khi cập nhật mã lỗi: ' . $e->getMessage()]);
        }
    }

    /**
     * Bật/Tắt trạng thái kích hoạt của mã lỗi.
     */
    public function toggleStatus(Request $request, MaLoiNhaCungCap $errorCode): RedirectResponse|JsonResponse
    {
        try {
            $newStatus = in_array($errorCode->trang_thai, ['ACTIVE', 'hoat_dong']) ? 'tam_dung' : 'hoat_dong';
            $errorCode->update(['trang_thai' => $newStatus]);

            $this->clearErrorCodeCache();

            $statusLabel = $newStatus === 'hoat_dong' ? 'Hoạt động' : 'Tạm dừng';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Đã chuyển trạng thái mã lỗi '{$errorCode->ma_loi}' sang {$statusLabel}.",
                    'trang_thai' => $newStatus,
                ]);
            }

            return redirect()->back()
                ->with('success', "Đã chuyển trạng thái mã lỗi '{$errorCode->ma_loi}' sang {$statusLabel}.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể đổi trạng thái: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Không thể đổi trạng thái: ' . $e->getMessage()]);
        }
    }

    /**
     * Xóa một mã lỗi.
     */
    public function destroy(Request $request, MaLoiNhaCungCap $errorCode): RedirectResponse|JsonResponse
    {
        try {
            $codeName = $errorCode->ma_loi;
            $errorCode->delete();

            $this->clearErrorCodeCache();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Đã xóa mã lỗi '{$codeName}' thành công.",
                ]);
            }

            return redirect()->route('admin.provider-error-codes')
                ->with('success', "Đã xóa mã lỗi '{$codeName}' thành công.");
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lỗi khi xóa mã lỗi: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Lỗi khi xóa mã lỗi: ' . $e->getMessage()]);
        }
    }

    /**
     * Xóa nhiều mã lỗi cùng lúc (Bulk Delete).
     */
    public function bulkDestroy(Request $request): RedirectResponse|JsonResponse
    {
        $ids = (array) ($request->input('ids') ?? []);

        if (empty($ids)) {
            $msg = 'Vui lòng chọn ít nhất một mã lỗi để xóa.';
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : redirect()->back()->withErrors(['error' => $msg]);
        }

        try {
            $deletedCount = DB::transaction(function () use ($ids) {
                return MaLoiNhaCungCap::whereIn('id', $ids)->delete();
            });

            $this->clearErrorCodeCache();

            $msg = "Đã xóa thành công {$deletedCount} mã lỗi đã chọn.";

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('admin.provider-error-codes')->with('success', $msg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Lỗi xóa hàng loạt: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Lỗi xóa hàng loạt: ' . $e->getMessage()]);
        }
    }

    /**
     * Khởi tạo lại danh sách mã lỗi mặc định (Seeder).
     */
    public function seedDefaults(Request $request): RedirectResponse|JsonResponse
    {
        try {
            $seeder = new MaLoiNhaCungCapSeeder();
            $seeder->run();

            $this->clearErrorCodeCache();

            $msg = 'Đã nạp / đồng bộ danh sách mã lỗi chuẩn từ hệ thống thành công.';

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('admin.provider-error-codes')->with('success', $msg);
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Lỗi khởi tạo: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->withErrors(['error' => 'Lỗi khởi tạo: ' . $e->getMessage()]);
        }
    }

    /**
     * Xóa cache mã lỗi an toàn cho mọi cache driver (file, redis, memcached...).
     */
    private function clearErrorCodeCache(?string $code = null, ?int $providerId = null): void
    {
        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['provider_error_codes'])->flush();
            }
        } catch (Throwable) {
            // Bỏ qua nếu cache store không hỗ trợ tags (như File Driver)
        }

        if ($code !== null) {
            Cache::forget("err_map_all_{$code}");
            if ($providerId) {
                Cache::forget("err_map_{$providerId}_{$code}");
            }
        }
    }

    /**
     * Xây dựng truy vấn tìm kiếm lọc mã lỗi.
     */
    private function buildQuery(Request $request): Builder
    {
        $query = MaLoiNhaCungCap::query();

        if ($q = trim((string) $request->query('q'))) {
            $query->search($q);
        }

        if ($providerId = $request->query('nha_cung_cap_id')) {
            if ($providerId === 'general') {
                $query->whereNull('nha_cung_cap_id');
            } else {
                $query->where('nha_cung_cap_id', (int) $providerId);
            }
        }

        if ($resultType = $request->query('loai_ket_qua')) {
            $query->where('loai_ket_qua', $resultType);
        }

        if ($actionType = $request->query('hanh_dong_he_thong')) {
            $query->where('hanh_dong_he_thong', $actionType);
        }

        if ($status = $request->query('trang_thai')) {
            if (in_array($status, ['hoat_dong', 'ACTIVE'])) {
                $query->whereIn('trang_thai', ['hoat_dong', 'ACTIVE']);
            } elseif (in_array($status, ['tam_dung', 'INACTIVE'])) {
                $query->whereIn('trang_thai', ['tam_dung', 'INACTIVE']);
            } else {
                $query->where('trang_thai', $status);
            }
        }

        return $query;
    }

    /**
     * Danh mục Loại kết quả phân loại.
     */
    public static function resultTypes(): array
    {
        return [
            MaLoiNhaCungCap::RESULT_SUCCESS => [
                'label' => 'Thành công (SUCCESS)',
                'class' => 'is-yes',
                'description' => 'Giao dịch được NCC xác nhận hoàn tất thành công',
            ],
            MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING => [
                'label' => 'Chờ xử lý / Nghi vấn (PENDING)',
                'class' => 'is-warn',
                'description' => 'Chưa rõ trạng thái hoặc đang chờ xử lý, cần hẹn giờ truy vấn lại',
            ],
            MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE => [
                'label' => 'Thất bại dứt điểm (FAILURE)',
                'class' => 'is-no',
                'description' => 'NCC từ chối giao dịch, tự động hoàn tiền cho khách',
            ],
        ];
    }

    /**
     * Danh mục Hành động hệ thống khi gặp lỗi.
     */
    public static function actionTypes(): array
    {
        return [
            MaLoiNhaCungCap::ACTION_RETRY_STATUS => [
                'label' => 'Tự động kiểm tra lại (Polling)',
                'badge' => 'badge-action-retry',
                'description' => 'Lên lịch Job kiểm tra lại trạng thái sau 10s, 30s...',
            ],
            MaLoiNhaCungCap::ACTION_REFUND_WALLET => [
                'label' => 'Tự động hoàn tiền ví',
                'badge' => 'badge-action-refund',
                'description' => 'Chuyển đơn sang FAILED và hoàn tiền vào ví khách ngay lập tức',
            ],
            MaLoiNhaCungCap::ACTION_MANUAL_REVIEW => [
                'label' => 'Chờ Admin xử lý thủ công',
                'badge' => 'badge-action-manual',
                'description' => 'Giữ đơn hàng để Admin kiểm tra và quyết định',
            ],
            MaLoiNhaCungCap::ACTION_NONE => [
                'label' => 'Không thao tác thêm',
                'badge' => 'badge-action-none',
                'description' => 'Ghi nhận kết quả bình thường',
            ],
        ];
    }
}
