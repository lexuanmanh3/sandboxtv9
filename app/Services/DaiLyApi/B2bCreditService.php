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
use Illuminate\Support\Facades\Log;
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
     *
     * @return bool true nếu công nợ đã được ghi nhận (hoặc đã ghi nhận trước đó),
     *              false nếu phát hiện mâu thuẫn dữ liệu cần đối soát thủ công.
     *              Khi trả về false, TUYỆT ĐỐI không có thay đổi tiền nào được thực hiện.
     */
    public function chotCongNoThanhCong(DonHang $donHang): bool
    {
        if (!$donHang->dai_ly_api_id) {
            return true;
        }

        return DB::transaction(function () use ($donHang) {
            // 1. Kiểm tra xem bút toán chốt công nợ đã tồn tại chưa (Idempotent)
            $maThamChieu = 'TX-ORD-' . $donHang->id;
            $existingLedger = SoPhatSinhCongNo::where('dai_ly_api_id', $donHang->dai_ly_api_id)
                ->where('ma_tham_chieu', $maThamChieu)
                ->lockForUpdate()
                ->first();

            if ($existingLedger) {
                return true;
            }

            $hold = KhoanGiuHanMuc::where('don_hang_id', $donHang->id)
                ->lockForUpdate()
                ->first();

            if ($hold && $hold->trang_thai === 'COMMITTED') {
                return true;
            }

            // 2. Mâu thuẫn nghiêm trọng: khoản giữ đã được giải phóng (đã kết luận đơn thất bại)
            //    nhưng NCC lại báo thành công. KHÔNG được tự ý commit lại khoản giữ, KHÔNG được
            //    tự tăng công nợ — phải để kế toán đối soát và ra quyết định.
            if ($hold && $hold->trang_thai === 'RELEASED') {
                return $this->baoDongMauThuan($donHang, $hold, 'Khoản giữ đã RELEASED nhưng nhận tín hiệu thành công từ NCC.');
            }

            // 3. Mâu thuẫn dữ liệu: đơn B2B thành công nhưng không có khoản giữ nào.
            //    Không được tự tạo khoản giữ để che giấu dữ liệu thiếu.
            if (!$hold) {
                return $this->baoDongMauThuan($donHang, null, 'Đơn B2B thành công nhưng không tìm thấy khoản giữ hạn mức nào.');
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
            $hold->update([
                'trang_thai' => 'COMMITTED',
                'chot_cong_no_luc' => now(),
            ]);

            // Ghi sổ cái phát sinh công nợ (Immutable ledger)
            SoPhatSinhCongNo::create([
                'dai_ly_api_id' => $donHang->dai_ly_api_id,
                'ma_tham_chieu' => $maThamChieu,
                'don_hang_id' => $donHang->id,
                'loai_phat_sinh' => 'TANG_CONG_NO_DON_HANG',
                'so_tien' => $soTien,
                'so_du_truoc' => $soDuTruoc,
                'so_du_sau' => $soDuSau,
                'ghi_chu' => "Ghi nhận công nợ đơn hàng #{$donHang->ma_don_hang} (Đối tác ref: {$donHang->ma_don_doi_tac})",
            ]);

            return true;
        });
    }

    /**
     * Đánh dấu đơn cần đối soát thủ công và cảnh báo vận hành khi phát hiện mâu thuẫn
     * giữa khoản giữ và kết quả nhà cung cấp. Không thay đổi bất kỳ số dư nào.
     */
    protected function baoDongMauThuan(DonHang $donHang, ?KhoanGiuHanMuc $hold, string $lyDo): bool
    {
        $chiTiet = [
            'don_hang_id' => $donHang->id,
            'ma_don_hang' => $donHang->ma_don_hang,
            'dai_ly_api_id' => $donHang->dai_ly_api_id,
            'trang_thai_khoan_giu' => $hold?->trang_thai,
            'so_tien' => (float) $donHang->gia_ban,
            'ly_do' => $lyDo,
        ];

        Log::emergency('B2bCreditService: MÂU THUẪN CÔNG NỢ B2B — cần đối soát thủ công. Không tự động thay đổi tiền.', $chiTiet);

        // Đánh dấu đơn cần đối soát (không đổi trạng thái đơn, không đổi tiền)
        if ($donHang->trang_thai_doi_soat !== 'cho_doi_soat') {
            $donHang->update(['trang_thai_doi_soat' => 'cho_doi_soat']);
        }

        try {
            app(\App\Services\Alert\TelegramAlertService::class)->alertManualReview(
                $donHang,
                "MÂU THUẪN CÔNG NỢ B2B: đơn #{$donHang->ma_don_hang} — {$lyDo} Hệ thống KHÔNG tự thay đổi công nợ, cần kế toán đối soát thủ công."
            );
        } catch (\Throwable $e) {
            // Lỗi thông báo không được làm hỏng kết quả nghiệp vụ
            Log::warning('B2bCreditService: không gửi được cảnh báo Telegram: ' . $e->getMessage());
        }

        return false;
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

            // Tuyệt đối không giải phóng khoản giữ đã COMMITTED
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
     * Tuyệt đối không cộng vào ví B2C. Chống lặp và kiểm tra điều kiện nghiệp vụ chặt chẽ.
     */
    public function hoanTienGiamCongNo(DonHang $donHang, string $lyDo, ?int $nguoiThaoTacId = null): void
    {
        if (!$donHang->dai_ly_api_id) {
            return;
        }

        DB::transaction(function () use ($donHang, $lyDo, $nguoiThaoTacId) {
            $maThamChieu = 'TX-REF-' . $donHang->id;

            // 1. Kiểm tra xem bút toán hoàn nợ đã tồn tại chưa (Idempotency check TRƯỚC KHI trừ tiền)
            $existingRefund = SoPhatSinhCongNo::where('dai_ly_api_id', $donHang->dai_ly_api_id)
                ->where('ma_tham_chieu', $maThamChieu)
                ->lockForUpdate()
                ->first();

            if ($existingRefund) {
                return; // Đã hoàn nợ trước đó, không thực hiện lại
            }

            // 2. Kiểm tra xem đơn hàng đã từng được ghi tăng nợ hay chưa
            $originalLedger = SoPhatSinhCongNo::where('dai_ly_api_id', $donHang->dai_ly_api_id)
                ->where('ma_tham_chieu', 'TX-ORD-' . $donHang->id)
                ->first();

            if (!$originalLedger) {
                throw new DomainException("Không thể hoàn công nợ cho đơn hàng #{$donHang->id} vì đơn này chưa từng được ghi nhận nợ.", 422);
            }

            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $donHang->dai_ly_api_id)
                ->lockForUpdate()
                ->firstOrFail();

            $soTien = (float) $donHang->gia_ban;

            // Chống hoàn vượt quá số tiền đã ghi nợ của chính đơn hàng gốc
            if ($soTien > (float) $originalLedger->so_tien) {
                throw new DomainException(
                    sprintf(
                        'Số tiền hoàn (%s) vượt quá số tiền đã ghi nợ của đơn hàng gốc (%s).',
                        number_format($soTien, 2, '.', ''),
                        number_format((float) $originalLedger->so_tien, 2, '.', '')
                    ),
                    422
                );
            }

            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;

            // Không để hoàn tiền biến dư nợ thành số dư có ngoài ý muốn.
            // Trường hợp dư nợ hiện tại nhỏ hơn số tiền hoàn (đại lý đã thanh toán một phần)
            // phải xử lý bằng bút toán điều chỉnh có người phê duyệt, không tự động ở đây.
            if ($soTien > $soDuTruoc) {
                throw new DomainException(
                    sprintf(
                        'Không thể hoàn công nợ: số tiền hoàn (%s) vượt quá dư nợ hiện tại (%s). Cần đối soát và ghi bút toán điều chỉnh có phê duyệt.',
                        number_format($soTien, 2, '.', ''),
                        number_format($soDuTruoc, 2, '.', '')
                    ),
                    422
                );
            }

            $soDuSau = $soDuTruoc - $soTien;

            $cauHinh->cong_no_hien_tai = $soDuSau;
            $cauHinh->save();

            SoPhatSinhCongNo::create([
                'dai_ly_api_id' => $donHang->dai_ly_api_id,
                'ma_tham_chieu' => $maThamChieu,
                'don_hang_id' => $donHang->id,
                'loai_phat_sinh' => 'GIAM_CONG_NO_HOAN_TIEN',
                'so_tien' => $soTien,
                'so_du_truoc' => $soDuTruoc,
                'so_du_sau' => $soDuSau,
                'ghi_chu' => "Hoàn tiền giảm công nợ đơn hàng #{$donHang->ma_don_hang}: {$lyDo}",
                'nguoi_tao_id' => $nguoiThaoTacId,
            ]);
        });
    }

    /**
     * Ghi nhận thanh toán từ đại lý (chuyển khoản trả nợ).
     *
     * Chính sách thanh toán dư: MẶC ĐỊNH TỪ CHỐI. Trước đây hệ thống dùng
     * max(0, số dư - số tiền) khiến phần thanh toán vượt dư nợ bị âm thầm nuốt mất,
     * đồng thời làm số dư tổng hợp lệch khỏi sổ phát sinh.
     * Chỉ khi đối tác vận hành chủ động bật $choPhepGhiNhanSoDuCo thì phần vượt mới
     * được ghi nhận thành số dư có (công nợ âm) và phản ánh đầy đủ trên sổ cái.
     */
    public function ghiNhanThanhToan(
        DaiLyApi $daiLy,
        float $soTien,
        string $phuongThuc = 'CHUYEN_KHOAN',
        ?string $maGiaoDichNganHang = null,
        ?string $ghiChu = null,
        ?int $nguoiTaoId = null,
        ?string $maThanhToan = null,
        bool $choPhepGhiNhanSoDuCo = false
    ): ThanhToanCongNo {
        if ($soTien <= 0) {
            throw new DomainException('Số tiền thanh toán phải lớn hơn 0.', 422);
        }

        return DB::transaction(function () use ($daiLy, $soTien, $phuongThuc, $maGiaoDichNganHang, $ghiChu, $nguoiTaoId, $maThanhToan, $choPhepGhiNhanSoDuCo) {
            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)
                ->lockForUpdate()
                ->firstOrFail();

            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;

            if ($soTien > $soDuTruoc && !$choPhepGhiNhanSoDuCo) {
                throw new DomainException(
                    sprintf(
                        'Số tiền thanh toán (%s) vượt quá dư nợ hiện tại (%s). Vui lòng kiểm tra lại hoặc bật chính sách ghi nhận số dư có.',
                        number_format($soTien, 2, '.', ''),
                        number_format($soDuTruoc, 2, '.', '')
                    ),
                    422
                );
            }

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

            $soDuSau = $soDuTruoc - $soTien;

            $cauHinh->cong_no_hien_tai = $soDuSau;
            $cauHinh->save();

            $ghiChuSoCai = "Thanh toán công nợ: {$maTT}" . ($maGiaoDichNganHang ? " (Ref: {$maGiaoDichNganHang})" : '');
            if ($soDuSau < 0) {
                $ghiChuSoCai .= sprintf(' [GHI NHẬN SỐ DƯ CÓ: thanh toán vượt dư nợ %s]', number_format(abs($soDuSau), 2, '.', ''));
            }

            // Ghi sổ cái
            SoPhatSinhCongNo::create([
                'dai_ly_api_id' => $daiLy->id,
                'thanh_toan_id' => $payment->id,
                'loai_phat_sinh' => 'GIAM_CONG_NO_THANH_TOAN',
                'so_tien' => $soTien,
                'so_du_truoc' => $soDuTruoc,
                'so_du_sau' => $soDuSau,
                'ma_tham_chieu' => 'TX-PAY-' . $payment->ma_thanh_toan,
                'ghi_chu' => $ghiChuSoCai,
                'nguoi_tao_id' => $nguoiTaoId,
            ]);

            return $payment;
        });
    }

    /**
     * Ghi nhận bút toán điều chỉnh công nợ.
     * Không dùng max(0, ...) để âm thầm cắt bớt phần điều chỉnh giảm vượt dư nợ.
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
        if (!in_array($loaiDieuChinh, ['TANG_NO', 'GIAM_NO'], true)) {
            throw new DomainException("Loại điều chỉnh '{$loaiDieuChinh}' không hợp lệ (chỉ chấp nhận TANG_NO hoặc GIAM_NO).", 422);
        }

        if ($soTien <= 0) {
            throw new DomainException('Số tiền điều chỉnh phải lớn hơn 0.', 422);
        }

        return DB::transaction(function () use ($daiLy, $loaiDieuChinh, $soTien, $lyDo, $maThamChieuGoc, $kyDoiSoatId, $nguoiTaoId) {
            $cauHinh = CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)
                ->lockForUpdate()
                ->firstOrFail();

            $soDuTruoc = (float) $cauHinh->cong_no_hien_tai;

            if ($loaiDieuChinh === 'GIAM_NO' && $soTien > $soDuTruoc) {
                throw new DomainException(
                    sprintf(
                        'Số tiền điều chỉnh giảm (%s) vượt quá dư nợ hiện tại (%s). Không thể ghi nhận.',
                        number_format($soTien, 2, '.', ''),
                        number_format($soDuTruoc, 2, '.', '')
                    ),
                    422
                );
            }

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

            $soDuSau = ($loaiDieuChinh === 'TANG_NO')
                ? ($soDuTruoc + $soTien)
                : ($soDuTruoc - $soTien);

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

    /**
     * Kiểm tra tính cân đối giữa số dư tổng hợp (cau_hinh_api_dai_ly.cong_no_hien_tai)
     * và sổ phát sinh công nợ bất biến (so_phat_sinh_cong_no).
     *
     * Bất biến nghiệp vụ:
     *   cong_no_hien_tai == SUM(TANG_*) - SUM(GIAM_*)
     *   cong_no_hien_tai == so_du_sau của bút toán mới nhất
     *
     * @return array{
     *   can_doi: bool,
     *   so_du_tong_hop: float,
     *   so_du_theo_so_cai: float,
     *   chenh_lech: float,
     *   so_du_but_toan_cuoi: float|null,
     *   chenh_lech_but_toan_cuoi: float
     * }
     */
    public function kiemTraCanDoiCongNo(DaiLyApi $daiLy): array
    {
        $cauHinh = $daiLy->cauHinhApi ?: CauHinhApiDaiLy::where('dai_ly_api_id', $daiLy->id)->first();
        $soDuTongHop = $cauHinh ? (float) $cauHinh->cong_no_hien_tai : 0.0;

        $soDuTheoSoCai = (float) SoPhatSinhCongNo::where('dai_ly_api_id', $daiLy->id)
            ->selectRaw(
                "COALESCE(SUM(CASE
                    WHEN loai_phat_sinh IN ('TANG_CONG_NO_DON_HANG', 'DIEU_CHINH_TANG') THEN so_tien
                    WHEN loai_phat_sinh IN ('GIAM_CONG_NO_HOAN_TIEN', 'GIAM_CONG_NO_THANH_TOAN', 'DIEU_CHINH_GIAM') THEN -so_tien
                    ELSE 0 END), 0) AS so_du"
            )
            ->value('so_du');

        $butToanCuoi = SoPhatSinhCongNo::where('dai_ly_api_id', $daiLy->id)
            ->orderByDesc('id')
            ->first();

        $soDuButToanCuoi = $butToanCuoi ? (float) $butToanCuoi->so_du_sau : 0.0;
        $chenhLech = round($soDuTongHop - $soDuTheoSoCai, 2);
        $chenhLechButToanCuoi = round($soDuTongHop - $soDuButToanCuoi, 2);

        return [
            'can_doi' => abs($chenhLech) < 0.01 && abs($chenhLechButToanCuoi) < 0.01,
            'so_du_tong_hop' => round($soDuTongHop, 2),
            'so_du_theo_so_cai' => round($soDuTheoSoCai, 2),
            'chenh_lech' => $chenhLech,
            'so_du_but_toan_cuoi' => $butToanCuoi ? round($soDuButToanCuoi, 2) : null,
            'chenh_lech_but_toan_cuoi' => $chenhLechButToanCuoi,
        ];
    }
}
