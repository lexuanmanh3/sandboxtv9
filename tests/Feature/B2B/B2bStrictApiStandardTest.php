<?php

namespace Tests\Feature\B2B;

use App\Enums\KetQuaNhaCungCap;
use App\Enums\TrangThaiDonHang;
use App\Jobs\ProcessMobileTopupJob;
use App\Models\KhoanGiuHanMuc;
use App\Models\B2bIdempotencyRecord;
use App\Models\B2bOrderOutbox;
use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2bStrictApiStandardTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected string $secret = 'strict_partner_secret_abcdef123456';
    protected SanPham $product;

    protected function setUp(): void
    {
        parent::setUp();

        $dichVu = DichVu::firstOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại',
            'trang_thai' => 'hoat_dong',
        ]);

        $loaiSp = LoaiSanPham::firstOrCreate(['ma_loai_san_pham' => 'VTE_TOPUP'], [
            'ten_loai_san_pham' => 'Viettel Topup',
            'dich_vu_id' => $dichVu->id,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->product = SanPham::firstOrCreate(['ma_san_pham' => 'TOPUP_VTE_10K'], [
            'ten_san_pham' => 'Viettel 10.000đ Chuẩn',
            'dich_vu_id' => $dichVu->id,
            'loai_san_pham_id' => $loaiSp->id,
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'chiet_khau' => 0,
            'gia_nhap' => 9500,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'STRICT_PARTNER_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý B2B Strict Test',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner->dichVu()->attach($dichVu->id);
        $this->partner->loaiSanPham()->attach($loaiSp->id);
        $this->partner->sanPham()->attach($this->product->id);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'strict_client_' . strtolower(Str::random(8)),
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

    protected function signHeaders(
        string $method,
        string $uri,
        string $body = '',
        string $idempotencyKey = '',
        ?int $timestamp = null,
        ?string $nonce = null,
        ?string $overrideSecret = null,
        ?string $overrideClientId = null
    ): array {
        $ts = $timestamp ?? time();
        $nc = $nonce ?? (string) Str::uuid();
        $cid = $overrideClientId ?? $this->config->client_id;
        $sec = $overrideSecret ?? $this->secret;

        $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH), '/');
        $query = parse_url($uri, PHP_URL_QUERY);
        $canonicalUri = $query ? "{$path}?{$query}" : $path;

        $rawBody = in_array(strtoupper($method), ['GET', 'HEAD']) ? '' : $body;
        $bodyHash = hash('sha256', $rawBody);

        // Chuẩn Canonical String duy nhất:
        // METHOD\nCANONICAL_PATH_WITH_QUERY\nTIMESTAMP\nNONCE\nIDEMPOTENCY_KEY\nBODY_SHA256
        $stringToSign = strtoupper($method) . "\n{$canonicalUri}\n{$ts}\n{$nc}\n{$idempotencyKey}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $stringToSign, $sec);

        $headers = [
            'X-Client-Id' => $cid,
            'X-Timestamp' => (string) $ts,
            'X-Nonce' => $nc,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    public function test_missing_authentication_headers_rejected(): void
    {
        $response = $this->getJson('/api/b2b/v1/credit');
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'MISSING_AUTHENTICATION_HEADERS',
            ]);
    }

    public function test_expired_timestamp_rejected(): void
    {
        $uri = '/api/b2b/v1/credit';
        $headers = $this->signHeaders('GET', $uri, '', '', time() - 600);
        $response = $this->getJson($uri, $headers);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'REQUEST_EXPIRED',
            ]);
    }

    public function test_replay_nonce_rejected(): void
    {
        $uri = '/api/b2b/v1/credit';
        $nonce = 'strict_nonce_' . Str::random(10);
        $headers = $this->signHeaders('GET', $uri, '', '', null, $nonce);

        $first = $this->getJson($uri, $headers);
        $first->assertStatus(200);

        $second = $this->getJson($uri, $headers);
        $second->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'REPLAY_DETECTED',
            ]);
    }

    public function test_key_revoked_immediately_blocked(): void
    {
        $this->config->update(['key_status' => 'revoked']);

        $uri = '/api/b2b/v1/credit';
        $headers = $this->signHeaders('GET', $uri);
        $response = $this->getJson($uri, $headers);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'KEY_REVOKED',
            ]);
    }

    public function test_missing_idempotency_key_on_post_orders_rejected(): void
    {
        $uri = '/api/b2b/v1/orders';
        $payload = ['partner_order_id' => 'ORD_1', 'product_code' => $this->product->ma_san_pham, 'account' => '0965657810'];
        $jsonPayload = json_encode($payload);

        // Không truyền Idempotency-Key
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, '');
        unset($headers['Idempotency-Key']);

        $response = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error_code' => 'MISSING_IDEMPOTENCY_KEY',
            ]);
    }

    public function test_tampered_body_fails_hmac(): void
    {
        $uri = '/api/b2b/v1/orders';
        $key = (string) Str::uuid();
        $originalPayload = ['partner_order_id' => 'ORD_TAMPER', 'product_code' => $this->product->ma_san_pham, 'account' => '0965657810'];
        $originalJson = json_encode($originalPayload);

        $headers = $this->signHeaders('POST', $uri, $originalJson, $key);

        // Client cố tình sửa body sau khi ký
        $tamperedPayload = ['partner_order_id' => 'ORD_TAMPER', 'product_code' => $this->product->ma_san_pham, 'account' => '0912345678'];
        $tamperedJson = json_encode($tamperedPayload);

        $response = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $tamperedJson);
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'INVALID_SIGNATURE',
            ]);
    }

    public function test_tampered_idempotency_key_fails_hmac(): void
    {
        $uri = '/api/b2b/v1/orders';
        $key1 = (string) Str::uuid();
        $key2 = (string) Str::uuid();
        $payload = ['partner_order_id' => 'ORD_TAMPER_KEY', 'product_code' => $this->product->ma_san_pham, 'account' => '0965657810'];
        $jsonPayload = json_encode($payload);

        $headers = $this->signHeaders('POST', $uri, $jsonPayload, $key1);
        // Đổi header Idempotency-Key sau khi ký
        $headers['Idempotency-Key'] = $key2;

        $response = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'INVALID_SIGNATURE',
            ]);
    }

    public function test_strict_order_creation_success_returns_pending_and_outbox(): void
    {
        \Illuminate\Support\Facades\Queue::fake([ProcessMobileTopupJob::class]);

        $uri = '/api/b2b/v1/orders';
        $key = (string) Str::uuid();
        $partnerOrderId = 'STRICT_ORD_' . time();
        $payload = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'account' => '0965657810',
        ];
        $jsonPayload = json_encode($payload);
        $headers = $this->signHeaders('POST', $uri, $jsonPayload, $key);

        $response = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $jsonPayload);
        $response->assertStatus(202);

        $data = $response->json();
        $this->assertEquals('pending', $data['status']);
        $this->assertNull($data['completed_at']);
        $this->assertEquals($partnerOrderId, $data['partner_order_id']);

        // Kiểm tra giữ hạn mức
        $hold = KhoanGiuHanMuc::where('dai_ly_api_id', $this->partner->id)
            ->where('don_hang_id', $data['order_id'])
            ->first();
        $this->assertNotNull($hold);
        $this->assertEquals('HOLDING', $hold->trang_thai);

        // Kiểm tra bản ghi Transactional Outbox được tạo cùng transaction
        $outbox = B2bOrderOutbox::where('don_hang_id', $data['order_id'])->first();
        $this->assertNotNull($outbox);
        $this->assertEquals('PENDING', $outbox->status);
        $this->assertEquals($partnerOrderId, $outbox->partner_order_id);
    }

    public function test_idempotent_replay_same_key_same_payload(): void
    {
        $uri = '/api/b2b/v1/orders';
        $key = (string) Str::uuid();
        $partnerOrderId = 'REPLAY_ORD_' . time();
        $payload = [
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'account' => '0965657810',
        ];
        $jsonPayload = json_encode($payload);

        // Lần 1: Tạo đơn
        $headers1 = $this->signHeaders('POST', $uri, $jsonPayload, $key);
        $resp1 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers1), $jsonPayload);
        $resp1->assertStatus(202);
        $orderId1 = $resp1->json('order_id');

        // Lần 2: Gửi lại (Retry) cùng key, cùng payload, chữ ký mới
        $headers2 = $this->signHeaders('POST', $uri, $jsonPayload, $key);
        $resp2 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers2), $jsonPayload);
        $resp2->assertStatus(202);
        $resp2->assertHeader('X-Cache', 'HIT');
        $this->assertEquals($orderId1, $resp2->json('order_id'));

        // Số lượng đơn hàng và số lượng hold chỉ đúng 1
        $this->assertEquals(1, DonHang::where('ma_don_doi_tac', $partnerOrderId)->count());
        $this->assertEquals(1, KhoanGiuHanMuc::where('don_hang_id', $orderId1)->count());
    }

    public function test_idempotency_conflict_same_key_different_payload(): void
    {
        $uri = '/api/b2b/v1/orders';
        $key = (string) Str::uuid();
        $payload1 = ['partner_order_id' => 'ORD_CONFLICT_1', 'product_code' => $this->product->ma_san_pham, 'account' => '0965657810'];
        $json1 = json_encode($payload1);
        $headers1 = $this->signHeaders('POST', $uri, $json1, $key);
        $resp1 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers1), $json1);
        $resp1->assertStatus(202);

        // Gửi cùng Idempotency-Key nhưng đổi số điện thoại nhận
        $payload2 = ['partner_order_id' => 'ORD_CONFLICT_1', 'product_code' => $this->product->ma_san_pham, 'account' => '0988888888'];
        $json2 = json_encode($payload2);
        $headers2 = $this->signHeaders('POST', $uri, $json2, $key);
        $resp2 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers2), $json2);

        $resp2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'IDEMPOTENCY_CONFLICT',
            ]);
    }

    public function test_duplicate_partner_order_id_with_different_key(): void
    {
        $uri = '/api/b2b/v1/orders';
        $partnerOrderId = 'DUP_ORD_' . time();

        $key1 = (string) Str::uuid();
        $payload1 = ['partner_order_id' => $partnerOrderId, 'product_code' => $this->product->ma_san_pham, 'account' => '0965657810'];
        $json1 = json_encode($payload1);
        $headers1 = $this->signHeaders('POST', $uri, $json1, $key1);
        $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers1), $json1)->assertStatus(202);

        // Đổi key mới nhưng giữ nguyên partner_order_id
        $key2 = (string) Str::uuid();
        $payload2 = ['partner_order_id' => $partnerOrderId, 'product_code' => $this->product->ma_san_pham, 'account' => '0965657810'];
        $json2 = json_encode($payload2);
        $headers2 = $this->signHeaders('POST', $uri, $json2, $key2);
        $resp2 = $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers2), $json2);

        $resp2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'DUPLICATE_ORDER',
            ]);
    }

    public function test_multi_tenant_isolation_partner_a_cannot_view_partner_b(): void
    {
        // Tạo đại lý B và đơn hàng của đại lý B
        $partnerB = DaiLyApi::create([
            'ma_dai_ly_api' => 'PARTNER_B_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý B',
            'trang_thai' => 'hoat_dong',
        ]);
        $orderB = DonHang::create([
            'ma_don_hang' => 'B2B_ORDER_B_' . time(),
            'dai_ly_api_id' => $partnerB->id,
            'dich_vu_id' => $this->product->dich_vu_id,
            'ma_don_doi_tac' => 'ORD_B_01',
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0912345678',
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'trang_thai_don_hang' => 'QUEUED',
        ]);

        // Đại lý A cố tình tra cứu đơn của Đại lý B theo ID
        $uri = "/api/b2b/v1/orders/{$orderB->id}";
        $headers = $this->signHeaders('GET', $uri);
        $response = $this->getJson($uri, $headers);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'ORDER_NOT_FOUND',
            ]);
    }

    public function test_outbox_recovery_command_dispatches_stale_orders(): void
    {
        $partnerOrderId = 'STALE_OUTBOX_' . time();
        $donHang = DonHang::create([
            'ma_don_hang' => 'B2B_STALE_' . time(),
            'dai_ly_api_id' => $this->partner->id,
            'dich_vu_id' => $this->product->dich_vu_id,
            'ma_don_doi_tac' => $partnerOrderId,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0965657810',
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'trang_thai_don_hang' => 'QUEUED',
        ]);

        // Giả lập bản ghi Outbox bị treo ở PENDING do server crash 2 phút trước
        $outbox = B2bOrderOutbox::create([
            'don_hang_id' => $donHang->id,
            'dai_ly_api_id' => $this->partner->id,
            'partner_order_id' => $partnerOrderId,
            'product_code' => $this->product->ma_san_pham,
            'account' => '0965657810',
            'payload_json' => json_encode(['account' => '0965657810']),
            'status' => 'PENDING',
        ]);

        \Illuminate\Support\Facades\DB::table('b2b_order_outbox')->where('id', $outbox->id)->update([
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        \Illuminate\Support\Facades\Queue::fake([ProcessMobileTopupJob::class]);

        // Chạy lệnh recover-outbox
        Artisan::call('b2b:recover-outbox');

        \Illuminate\Support\Facades\Queue::assertPushed(ProcessMobileTopupJob::class, function ($job) use ($donHang) {
            return $job->donHangId === $donHang->id;
        });

        $outbox->refresh();
        $this->assertEquals('PROCESSING', $outbox->status);
        $this->assertEquals(1, $outbox->attempts);
        $this->assertNotNull($outbox->locked_until);
    }
}
