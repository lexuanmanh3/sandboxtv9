<?php

namespace App\Services\DaiLyApi;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DieuChinhCongNo;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\SoPhatSinhCongNo;
use App\Models\ThanhToanCongNo;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class B2bCreditService
{
    /**
     * Tính toán thông tin hạn mức và công nợ khả dụng của đại lý.
     */
    public function tinhHanMucKhaDung(DaiLyApi $daiLy): array
    {
        $cauHinh = $daiLy->cauHinhApi ?: CauHinhApiDaiLy::firstOrCreate(['dai_ly_api_id' => $daiLy->id]);

        $hanMucDuocCap = (float) $cauHinh->han_muc_cong_no;
        $congNoHienTai = (float) $cauHinh->cong_no_hien_tai;

        // Tổng các khoản tiền đang bị giữ cho các đơn đang xử lý
        $khoanDangGiu = (float) KhoanGiuHanMuc::where('dai_ly_api_id', $daiLy->id)
            ->where('trang_thai', 'HOLDING')
            ->sum('so_tien_giu');

        $hanMucKhaDung = max(0.0, $hanMucDuocCap - $congNoHienTai - $khoanDangGiu);
        $nguongCanhBao = (float) $cauHinh->nguong_canh_bao_han_muc;
        $canhBaoVuotNguong = ($nguongCanhBao > 0 && ($congNoHienTai + $khoanDangGiu) >= $nguongCanhBao);

        return [
            'han_muc_duoc_cap' => $hanMucDuocCap,
            'cong_no_hien_tai' => $congNoHienTai,
            'khoan_dang_giu' => $khoanDangGiu,
            'han_muc_kha_dung' => $hanMucKhaDung,
            'nguong_canh_bao' => $nguongCanhBao,
            'canh_bao_vuot_nguong' => $canhBaoVuotNguong,
            'cho_phep_nhan_don' => (bool) $cauHinh->cho_phep_nhan_don,
        ];
    }

    /**
     * Kiểm tra và giữ hạn mức nguyên tử cho đơn hàng B2B mới.
     * Yêu cầu chạy bên trong DB transaction.
     */
    public function kiemTraVaGiuHanMuc(DaiLyApi $daiLy, DonHang $donHang, float $soTien): KhoanGiuHanMuc
    {
        // 1. Khóa dòng cấu hình đối tác để chống race-condition
        $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (!$cauHinh->cho_phep_nhan_don) {
            throw new DomainException('Đại lý đang bị tạm ngừng tiếp nhận đơn mới.', 403);
        }

        $hanMucDuocCap = (float) $cauHinh->han_muc_cong_no;
        $congNoHienTai = (float) $cauHinh->cong_no_hien_tai;

        // Tính tổng tiền đang bị giữ
        $khoanDangGiu = (float) KhoanGiuHanMuc::where('dai_ly_api_id', $daiLy->id)
            ->where('trang_thai', 'HOLDING')
            ->lockForUpdate()
            ->sum('so_tien_giu');

        $hanMucKhaDung = $hanMucDuocCap - $congNoHienTai - $khoanDangGiu;

        if ($soTien > $hanMucKhaDung) {
            throw new DomainException(
                "Hạn mức công nợ khả dụng không đủ (Cần: {$soTien}, Khả dụng: {$hanMucKhaDung}).",
                422
            );
        }

        // 2. Tạo bản ghi giữ hạn mức
        return KhoanGiuHanMuc::create([
            'dai_ly_api_id' => $daiLy->id,
            'don_hang_id' => $donHang->id,
            'so_tien_giu' => $soTien,
            'trang_thai' => 'HOLDING',
        ]);
    }

    /**
     * Chốt công nợ khi đơn hàng B2B thành công:
     * Chuyển khoản giữ sang COMMITTED và ghi tăng sổ phát sinh công nợ.
     */
    public function chotCongNoThanhCong(DonHang $donHang): void
    {
        if (!$donHang->dai_ly_api_id) {
            return;
        }

        DB::transaction(function () use ($donHang) {
            $hold = KhoanGiuHanMuc::where('don_hang_id', $donHang->id)
                ->lockForUpdate()
                ->first();

            // Nếu đã commit trước đó (idempotent), bỏ qua
            if ($hold && $hold->trang_thai === 'COMMITTED') {
                return;
            }

            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $donHang->dai_ly_api_id)
                ->lockForUpdate()
                ->firstOrFail();

            $soTien = (float) $donHang->gia_ban;
            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;
            $soDuSau = $soDuTruoc + $soTien;

            // Cập nhật công nợ
            $cauHinh->cong_no_hien_tai = $soDuSau;
            $cauHinh->save();

            // Chốt khoản giữ
            if ($hold) {
                $hold->update([
                    'trang_thai' => 'COMMITTED',
                    'chot_cong_no_luc' => now(),
                ]);
            } else {
                KhoanGiuHanMuc::create([
                    'dai_ly_api_id' => $donHang->dai_ly_api_id,
                    'don_hang_id' => $donHang->id,
                    'so_tien_giu' => $soTien,
                    'trang_thai' => 'COMMITTED',
                    'chot_cong_no_luc' => now(),
                ]);
            }

            // Ghi sổ cái phát sinh công nợ (Immutable ledger)
            $maThamChieu = 'TX-ORD-' . $donHang->id;
            SoPhatSinhCongNo::firstOrCreate(
                [
                    'dai_ly_api_id' => $donHang->dai_ly_api_id,
                    'ma_tham_chieu' => $maThamChieu,
                ],
                [
                    'don_hang_id' => $donHang->id,
                    'loai_phat_sinh' => 'TANG_CONG_NO_DON_HANG',
                    'so_tien' => $soTien,
                    'so_du_truoc' => $soDuTruoc,
                    'so_du_sau' => $soDuSau,
                    'ghi_chu' => "Ghi nhận công nợ đơn hàng #{$donHang->ma_don_hang} (Đối tác ref: {$donHang->ma_don_doi_tac})",
                ]
            );
        });
    }

    /**
     * Giải phóng khoản giữ khi đơn hàng B2B thất bại chắc chắn.
     */
    public function giaiPhongKhoanGiu(DonHang $donHang, string $lyDo = 'Đơn hàng thất bại'): void
    {
        if (!$donHang->dai_ly_api_id) {
            return;
        }

        DB::transaction(function () use ($donHang, $lyDo) {
            $hold = KhoanGiuHanMuc::where('don_hang_id', $donHang->id)
                ->lockForUpdate()
                ->first();

            if (!$hold || $hold->trang_thai !== 'HOLDING') {
                return;
            }

            $hold->update([
                'trang_thai' => 'RELEASED',
                'giai_phong_luc' => now(),
                'ly_do_giai_phong' => $lyDo,
            ]);
        });
    }

    /**
     * Hoàn tiền / giảm công nợ cho đơn B2B đã thành công trước đó.
     * Tuyệt đối không cộng vào ví B2C.
     */
    public function hoanTienGiamCongNo(DonHang $donHang, string $lyDo, ?int $nguoiThaoTacId = null): void
    {
        if (!$donHang->dai_ly_api_id) {
            return;
        }

        DB::transaction(function () use ($donHang, $lyDo, $nguoiThaoTacId) {
            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $donHang->dai_ly_api_id)
                ->lockForUpdate()
                ->firstOrFail();

            $soTien = (float) $donHang->gia_ban;
            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;
            $soDuSau = max(0.0, $soDuTruoc - $soTien);

            $cauHinh->cong_no_hien_tai = $soDuSau;
            $cauHinh->save();

            $maThamChieu = 'TX-REF-' . $donHang->id;
            SoPhatSinhCongNo::firstOrCreate(
                [
                    'dai_ly_api_id' => $donHang->dai_ly_api_id,
                    'ma_tham_chieu' => $maThamChieu,
                ],
                [
                    'don_hang_id' => $donHang->id,
                    'loai_phat_sinh' => 'GIAM_CONG_NO_HOAN_TIEN',
                    'so_tien' => $soTien,
                    'so_du_truoc' => $soDuTruoc,
                    'so_du_sau' => $soDuSau,
                    'ghi_chu' => "Hoàn tiền giảm công nợ đơn hàng #{$donHang->ma_don_hang}: {$lyDo}",
                    'nguoi_tao_id' => $nguoiThaoTacId,
                ]
            );
        });
    }

    /**
     * Ghi nhận thanh toán từ đại lý (chuyển khoản trả nợ).
     */
    public function ghiNhanThanhToan(
        DaiLyApi $daiLy,
        float $soTien,
        string $phuongThuc = 'CHUYEN_KHOAN',
        ?string $maGiaoDichNganHang = null,
        ?string $ghiChu = null,
        ?int $nguoiTaoId = null,
        ?string $maThanhToan = null
    ): ThanhToanCongNo {
        return DB::transaction(function () use ($daiLy, $soTien, $phuongThuc, $maGiaoDichNganHang, $ghiChu, $nguoiTaoId, $maThanhToan) {
            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)
                ->lockForUpdate()
                ->firstOrFail();

            $maTT = $maThanhToan ?: 'PAY-' . date('YmdHis') . '-' . strtoupper(Str::random(6));

            $payment = ThanhToanCongNo::create([
                'dai_ly_api_id' => $daiLy->id,
                'ma_thanh_toan' => $maTT,
                'so_tien' => $soTien,
                'ngay_thanh_toan' => now(),
                'phuong_thuc' => $phuongThuc,
                'ma_giao_dich_ngan_hang' => $maGiaoDichNganHang,
                'ghi_chu' => $ghiChu,
                'trang_thai' => 'DA_XAC_NHAN',
                'nguoi_tao_id' => $nguoiTaoId,
            ]);

            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;
            $soDuSau = max(0.0, $soDuTruoc - $soTien);

            $cauHinh->cong_no_hien_tai = $soDuSau;
            $cauHinh->save();

            // Ghi sổ cái
            SoPhatSinhCongNo::create([
                'dai_ly_api_id' => $daiLy->id,
                'thanh_toan_id' => $payment->id,
                'loai_phat_sinh' => 'GIAM_CONG_NO_THANH_TOAN',
                'so_tien' => $soTien,
                'so_du_truoc' => $soDuTruoc,
                'so_du_sau' => $soDuSau,
                'ma_tham_chieu' => 'TX-PAY-' . $payment->ma_thanh_toan,
                'ghi_chu' => "Thanh toán công nợ: {$maTT}" . ($maGiaoDichNganHang ? " (Ref: {$maGiaoDichNganHang})" : ''),
                'nguoi_tao_id' => $nguoiTaoId,
            ]);

            return $payment;
        });
    }

    /**
     * Ghi nhận bút toán điều chỉnh công nợ.
     */
    public function ghiNhanDieuChinh(
        DaiLyApi $daiLy,
        string $loaiDieuChinh, // TANG_NO | GIAM_NO
        float $soTien,
        string $lyDo,
        ?string $maThamChieuGoc = null,
        ?int $kyDoiSoatId = null,
        ?int $nguoiTaoId = null
    ): DieuChinhCongNo {
        return DB::transaction(function () use ($daiLy, $loaiDieuChinh, $soTien, $lyDo, $maThamChieuGoc, $kyDoiSoatId, $nguoiTaoId) {
            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)
                ->lockForUpdate()
                ->firstOrFail();

            $maDC = 'ADJ-' . date('YmdHis') . '-' . strtoupper(Str::random(6));

            $adjustment = DieuChinhCongNo::create([
                'dai_ly_api_id' => $daiLy->id,
                'ky_doi_soat_id' => $kyDoiSoatId,
                'ma_dieu_chinh' => $maDC,
                'loai_dieu_chinh' => $loaiDieuChinh,
                'so_tien' => $soTien,
                'ly_do' => $lyDo,
                'ma_tham_chieu_goc' => $maThamChieuGoc,
                'nguoi_tao_id' => $nguoiTaoId,
                'created_at' => now(),
            ]);

            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;
            $soDuSau = ($loaiDieuChinh === 'TANG_NO')
                ? ($soDuTruoc + $soTien)
                : max(0.0, $soDuTruoc - $soTien);

            $cauHinh->cong_no_hien_tai = $soDuSau;
            $cauHinh->save();

            // Ghi sổ cái
            SoPhatSinhCongNo::create([
                'dai_ly_api_id' => $daiLy->id,
                'dieu_chinh_id' => $adjustment->id,
                'ky_doi_soat_id' => $kyDoiSoatId,
                'loai_phat_sinh' => ($loaiDieuChinh === 'TANG_NO' ? 'DIEU_CHINH_TANG' : 'DIEU_CHINH_GIAM'),
                'so_tien' => $soTien,
                'so_du_truoc' => $soDuTruoc,
                'so_du_sau' => $soDuSau,
                'ma_tham_chieu' => 'TX-ADJ-' . $adjustment->ma_dieu_chinh,
                'ghi_chu' => "Điều chỉnh công nợ ({$loaiDieuChinh}): {$lyDo}",
                'nguoi_tao_id' => $nguoiTaoId,
            ]);

            return $adjustment;
        });
    }
}
