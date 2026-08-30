<?php

namespace App\Services\Audit;

use App\Models\NhatKyKiemTra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Ghi sự kiện bảo mật sau khi loại bỏ đệ quy các trường dữ liệu nhạy cảm. */
class AuditService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token',
        'refresh_token', 'remember_token', 'cookie', 'secret', 'api_key',
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        string $status = 'thanh_cong',
        ?string $message = null,
        ?Request $request = null
    ): void {
        try {
            $request ??= request();
            NhatKyKiemTra::create([
                'nguoi_thuc_hien_id' => auth()->id(),
                'hanh_dong' => $action,
                'doi_tuong_type' => $subject ? $subject::class : null,
                'doi_tuong_id' => $subject?->getKey(),
                'du_lieu_truoc' => $this->sanitize($before),
                'du_lieu_sau' => $this->sanitize($after),
                'ip' => $request->ip(),
                'route' => optional($request->route())->getName() ?: $request->path(),
                'trang_thai' => $status,
                'thong_tin' => $message,
            ]);
        } catch (Throwable $exception) {
            // Lỗi ghi nhật ký không được làm lộ chi tiết hoặc biến phản hồi từ chối quyền thành lỗi 500.
            Log::warning('Không thể ghi nhật ký kiểm tra.', ['exception' => $exception::class]);
        }
    }

    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return collect($data)->mapWithKeys(function ($value, $key) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                return [$key => '[REDACTED]'];
            }
            return [$key => is_array($value) ? $this->sanitize($value) : $value];
        })->all();
    }
}
