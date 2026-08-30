<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NhatKyKiemTra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Danh sách Nhật ký hoạt động (Audit Logs).
     */
    public function index(Request $request): View
    {
        $query = $this->buildQuery($request);

        $logs = $query->with('nguoiThucHien')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $services = NhatKyKiemTra::query()
            ->whereNotNull('dich_vu')
            ->select('dich_vu')
            ->distinct()
            ->orderBy('dich_vu')
            ->pluck('dich_vu');

        return view('admin.audit-logs', [
            'logs' => $logs,
            'services' => $services,
            'filters' => $request->only(['q', 'dich_vu', 'trang_thai', 'tu_ngay', 'den_ngay']),
        ]);
    }

    /**
     * Lấy chi tiết một bản ghi log phục vụ Modal xem chi tiết (🔍).
     */
    public function detail(NhatKyKiemTra $log): JsonResponse
    {
        $log->load('nguoiThucHien');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $log->id,
                'ten_truy_cap' => $log->nguoiThucHien?->name ?? $log->khach_hang ?? 'backend',
                'dich_vu' => $log->dich_vu ?: 'UnknownController',
                'hoat_dong' => $log->hoat_dong ?: 'Index',
                'thoi_gian_thuc_thi' => ($log->thoi_gian_thuc_thi_ms ?? 0) . ' mili giây',
                'ip' => $log->ip ?: '127.0.0.1',
                'user_agent' => $log->user_agent ?: 'Unknown User-Agent',
                'khach_hang' => $log->khach_hang ?: '-',
                'trang_thai' => $log->trang_thai,
                'thong_tin' => $log->thong_tin,
                'tham_so' => $log->tham_so,
                'du_lieu_truoc' => $log->du_lieu_truoc,
                'du_lieu_sau' => $log->du_lieu_sau,
                'loi_ngoai_le' => $log->loi_ngoai_le,
                'thoi_gian' => optional($log->created_at)->format('Y-m-d H:i:s') ?: '-',
            ],
        ]);
    }

    /**
     * Xuất danh sách nhật ký hoạt động ra file CSV (UTF-8 BOM).
     */
    public function export(Request $request): StreamedResponse
    {
        $logs = $this->buildQuery($request)->with('nguoiThucHien')->orderByDesc('id')->limit(5000)->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // Thêm UTF-8 BOM để Excel không lỗi font tiếng Việt
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['ID', 'Trạng thái', 'Tên truy cập', 'Dịch vụ', 'Hoạt động', 'Khoảng thời gian (ms)', 'Địa chỉ IP', 'Khách hàng', 'Trình duyệt', 'Thời gian', 'Lỗi ngoại lệ']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->trang_thai === 'thanh_cong' ? 'Thành công' : 'Lỗi / Thất bại',
                    $log->nguoiThucHien?->name ?? $log->khach_hang ?? 'backend',
                    $log->dich_vu ?: '-',
                    $log->hoat_dong ?: '-',
                    $log->thoi_gian_thuc_thi_ms ?? 0,
                    $log->ip ?: '-',
                    $log->khach_hang ?: '-',
                    $log->user_agent ?: '-',
                    optional($log->created_at)->format('Y-m-d H:i:s') ?: '-',
                    $log->loi_ngoai_le ?: '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function buildQuery(Request $request): Builder
    {
        $query = NhatKyKiemTra::query();

        if ($keyword = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('hanh_dong', 'like', "%{$keyword}%")
                  ->orWhere('dich_vu', 'like', "%{$keyword}%")
                  ->orWhere('hoat_dong', 'like', "%{$keyword}%")
                  ->orWhere('ip', 'like', "%{$keyword}%")
                  ->orWhere('khach_hang', 'like', "%{$keyword}%")
                  ->orWhere('thong_tin', 'like', "%{$keyword}%");
            });
        }

        if ($dichVu = $request->query('dich_vu')) {
            $query->where('dich_vu', $dichVu);
        }

        if ($trangThai = $request->query('trang_thai')) {
            $query->where('trang_thai', $trangThai);
        }

        if ($tuNgay = $request->query('tu_ngay')) {
            $query->whereDate('created_at', '>=', $tuNgay);
        }

        if ($denNgay = $request->query('den_ngay')) {
            $query->whereDate('created_at', '<=', $denNgay);
        }

        return $query;
    }
}
