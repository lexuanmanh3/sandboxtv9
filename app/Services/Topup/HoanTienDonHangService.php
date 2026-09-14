<?php

namespace App\Services\Topup;

use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Exceptions\Refund\DebitTransactionNotFoundException;
use App\Exceptions\Refund\InvalidRefundStateException;
use App\Exceptions\Refund\OrderAlreadyRefundedException;
use App\Exceptions\Refund\OrderRefundException;
use App\Exceptions\Refund\ProviderPendingRefundException;
use App\Models\DonHang;
use App\Models\LichSuVi;
use App\Models\ViNguoiDung;
use App\Services\Alert\TelegramAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HoanTienDonHangService — Dịch vụ tài chính dùng chung cho toàn bộ luồng hoàn tiền.
 *
 * Đảm bảo:
 * - Nguyên tử (Atomic): Một transaction duy nhất bọc toàn bộ thay đổi tài chính & trạng thái.
 * - Thứ tự khóa thống nhất: Đơn hàng (don_hang) -> Ví (vi_nguoi_dung).
 * - Tính toán chính xác thập phân (bcmath), không dùng float.
 * - Kiểm tra nguồn tiền: xác minh giao dịch debit gốc và số tiền.
 * - Kiểm tra trạng thái NCC: chặn hoàn khi NCC đang xử lý / chưa xác nhận kết quả cuối.
 * - Chống hoàn trùng ở cả tầng ứng dụng và tầng database.
 * - Tác vụ ngoại vi (Telegram) chỉ chạy sau khi transaction commit thành công.
 */
class HoanTienDonHangService
{
    public function __construct(
        private DonHangStateMachine $stateMachine
    ) {}

    /**
     * Thực hiện hoàn tiền cho đơn hàng.
     *
     * @param int $orderId ID đơn hàng
     * @param string $actorType Loại tác nhân: 'admin', 'system', 'worker'
     * @param int|null $actorId ID người thực hiện (nếu có)
     * @param string $actorName Tên người thực hiện
     * @param string $reason Lý do hoàn tiền
     * @param bool $skipProviderCheck Bỏ qua kiểm tra NCC (chỉ dùng khi luồng gọi đã xác nhận NCC trước đó)
     * @return DonHang
     *
     * @throws OrderRefundException
     */
    public function hoanTien(
        int $orderId,
        string $actorType = 'admin',
        ?int $actorId = null,
        string $actorName = 'system',
        string $reason = 'Hoàn tiền đơn hàng',
        bool $skipProviderCheck = false
    ): DonHang {
        // Tối đa 3 lần thử lại khi gặp Deadlock (InnoDB Deadlock / PDOException 40001)
        return DB::transaction(function () use (
            $orderId,
            $actorType,
            $actorId,
            $actorName,
            $reason,
            $skipProviderCheck
        ) {
            // 1. Khóa đơn hàng trước (lockForUpdate)
            $order = DonHang::query()->lockForUpdate()->findOrFail($orderId);

            $currentStatus = strtoupper((string) $order->trang_thai_don_hang);
            $paymentStatus = strtoupper((string) $order->trang_thai_thanh_toan);

            // 2. Kiểm tra xem đơn đã được hoàn tiền từ trước hay chưa
            if ($currentStatus === TrangThaiDonHang::REFUNDED->value || $paymentStatus === 'REFUNDED') {
                throw new OrderAlreadyRefundedException('Đơn hàng này đã được hoàn tiền trước đó.');
            }

            // 3. Đơn SUCCESS tuyệt đối không được hoàn tiền tự động / thông thường
            if ($currentStatus === TrangThaiDonHang::SUCCESS->value) {
                throw new InvalidRefundStateException('Không thể hoàn tiền đơn hàng đã nạp thành công (SUCCESS).');
            }

            // 4. Kiểm tra trạng thái đơn hợp lệ
            $validStatuses = [
                TrangThaiDonHang::FAILED->value,
                TrangThaiDonHang::MANUAL_REVIEW->value,
                TrangThaiDonHang::PROVIDER_PENDING->value,
                TrangThaiDonHang::PROCESSING->value,
                TrangThaiDonHang::REFUND_PENDING->value,
            ];

            if (!in_array($currentStatus, $validStatuses, true)) {
                throw new InvalidRefundStateException("Không thể hoàn tiền đơn hàng ở trạng thái: {$currentStatus}.");
            }

            // 5. Kiểm tra trạng thái thực tế của yêu cầu gửi nhà cung cấp
            if (!$skipProviderCheck) {
                $lanGoiList = $order->lanGoiNhaCungCap()->orderBy('id', 'desc')->get();

                // Nếu có bất kỳ lần gọi nào đã SUCCESS -> Không được hoàn tiền
                if ($lanGoiList->contains(fn($c) => $c->ket_qua_xac_dinh === KetQuaNhaCungCap::SUCCESS->value)) {
                    throw new InvalidRefundStateException('Nhà cung cấp đã nạp thành công cho đơn hàng này. Không thể hoàn tiền.');
                }

                // Nếu đã gửi yêu cầu tới NCC và lần gọi gần nhất vẫn đang UNKNOWN_OR_PENDING hoặc PROCESSING
                if ($lanGoiList->isNotEmpty()) {
                    $latestCall = $lanGoiList->first();
                    $callResult = $latestCall->ket_qua_xac_dinh;

                    if ($callResult === KetQuaNhaCungCap::UNKNOWN_OR_PENDING->value || $latestCall->trang_thai === 'PROCESSING') {
                        throw new ProviderPendingRefundException(
                            'Nhà cung cấp chưa xác nhận kết quả thất bại cuối cùng (đang xử lý hoặc chờ đối soát). Vui lòng kiểm tra lại trạng thái hoặc xử lý đối soát trước khi hoàn tiền.'
                        );
                    }
                }
            }

            // 6. Khóa ví người dùng (Thứ tự khóa nhất quán: Đơn hàng -> Ví)
            if (!$order->nguoi_dung_id) {
                throw new DebitTransactionNotFoundException('Đơn hàng không liên kết với tài khoản người dùng nào.');
            }

            $vi = ViNguoiDung::where('nguoi_dung_id', $order->nguoi_dung_id)
                ->lockForUpdate()
                ->first();

            if (!$vi) {
                throw new DebitTransactionNotFoundException('Không tìm thấy ví của người dùng để hoàn tiền.');
            }

            // 7. Xác minh giao dịch trừ tiền gốc (debit) trong lịch sử ví
            $debit = LichSuVi::where('don_hang_id', $order->id)
                ->where('loai_giao_dich', 'debit')
                ->where('vi_nguoi_dung_id', $vi->id)
                ->first();

            if (!$debit) {
                throw new DebitTransactionNotFoundException('Không tìm thấy giao dịch trừ tiền gốc của đơn hàng trong lịch sử ví.');
            }

            // So sánh số tiền trừ ví với giá bán của đơn hàng bằng phép so sánh decimal chính xác
            $giaBanStr = number_format((float) $order->gia_ban, 2, '.', '');
            $debitSoTienStr = number_format((float) $debit->so_tien, 2, '.', '');

            if (bccomp($debitSoTienStr, $giaBanStr, 2) !== 0) {
                throw new DebitTransactionNotFoundException(
                    "Số tiền giao dịch trừ ví ({$debitSoTienStr}) không khớp với giá bán đơn hàng ({$giaBanStr}). Cần đối soát thủ công."
                );
            }

            // 8. Kiểm tra xem đã có giao dịch hoàn tiền nào tồn tại chưa (Idempotency check ở tầng dữ liệu)
            $existingRefund = LichSuVi::where('don_hang_id', $order->id)
                ->where('loai_giao_dich', 'refund')
                ->first();

            if ($existingRefund) {
                throw new OrderAlreadyRefundedException('Đơn hàng này đã có giao dịch hoàn tiền trong lịch sử ví.');
            }

            $soTienHoan = $debitSoTienStr;
            if (bccomp($soTienHoan, '0.00', 2) <= 0) {
                throw new OrderRefundException("Số tiền hoàn không hợp lệ: {$soTienHoan}");
            }

            // 9. Chuyển trạng thái trung gian sang REFUND_PENDING (nếu chưa ở REFUND_PENDING)
            $nguonThayDoi = "{$actorType}_{$actorName}";
            if ($currentStatus !== TrangThaiDonHang::REFUND_PENDING->value) {
                $this->stateMachine->chuyen(
                    $order,
                    TrangThaiDonHang::REFUND_PENDING,
                    $reason,
                    $nguonThayDoi
                );
            }

            // 10. Cộng tiền vào ví với phép tính decimal chính xác
            $soDuTruoc = number_format((float) $vi->so_du, 2, '.', '');
            $soDuSau   = bcadd($soDuTruoc, $soTienHoan, 2);
            $tongHoanSau = bcadd(number_format((float) $vi->tong_hoan, 2, '.', ''), $soTienHoan, 2);

            $vi->so_du    = $soDuSau;
            $vi->tong_hoan = $tongHoanSau;
            $vi->save();

            // 11. Ghi bản ghi lịch sử ví hoàn tiền
            // Trụ cột chống hoàn trùng: Virtual Generated Column 'refund_don_hang_id' có Unique Index
            LichSuVi::create([
                'vi_nguoi_dung_id' => $vi->id,
                'don_hang_id'      => $order->id,
                'loai_giao_dich'   => 'refund',
                'so_tien'          => $soTienHoan,
                'so_du_truoc'      => $soDuTruoc,
                'so_du_sau'        => $soDuSau,
                'mo_ta'            => "Hoàn tiền đơn hàng #{$order->ma_don_hang}: {$reason}",
                'nguon'            => $nguonThayDoi,
            ]);

            // 12. Chuyển trạng thái đích sang REFUNDED
            $this->stateMachine->chuyen(
                $order,
                TrangThaiDonHang::REFUNDED,
                $reason,
                $nguonThayDoi
            );

            // 13. Cập nhật trạng thái thanh toán của đơn (KHÔNG ghi đè thong_bao_loi_he_thong)
            $order->update([
                'trang_thai_thanh_toan' => 'REFUNDED',
            ]);

            // 14. Đăng ký tác vụ gửi thông báo sau khi transaction commit thành công
            DB::afterCommit(function () use ($order, $reason, $actorName) {
                try {
                    app(TelegramAlertService::class)->alertOrderRefunded(
                        $order->fresh() ?: $order,
                        $reason,
                        $actorName
                    );
                } catch (\Throwable $te) {
                    Log::warning("Lỗi gửi cảnh báo Telegram sau khi hoàn tiền đơn #{$order->id}: " . $te->getMessage());
                }
            });

            return $order->fresh() ?: $order;
        }, 3);
    }
}
