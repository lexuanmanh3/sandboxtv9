<?php

namespace App\Services\DaiLyApi;

use App\Jobs\ProcessMobileTopupJob;
use App\Models\B2bIdempotencyRecord;
use App\Models\BangGiaDaiLy;
use App\Models\DaiLyApi;
use App\Models\DonHang;
use App\Models\SanPham;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class B2bOrderService
{
    public function __construct(
        protected KiemTraQuyenDaiLyApiService $quyenService,
        protected B2bCreditService $creditService
    ) {}

    /**
     * Tạo đơn nạp tiền B2B có kiểm soát quyền, hạn mức, giá bán và idempotency.
     */
    public function taoDonHang(DaiLyApi $daiLy, array $payload, string $idempotencyKey, string $rawRequestContent): array
    {
        $partnerOrderId = trim((string) ($payload['partner_order_id'] ?? ''));
        $productCode = trim((string) ($payload['product_code'] ?? ''));
        $account = trim((string) ($payload['phone_number'] ?? $payload['account'] ?? ''));

        if (empty($partnerOrderId)) {
            throw new DomainException('Mã đơn hàng đối tác (partner_order_id) là bắt buộc.', 422);
        }
        if (empty($productCode)) {
            throw new DomainException('Mã sản phẩm (product_code) là bắt buộc.', 422);
        }
        if (empty($account)) {
            throw new DomainException('Số điện thoại/Tài khoản nhận là bắt buộc.', 422);
        }

        $requestHash = hash('sha256', $rawRequestContent);

        // 1. Kiểm tra Idempotency Record
        $existingIdempotency = B2bIdempotencyRecord::where('dai_ly_api_id', $daiLy->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingIdempotency) {
            if ($existingIdempotency->request_hash !== $requestHash) {
                throw new DomainException('Idempotency-Key đã được sử dụng với nội dung request khác.', 409);
            }

            if ($existingIdempotency->status === 'COMPLETED' && $existingIdempotency->response_json) {
                return [
                    'http_code' => $existingIdempotency->http_status ?: 200,
                    'data' => json_decode($existingIdempotency->response_json, true),
                    'is_replay' => true,
                ];
            }

            if ($existingIdempotency->status === 'PROCESSING') {
                throw new DomainException('Yêu cầu đang được hệ thống xử lý, vui lòng chờ và tra cứu lại với Idempotency-Key.', 409);
            }
        }

        // 2. Kiểm tra trùng mã đơn đối tác (partner_order_id)
        $existingOrder = DonHang::where('dai_ly_api_id', $daiLy->id)
            ->where('ma_don_doi_tac', $partnerOrderId)
            ->first();

        if ($existingOrder) {
            throw new DomainException("Mã đơn đối tác '{$partnerOrderId}' đã tồn tại trong hệ thống.", 422);
        }

        // 3. Tra cứu sản phẩm
        $sanPham = SanPham::with(['loaiSanPham', 'dichVu'])
            ->where('ma_san_pham', $productCode)
            ->first();

        if (!$sanPham) {
            throw new DomainException("Sản phẩm với mã '{$productCode}' không tồn tại.", 404);
        }

        // 4. Kiểm tra sản phẩm có bị loại trừ không
        $cauHinh = $daiLy->cauHinhApi;
        $excludedProducts = (array) ($cauHinh?->san_pham_loai_tru ?? []);
        if (in_array((string) $sanPham->id, $excludedProducts, true) || in_array($sanPham->ma_san_pham, $excludedProducts, true)) {
            throw new DomainException("Sản phẩm '{$productCode}' nằm trong danh sách loại trừ của đại lý.", 403);
        }

        // 5. Kiểm tra quyền 3 tầng (dịch vụ, loại sản phẩm, sản phẩm)
        $kiemTraQuyen = $this->quyenService->kiemTraQuyenSanPham($daiLy, $sanPham);
        if (!$kiemTraQuyen['duoc_phep']) {
            throw new DomainException($kiemTraQuyen['ly_do'] ?: 'Đại lý không được phép sử dụng sản phẩm này.', 403);
        }

        // 6. Tính giá bán đại lý (Bảng giá riêng hoặc mặc định hệ thống)
        $bangGia = BangGiaDaiLy::where('dai_ly_api_id', $daiLy->id)
            ->where('san_pham_id', $sanPham->id)
            ->whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])
            ->first();

        $menhGia = (float) $sanPham->menh_gia;
        if ($bangGia) {
            if ($bangGia->loai_chiet_khau === 'FIXED_PRICE' && (float) $bangGia->gia_ban_ap_dung > 0) {
                $giaBan = (float) $bangGia->gia_ban_ap_dung;
                $chietKhau = max(0.0, $menhGia - $giaBan);
            } else {
                $phanTram = (float) $bangGia->gia_tri_chiet_khau;
                $chietKhau = round($menhGia * ($phanTram / 100), 2);
                $giaBan = $menhGia - $chietKhau;
            }
        } else {
            $giaBan = (float) ($sanPham->gia_ban ?: $menhGia);
            $chietKhau = (float) ($sanPham->chiet_khau ?: ($menhGia - $giaBan));
        }

        // 7. Thực hiện giữ hạn mức & tạo đơn hàng nguyên tử
        $result = DB::transaction(function () use (
            $daiLy,
            $sanPham,
            $partnerOrderId,
            $idempotencyKey,
            $requestHash,
            $account,
            $menhGia,
            $giaBan,
            $chietKhau
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

            // Giữ hạn mức
            $this->creditService->kiemTraVaGiuHanMuc($daiLy, $donHang, $giaBan);

            // Lưu bản ghi Idempotency
            $responseData = [
                'success' => true,
                'order_id' => $donHang->id,
                'order_code' => $donHang->ma_don_hang,
                'partner_order_id' => $donHang->ma_don_doi_tac,
                'status' => 'QUEUED',
                'message' => 'Đơn hàng B2B đã được tiếp nhận và đưa vào hàng đợi xử lý.',
                'account' => $donHang->tai_khoan_nhan,
                'product_code' => $sanPham->ma_san_pham,
                'amount' => $menhGia,
                'price' => $giaBan,
                'discount' => $chietKhau,
                'created_at' => $donHang->created_at->toIso8601String(),
            ];

            B2bIdempotencyRecord::create([
                'dai_ly_api_id' => $daiLy->id,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
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

        // 8. Đẩy job nạp tiền vào Queue sau khi transaction đã commit thành công
        ProcessMobileTopupJob::dispatch($result['don_hang']->id)->afterCommit();

        return [
            'http_code' => 202,
            'data' => $result['response_data'],
            'is_replay' => false,
        ];
    }
}
