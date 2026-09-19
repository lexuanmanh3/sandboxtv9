<?php

namespace App\Services\DaiLyApi;

use App\Models\ChiTietKyDoiSoat;
use App\Models\DaiLyApi;
use App\Models\DieuChinhCongNo;
use App\Models\DonHang;
use App\Models\KyDoiSoat;
use App\Models\SoPhatSinhCongNo;
use App\Models\ThanhToanCongNo;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class B2bReconciliationService
{
    /**
     * Tạo kỳ đối soát định kỳ (tháng/tuần) cho đại lý.
     */
    public function taoKyDoiSoat(DaiLyApi $daiLy, string $tuNgay, string $denNgay, ?int $nguoiTaoId = null): KyDoiSoat
    {
        $tu = Carbon::parse($tuNgay)->startOfDay();
        $den = Carbon::parse($denNgay)->endOfDay();

        if ($tu->gt($den)) {
            throw new DomainException('Từ ngày không được lớn hơn Đến ngày.');
        }

        $isFullMonth = ($tu->copy()->startOfMonth()->toDateString() === $tu->toDateString() && $den->copy()->endOfMonth()->toDateString() === $den->toDateString());
        $maKy = $isFullMonth
            ? ('DS-' . $daiLy->ma_dai_ly_api . '-' . $tu->format('Ym'))
            : ('DS-' . $daiLy->ma_dai_ly_api . '-' . $tu->format('Ymd') . '-' . $den->format('Ymd'));

        // Kiểm tra xem kỳ đã tồn tại chưa
        $existing = KyDoiSoat::where('dai_ly_api_id', $daiLy->id)
            ->where('ma_ky', $maKy)
            ->first();

        if ($existing) {
            if ($existing->trang_thai === 'DA_KHOA') {
                throw new DomainException("Kỳ đối soát '{$maKy}' đã được khóa và chốt sổ, không thể tạo lại.");
            }
            $ky = $existing;
        } else {
            // Tìm số dư cuối kỳ của kỳ gần nhất làm số dư đầu kỳ
            $kyTruoc = KyDoiSoat::where('dai_ly_api_id', $daiLy->id)
                ->where('den_ngay', '<', $tu->toDateString())
                ->orderByDesc('den_ngay')
                ->first();

            $soDuDauKy = $kyTruoc ? (float) $kyTruoc->so_du_cuoi_ky : 0.0;

            $ky = KyDoiSoat::create([
                'dai_ly_api_id' => $daiLy->id,
                'ma_ky' => $maKy,
                'tu_ngay' => $tu->toDateString(),
                'den_ngay' => $den->toDateString(),
                'so_du_dau_ky' => $soDuDauKy,
                'trang_thai' => 'DANG_MO',
            ]);
        }

        $this->tinhToanLaiKy($ky);

        return $ky->fresh(['chiTiet', 'daiLyApi']);
    }

    /**
     * Tính toán tổng hợp số liệu cho một kỳ đối soát đang mở.
     */
    public function tinhToanLaiKy(KyDoiSoat $ky): void
    {
        if ($ky->trang_thai === 'DA_KHOA') {
            throw new DomainException('Không được tính toán lại kỳ đối soát đã bị khóa.');
        }

        DB::transaction(function () use ($ky) {
            $tu = Carbon::parse($ky->tu_ngay)->startOfDay();
            $den = Carbon::parse($ky->den_ngay)->endOfDay();

            // 1. Quét danh sách đơn hàng hoàn thành trong kỳ dựa trên Sổ phát sinh công nợ
            // Điều này đảm bảo đơn hàng tạo cuối tháng trước nhưng thành công vào đầu tháng này
            // sẽ được ghi nhận chính xác vào kỳ đối soát tương ứng theo thời điểm phát sinh nợ thực tế.
            $ledgerEntries = SoPhatSinhCongNo::where('dai_ly_api_id', $ky->dai_ly_api_id)
                ->where('loai_phat_sinh', 'TANG_CONG_NO_DON_HANG')
                ->whereBetween('created_at', [$tu, $den])
                ->get();

            $orderIdsFromLedger = $ledgerEntries->pluck('don_hang_id')->filter()->all();

            $donHangs = DonHang::where('dai_ly_api_id', $ky->dai_ly_api_id)
                ->where('trang_thai_don_hang', 'SUCCESS')
                ->where(function ($q) use ($tu, $den, $orderIdsFromLedger) {
                    if (!empty($orderIdsFromLedger)) {
                        $q->whereIn('id', $orderIdsFromLedger)
                          ->orWhereBetween('created_at', [$tu, $den]);
                    } else {
                        $q->whereBetween('created_at', [$tu, $den]);
                    }
                })
                ->get();

            // Cập nhật chi tiết kỳ
            foreach ($donHangs as $don) {
                ChiTietKyDoiSoat::firstOrCreate(
                    [
                        'ky_doi_soat_id' => $ky->id,
                        'don_hang_id' => $don->id,
                    ],
                    [
                        'so_tien' => $don->gia_ban,
                        'trang_thai_don_hang' => $don->trang_thai_don_hang,
                        'ngay_tao_don' => $don->created_at,
                    ]
                );
            }

            // 2. Tổng phát sinh tăng (Đơn thành công)
            $tongPhatSinhTang = (float) $donHangs->sum('gia_ban');

            // 3. Tổng phát sinh giảm (Hoàn tiền trong kỳ)
            $tongPhatSinhGiam = (float) SoPhatSinhCongNo::where('dai_ly_api_id', $ky->dai_ly_api_id)
                ->where('loai_phat_sinh', 'GIAM_CONG_NO_HOAN_TIEN')
                ->whereBetween('created_at', [$tu, $den])
                ->sum('so_tien');

            // 4. Tổng thanh toán
            $tongThanhToan = (float) ThanhToanCongNo::where('dai_ly_api_id', $ky->dai_ly_api_id)
                ->where('trang_thai', 'DA_XAC_NHAN')
                ->whereBetween('ngay_thanh_toan', [$tu, $den])
                ->sum('so_tien');

            // 5. Tổng điều chỉnh
            $dieuChinhTang = (float) DieuChinhCongNo::where('dai_ly_api_id', $ky->dai_ly_api_id)
                ->where('loai_dieu_chinh', 'TANG_NO')
                ->whereBetween('created_at', [$tu, $den])
                ->sum('so_tien');

            $dieuChinhGiam = (float) DieuChinhCongNo::where('dai_ly_api_id', $ky->dai_ly_api_id)
                ->where('loai_dieu_chinh', 'GIAM_NO')
                ->whereBetween('created_at', [$tu, $den])
                ->sum('so_tien');

            $tongDieuChinh = $dieuChinhTang - $dieuChinhGiam;

            // 6. Số dư cuối kỳ = Số dư đầu kỳ + Tăng - Giảm - Thanh toán + Điều chỉnh
            $soDuCuoiKy = (float) $ky->so_du_dau_ky + $tongPhatSinhTang - $tongPhatSinhGiam - $tongThanhToan + $tongDieuChinh;

            $ky->update([
                'tong_phat_sinh_tang' => $tongPhatSinhTang,
                'tong_phat_sinh_giam' => $tongPhatSinhGiam,
                'tong_thanh_toan' => $tongThanhToan,
                'tong_dieu_chinh' => $tongDieuChinh,
                'so_du_cuoi_ky' => $soDuCuoiKy,
            ]);
        });
    }

    /**
     * Khóa kỳ đối soát để chốt số liệu công nợ.
     */
    public function khoaKy(KyDoiSoat $ky, ?int $nguoiKhoaId = null): void
    {
        if ($ky->trang_thai === 'DA_KHOA') {
            return;
        }

        $ky->update([
            'trang_thai' => 'DA_KHOA',
            'ngay_khoa' => now(),
            'nguoi_khoa_id' => $nguoiKhoaId,
        ]);
    }
}
