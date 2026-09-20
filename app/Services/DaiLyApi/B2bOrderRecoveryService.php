<?php

namespace App\Services\DaiLyApi;

use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Jobs\CheckMobileTopupStatusJob;
use App\Jobs\ProcessMobileTopupJob;
use App\Models\B2bOrderOutbox;
use App\Models\DonHang;
use App\Models\LanGoiNhaCungCap;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\DonHangStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phục hồi đơn hàng B2B bị treo dựa trên BẰNG CHỨNG, không dựa trên tuổi đơn.
 *
 * Nguyên tắc bất di bất dịch:
 *  - Timeout, hết lượt retry, hết thời hạn tra cứu hay worker chết KHÔNG BAO GIỜ
 *    tự động trở thành "thất bại", không tự hoàn tiền, không tự giải phóng hạn mức.
 *  - Khi đã có khả năng lệnh đã được gửi sang NCC, tuyệt đối không tạo
 *    partner_ref_id mới; chỉ tra cứu lại bằng chính mã tham chiếu gốc.
 *  - Mọi cập nhật nghiệp vụ đều chống xử lý lặp ở tầng dịch vụ (idempotent).
 */
class B2bOrderRecoveryService
{
    /** Hành động phục hồi đã thực hiện cho một đơn. */
    public const ACTION_ALREADY_FINAL = 'already_final';
    public const ACTION_POLL_EXISTING_REF = 'poll_existing_ref';
    public const ACTION_COMPLETE_SUCCESS = 'complete_success';
    public const ACTION_COMPLETE_FAILURE = 'complete_failure';
    public const ACTION_REQUEUE_UNSENT = 'requeue_unsent';
    public const ACTION_HOLD_FOR_LEASE = 'hold_for_lease';
    public const ACTION_MANUAL_REVIEW = 'manual_review';

    public function __construct(
        protected DonHangStateMachine $state,
        protected B2bCreditService $creditService,
        protected B2bWebhookService $webhookService,
        protected TelegramAlertService $telegramAlert
    ) {}

    /**
     * Quyết định và thực thi hành động phục hồi cho một đơn hàng B2B bị treo.
     *
     * @return string Một trong các hằng ACTION_* ở trên.
     */
    public function phucHoi(DonHang $donHang, ?B2bOrderOutbox $record = null): string
    {
        $trangThai = strtoupper((string) $donHang->trang_thai_don_hang);

        // 1. Đơn đã có kết quả cuối.
        //
        // TỰ CHỮA LÀNH cho SUCCESS/FAILED: trạng thái cuối có thể đã được ghi vào đơn
        // nhưng tiến trình chết NGAY SAU ĐÓ, trước khi kịp chốt công nợ / giải phóng
        // khoản giữ / tạo sự kiện webhook. Không có bước này, khoản giữ sẽ kẹt ở HOLDING
        // VĨNH VIỄN: mọi lượt phục hồi về sau đều thoát ngay tại đây nên không gì sửa được.
        //
        // Ba thao tác dưới đây đều đã idempotent nên gọi lại là an toàn:
        //   - chotCongNoThanhCong: thoát ngay nếu đã có bút toán TX-ORD-{id} hoặc khoản giữ đã COMMITTED
        //   - giaiPhongKhoanGiu: chỉ tác động khi khoản giữ còn HOLDING
        //   - taoSuKien: firstOrCreate theo event_id tất định (uuid5)
        if (in_array($trangThai, ['SUCCESS', 'FAILED'], true)) {
            if ($donHang->dai_ly_api_id) {
                if ($trangThai === 'SUCCESS') {
                    $this->creditService->chotCongNoThanhCong($donHang);
                    $this->webhookService->taoSuKien($donHang, 'order.success');
                } else {
                    $this->creditService->giaiPhongKhoanGiu($donHang, 'Phục hồi: đơn đã kết luận thất bại dứt khoát');
                    $this->webhookService->taoSuKien($donHang, 'order.failed');
                }
            }

            $this->chotOutbox($record);
            return self::ACTION_ALREADY_FINAL;
        }

        // Đơn đã hoàn tiền hoặc đang đối soát thủ công: KHÔNG đụng tới khoản giữ và công nợ.
        // Giữ nguyên quy tắc nghiệp vụ: đối soát thủ công không tự kết luận, không tự hoàn.
        if (in_array($trangThai, ['REFUNDED', 'MANUAL_REVIEW'], true)) {
            $this->chotOutbox($record);
            return self::ACTION_ALREADY_FINAL;
        }

        // 2. Tìm bằng chứng về các lần gọi NCC
        $unresolvedCall = $this->lanGoiChuaRoKetQua($donHang);
        $lastResolvedCall = $this->lanGoiDaCoKetQuaCuoi($donHang);

        // 3. Có lần gọi chưa rõ kết quả -> CHỈ tra cứu lại bằng partner_ref_id gốc.
        if ($unresolvedCall) {
            $this->chuyenSangTraCuu($donHang, $unresolvedCall);
            return self::ACTION_POLL_EXISTING_REF;
        }

        // 4. NCC đã trả kết quả cuối nhưng worker chết trước khi chốt đơn.
        if ($lastResolvedCall && in_array($trangThai, ['PROCESSING', 'DANG_XU_LY', 'PROVIDER_PENDING'], true)) {
            if ($lastResolvedCall->ket_qua_xac_dinh === KetQuaNhaCungCap::SUCCESS->value) {
                $this->hoanTatThanhCong($donHang, $lastResolvedCall, $record);
                return self::ACTION_COMPLETE_SUCCESS;
            }

            if ($lastResolvedCall->ket_qua_xac_dinh === KetQuaNhaCungCap::DEFINITIVE_FAILURE->value) {
                $this->hoanTatThatBai($donHang, $record);
                return self::ACTION_COMPLETE_FAILURE;
            }
        }

        // 5. Chưa từng có lần gọi NCC nào -> có thể an toàn đưa lại vào hàng đợi,
        //    nhưng CHỈ KHI không còn worker nào đang sở hữu đơn (lease đã hết hạn).
        if (!$lastResolvedCall && !$unresolvedCall) {
            if ($trangThai === 'QUEUED') {
                ProcessMobileTopupJob::dispatch($donHang->id);
                return self::ACTION_REQUEUE_UNSENT;
            }

            if (in_array($trangThai, ['PROCESSING', 'DANG_XU_LY'], true)) {
                if ($donHang->dangCoLeaseXuLy()) {
                    // Worker còn sống và đang giữ lease -> tuyệt đối không chen vào.
                    return self::ACTION_HOLD_FOR_LEASE;
                }

                ProcessMobileTopupJob::dispatch($donHang->id);
                return self::ACTION_REQUEUE_UNSENT;
            }

            // Đơn ở trạng thái chờ NCC/đối soát nhưng không có lần gọi nào:
            // dữ liệu mâu thuẫn -> giữ nguyên hạn mức, chuyển đối soát, cảnh báo vận hành.
            if (in_array($trangThai, ['PROVIDER_PENDING', 'MANUAL_REVIEW'], true)) {
                $this->chuyenDoiSoat($donHang, 'Đơn ở trạng thái chờ nhà cung cấp nhưng không có bản ghi lần gọi nào.');
                return self::ACTION_MANUAL_REVIEW;
            }
        }

        // 6. Dữ liệu thiếu hoặc mâu thuẫn -> đối soát thủ công, giữ nguyên hạn mức.
        $this->chuyenDoiSoat($donHang, "Không đủ bằng chứng để phục hồi tự động (trạng thái: {$trangThai}).");
        return self::ACTION_MANUAL_REVIEW;
    }

    /**
     * Lần gọi CHARGING gần nhất chưa có kết quả cuối.
     * Đây là bằng chứng "lệnh có thể đã được gửi sang NCC".
     */
    public function lanGoiChuaRoKetQua(DonHang $donHang): ?LanGoiNhaCungCap
    {
        return $donHang->lanGoiNhaCungCap()
            ->where('loai_yeu_cau', 'CHARGING')
            ->where(function ($q) {
                $q->whereNull('ket_qua_xac_dinh')
                  ->orWhere('ket_qua_xac_dinh', KetQuaNhaCungCap::UNKNOWN_OR_PENDING->value);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Lần gọi CHARGING gần nhất đã có kết quả cuối từ NCC.
     */
    public function lanGoiDaCoKetQuaCuoi(DonHang $donHang): ?LanGoiNhaCungCap
    {
        return $donHang->lanGoiNhaCungCap()
            ->where('loai_yeu_cau', 'CHARGING')
            ->whereIn('ket_qua_xac_dinh', [
                KetQuaNhaCungCap::SUCCESS->value,
                KetQuaNhaCungCap::DEFINITIVE_FAILURE->value,
            ])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Chuyển đơn sang PROVIDER_PENDING và tra cứu lại bằng partner_ref_id gốc.
     */
    protected function chuyenSangTraCuu(DonHang $donHang, LanGoiNhaCungCap $call): void
    {
        if (strtoupper((string) $donHang->trang_thai_don_hang) === 'PROCESSING') {
            $this->state->chuyen($donHang, TrangThaiDonHang::PROVIDER_PENDING, 'Phục hồi: có lần gọi NCC chưa rõ kết quả, chuyển sang tra cứu theo mã tham chiếu gốc');
            $donHang->refresh();
        }

        CheckMobileTopupStatusJob::dispatch($call->id);
    }

    /**
     * Hoàn tất chốt đơn thành công dựa trên kết quả cuối của NCC (chống lặp).
     *
     * Toàn bộ việc đổi trạng thái đơn, chốt công nợ và tạo sự kiện webhook nằm trong
     * MỘT transaction và có khóa dòng đơn. Trước đây ba việc này tách rời và không khóa:
     * tiến trình chết giữa chừng sẽ để lại đơn SUCCESS nhưng khoản giữ vẫn HOLDING và
     * không có bút toán công nợ — sai lệch sổ sách mà không lượt phục hồi nào sửa được.
     */
    protected function hoanTatThanhCong(DonHang $donHang, LanGoiNhaCungCap $call, ?B2bOrderOutbox $record): void
    {
        DB::transaction(function () use ($donHang, $call, $record) {
            // Khóa dòng đơn rồi mới đọc trạng thái: hai tiến trình phục hồi chạy song song
            // sẽ nối tiếp nhau thay vì cùng chốt một đơn.
            $donHang = DonHang::query()->lockForUpdate()->find($donHang->id) ?: $donHang;

            if (strtoupper((string) $donHang->trang_thai_don_hang) !== 'SUCCESS') {
                $this->state->chuyen($donHang, TrangThaiDonHang::SUCCESS, 'Phục hồi: NCC đã xác nhận thành công trước khi worker dừng');
                $donHang->update([
                    'nha_cung_cap_thanh_cong_id' => $call->nha_cung_cap_id,
                    'lan_goi_thanh_cong_id' => $call->id,
                    'hoan_thanh_luc' => $donHang->hoan_thanh_luc ?: now(),
                ]);
                $donHang->refresh();
            }

            if ($donHang->dai_ly_api_id) {
                $this->creditService->chotCongNoThanhCong($donHang);
                $this->webhookService->taoSuKien($donHang, 'order.success');
            }

            $this->chotOutbox($record);
        });
    }

    /**
     * Hoàn tất chốt đơn thất bại chắc chắn (chống lặp).
     * Chỉ gọi khi TOÀN BỘ lần gọi NCC đã có kết quả thất bại dứt khoát.
     *
     * Cùng lý do như hoanTatThanhCong(): đổi trạng thái, giải phóng khoản giữ và tạo
     * sự kiện webhook phải nằm trong một transaction có khóa dòng đơn.
     */
    protected function hoanTatThatBai(DonHang $donHang, ?B2bOrderOutbox $record): void
    {
        DB::transaction(function () use ($donHang, $record) {
            $donHang = DonHang::query()->lockForUpdate()->find($donHang->id) ?: $donHang;

            if (strtoupper((string) $donHang->trang_thai_don_hang) !== 'FAILED') {
                $this->state->chuyen($donHang, TrangThaiDonHang::FAILED, 'Phục hồi: NCC đã trả kết quả thất bại dứt khoát trước khi worker dừng');
                $donHang->update(['that_bai_luc' => $donHang->that_bai_luc ?: now()]);
                $donHang->refresh();
            }

            if ($donHang->dai_ly_api_id) {
                $this->creditService->giaiPhongKhoanGiu($donHang, 'Phục hồi: NCC thất bại dứt khoát');
                $this->webhookService->taoSuKien($donHang, 'order.failed');
            }

            $this->chotOutbox($record);
        });
    }

    /**
     * Chuyển đơn sang đối soát thủ công, GIỮ NGUYÊN hạn mức và công nợ.
     */
    protected function chuyenDoiSoat(DonHang $donHang, string $lyDo): void
    {
        $trangThai = strtoupper((string) $donHang->trang_thai_don_hang);

        if ($trangThai === 'PROCESSING') {
            // PROCESSING -> MANUAL_REVIEW không có trong state machine; đi vòng qua PROVIDER_PENDING
            $this->state->chuyen($donHang, TrangThaiDonHang::PROVIDER_PENDING, $lyDo);
            $donHang->refresh();
            $trangThai = 'PROVIDER_PENDING';
        }

        if ($trangThai === 'PROVIDER_PENDING') {
            $this->state->chuyen($donHang, TrangThaiDonHang::MANUAL_REVIEW, $lyDo);
            $donHang->refresh();
        }

        $donHang->update(['trang_thai_doi_soat' => 'cho_doi_soat']);

        Log::error('B2B Order Recovery: đơn cần đối soát thủ công.', [
            'don_hang_id' => $donHang->id,
            'ma_don_hang' => $donHang->ma_don_hang,
            'ly_do' => $lyDo,
        ]);

        try {
            $this->telegramAlert->alertManualReview(
                $donHang->fresh() ?: $donHang,
                "PHỤC HỒI B2B: Đơn #{$donHang->ma_don_hang} cần đối soát thủ công. Lý do: {$lyDo} (giữ nguyên hạn mức/công nợ)."
            );
        } catch (\Throwable $e) {
            // Lỗi thông báo không được làm hỏng kết quả nghiệp vụ
            Log::warning('B2B Order Recovery: không gửi được cảnh báo Telegram: ' . $e->getMessage());
        }
    }

    protected function chotOutbox(?B2bOrderOutbox $record): void
    {
        if (!$record || $record->status === 'PROCESSED') {
            return;
        }

        DB::transaction(function () use ($record) {
            // Khóa dòng outbox trước khi ghi: lệnh phục hồi và worker gửi webhook có thể
            // chạy song song trên cùng một bản ghi.
            $moiNhat = B2bOrderOutbox::query()->lockForUpdate()->find($record->id);

            if ($moiNhat && $moiNhat->status !== 'PROCESSED') {
                $moiNhat->update(['status' => 'PROCESSED', 'processed_at' => now()]);
            }
        });
    }
}
