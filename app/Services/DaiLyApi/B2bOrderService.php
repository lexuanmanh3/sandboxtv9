<?php

namespace App\Services\DaiLyApi;

use App\Jobs\ProcessMobileTopupJob;
use App\Models\B2bIdempotencyRecord;
use App\Models\B2bOrderOutbox;
use App\Models\BangGiaDaiLy;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use App\Models\SanPham;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class B2bOrderService
{
    public function __construct(
        protected KiemTraQuyenDaiLyApiService $quyenService,
        protected B2bCreditService $creditService,
        protected B2bPricingService $pricingService
    ) {}

    /**
     * Tạo đơn nạp tiền B2B có kiểm soát quyền, hạn mức, giá bán, Idempotency và Transactional Outbox.
     */
    public function taoDonHang(DaiLyApi $daiLy, array $payload, string $idempotencyKey, string $rawRequestContent): array
    {
        $partnerOrderId = trim((string) ($payload['partner_order_id'] ?? ''));
        $productCode = trim((string) ($payload['product_code'] ?? ''));
        $account = trim((string) ($payload['account'] ?? $payload['phone_number'] ?? ''));

        // 1. Validate dữ liệu đầu vào nghiêm ngặt
        if (empty($partnerOrderId)) {
            throw new DomainException('Mã đơn hàng đối tác (partner_order_id) là bắt buộc.', 422);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $partnerOrderId)) {
            throw new DomainException('Mã đơn hàng đối tác không đúng định dạng (1-64 ký tự chữ, số, gạch dưới, gạch ngang).', 422);
        }

        if (empty($productCode)) {
            throw new DomainException('Mã sản phẩm (product_code) là bắt buộc.', 422);
        }

        if (empty($account)) {
            throw new DomainException('Số điện thoại/Tài khoản nhận là bắt buộc.', 422);
        }
        if (!preg_match('/^0[0-9]{9}$/', $account)) {
            throw new DomainException('Số điện thoại nhận nạp tiền không đúng định dạng hợp lệ (10 chữ số bắt đầu bằng 0).', 422);
        }

        // 2. Tra cứu sản phẩm trong kho (Chính xác theo catalog, không tự ý suy đoán)
        $sanPham = SanPham::with(['loaiSanPham', 'dichVu'])
            ->where('ma_san_pham', $productCode)
            ->first();

        if (!$sanPham) {
            throw new DomainException("Sản phẩm với mã '{$productCode}' không tồn tại trên hệ thống.", 404);
        }

        // 3. Chuẩn hóa Fingerprint nghiệp vụ (Business Fingerprint)
        // Không phụ thuộc vào whitespace JSON, timestamp, nonce hay chữ ký
        $businessData = [
            'account' => $account,
            'partner_order_id' => $partnerOrderId,
            'product_code' => $sanPham->ma_san_pham,
        ];
        ksort($businessData);
        $businessFingerprint = hash('sha256', json_encode($businessData, JSON_UNESCAPED_UNICODE));

        // 4. Kiểm tra Idempotency Record trước khi mở transaction
        $existingIdempotency = B2bIdempotencyRecord::where('dai_ly_api_id', $daiLy->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingIdempotency) {
            if ($existingIdempotency->request_hash !== $businessFingerprint) {
                throw new DomainException('Idempotency-Key đã được sử dụng với nội dung giao dịch khác.', 409);
            }

            if ($existingIdempotency->status === 'COMPLETED' && $existingIdempotency->response_json) {
                return [
                    'http_code' => $existingIdempotency->http_status ?: 202,
                    'data' => json_decode($existingIdempotency->response_json, true),
                    'is_replay' => true,
                ];
            }

            if ($existingIdempotency->status === 'PROCESSING') {
                throw new DomainException('Yêu cầu đang được hệ thống xử lý, vui lòng chờ và tra cứu lại.', 409);
            }
        }

        // 5. Kiểm tra trùng mã đơn đối tác (partner_order_id) với Idempotency-Key khác
        $existingOrder = DonHang::where('dai_ly_api_id', $daiLy->id)
            ->where('ma_don_doi_tac', $partnerOrderId)
            ->first();

        if ($existingOrder) {
            throw new DomainException("Mã đơn đối tác '{$partnerOrderId}' đã tồn tại trong hệ thống.", 409);
        }

        // 6. Kiểm tra sản phẩm có bị loại trừ không
        $cauHinh = $daiLy->cauHinhApi;
        $excludedProducts = (array) ($cauHinh?->san_pham_loai_tru ?? []);
        if (in_array((string) $sanPham->id, $excludedProducts, true) || in_array($sanPham->ma_san_pham, $excludedProducts, true)) {
            throw new DomainException("Sản phẩm '{$productCode}' nằm trong danh sách loại trừ của đại lý.", 403);
        }

        // 7. Kiểm tra quyền 3 tầng (dịch vụ, loại sản phẩm, sản phẩm)
        $kiemTraQuyen = $this->quyenService->kiemTraQuyenSanPham($daiLy, $sanPham);
        if (!$kiemTraQuyen['duoc_phep']) {
            throw new DomainException($kiemTraQuyen['ly_do'] ?: 'Đại lý không được phép sử dụng sản phẩm này.', 403);
        }

        // 8. Tính giá bán đại lý qua B2bPricingService thống nhất
        $priceInfo = $this->pricingService->tinhGia($daiLy, $sanPham);
        $menhGia = $priceInfo['face_value'];
        $giaBan = $priceInfo['price'];
        $chietKhau = $priceInfo['discount'];

        // 9. Thực hiện lưu Transaction nguyên tử: DonHang + GiuHanMuc + Outbox + Idempotency
        try {
            $result = DB::transaction(function () use (
                $daiLy,
                $sanPham,
                $partnerOrderId,
                $idempotencyKey,
                $businessFingerprint,
                $account,
                $menhGia,
                $giaBan,
                $chietKhau,
                $businessData
            ) {
                $maDonHang = 'B2B' . date('YmdHis') . strtoupper(Str::random(6));

                $donHang = DonHang::create([
                    'ma_don_hang' => $maDonHang,
                    'nguon_don' => 'b2b',
                    'dai_ly_api_id' => $daiLy->id,
                    'ma_don_doi_tac' => $partnerOrderId,
                    'idempotency_key' => $idempotencyKey,
                    'dich_vu_id' => $sanPham->dich_vu_id,
                    'loai_san_pham_id' => $sanPham->loai_san_pham_id,
                    'san_pham_id' => $sanPham->id,
                    'tai_khoan_nhan' => $account,
                    'so_luong' => 1,
                    'menh_gia' => $menhGia,
                    'gia_ban' => $giaBan,
                    'gia_von' => (float) ($sanPham->gia_nhap ?? 0),
                    'chiet_khau' => $chietKhau,
                    'loi_nhuan' => max(0.0, $giaBan - (float) ($sanPham->gia_nhap ?? 0)),
                    'phuong_thuc_thanh_toan' => 'cong_no',
                    'trang_thai_thanh_toan' => 'chua_thanh_toan',
                    'trang_thai_don_hang' => 'QUEUED',
                    'ma_san_pham_snapshot' => $sanPham->ma_san_pham,
                    'ten_san_pham_snapshot' => $sanPham->ten_san_pham,
                    'menh_gia_snapshot' => $menhGia,
                    'gia_ban_snapshot' => $giaBan,
                    'nha_mang_yeu_cau' => $sanPham->loaiSanPham?->ma_loai_san_pham ?? null,
                    'loai_thue_bao_yeu_cau' => 'PREPAID',
                ]);

                // Giữ hạn mức nguyên tử (Khóa bi quan kiểm tra số dư)
                $this->creditService->kiemTraVaGiuHanMuc($daiLy, $donHang, $giaBan);

                // Lưu bản ghi Transactional Outbox
                B2bOrderOutbox::create([
                    'don_hang_id' => $donHang->id,
                    'dai_ly_api_id' => $daiLy->id,
                    'partner_order_id' => $partnerOrderId,
                    'product_code' => $sanPham->ma_san_pham,
                    'account' => $account,
                    'payload_json' => json_encode($businessData, JSON_UNESCAPED_UNICODE),
                    'status' => 'PENDING',
                ]);

                // Chuẩn hóa response trả về (trạng thái tiếp nhận thực tế là pending, completed_at là null)
                $responseData = [
                    'success' => true,
                    'order_id' => $donHang->id,
                    'order_code' => $donHang->ma_don_hang,
                    'partner_order_id' => $donHang->ma_don_doi_tac,
                    'status' => 'pending',
                    'message' => 'Đơn hàng B2B đã được tiếp nhận và đưa vào hàng đợi xử lý.',
                    'account' => $donHang->tai_khoan_nhan,
                    'product_code' => $sanPham->ma_san_pham,
                    'amount' => $menhGia,
                    'price' => $giaBan,
                    'discount' => $chietKhau,
                    'created_at' => $donHang->created_at->toIso8601String(),
                    'completed_at' => null,
                ];

                // Lưu bản ghi Idempotency Record
                B2bIdempotencyRecord::create([
                    'dai_ly_api_id' => $daiLy->id,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $businessFingerprint,
                    'status' => 'COMPLETED',
                    'http_status' => 202,
                    'response_json' => json_encode($responseData, JSON_UNESCAPED_UNICODE),
                    'don_hang_id' => $donHang->id,
                    'expires_at' => now()->addDays(7),
                ]);

                return [
                    'don_hang' => $donHang,
                    'response_data' => $responseData,
                ];
            });
        } catch (QueryException $qe) {
            // Xử lý race condition khi 2 request gửi đồng thời đụng unique constraint
            $errorCode = $qe->errorInfo[1] ?? null;
            $errorMsg = $qe->getMessage();

            // Mã lỗi SQL 1062: Duplicate entry
            if ($errorCode === 1062 || str_contains($errorMsg, '1062 Duplicate entry')) {
                // Kiểm tra xem trùng trên index nào
                if (str_contains($errorMsg, 'uq_b2b_idempotency_key')) {
                    $retryRecord = B2bIdempotencyRecord::where('dai_ly_api_id', $daiLy->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();

                    if ($retryRecord && $retryRecord->request_hash === $businessFingerprint && $retryRecord->response_json) {
                        return [
                            'http_code' => $retryRecord->http_status ?: 202,
                            'data' => json_decode($retryRecord->response_json, true),
                            'is_replay' => true,
                        ];
                    }

                    throw new DomainException('Idempotency-Key đã được sử dụng với nội dung giao dịch khác.', 409);
                }

                if (str_contains($errorMsg, 'uq_don_hang_partner_order')) {
                    // Kiểm tra xem đơn hàng đã tồn tại có cùng Idempotency-Key không
                    $existingOrder = DonHang::where('dai_ly_api_id', $daiLy->id)
                        ->where('ma_don_doi_tac', $partnerOrderId)
                        ->first();

                    if ($existingOrder && $existingOrder->idempotency_key === $idempotencyKey) {
                        $retryRecord = B2bIdempotencyRecord::where('dai_ly_api_id', $daiLy->id)
                            ->where('idempotency_key', $idempotencyKey)
                            ->first();

                        if ($retryRecord && $retryRecord->request_hash === $businessFingerprint && $retryRecord->response_json) {
                            return [
                                'http_code' => $retryRecord->http_status ?: 202,
                                'data' => json_decode($retryRecord->response_json, true),
                                'is_replay' => true,
                            ];
                        }
                    }

                    throw new DomainException("Mã đơn đối tác '{$partnerOrderId}' đã tồn tại trong hệ thống.", 409);
                }
            }

            throw $qe;
        }

        // 10. Đẩy job nạp tiền vào Queue sau khi transaction đã commit thành công
        ProcessMobileTopupJob::dispatch($result['don_hang']->id)->afterCommit();

        return [
            'http_code' => 202,
            'data' => $result['response_data'],
            'is_replay' => false,
        ];
    }
}
