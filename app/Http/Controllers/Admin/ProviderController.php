<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Providers\StoreProviderRequest;
use App\Http\Requests\Admin\Providers\UpdateProviderRequest;
use App\Models\KetNoiNhaCungCap;
use App\Models\NhaCungCap;
use App\Services\Topup\NhaCungCapResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProviderController extends Controller
{
    /**
     * Màn hình danh sách nhà cung cấp và kết nối API.
     */
    public function index(Request $request): View
    {
        $query = $this->buildProviderQuery($request);

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 10;

        $providers = $query->with(['nhaCungCapCha', 'ketNoi'])
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $circuitStats = [];
        foreach ($providers as $p) {
            $conn = $p->ketNoi->first();
            if ($conn) {
                $cacheKey = "circuit_breaker_fails_{$conn->id}";
                $circuitStats[$p->id] = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
            } else {
                $circuitStats[$p->id] = 0;
            }
        }

        return view('admin.providers', [
            'providers' => $providers,
            'circuitStats' => $circuitStats,
            'parentProviders' => NhaCungCap::where('trang_thai', 'hoat_dong')->orderBy('ten_ncc')->get(),
            'statuses' => $this->statuses(),
            'filters' => $request->only(['ma_ncc', 'ten_ncc', 'trang_thai']),
            'telegramConfig' => \App\Models\CauHinhThongBao::layCauHinhTelegram(),
        ]);
    }

    /**
     * Tạo mới nhà cung cấp và cấu hình kết nối API trong cùng một Transaction.
     */
    public function store(StoreProviderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            // 1. Tạo bản ghi Nhà cung cấp
            $provider = NhaCungCap::create([
                'ma_ncc' => strtoupper($validated['ma_ncc']),
                'ten_ncc' => $validated['ten_ncc'],
                'nha_cung_cap_cha_id' => $validated['nha_cung_cap_cha_id'] ?? null,
                'so_dien_thoai' => $validated['so_dien_thoai'] ?? null,
                'email' => $validated['email'] ?? null,
                'trang_thai' => $validated['trang_thai'],
            ]);

            // 2. Tạo bản ghi Kết nối API của NCC
            $secretKey = !empty($validated['password']) ? $validated['password'] : (!empty($validated['api_password']) ? $validated['api_password'] : null);
            $apiKey = !empty($validated['api_password']) ? $validated['api_password'] : ($validated['api_user'] ?? $validated['username'] ?? null);
            $partnerCode = $validated['api_user'] ?? $validated['username'] ?? null;

            KetNoiNhaCungCap::create([
                'nha_cung_cap_id' => $provider->id,
                'ten_ket_noi' => 'Kết nối ' . $provider->ten_ncc,
                'username' => $validated['username'] ?? null,
                'password_ma_hoa' => !empty($validated['password']) ? $validated['password'] : null,
                'api_user' => $validated['api_user'] ?? null,
                'api_password_ma_hoa' => !empty($validated['api_password']) ? $validated['api_password'] : null,
                'partner_code' => $partnerCode,
                'api_key_ma_hoa' => $apiKey,
                'secret_key_ma_hoa' => $secretKey,
                'api_url' => $validated['api_url'] ?? null,
                'base_url' => $validated['api_url'] ?? null,
                'cau_hinh_ma_gd' => $validated['cau_hinh_ma_gd'] ?? null,
                'public_key' => $validated['public_key'] ?? null,
                'public_key_file' => $validated['public_key_file'] ?? null,
                'private_key_file_ma_hoa' => !empty($validated['private_key_file']) ? $validated['private_key_file'] : null,
                'timeout_he_thong' => $validated['timeout_he_thong'] ?? 60,
                'timeout_ncc' => $validated['timeout_ncc'] ?? 60,
                'trang_thai' => $validated['trang_thai'],
                'cau_hinh_canh_bao_so_du' => [
                    'so_du_canh_bao' => (float) ($validated['so_du_canh_bao'] ?? 0),
                    'so_du_toi_thieu_nap' => (float) ($validated['so_du_toi_thieu_nap'] ?? 0),
                    'so_tien_nap_moi_lan' => (float) ($validated['so_tien_nap_moi_lan'] ?? 0),
                    'chay_nhieu_tk_con' => (bool) ($validated['chay_nhieu_tk_con'] ?? false),
                    'tu_dong_nap_tien' => (bool) ($validated['tu_dong_nap_tien'] ?? false),
                    'kich_hoat_nap_cham' => (bool) ($validated['kich_hoat_nap_cham'] ?? false),
                ],
                'cau_hinh_dong_tu_dong' => [
                    'so_gd_that_bai_lien_tiep' => (int) ($validated['so_gd_that_bai_lien_tiep'] ?? 0),
                    'thoi_gian_dong' => (int) ($validated['thoi_gian_dong'] ?? 0),
                    'ma_loi_bo_qua' => $validated['ma_loi_bo_qua'] ?? '',
                    'tinh_gd_loi' => (bool) ($validated['tinh_gd_loi'] ?? false),
                    'so_gd_nghi_ngo' => (int) ($validated['so_gd_nghi_ngo'] ?? 0),
                    'thoi_gian_quet' => (int) ($validated['thoi_gian_quet'] ?? 0),
                    'tong_so_gd_quet' => (int) ($validated['tong_so_gd_quet'] ?? 0),
                    'so_gd_loi_toi_da' => (int) ($validated['so_gd_loi_toi_da'] ?? 0),
                    'so_lan_kiem_tra_lai' => (int) ($validated['so_lan_kiem_tra_lai'] ?? 6),
                    'hanh_dong_khi_het_gio' => $validated['hanh_dong_khi_het_gio'] ?? 'TU_DONG_HOAN_TIEN',
                ],
                'cau_hinh_canh_bao_loi' => [
                    'bat_canh_bao' => (bool) ($validated['bat_canh_bao'] ?? false),
                    'canh_bao_xu_ly_cham_giay' => (int) ($validated['canh_bao_xu_ly_cham_giay'] ?? 0),
                    'kenh_canh_bao' => $validated['kenh_canh_bao'] ?? 'Telegram',
                    'nhom_canh_bao_chat_id' => $validated['nhom_canh_bao_chat_id'] ?? '',
                    'bo_qua_ma_loi_ncc' => $validated['bo_qua_ma_loi_ncc'] ?? '',
                    'bo_qua_message_ncc' => $validated['bo_qua_message_ncc'] ?? '',
                ],
            ]);
        });

        return redirect()->route('admin.providers')->with('success', 'Thêm mới nhà cung cấp thành công.');
    }

    /**
     * Cập nhật thông tin nhà cung cấp và cấu hình kết nối API.
     */
    public function update(UpdateProviderRequest $request, NhaCungCap $provider): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($provider, $validated) {
            // 1. Cập nhật Nhà cung cấp
            $provider->update([
                'ma_ncc' => strtoupper($validated['ma_ncc']),
                'ten_ncc' => $validated['ten_ncc'],
                'nha_cung_cap_cha_id' => $validated['nha_cung_cap_cha_id'] ?? null,
                'so_dien_thoai' => $validated['so_dien_thoai'] ?? null,
                'email' => $validated['email'] ?? null,
                'trang_thai' => $validated['trang_thai'],
            ]);

            // 2. Tìm hoặc tạo kết nối API
            $connection = $provider->ketNoi()->first() ?? new KetNoiNhaCungCap(['nha_cung_cap_id' => $provider->id]);

            $connectionData = [
                'ten_ket_noi' => 'Kết nối ' . $provider->ten_ncc,
                'username' => $validated['username'] ?? null,
                'api_user' => $validated['api_user'] ?? null,
                'partner_code' => $validated['api_user'] ?? $validated['username'] ?? null,
                'api_url' => $validated['api_url'] ?? null,
                'base_url' => $validated['api_url'] ?? null,
                'cau_hinh_ma_gd' => $validated['cau_hinh_ma_gd'] ?? null,
                'public_key' => $validated['public_key'] ?? null,
                'public_key_file' => $validated['public_key_file'] ?? null,
                'timeout_he_thong' => $validated['timeout_he_thong'] ?? 60,
                'timeout_ncc' => $validated['timeout_ncc'] ?? 60,
                'trang_thai' => $validated['trang_thai'],
                'cau_hinh_canh_bao_so_du' => [
                    'so_du_canh_bao' => (float) ($validated['so_du_canh_bao'] ?? 0),
                    'so_du_toi_thieu_nap' => (float) ($validated['so_du_toi_thieu_nap'] ?? 0),
                    'so_tien_nap_moi_lan' => (float) ($validated['so_tien_nap_moi_lan'] ?? 0),
                    'chay_nhieu_tk_con' => (bool) ($validated['chay_nhieu_tk_con'] ?? false),
                    'tu_dong_nap_tien' => (bool) ($validated['tu_dong_nap_tien'] ?? false),
                    'kich_hoat_nap_cham' => (bool) ($validated['kich_hoat_nap_cham'] ?? false),
                ],
                'cau_hinh_dong_tu_dong' => [
                    'so_gd_that_bai_lien_tiep' => (int) ($validated['so_gd_that_bai_lien_tiep'] ?? 0),
                    'thoi_gian_dong' => (int) ($validated['thoi_gian_dong'] ?? 0),
                    'ma_loi_bo_qua' => $validated['ma_loi_bo_qua'] ?? '',
                    'tinh_gd_loi' => (bool) ($validated['tinh_gd_loi'] ?? false),
                    'so_gd_nghi_ngo' => (int) ($validated['so_gd_nghi_ngo'] ?? 0),
                    'thoi_gian_quet' => (int) ($validated['thoi_gian_quet'] ?? 0),
                    'tong_so_gd_quet' => (int) ($validated['tong_so_gd_quet'] ?? 0),
                    'so_gd_loi_toi_da' => (int) ($validated['so_gd_loi_toi_da'] ?? 0),
                    'so_lan_kiem_tra_lai' => (int) ($validated['so_lan_kiem_tra_lai'] ?? 6),
                    'hanh_dong_khi_het_gio' => $validated['hanh_dong_khi_het_gio'] ?? 'TU_DONG_HOAN_TIEN',
                ],
                'cau_hinh_canh_bao_loi' => [
                    'bat_canh_bao' => (bool) ($validated['bat_canh_bao'] ?? false),
                    'canh_bao_xu_ly_cham_giay' => (int) ($validated['canh_bao_xu_ly_cham_giay'] ?? 0),
                    'kenh_canh_bao' => $validated['kenh_canh_bao'] ?? 'Telegram',
                    'nhom_canh_bao_chat_id' => $validated['nhom_canh_bao_chat_id'] ?? '',
                    'bo_qua_ma_loi_ncc' => $validated['bo_qua_ma_loi_ncc'] ?? '',
                    'bo_qua_message_ncc' => $validated['bo_qua_message_ncc'] ?? '',
                ],
            ];

            // Chỉ ghi đè mật khẩu nếu người dùng nhập mới (bỏ qua nếu để trống hoặc là placeholder masked)
            if (!empty($validated['password']) && $validated['password'] !== '••••••••••••') {
                $connectionData['password_ma_hoa'] = $validated['password'];
                $connectionData['secret_key_ma_hoa'] = $validated['password'];
            }
            if (!empty($validated['api_password']) && $validated['api_password'] !== '••••••••••••') {
                $connectionData['api_password_ma_hoa'] = $validated['api_password'];
                $connectionData['api_key_ma_hoa'] = $validated['api_password'];
            }
            if (!empty($validated['private_key_file']) && $validated['private_key_file'] !== '••••••••••••') {
                $connectionData['private_key_file_ma_hoa'] = $validated['private_key_file'];
            }

            $connection->fill($connectionData)->save();

            if (($validated['trang_thai'] ?? '') === 'hoat_dong') {
                \Illuminate\Support\Facades\Cache::forget("circuit_breaker_fails_{$connection->id}");
                \Illuminate\Support\Facades\Cache::forget("circuit_breaker_tripped_{$connection->id}");
            }
        });

        return back()->with('success', 'Cập nhật cấu hình nhà cung cấp thành công.');
    }

    /**
     * Bật / Tắt nhanh trạng thái nhà cung cấp (Hoạt động <-> Tạm dừng).
     */
    public function toggleStatus(NhaCungCap $provider): RedirectResponse
    {
        $newStatus = ($provider->trang_thai === 'hoat_dong') ? 'tam_dung' : 'hoat_dong';

        DB::transaction(function () use ($provider, $newStatus) {
            $provider->update(['trang_thai' => $newStatus]);
            $provider->ketNoi()->update(['trang_thai' => $newStatus]);
            foreach ($provider->ketNoi as $conn) {
                \Illuminate\Support\Facades\Cache::forget("circuit_breaker_fails_{$conn->id}");
                \Illuminate\Support\Facades\Cache::forget("circuit_breaker_tripped_{$conn->id}");
            }
        });

        $statusLabel = $newStatus === 'hoat_dong' ? 'Hoạt động' : 'Tạm dừng';
        return back()->with('success', "Đã chuyển trạng thái NCC {$provider->ten_ncc} sang '{$statusLabel}'.");
    }

    /**
     * Kiểm tra kết nối API tới Nhà Cung Cấp.
     */
    public function testConnection(NhaCungCap $provider, NhaCungCapResolver $resolver): JsonResponse
    {
        $connection = $provider->ketNoi()->first();
        if (!$connection) {
            return response()->json([
                'success' => false,
                'message' => 'Nhà cung cấp này chưa có cấu hình kết nối API.',
            ], 422);
        }

        try {
            $startTime = microtime(true);
            $adapter = $resolver->resolve($connection);

            // Thử gọi lấy danh sách sản phẩm để kiểm tra xác thực JWT/Signature và kết nối endpoint
            $products = $adapter->layDanhSachSanPham();
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => true,
                'message' => "Kết nối API tới {$provider->ten_ncc} THÀNH CÔNG!",
                'latency_ms' => $durationMs,
                'product_count' => $products->count(),
                'endpoint' => $connection->api_url ?: $connection->base_url,
            ]);
        } catch (Throwable $e) {
            $durationMs = isset($startTime) ? round((microtime(true) - $startTime) * 1000, 2) : 0;
            return response()->json([
                'success' => false,
                'message' => "Kết nối tới {$provider->ten_ncc} THẤT BẠI: " . $e->getMessage(),
                'latency_ms' => $durationMs,
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Đặt lại (Reset) bộ đếm lỗi Circuit Breaker cho nhà cung cấp.
     */
    public function resetCircuitBreaker(NhaCungCap $provider): RedirectResponse
    {
        $conn = $provider->ketNoi()->first();
        if ($conn) {
            \Illuminate\Support\Facades\Cache::forget("circuit_breaker_fails_{$conn->id}");
            \Illuminate\Support\Facades\Cache::forget("circuit_breaker_tripped_{$conn->id}");
            $conn->update(['trang_thai' => 'hoat_dong']);
            $provider->update(['trang_thai' => 'hoat_dong']);
        }
        return back()->with('success', "Đã đặt lại (reset) bộ đếm lỗi Circuit Breaker và mở lại kết nối cho nhà cung cấp {$provider->ten_ncc}.");
    }

    /**
     * Xóa nhà cung cấp (có kiểm tra ràng buộc khóa ngoại).
     */
    public function destroy(NhaCungCap $provider): RedirectResponse
    {
        try {
            $provider->delete();
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors([
                'provider' => 'Không thể xóa nhà cung cấp này vì đang có liên kết với sản phẩm, kết nối hoặc lịch sử giao dịch.',
            ]);
        }

        return back()->with('success', 'Xóa nhà cung cấp thành công.');
    }

    /**
     * Xóa nhiều nhà cung cấp cùng lúc.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:nha_cung_cap,id'],
        ]);

        $ids = $validated['ids'];
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $provider = NhaCungCap::find($id);
                if ($provider) {
                    $provider->ketNoi()->delete();
                    $provider->delete();
                    $deleted++;
                }
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        if ($failed > 0) {
            return back()->with('success', "Đã xóa thành công {$deleted} nhà cung cấp. ({$failed} nhà cung cấp không thể xóa do đang có ràng buộc dữ liệu liên quan).");
        }

        return back()->with('success', "Đã xóa thành công {$deleted} nhà cung cấp đã chọn.");
    }

    /**
     * Xây dựng query tìm kiếm bộ lọc.
     */
    private function buildProviderQuery(Request $request): Builder
    {
        $query = NhaCungCap::query();

        if ($ma = trim((string) $request->query('ma_ncc'))) {
            $query->where('ma_ncc', 'like', "%{$ma}%");
        }

        if ($ten = trim((string) $request->query('ten_ncc'))) {
            $query->where('ten_ncc', 'like', "%{$ten}%");
        }

        if ($trangThai = $request->query('trang_thai')) {
            $query->where('trang_thai', $trangThai);
        }

        return $query;
    }

    /**
     * Danh sách trạng thái NCC.
     */
    private function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_dung' => 'Tạm dừng',
            'khoa' => 'Khóa',
        ];
    }
}
