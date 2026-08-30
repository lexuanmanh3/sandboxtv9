<?php

namespace App\Services\Topup;

use App\Models\DonHang;
use App\Models\LichSuVi;
use App\Models\User;
use App\Models\ViNguoiDung;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ViNguoiDungService — Quản lý số dư ví người dùng.
 *
 * Nguyên tắc an toàn:
 * - LUÔN dùng lockForUpdate() khi đọc số dư để chống race condition.
 * - Mọi thao tác trừ/cộng phải nằm trong DB::transaction().
 * - Không bao giờ nhận số tiền từ frontend.
 */
final class ViNguoiDungService
{
    /**
     * Lấy ví của user, tạo mới nếu chưa có.
     */
    public function layHoacTao(int $userId): ViNguoiDung
    {
        return ViNguoiDung::firstOrCreate(
            ['nguoi_dung_id' => $userId],
            ['so_du' => 0.00, 'trang_thai' => 'hoat_dong']
        );
    }

    /**
     * Trừ số dư ví trong một transaction (phải gọi từ trong DB::transaction).
     *
     * @throws RuntimeException Nếu số dư không đủ hoặc ví bị khóa.
     */
    public function truTienTrongTransaction(int $userId, float $soTien, DonHang $donHang, string $moTa): void
    {
        // lockForUpdate() đảm bảo chỉ 1 request được đọc/sửa ví cùng lúc
        $vi = ViNguoiDung::where('nguoi_dung_id', $userId)
            ->lockForUpdate()
            ->first();

        if (!$vi) {
            throw new RuntimeException('Tài khoản chưa có ví. Vui lòng liên hệ hỗ trợ.');
        }

        if (!$vi->dangHoatDong()) {
            throw new RuntimeException('Ví của bạn đang bị khóa. Vui lòng liên hệ hỗ trợ.');
        }

        if (!$vi->duSoDu($soTien)) {
            throw new RuntimeException(
                sprintf('Số dư không đủ. Số dư hiện tại: %s, cần: %s.',
                    number_format((float) $vi->so_du, 0, ',', '.') . 'đ',
                    number_format($soTien, 0, ',', '.') . 'đ'
                )
            );
        }

        $soDuTruoc = (float) $vi->so_du;
        $soDuSau   = $soDuTruoc - $soTien;

        // Cập nhật số dư
        $vi->so_du    = $soDuSau;
        $vi->tong_chi = (float) $vi->tong_chi + $soTien;
        $vi->save();

        // Ghi lịch sử — không bao giờ bỏ qua bước này
        LichSuVi::create([
            'vi_nguoi_dung_id' => $vi->id,
            'don_hang_id'      => $donHang->id,
            'loai_giao_dich'   => 'debit',
            'so_tien'          => $soTien,
            'so_du_truoc'      => $soDuTruoc,
            'so_du_sau'        => $soDuSau,
            'mo_ta'            => $moTa,
            'nguon'            => 'user',
        ]);
    }

    /**
     * Hoàn tiền vào ví trong một transaction độc lập.
     * Luôn dùng transaction riêng để hoàn tiền không bị rollback cùng lỗi nạp tiền.
     */
    public function hoanTien(int $userId, float $soTien, DonHang $donHang, string $moTa): void
    {
        DB::transaction(function () use ($userId, $soTien, $donHang, $moTa) {
            $vi = ViNguoiDung::where('nguoi_dung_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$vi) {
                // Log lỗi nhưng không throw — hoàn tiền phải được thực hiện
                \Illuminate\Support\Facades\Log::error("Không tìm thấy ví khi hoàn tiền. User: {$userId}, DonHang: {$donHang->id}");
                return;
            }

            $soDuTruoc = (float) $vi->so_du;
            $soDuSau   = $soDuTruoc + $soTien;

            $vi->so_du    = $soDuSau;
            $vi->tong_hoan = (float) $vi->tong_hoan + $soTien;
            $vi->save();

            LichSuVi::create([
                'vi_nguoi_dung_id' => $vi->id,
                'don_hang_id'      => $donHang->id,
                'loai_giao_dich'   => 'refund',
                'so_tien'          => $soTien,
                'so_du_truoc'      => $soDuTruoc,
                'so_du_sau'        => $soDuSau,
                'mo_ta'            => $moTa,
                'nguon'            => 'refund_job',
            ]);
        });
    }

    /**
     * Nạp tiền vào ví (admin / cổng nạp).
     */
    public function napTien(int $userId, float $soTien, string $moTa, string $nguon = 'admin'): ViNguoiDung
    {
        return DB::transaction(function () use ($userId, $soTien, $moTa, $nguon) {
            $vi = ViNguoiDung::where('nguoi_dung_id', $userId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['nguoi_dung_id' => $userId],
                    ['so_du' => 0.00, 'trang_thai' => 'hoat_dong']
                );

            $soDuTruoc = (float) $vi->so_du;
            $soDuSau   = $soDuTruoc + $soTien;

            $vi->so_du   = $soDuSau;
            $vi->tong_nap = (float) $vi->tong_nap + $soTien;
            $vi->save();

            LichSuVi::create([
                'vi_nguoi_dung_id' => $vi->id,
                'don_hang_id'      => null,
                'loai_giao_dich'   => 'credit',
                'so_tien'          => $soTien,
                'so_du_truoc'      => $soDuTruoc,
                'so_du_sau'        => $soDuSau,
                'mo_ta'            => $moTa,
                'nguon'            => $nguon,
            ]);

            return $vi->fresh();
        });
    }
}
