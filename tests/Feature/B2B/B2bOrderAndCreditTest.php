<?php

namespace Tests\Feature\B2B;

use App\Models\BangGiaDaiLy;
use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\SoPhatSinhCongNo;
use App\Services\DaiLyApi\B2bCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2bOrderAndCreditTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected SanPham $product;
    protected string $secret = 'b2b_order_test_secret_key_123456';
    protected B2bCreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->creditService = app(B2bCreditService::class);

        $dichVu = DichVu::firstOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại',
            'trang_thai' => 'hoat_dong',
        ]);

        $loaiSp = LoaiSanPham::firstOrCreate(['ma_loai_san_pham' => 'VTE_TOPUP'], [
            'ten_loai_san_pham' => 'Viettel Topup',
            'dich_vu_id' => $dichVu->id,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->product = SanPham::firstOrCreate(['ma_san_pham' => 'TEST_VT_10K'], [
            'ten_san_pham' => 'Viettel 10.000đ Test',
            'dich_vu_id' => $dichVu->id,
            'loai_san_pham_id' => $loaiSp->id,
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'chiet_khau' => 300,
            'gia_nhap' => 9500,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'PARTNER_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý B2B Order Test',
            'trang_thai' => 'hoat_dong',
        ]);

        // Cấp quyền 3 tầng cho đối tác: Dịch vụ -> Loại SP -> Sản phẩm
        $this->partner->dichVu()->attach($dichVu->id);
        $this->partner->loaiSanPham()->attach($loaiSp->id);
        $this->partner->sanPham()->attach($this->product->id);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'client_' . strtolower(Str::random(10)),
            'secret_key_ma_hoa' => $this->secret,
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => 1000000,
            'cong_no_hien_tai' => 0,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
            'dich_vu_duoc_phep' => ['MOBILE_TOPUP'],
            'san_pham_loai_tru' => [],
        ]);
    }

    protected function signHeaders(string $method, string $uri, string $body = '', array $extraHeaders = []): array
    {
        $ts = time();
        $nc = (string) Str::uuid();
        $cid = $this->config->client_id;
        $sec = $this->secret;
        $idempotencyKey = $extraHeaders['Idempotency-Key'] ?? $extraHeaders['X-Idempotency-Key'] ?? '';

        $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH), '/');
        $query = parse_url($uri, PHP_URL_QUERY);
        $canonicalUri = $query ? "{$path}?{$query}" : $path;

        $rawBody = in_array(strtoupper($method), ['GET', 'HEAD']) ? '' : $body;
        $bodyHash = hash('sha256', $rawBody);
        $stringToSign = strtoupper($method) . "\n{$canonicalUri}\n{$ts}\n{$nc}\n{$idempotencyKey}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $stringToSign, $sec);

        return array_merge([
            'X-Client-Id' => $cid,
            'X-Timestamp' => (string) $ts,
            'X-Nonce' => $nc,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $extraHeaders);
    }

    public function test_create_order_success_holds_credit_and_queues_job(): void
    {
        $uri = '/api/b2b/v1/orders';
        $partnerOrderId = 'PARTNER_ORD_' . time();
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988776655',
        ];

        $jsonPayload = json_encode($payload);
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $response = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);

        $response->assertStatus(202);
        $data = $response->json();
        $this->assertTrue(in_array($data['status'], ['QUEUED', 'pending'], true));
        $this->assertEquals($partnerOrderId, $data['partner_order_id']);
        $this->assertEquals(9700, $data['price']);

        // Kiểm tra giữ hạn mức
        $order = DonHang::where('ma_don_doi_tac', $partnerOrderId)->first();
        $this->assertNotNull($order);
        $this->assertEquals('QUEUED', $order->trang_thai_don_hang);
        $this->assertEquals('cong_no', $order->phuong_thuc_thanh_toan);

        $hold = KhoanGiuHanMuc::where('don_hang_id', $order->id)->first();
        $this->assertNotNull($hold);
        $this->assertEquals('HOLDING', $hold->trang_thai);
        $this->assertEquals(9700, (float) $hold->so_tien_giu);

        // Hạn mức khả dụng phải giảm đúng 9700
        $tinhToan = $this->creditService->tinhHanMucKhaDung($this->partner);
        $this->assertEquals(9700, $tinhToan['khoan_dang_giu']);
        $this->assertEquals(1000000 - 9700, $tinhToan['han_muc_kha_dung']);
    }

    public function test_idempotent_replay_returns_cached_response(): void
    {
        $uri = '/api/b2b/v1/orders';
        $partnerOrderId = 'PARTNER_ORD_IDEMP_' . time();
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988776655',
        ];

        $jsonPayload = json_encode($payload);
        $headers1 = $this->signHeaders('POST', $uri, $jsonPayload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $resp1 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers1), $jsonPayload);
        $resp1->assertStatus(202);

        // Replay request với cùng Idempotency-Key và cùng payload
        $headers2 = $this->signHeaders('POST', $uri, $jsonPayload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $resp2 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers2), $jsonPayload);
        $resp2->assertStatus(202);
        $resp2->assertHeader('X-Cache', 'HIT');
        $this->assertEquals($resp1->json('order_id'), $resp2->json('order_id'));

        // Chỉ có 1 bản ghi DonHang và 1 bản ghi KhoanGiuHanMuc duy nhất
        $this->assertEquals(1, DonHang::where('ma_don_doi_tac', $partnerOrderId)->count());
        $this->assertEquals(1, KhoanGiuHanMuc::where('dai_ly_api_id', $this->partner->id)->count());
    }

    public function test_idempotency_conflict_returns_409(): void
    {
        $uri = '/api/b2b/v1/orders';
        $idempotencyKey = (string) Str::uuid();

        $payload1 = [
            'partner_order_id' => 'ORD_1',
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988111111',
        ];
        $jsonPayload1 = json_encode($payload1);
        $headers1 = $this->signHeaders('POST', $uri, $jsonPayload1, ['Idempotency-Key' => $idempotencyKey]);
        $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers1), $jsonPayload1);

        // Gửi cùng Idempotency-Key nhưng payload khác
        $payload2 = [
            'partner_order_id' => 'ORD_DIFFERENT',
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988222222',
        ];
        $jsonPayload2 = json_encode($payload2);
        $headers2 = $this->signHeaders('POST', $uri, $jsonPayload2, ['Idempotency-Key' => $idempotencyKey]);
        $resp2 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers2), $jsonPayload2);

        $resp2->assertStatus(409);
    }

    public function test_duplicate_partner_order_id_returns_422(): void
    {
        $uri = '/api/b2b/v1/orders';
        $partnerOrderId = 'PARTNER_ORD_DUP_' . time();

        $payload1 = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988111111',
        ];
        $jsonPayload1 = json_encode($payload1);
        $headers1 = $this->signHeaders('POST', $uri, $jsonPayload1, ['Idempotency-Key' => (string) Str::uuid()]);
        $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers1), $jsonPayload1);

        // Gửi cùng partner_order_id nhưng khác Idempotency-Key
        $payload2 = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988222222',
        ];
        $jsonPayload2 = json_encode($payload2);
        $headers2 = $this->signHeaders('POST', $uri, $jsonPayload2, ['Idempotency-Key' => (string) Str::uuid()]);
        $resp2 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers2), $jsonPayload2);

        $this->assertTrue(in_array($resp2->status(), [409, 422], true));
    }

    public function test_unauthorized_service_returns_403(): void
    {
        // Gỡ quyền dịch vụ của đối tác
        $this->partner->dichVu()->detach();

        $uri = '/api/b2b/v1/orders';
        $payload = [
            'partner_order_id' => 'ORD_UNAUTH_' . time(),
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988111111',
        ];
        $jsonPayload = json_encode($payload);
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, ['Idempotency-Key' => (string) Str::uuid()]);
        $resp = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);

        $resp->assertStatus(403);
    }

    public function test_excluded_product_returns_403(): void
    {
        $this->config->update(['san_pham_loai_tru' => [$this->product->ma_san_pham]]);

        $uri = '/api/b2b/v1/orders';
        $payload = [
            'partner_order_id' => 'ORD_EXCL_' . time(),
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988111111',
        ];
        $jsonPayload = json_encode($payload);
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, ['Idempotency-Key' => (string) Str::uuid()]);
        $resp = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);

        $resp->assertStatus(403);
    }

    public function test_insufficient_credit_limit_returns_422(): void
    {
        // Giảm hạn mức của đại lý xuống 5,000 (nhỏ hơn giá 9,700)
        $this->config->update([
            'han_muc_cong_no' => 5000,
            'cong_no_hien_tai' => 0,
        ]);

        $uri = '/api/b2b/v1/orders';
        $payload = [
            'partner_order_id' => 'ORD_OVERDRAFT_' . time(),
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988111111',
        ];
        $jsonPayload = json_encode($payload);
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, ['Idempotency-Key' => (string) Str::uuid()]);
        $resp = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);

        $resp->assertStatus(422);
    }

    public function test_provider_success_commits_hold_and_records_debt_ledger(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'B2B_TEST_COMMIT_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'PARTNER_ORD_SUCCESS_' . time(),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988333444',
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'gia_von' => 9500,
            'chiet_khau' => 300,
            'loi_nhuan' => 200,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'QUEUED',
        ]);

        // Tạo giữ hạn mức
        $this->creditService->kiemTraVaGiuHanMuc($this->partner, $donHang, 9700);
        $this->assertEquals(0, (float) $this->config->fresh()->cong_no_hien_tai);

        // Chốt công nợ thành công
        $this->creditService->chotCongNoThanhCong($donHang);

        // Kiểm tra trạng thái khoản giữ là COMMITTED
        $hold = KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->first();
        $this->assertEquals('COMMITTED', $hold->trang_thai);

        // Kiểm tra công nợ hiện tại tăng lên 9700
        $this->assertEquals(9700, (float) $this->config->fresh()->cong_no_hien_tai);

        // Kiểm tra sổ phát sinh công nợ
        $ledger = SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('TANG_CONG_NO_DON_HANG', $ledger->loai_phat_sinh);
        $this->assertEquals(9700, (float) $ledger->so_tien);
        $this->assertEquals(9700, (float) $ledger->so_du_sau);
    }

    public function test_provider_failure_releases_hold_without_debt(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'B2B_TEST_RELEASE_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'PARTNER_ORD_FAIL_' . time(),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988555666',
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'gia_von' => 9500,
            'chiet_khau' => 300,
            'loi_nhuan' => 200,
            'phuong_thuc_thanh_toan' => 'cong_no',
            'trang_thai_thanh_toan' => 'chua_thanh_toan',
            'trang_thai_don_hang' => 'QUEUED',
        ]);

        $this->creditService->kiemTraVaGiuHanMuc($this->partner, $donHang, 9700);

        // Giải phóng giữ hạn mức do lỗi nhà cung cấp
        $this->creditService->giaiPhongKhoanGiu($donHang, 'Nhà cung cấp trả lời: Thất bại');

        // Kiểm tra khoản giữ đổi sang RELEASED
        $hold = KhoanGiuHanMuc::where('don_hang_id', $donHang->id)->first();
        $this->assertEquals('RELEASED', $hold->trang_thai);

        // Công nợ hiện tại vẫn là 0
        $this->assertEquals(0, (float) $this->config->fresh()->cong_no_hien_tai);

        // Không có phát sinh tăng công nợ trong sổ cái
        $this->assertEquals(0, SoPhatSinhCongNo::where('don_hang_id', $donHang->id)->count());
    }

    public function test_b2b_custom_pricing_applies_and_is_immutable(): void
    {
        // Thiết lập bảng giá riêng cho đại lý: chiết khấu 5% (giá bán = 9500)
        BangGiaDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'san_pham_id' => $this->product->id,
            'loai_chiet_khau' => 'PERCENT',
            'gia_tri_chiet_khau' => 5.0,
            'gia_ban_ap_dung' => 9500,
            'trang_thai' => 'hoat_dong',
        ]);

        $uri = '/api/b2b/v1/orders';
        $partnerOrderId = 'PARTNER_ORD_CUSTOM_PRICE_' . time();
        $payload = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'phone_number' => '0988776655',
        ];
        $jsonPayload = json_encode($payload);
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, ['Idempotency-Key' => (string) Str::uuid()]);
        $resp = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);

        $resp->assertStatus(202);
        $this->assertEquals(9500, $resp->json('price'));

        $order = DonHang::where('ma_don_doi_tac', $partnerOrderId)->first();
        $this->assertEquals(9500, (float) $order->gia_ban);
        $this->assertEquals(9500, (float) $order->gia_ban_snapshot);

        // Thay đổi bảng giá đại lý sau đó sang chiết khấu 2% (giá bán = 9800)
        BangGiaDaiLy::where('dai_ly_api_id', $this->partner->id)
            ->where('san_pham_id', $this->product->id)
            ->update(['gia_tri_chiet_khau' => 2.0, 'gia_ban_ap_dung' => 9800]);

        // Đơn hàng đã tạo vẫn giữ nguyên 9500
        $this->assertEquals(9500, (float) $order->fresh()->gia_ban);
        $this->assertEquals(9500, (float) $order->fresh()->gia_ban_snapshot);
    }
}
