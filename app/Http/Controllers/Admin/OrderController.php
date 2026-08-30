<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\NhaCungCap;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(private ViNguoiDungService $viService) {}

    /**
     * Danh sách tất cả Hóa đơn / Đơn hàng trong hệ thống.
     */
    public function index(Request $request)
    {
        $query = DonHang::query()->with([
            'nguoiDung',
            'dichVu',
            'loaiSanPham',
            'sanPham',
            'nhaCungCap',
            'nhaCungCapThanhCong',
            'lanGoiNhaCungCap',
            'lichSuTrangThai',
        ]);

        // 1. Tìm kiếm từ khóa (Mã đơn hàng, Số điện thoại / Tài khoản nhận, Mã đơn đối tác, Mã lỗi)
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('ma_don_hang', 'like', "%{$q}%")
                    ->orWhere('tai_khoan_nhan', 'like', "%{$q}%")
                    ->orWhere('ma_don_doi_tac', 'like', "%{$q}%")
                    ->orWhere('ma_loi_he_thong', 'like', "%{$q}%")
                    ->orWhereHas('nguoiDung', function ($uq) use ($q) {
                        $uq->where('name', 'like', "%{$q}%")
                           ->orWhere('ten_dang_nhap', 'like', "%{$q}%")
                           ->orWhere('email', 'like', "%{$q}%")
                           ->orWhere('so_dien_thoai', 'like', "%{$q}%");
                    });
            });
        }

        // 2. Lọc theo Dịch vụ
        if ($dichVuId = $request->query('dich_vu_id')) {
            $query->where('dich_vu_id', $dichVuId);
        }

        // 3. Lọc theo Loại sản phẩm / Nhà mạng
        if ($loaiSpId = $request->query('loai_san_pham_id')) {
            $query->where('loai_san_pham_id', $loaiSpId);
        }

        // 4. Lọc theo Nhà cung cấp
        if ($nccId = $request->query('nha_cung_cap_id')) {
            $query->where(function ($nq) use ($nccId) {
                $nq->where('nha_cung_cap_id', $nccId)
                   ->orWhere('nha_cung_cap_thanh_cong_id', $nccId);
            });
        }

        // 5. Lọc theo Trạng thái đơn hàng
        if ($status = $request->query('trang_thai_don_hang')) {
            $query->where('trang_thai_don_hang', $status);
        }

        // 6. Lọc theo Trạng thái thanh toán
        if ($paymentStatus = $request->query('trang_thai_thanh_toan')) {
            $query->where('trang_thai_thanh_toan', $paymentStatus);
        }

        // 7. Lọc theo Khoảng ngày tạo
        if ($fromDate = $request->query('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->query('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        // Thống kê tổng quan (Dashboard metrics)
        $stats = [
            'total_orders'   => DonHang::count(),
            'total_revenue'  => (float) DonHang::whereIn('trang_thai_don_hang', ['SUCCESS', 'thanh_cong'])->sum('gia_ban'),
            'total_profit'   => (float) DonHang::whereIn('trang_thai_don_hang', ['SUCCESS', 'thanh_cong'])->sum('loi_nhuan'),
            'success_count'  => DonHang::whereIn('trang_thai_don_hang', ['SUCCESS', 'thanh_cong'])->count(),
            'pending_count'  => DonHang::whereIn('trang_thai_don_hang', [
                'CREATED', 'WAITING_PAYMENT', 'PAID', 'QUEUED',
                'PROCESSING', 'PROVIDER_PENDING', 'MANUAL_REVIEW',
                'cho_xu_ly', 'dang_xu_ly',
            ])->count(),
            'failed_count'   => DonHang::whereIn('trang_thai_don_hang', ['FAILED', 'that_bai'])->count(),
            'refunded_count' => DonHang::whereIn('trang_thai_don_hang', ['REFUNDED', 'da_hoan_tien'])->count(),
        ];

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 20;

        $orders = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.orders', [
            'orders'     => $orders,
            'stats'      => $stats,
            'services'   => DichVu::orderBy('thu_tu')->get(),
            'categories' => LoaiSanPham::orderBy('thu_tu')->get(),
            'providers'  => NhaCungCap::orderBy('ten_ncc')->get(),
            'filters'    => $request->all(),
        ]);
    }

    /**
     * Xem chi tiết 1 đơn hàng (JSON API cho Modal).
     */
    public function show(int $id)
    {
        $order = DonHang::with([
            'nguoiDung',
            'dichVu',
            'loaiSanPham',
            'sanPham',
            'nhaCungCap',
            'nhaCungCapThanhCong',
            'lanGoiNhaCungCap' => fn($q) => $q->with(['nhaCungCap', 'ketNoi'])->orderBy('id', 'asc'),
            'lichSuTrangThai' => fn($q) => $q->orderByDesc('id'),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Xuất danh sách Hóa đơn ra file CSV/Excel.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $fileName = 'danh-sach-hoa-don-' . date('Y-m-d_His') . '.csv';

        $query = DonHang::query()->with([
            'nguoiDung',
            'dichVu',
            'loaiSanPham',
            'sanPham',
            'nhaCungCap',
        ]);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('ma_don_hang', 'like', "%{$q}%")
                    ->orWhere('tai_khoan_nhan', 'like', "%{$q}%")
                    ->orWhere('ma_don_doi_tac', 'like', "%{$q}%");
            });
        }
        if ($dichVuId = $request->query('dich_vu_id')) {
            $query->where('dich_vu_id', $dichVuId);
        }
        if ($status = $request->query('trang_thai_don_hang')) {
            $query->where('trang_thai_don_hang', $status);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // BOM cho UTF-8 Excel hiển thị tiếng Việt không bị lỗi font
            fputs($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Mã hóa đơn',
                'Thời gian tạo',
                'Khách hàng / Người nhận',
                'Số ĐT / TK nhận',
                'Dịch vụ',
                'Loại sản phẩm',
                'Tên sản phẩm',
                'Mệnh giá (VNĐ)',
                'Giá bán (VNĐ)',
                'Chiết khấu (%)',
                'Giá vốn (VNĐ)',
                'Lợi nhuận (VNĐ)',
                'Nhà cung cấp',
                'Trạng thái đơn hàng',
                'Trạng thái thanh toán',
            ]);

            $query->orderByDesc('id')->chunk(500, function ($orders) use ($handle) {
                foreach ($orders as $o) {
                    fputcsv($handle, [
                        $o->id,
                        $o->ma_don_hang,
                        optional($o->created_at)->format('d/m/Y H:i:s') ?? '',
                        $o->nguoiDung?->name ?? $o->nguoiDung?->ten_dang_nhap ?? 'Khách lẻ',
                        $o->tai_khoan_nhan ?? '',
                        $o->dichVu?->ten_dich_vu ?? '',
                        $o->loaiSanPham?->ten_loai_san_pham ?? '',
                        $o->ten_san_pham_snapshot ?: ($o->sanPham?->ten_san_pham ?? ''),
                        (float) $o->menh_gia,
                        (float) $o->gia_ban,
                        (float) $o->chiet_khau,
                        (float) ($o->gia_von_thuc_te ?: $o->gia_von),
                        (float) $o->loi_nhuan,
                        $o->nhaCungCap?->ten_ncc ?? '',
                        $o->trang_thai_don_hang,
                        $o->trang_thai_thanh_toan,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Admin hoàn tiền thủ công cho đơn hàng.
     * Chỉ cho phép từ trạng thái hợp lệ: FAILED, MANUAL_REVIEW, PROVIDER_PENDING, PROCESSING.
     * Dùng lockForUpdate() bên trong transaction để chống hoàn tiền 2 lần đồng thời.
     */
    public function refund(Request $request, int $id, \App\Services\Topup\DonHangStateMachine $state): RedirectResponse
    {
        $reason = trim((string) $request->input('ly_do_hoan_tien', 'Admin hoàn tiền thủ công'));

        $donHang = null;

        try {
            DB::transaction(function () use ($id, $state, $reason, &$donHang) {
                // lockForUpdate() bên trong transaction — chặn race condition hoàn tiền 2 lần
                $donHang = DonHang::lockForUpdate()->findOrFail($id);

                // Kiểm tra trạng thái SAU KHI đã lock (không tin dữ liệu trước lock)
                $validRefundStatuses = ['FAILED', 'MANUAL_REVIEW', 'PROVIDER_PENDING', 'PROCESSING'];
                $currentStatus = strtoupper((string) $donHang->trang_thai_don_hang);

                if ($donHang->trang_thai_thanh_toan === 'REFUNDED' || $currentStatus === 'REFUNDED') {
                    throw new \RuntimeException('Đơn hàng này đã được hoàn tiền từ trước.');
                }

                if (!in_array($currentStatus, $validRefundStatuses, true)) {
                    throw new \RuntimeException("Không thể hoàn tiền đơn hàng ở trạng thái: {$currentStatus}.");
                }

                // Hoàn tiền vào ví người dùng nếu có tài khoản
                if ($donHang->nguoi_dung_id && (float) $donHang->gia_ban > 0) {
                    $this->viService->hoanTien(
                        userId: $donHang->nguoi_dung_id,
                        soTien: (float) $donHang->gia_ban,
                        donHang: $donHang,
                        moTa: "Hoàn tiền đơn hàng #{$donHang->ma_don_hang}: {$reason}"
                    );
                }

                // Chuyển trạng thái qua state machine — KHÔNG ép trực tiếp bypass state machine
                $adminName = auth()->user()?->ten_dang_nhap ?? 'admin';
                $state->chuyen($donHang, \App\Enums\TrangThaiDonHang::REFUNDED, $reason, "admin_{$adminName}");
                $donHang->update([
                    'trang_thai_thanh_toan' => 'REFUNDED',
                    'thong_bao_loi_he_thong' => $reason,
                ]);

                // Gửi thông báo Telegram về nhóm Admin/Kế toán
                app(\App\Services\Alert\TelegramAlertService::class)->alertOrderRefunded(
                    $donHang,
                    $reason,
                    $adminName
                );
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('success', "Đã hoàn tiền thành công " . number_format($donHang->gia_ban, 0, ',', '.') . "đ cho đơn hàng #{$donHang->ma_don_hang}.");
    }

}
