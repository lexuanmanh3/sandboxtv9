<?php

namespace App\Services\Topup;

use App\Models\DonHang;
use App\Models\SanPhamNhaCungCap;
use Illuminate\Support\Collection;

final class NhaCungCapRoutingService
{
    public function danhSach(DonHang $donHang): Collection
    {
        $daGoi = $donHang->lanGoiNhaCungCap()->whereNotNull('san_pham_nha_cung_cap_id')->pluck('san_pham_nha_cung_cap_id');

        return SanPhamNhaCungCap::query()
            ->with(['nhaCungCap', 'ketNoi.nhaCungCap'])
            ->where('san_pham_id', $donHang->san_pham_id)
            ->where('trang_thai', 'ACTIVE')
            ->whereHas('nhaCungCap', fn ($q) => $q->whereIn('trang_thai', ['ACTIVE', 'hoat_dong']))
            ->whereHas('ketNoi', fn ($q) => $q->whereIn('trang_thai', ['ACTIVE', 'hoat_dong']))
            ->whereNotIn('id', $daGoi)
            ->orderBy('muc_uu_tien')->orderByRaw('gia_nhap IS NULL')->orderBy('gia_nhap')
            ->get()
            ->filter(function ($item) {
                // Bỏ qua kết nối đang trong thời gian tạm dừng của Circuit Breaker
                return !\Illuminate\Support\Facades\Cache::has("circuit_breaker_tripped_{$item->ket_noi_nha_cung_cap_id}");
            });
    }
}
