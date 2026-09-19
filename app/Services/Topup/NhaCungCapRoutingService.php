<?php

namespace App\Services\Topup;

use App\Models\CauHinhDichVu;
use App\Models\DonHang;
use App\Models\SanPhamNhaCungCap;
use Illuminate\Support\Collection;

final class NhaCungCapRoutingService
{
    public function danhSach(DonHang $donHang): Collection
    {
        $daGoi = $donHang->lanGoiNhaCungCap()->whereNotNull('san_pham_nha_cung_cap_id')->pluck('san_pham_nha_cung_cap_id');

        // 1. Kiểm tra cấu hình tuyến dịch vụ (CauHinhDichVu)
        $configsQuery = CauHinhDichVu::query()
            ->where('dang_mo', true)
            ->where(function ($q) use ($donHang) {
                $q->whereNull('dich_vu_id')->orWhere('dich_vu_id', $donHang->dich_vu_id);
            })
            ->where(function ($q) use ($donHang) {
                $q->whereNull('loai_san_pham_id')->orWhere('loai_san_pham_id', $donHang->loai_san_pham_id);
            });

        if ($donHang->dai_ly_api_id) {
            $configsQuery->where(function ($q) use ($donHang) {
                $q->where('dai_ly_ap_dung_id', $donHang->dai_ly_api_id)
                  ->orWhereNull('dai_ly_ap_dung_id');
            });
        } else {
            $configsQuery->whereNull('dai_ly_ap_dung_id');
        }

        $allConfigs = $configsQuery->get();

        // Lọc các tuyến khớp với sản phẩm cụ thể
        $matchingConfigs = $allConfigs->filter(function ($cfg) use ($donHang) {
            if (!empty($cfg->danh_sach_san_pham_id) && is_array($cfg->danh_sach_san_pham_id)) {
                return in_array((int) $donHang->san_pham_id, array_map('intval', $cfg->danh_sach_san_pham_id), true);
            }
            if ($cfg->san_pham_id) {
                return (int) $cfg->san_pham_id === (int) $donHang->san_pham_id;
            }
            return true;
        });

        if ($matchingConfigs->isNotEmpty()) {
            // Sắp xếp theo: Đại lý riêng trước > Sản phẩm cụ thể trước > Mức ưu tiên
            $sortedConfigs = $matchingConfigs->sort(function ($a, $b) use ($donHang) {
                $aPartner = ($a->dai_ly_ap_dung_id === $donHang->dai_ly_api_id && $donHang->dai_ly_api_id) ? 0 : 1;
                $bPartner = ($b->dai_ly_ap_dung_id === $donHang->dai_ly_api_id && $donHang->dai_ly_api_id) ? 0 : 1;
                if ($aPartner !== $bPartner) return $aPartner <=> $bPartner;

                $aSpecific = (!empty($a->danh_sach_san_pham_id) || $a->san_pham_id) ? 0 : ($a->loai_san_pham_id ? 1 : 2);
                $bSpecific = (!empty($b->danh_sach_san_pham_id) || $b->san_pham_id) ? 0 : ($b->loai_san_pham_id ? 1 : 2);
                if ($aSpecific !== $bSpecific) return $aSpecific <=> $bSpecific;

                return ($a->muc_uu_tien ?? 1) <=> ($b->muc_uu_tien ?? 1);
            });

            $result = collect();
            foreach ($sortedConfigs as $cfg) {
                $mapping = SanPhamNhaCungCap::query()
                    ->with(['nhaCungCap', 'ketNoi.nhaCungCap'])
                    ->where('san_pham_id', $donHang->san_pham_id)
                    ->where('nha_cung_cap_id', $cfg->nha_cung_cap_id)
                    ->whereIn('trang_thai', ['ACTIVE', 'hoat_dong'])
                    ->whereHas('nhaCungCap', fn ($q) => $q->whereIn('trang_thai', ['ACTIVE', 'hoat_dong']))
                    ->whereHas('ketNoi', fn ($q) => $q->whereIn('trang_thai', ['ACTIVE', 'hoat_dong']))
                    ->whereNotIn('id', $daGoi)
                    ->orderByRaw('gia_nhap IS NULL')->orderBy('gia_nhap')
                    ->first();

                if ($mapping && !$result->contains('id', $mapping->id)) {
                    $mapping->cauHinhDichVu = $cfg;
                    $result->push($mapping);
                }
            }

            if ($result->isNotEmpty()) {
                return $result;
            }
        }

        // 2. Mặc định fallback về truy vấn SanPhamNhaCungCap nếu không có CauHinhDichVu phù hợp
        return SanPhamNhaCungCap::query()
            ->with(['nhaCungCap', 'ketNoi.nhaCungCap'])
            ->where('san_pham_id', $donHang->san_pham_id)
            ->whereIn('trang_thai', ['ACTIVE', 'hoat_dong'])
            ->whereHas('nhaCungCap', fn ($q) => $q->whereIn('trang_thai', ['ACTIVE', 'hoat_dong']))
            ->whereHas('ketNoi', fn ($q) => $q->whereIn('trang_thai', ['ACTIVE', 'hoat_dong']))
            ->whereNotIn('id', $daGoi)
            ->orderBy('muc_uu_tien')->orderByRaw('gia_nhap IS NULL')->orderBy('gia_nhap')
            ->get();
    }
}
