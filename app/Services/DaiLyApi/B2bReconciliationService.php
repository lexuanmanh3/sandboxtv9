<?php

namespace App\Services\DaiLyApi;

use App\Models\ChiTietKyDoiSoat;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use App\Models\KyDoiSoat;
use App\Models\SoPhatSinhCongNo;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class B2bReconciliationService
{
    /** Các loại phát sinh tăng công nợ trên sổ cái. */
    private const LOAI_TANG = ['TANG_CONG_NO_DON_HANG', 'DIEU_CHINH_TANG'];

    /** Các loại phát sinh giảm công nợ trên sổ cái. */
    private const LOAI_GIAM = ['GIAM_CONG_NO_HOAN_TIEN', 'GIAM_CONG_NO_THANH_TOAN', 'DIEU_CHINH_GIAM'];

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

        return DB::transaction(function () use ($daiLy, $tu, $den, $maKy) {
            $existing = KyDoiSoat::where('dai_ly_api_id', $daiLy->id)
                ->where('ma_ky', $maKy)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->trang_thai === 'DA_KHOA') {
                    throw new DomainException("Kỳ đối soát '{$maKy}' đã được khóa và chốt sổ, không thể tạo lại.");
                }
                $ky = $existing;
            } else {
                // Chống kỳ trùng/chồng lấn: hai kỳ của cùng một đại lý không được giao nhau,
                // nếu không cùng một bút toán sẽ bị tính vào hai kỳ và công nợ bị nhân đôi.
                $overlap = KyDoiSoat::where('dai_ly_api_id', $daiLy->id)
                    ->where('ma_ky', '!=', $maKy)
                    ->where('tu_ngay', '<=', $den->toDateString())
                    ->where('den_ngay', '>=', $tu->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($overlap) {
                    throw new DomainException(
                        "Khoảng thời gian {$tu->toDateString()} - {$den->toDateString()} chồng lấn với kỳ '{$overlap->ma_ky}' ({$overlap->tu_ngay} - {$overlap->den_ngay})."
                    );
                }

                $ky = KyDoiSoat::create([
                    'dai_ly_api_id' => $daiLy->id,
                    'ma_ky' => $maKy,
                    'tu_ngay' => $tu->toDateString(),
                    'den_ngay' => $den->toDateString(),
                    'so_du_dau_ky' => 0,
                    'trang_thai' => 'DANG_MO',
                ]);
            }

            $this->tinhToanLaiKyTrongTransaction($ky);

            return $ky->fresh(['chiTiet', 'daiLyApi']);
        });
    }

    /**
     * Tính toán tổng hợp số liệu cho một kỳ đối soát đang mở.
     */
    public function tinhToanLaiKy(KyDoiSoat $ky): void
    {
        DB::transaction(function () use ($ky) {
            $this->tinhToanLaiKyTrongTransaction($ky);
        });
    }

    /**
     * Tính lại số liệu kỳ đối soát. BẮT BUỘC gọi bên trong một DB transaction
     * đã khóa dòng kỳ đối soát (xem taoKyDoiSoat / tinhToanLaiKy / khoaKy).
     *
     * Mọi số liệu đều lấy từ sổ phát sinh công nợ bất biến (so_phat_sinh_cong_no),
     * không lấy theo ngày tạo đơn. Nhờ đó đơn tạo cuối tháng trước nhưng thành công
     * đầu tháng này được ghi nhận đúng vào kỳ phát sinh nợ thực tế, và không bị
     * tính trùng vào kỳ cũ.
     */
    protected function tinhToanLaiKyTrongTransaction(KyDoiSoat $ky): void
    {
        // Khóa dòng kỳ để serialize với tác vụ khóa kỳ và các lần tính lại song song
        $locked = KyDoiSoat::where('id', $ky->id)->lockForUpdate()->first();

        if (!$locked) {
            throw new DomainException("Không tìm thấy kỳ đối soát #{$ky->id}.");
        }

        if ($locked->trang_thai === 'DA_KHOA') {
            throw new DomainException('Không được tính toán lại kỳ đối soát đã bị khóa.');
        }

        $tu = Carbon::parse($locked->tu_ngay)->startOfDay();
        $den = Carbon::parse($locked->den_ngay)->endOfDay();

        // 1. Số dư đầu kỳ = so_du_sau của bút toán cuối cùng TRƯỚC kỳ này (suy ra từ sổ cái)
        $butToanTruocKy = SoPhatSinhCongNo::where('dai_ly_api_id', $locked->dai_ly_api_id)
            ->where('created_at', '<', $tu)
            ->orderByDesc('id')
            ->first();

        $soDuDauKy = $butToanTruocKy ? (float) $butToanTruocKy->so_du_sau : 0.0;

        // 2. Toàn bộ bút toán phát sinh TRONG kỳ
        $butToanTrongKy = SoPhatSinhCongNo::where('dai_ly_api_id', $locked->dai_ly_api_id)
            ->whereBetween('created_at', [$tu, $den])
            ->orderBy('id')
            ->get();

        $tongPhatSinhTang = (float) $butToanTrongKy
            ->whereIn('loai_phat_sinh', self::LOAI_TANG)
            ->sum('so_tien');

        $tongPhatSinhGiam = (float) $butToanTrongKy
            ->where('loai_phat_sinh', 'GIAM_CONG_NO_HOAN_TIEN')
            ->sum('so_tien');

        $tongThanhToan = (float) $butToanTrongKy
            ->where('loai_phat_sinh', 'GIAM_CONG_NO_THANH_TOAN')
            ->sum('so_tien');

        $dieuChinhTang = (float) $butToanTrongKy->where('loai_phat_sinh', 'DIEU_CHINH_TANG')->sum('so_tien');
        $dieuChinhGiam = (float) $butToanTrongKy->where('loai_phat_sinh', 'DIEU_CHINH_GIAM')->sum('so_tien');
        $tongDieuChinh = $dieuChinhTang - $dieuChinhGiam;

        $soDuCuoiKy = $soDuDauKy + $tongPhatSinhTang - $tongPhatSinhGiam - $tongThanhToan + $tongDieuChinh;

        // 3. Đồng bộ chi tiết kỳ với đúng tập đơn hàng có phát sinh tăng nợ trong kỳ.
        //    Phải PRUNE các chi tiết không còn thuộc kỳ, nếu không tổng chi tiết sẽ lệch tổng kỳ.
        $donHangIds = $butToanTrongKy
            ->where('loai_phat_sinh', 'TANG_CONG_NO_DON_HANG')
            ->pluck('don_hang_id')
            ->filter()
            ->unique()
            ->values();

        $donHangs = DonHang::whereIn('id', $donHangIds->all())
            ->get()
            ->keyBy('id');

        foreach ($donHangIds as $donHangId) {
            $don = $donHangs->get($donHangId);
            if (!$don) {
                continue;
            }

            ChiTietKyDoiSoat::updateOrCreate(
                [
                    'ky_doi_soat_id' => $locked->id,
                    'don_hang_id' => $don->id,
                ],
                [
                    'so_tien' => $don->gia_ban,
                    'trang_thai_don_hang' => $don->trang_thai_don_hang,
                    'ngay_tao_don' => $don->created_at,
                ]
            );
        }

        // Xóa các chi tiết không còn thuộc kỳ (đơn đã rời kỳ do tính lại)
        $pruneQuery = ChiTietKyDoiSoat::where('ky_doi_soat_id', $locked->id);
        if ($donHangIds->isNotEmpty()) {
            $pruneQuery->whereNotIn('don_hang_id', $donHangIds->all());
        }
        $pruneQuery->delete();

        $locked->update([
            'so_du_dau_ky' => $soDuDauKy,
            'tong_phat_sinh_tang' => $tongPhatSinhTang,
            'tong_phat_sinh_giam' => $tongPhatSinhGiam,
            'tong_thanh_toan' => $tongThanhToan,
            'tong_dieu_chinh' => $tongDieuChinh,
            'so_du_cuoi_ky' => $soDuCuoiKy,
        ]);

        $ky->refresh();
    }

    /**
     * Khóa kỳ đối soát để chốt số liệu công nợ.
     *
     * Tính lại lần cuối NGAY TRONG cùng transaction với thao tác khóa, dưới khóa dòng,
     * để một tác vụ tính lại song song không thể ghi số liệu sau khi kỳ đã bị khóa.
     */
    public function khoaKy(KyDoiSoat $ky, ?int $nguoiKhoaId = null): void
    {
        DB::transaction(function () use ($ky, $nguoiKhoaId) {
            $locked = KyDoiSoat::where('id', $ky->id)->lockForUpdate()->first();

            if (!$locked || $locked->trang_thai === 'DA_KHOA') {
                return;
            }

            $this->tinhToanLaiKyTrongTransaction($locked);

            $locked->update([
                'trang_thai' => 'DA_KHOA',
                'ngay_khoa' => now(),
                'nguoi_khoa_id' => $nguoiKhoaId,
            ]);

            $ky->refresh();
        });
    }
}
