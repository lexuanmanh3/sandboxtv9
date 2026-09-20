<?php

namespace App\Jobs;

use App\DTOs\YeuCauNapTienDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Models\DonHang;
use App\Models\LanGoiNhaCungCap;
use App\Services\Alert\TelegramAlertService;
use App\Services\Topup\DonHangStateMachine;
use App\Services\Topup\DuLieuNhayCam;
use App\Services\Topup\NhaCungCapResolver;
use App\Services\Topup\NhaCungCapRoutingService;
use App\Services\Topup\ViNguoiDungService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessMobileTopupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    /**
     * Thời hạn lease sở hữu xử lý đơn hàng (giây).
     * Bắt buộc phải lớn hơn $timeout để recovery không thể chen vào giữa lúc worker
     * còn đang gọi nhà cung cấp.
     */
    public const LEASE_SECONDS = 300;

    /**
     * Các trạng thái coi như đã kết thúc — worker tuyệt đối không xử lý tiếp.
     */
    private const TRANG_THAI_KET_THUC = ['SUCCESS', 'PROVIDER_PENDING', 'MANUAL_REVIEW', 'REFUND_PENDING', 'REFUNDED', 'FAILED'];

    public function __construct(public int $donHangId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('topup-order-'.$this->donHangId))->expireAfter(self::LEASE_SECONDS)->dontRelease()];
    }

    public function handle(
        NhaCungCapRoutingService $routing,
        NhaCungCapResolver $resolver,
        DonHangStateMachine $state,
        TelegramAlertService $telegramAlert,
        ViNguoiDungService $viService
    ): void {
        $owner = (string) Str::uuid();

        // 1. Nhận sở hữu xử lý đơn hàng bằng lease nguyên tử.
        //    Đây là bằng chứng duy nhất cho biết "còn worker nào đang sở hữu đơn" —
        //    recovery dựa vào lease này thay vì dựa vào tuổi đơn.
        $donHang = DB::transaction(function () use ($state, $owner) {
            $order = DonHang::query()->lockForUpdate()->findOrFail($this->donHangId);

            if (in_array(strtoupper($order->trang_thai_don_hang), self::TRANG_THAI_KET_THUC, true)) {
                return null;
            }

            // Worker khác đang giữ lease còn hiệu lực -> từ chối nhận.
            // Không được tạo lần gọi NCC mới khi chưa chắc chắn worker cũ đã chết.
            if ($order->dangCoLeaseXuLy() && $order->xu_ly_owner !== $owner) {
                Log::info("ProcessMobileTopupJob: Đơn #{$order->id} đang được worker khác sở hữu (lease đến {$order->xu_ly_lease_den->toIso8601String()}), bỏ qua để tránh nạp trùng.");
                return null;
            }

            if (strtoupper($order->trang_thai_don_hang) === 'QUEUED') {
                $state->chuyen($order, TrangThaiDonHang::PROCESSING, 'Worker bắt đầu xử lý');
                $order->update(['bat_dau_xu_ly_luc' => now()]);
            }

            $order->update([
                'xu_ly_owner' => $owner,
                'xu_ly_lease_den' => now()->addSeconds(self::LEASE_SECONDS),
            ]);

            return $order;
        });
        if (!$donHang) return;

        \App\Models\B2bOrderOutbox::where('don_hang_id', $donHang->id)
            ->where('status', 'PENDING')
            ->update(['status' => 'PROCESSING', 'locked_until' => now()->addMinutes(5)]);

        // Chống nạp trùng khi worker trước bị timeout/crash giữa chừng:
        // Bất kỳ lần gọi CHARGING nào chưa có kết quả cuối (kể cả đã bị recovery đánh dấu
        // UNKNOWN_OR_PENDING) đều là bằng chứng "có thể đã gửi lệnh sang NCC".
        // TUYỆT ĐỐI không tạo partner_ref_id mới trong trường hợp này.
        $unresolvedCall = $donHang->lanGoiNhaCungCap()
            ->where('loai_yeu_cau', 'CHARGING')
            ->where(function ($q) {
                $q->whereNull('ket_qua_xac_dinh')
                  ->orWhere('ket_qua_xac_dinh', KetQuaNhaCungCap::UNKNOWN_OR_PENDING->value);
            })
            ->orderByDesc('id')
            ->first();

        if ($unresolvedCall) {
            DB::transaction(function () use ($donHang, $unresolvedCall, $state) {
                $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
                if ($order->trang_thai_don_hang !== TrangThaiDonHang::PROVIDER_PENDING->value) {
                    $state->chuyen($order, TrangThaiDonHang::PROVIDER_PENDING, 'Phát hiện yêu cầu nạp đang xử lý dở dang, chuyển sang tra cứu trạng thái');
                }
                $unresolvedCall->update([
                    'trang_thai' => 'UNKNOWN_OR_PENDING',
                    'ket_qua_xac_dinh' => 'UNKNOWN_OR_PENDING',
                ]);
            });
            $this->nhaLeaseXuLy($owner);
            CheckMobileTopupStatusJob::dispatch($unresolvedCall->id)->delay(now()->addSeconds(10));
            return;
        }

        foreach ($routing->danhSach($donHang) as $mapping) {
            // Kiểm tra quyền sở hữu trước mỗi lần tạo lệnh nạp mới.
            // Nếu recovery/tiến trình khác đã tiếp quản hoặc đơn đã rời PROCESSING thì
            // dừng ngay — không được tạo thêm partner_ref_id mới.
            if (!$this->conSoHuuXuLy($donHang->id, $owner)) {
                Log::warning("ProcessMobileTopupJob: Mất quyền sở hữu xử lý đơn #{$donHang->id}, dừng fallback để tránh nạp trùng.");
                return;
            }

            $lanGoi = LanGoiNhaCungCap::create([
                'don_hang_id' => $donHang->id,
                'cau_hinh_dich_vu_id' => $mapping->cauHinhDichVu?->id,
                'nha_cung_cap_id' => $mapping->nha_cung_cap_id,
                'ket_noi_nha_cung_cap_id' => $mapping->ket_noi_nha_cung_cap_id,
                'san_pham_nha_cung_cap_id' => $mapping->id,
                'lan_thu' => $donHang->lanGoiNhaCungCap()->count() + 1,
                'loai_yeu_cau' => 'CHARGING',
                'partner_ref_id' => $this->partnerRef(),
                'ma_san_pham_ncc' => $mapping->ma_san_pham_ncc,
                'endpoint' => '/api/v2/service/topup/charging',
                'trang_thai' => 'PROCESSING',
                'bat_dau_luc' => now(),
            ]);

            $dto = new YeuCauNapTienDTO(
                $lanGoi->partner_ref_id,
                $donHang->tai_khoan_nhan,
                $mapping->ma_san_pham_ncc,
                $donHang->nha_mang_thuc_te ?: $donHang->nha_mang_yeu_cau ?: $mapping->ma_nha_mang_ncc,
                $donHang->loai_thue_bao_thuc_te ?: $donHang->loai_thue_bao_yeu_cau ?: $mapping->loai_thue_bao ?: 'PREPAID'
            );
            $lanGoi->update(['request_json' => DuLieuNhayCam::che((array) $dto)]);

            // Đo thời gian gọi NCC để phục vụ cảnh báo giao dịch chậm
            $startMicro = microtime(true);
            try {
                $ketQua = $resolver->resolve($mapping->ketNoi)->napTien($dto);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("ProcessMobileTopupJob: Ngoại lệ khi gọi NCC {$mapping->nhaCungCap?->ten_ncc}: " . $e->getMessage(), [
                    'don_hang_id' => $donHang->id,
                    'lan_goi_id'  => $lanGoi->id,
                ]);
                $ketQua = new \App\DTOs\KetQuaNhaCungCapDTO(
                    \App\Enums\KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
                    null,
                    $e->getMessage() ?: 'Lỗi ngoại lệ khi kết nối nhà cung cấp'
                );
            }
            $durationSeconds = microtime(true) - $startMicro;

            // Kiểm tra cảnh báo xử lý GD chậm
            $alertConfig = $mapping->ketNoi->cau_hinh_canh_bao_loi ?? [];
            $slowThreshold = (int) ($alertConfig['canh_bao_xu_ly_cham_giay'] ?? 0);
            if ($slowThreshold > 0 && $durationSeconds >= $slowThreshold) {
                $telegramAlert->alertSlowTransaction($lanGoi, $durationSeconds, $slowThreshold, $mapping->ketNoi);
            }

            DB::transaction(function () use ($donHang, $lanGoi, $mapping, $ketQua, $state) {
                $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
                $lanGoi->update([
                    'response_json' => DuLieuNhayCam::che($ketQua->duLieu),
                    'http_status' => $ketQua->httpStatus,
                    'response_signature' => $ketQua->responseSignature,
                    'chu_ky_hop_le' => $ketQua->chuKyHopLe,
                    'ma_giao_dich_ncc' => $ketQua->maGiaoDichNcc,
                    'ma_loi_ncc' => $ketQua->maLoi,
                    'so_du_ncc_sau_giao_dich' => $ketQua->soDu,
                    'thoi_gian_giao_dich_ncc' => $ketQua->thoiGianGiaoDich,
                    'ket_qua_xac_dinh' => $ketQua->ketQua->value,
                    'trang_thai' => $ketQua->ketQua->value,
                    'ket_thuc_luc' => now(),
                ]);

                if ($ketQua->ketQua === KetQuaNhaCungCap::SUCCESS) {
                    $state->chuyen($order, TrangThaiDonHang::SUCCESS, 'Nhà cung cấp xác nhận thành công');
                    $order->update([
                        'nha_cung_cap_thanh_cong_id' => $mapping->nha_cung_cap_id,
                        'lan_goi_thanh_cong_id' => $lanGoi->id,
                        'gia_von_thuc_te' => $mapping->gia_nhap,
                        'hoan_thanh_luc' => now()
                    ]);

                    // Xử lý B2B: Chuyển khoản giữ sang công nợ và tạo outbox webhook
                    if ($order->dai_ly_api_id) {
                        app(\App\Services\DaiLyApi\B2bCreditService::class)->chotCongNoThanhCong($order);
                        app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.success');
                        \App\Models\B2bOrderOutbox::where('don_hang_id', $order->id)->update(['status' => 'PROCESSED', 'processed_at' => now()]);
                    }
                } elseif ($ketQua->ketQua === KetQuaNhaCungCap::UNKNOWN_OR_PENDING) {
                    $state->chuyen($order, TrangThaiDonHang::PROVIDER_PENDING, 'Kết quả NCC chưa xác định');
                    CheckMobileTopupStatusJob::dispatch($lanGoi->id)->delay(now()->addSeconds(10))->afterCommit();
                }
            });

            // Xử lý Circuit Breaker & Cảnh báo lỗi
            $circuitConfig = $mapping->ketNoi->cau_hinh_dong_tu_dong ?? [];
            $maxConsecutiveFailures = (int) ($circuitConfig['so_gd_that_bai_lien_tiep'] ?? 0);
            $suspendSeconds = (int) ($circuitConfig['thoi_gian_dong'] ?? $circuitConfig['thoi_gian_dong_giay'] ?? 0);
            $cacheKey = "circuit_breaker_fails_{$mapping->ketNoi->id}";

            if ($ketQua->ketQua === KetQuaNhaCungCap::SUCCESS) {
                // Reset bộ đếm lỗi liên tiếp khi giao dịch thành công
                Cache::forget($cacheKey);
                $this->nhaLeaseXuLy($owner);
                $telegramAlert->alertOrderSuccess($donHang->fresh(), $lanGoi);
                return;
            }

            // Cả lỗi từ chối lẫn lỗi đang chờ xử lý liên tiếp (nghẽn mạng/mã 34) đều được tính vào bộ đếm ngắt mạch
            $isFailureForCircuit = in_array($ketQua->ketQua, [KetQuaNhaCungCap::DEFINITIVE_FAILURE, KetQuaNhaCungCap::UNKNOWN_OR_PENDING], true);

            if ($isFailureForCircuit) {
                // Gửi cảnh báo giao dịch cho tất cả trường hợp không thành công (ngoại trừ mã lỗi cấu hình bỏ qua)
                $lanGoiFresh = $lanGoi->fresh() ?: $lanGoi;
                $telegramAlert->alertTransactionFailure($lanGoiFresh, $ketQua->thongBao ?: 'Giao dịch phát sinh phản hồi chưa thành công từ NCC', $mapping->ketNoi);

                // Kiểm tra xem mã lỗi có thuộc danh sách bỏ qua không
                $ignoredCodes = array_filter(array_map('trim', explode(',', (string) ($circuitConfig['ma_loi_bo_qua'] ?? ''))));
                if (!$lanGoiFresh->ma_loi_ncc || !in_array((string) $lanGoiFresh->ma_loi_ncc, $ignoredCodes, true)) {
                    if (!Cache::has($cacheKey)) {
                        Cache::add($cacheKey, 1, now()->addHours(24));
                        $currentFailures = 1;
                    } else {
                        $currentFailures = (int) Cache::increment($cacheKey);
                    }

                    // Kích hoạt Circuit Breaker nếu chạm hoặc vượt ngưỡng lỗi liên tiếp
                    if ($maxConsecutiveFailures > 0 && $currentFailures >= $maxConsecutiveFailures) {
                        // Cập nhật trạng thái trực tiếp trong Database sang TẠM DỪNG
                        $mapping->ketNoi->update(['trang_thai' => 'tam_dung']);
                        $mapping->nhaCungCap?->update(['trang_thai' => 'tam_dung']);

                        Cache::forget($cacheKey); // Reset đếm lỗi sau khi đã ngắt
                        // Lưu cờ đánh dấu circuit breaker bị tripped
                        Cache::put("circuit_breaker_tripped_{$mapping->ketNoi->id}", true, now()->addSeconds($suspendSeconds > 0 ? $suspendSeconds : 300));
                        $telegramAlert->alertCircuitBreakerTriggered($mapping->ketNoi, $currentFailures, $suspendSeconds);
                    }
                }
            }

            if ($ketQua->ketQua !== KetQuaNhaCungCap::DEFINITIVE_FAILURE) return;

            // Nếu tuyến cấu hình chặn fallback khi lỗi chắc chắn (ket_thuc_cau_hinh), dừng luồng ngay không thử NCC khác
            if ($mapping->cauHinhDichVu?->ket_thuc_cau_hinh) {
                break;
            }
        }

        DB::transaction(function () use ($donHang, $state) {
            $order = DonHang::query()->lockForUpdate()->findOrFail($donHang->id);
            if (strtoupper($order->trang_thai_don_hang) === 'PROCESSING') {
                $state->chuyen($order, TrangThaiDonHang::FAILED, 'Tất cả NCC đều thất bại chắc chắn');
                $order->update(['that_bai_luc' => now()]);

                // Tự động hoàn tiền ví nếu đơn từ frontend và đã thanh toán
                if ($order->nguon_don === 'frontend' && $order->nguoi_dung_id && (float) $order->gia_ban > 0) {
                    app(\App\Services\Topup\HoanTienDonHangService::class)->hoanTien(
                        orderId: $order->id,
                        actorType: 'system',
                        actorId: null,
                        actorName: 'worker',
                        reason: 'Tất cả NCC đều thất bại chắc chắn',
                        skipProviderCheck: true
                    );
                }

                // Xử lý B2B: Giải phóng khoản giữ hạn mức và gửi webhook thông báo thất bại
                if ($order->dai_ly_api_id) {
                    app(\App\Services\DaiLyApi\B2bCreditService::class)->giaiPhongKhoanGiu($order, 'Tất cả NCC đều thất bại chắc chắn');
                    app(\App\Services\DaiLyApi\B2bWebhookService::class)->taoSuKien($order, 'order.failed');
                    \App\Models\B2bOrderOutbox::where('don_hang_id', $order->id)->update(['status' => 'PROCESSED', 'processed_at' => now()]);
                }
            }
        });

        // Đơn đã có kết quả cuối hoặc đã chuyển sang luồng tra cứu -> nhả lease sở hữu.
        $this->nhaLeaseXuLy($owner);
    }

    /**
     * Kiểm tra worker còn thực sự sở hữu quyền xử lý đơn hàng hay không.
     * Trả về false nếu lease đã hết hạn, đã bị tiến trình khác chiếm, hoặc đơn đã
     * rời trạng thái PROCESSING. Dùng trước mọi hành động có thể gửi lệnh sang NCC.
     */
    private function conSoHuuXuLy(int $donHangId, string $owner): bool
    {
        return DB::transaction(function () use ($donHangId, $owner) {
            $order = DonHang::query()->lockForUpdate()->find($donHangId);

            if (!$order || $order->xu_ly_owner !== $owner) {
                return false;
            }

            if (!$order->dangCoLeaseXuLy()) {
                return false;
            }

            if (strtoupper((string) $order->trang_thai_don_hang) !== 'PROCESSING') {
                return false;
            }

            // Gia hạn lease cho lần lặp tiếp theo của vòng fallback nhà cung cấp
            $order->update(['xu_ly_lease_den' => now()->addSeconds(self::LEASE_SECONDS)]);

            return true;
        });
    }

    /**
     * Nhả lease sở hữu xử lý (chỉ nhả nếu vẫn đang là chủ sở hữu).
     */
    private function nhaLeaseXuLy(string $owner): void
    {
        DonHang::where('id', $this->donHangId)
            ->where('xu_ly_owner', $owner)
            ->update(['xu_ly_owner' => null, 'xu_ly_lease_den' => null]);
    }

    private function partnerRef(): string
    {
        return 'TP'.now()->format('YmdHis').strtoupper(Str::random(12));
    }
}
