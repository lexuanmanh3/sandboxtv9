<?php

namespace App\Services\Topup\AppotaPay;

use App\Enums\KetQuaNhaCungCap;
use App\Models\MaLoiNhaCungCap;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class AppotaPayErrorMapper
{
    public function map(?string $code, ?int $nhaCungCapId = null): KetQuaNhaCungCap
    {
        if ($code === null || $code === '') {
            return KetQuaNhaCungCap::UNKNOWN_OR_PENDING;
        }

        if ((string) $code === '0') {
            return KetQuaNhaCungCap::SUCCESS;
        }

        try {
            $cacheKey = "err_map_" . ($nhaCungCapId ?: 'all') . "_{$code}";
            $configured = Cache::remember($cacheKey, 300, function () use ($code, $nhaCungCapId) {
                return MaLoiNhaCungCap::query()
                    ->active()
                    ->forProvider($nhaCungCapId)
                    ->where('ma_loi', (string) $code)
                    ->orderByRaw('nha_cung_cap_id IS NULL') // Ưu tiên cấu hình riêng cho NCC trước
                    ->first();
            });

            if ($configured) {
                return match ($configured->loai_ket_qua) {
                    MaLoiNhaCungCap::RESULT_SUCCESS => KetQuaNhaCungCap::SUCCESS,
                    MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING => KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
                    default => KetQuaNhaCungCap::DEFINITIVE_FAILURE,
                };
            }
        } catch (Throwable) {
            // Fallback dự phòng nếu DB có sự cố
        }

        // Quy tắc mặc định an toàn nếu chưa thiết lập trong DB
        if (in_array((string) $code, ['34', '35', '99'], true)) {
            return KetQuaNhaCungCap::UNKNOWN_OR_PENDING;
        }

        return KetQuaNhaCungCap::DEFINITIVE_FAILURE;
    }
}
