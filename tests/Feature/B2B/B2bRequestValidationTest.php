<?php

namespace Tests\Feature\B2B;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiểm thử ràng buộc hình dạng request và tham số tra cứu của B2B API v1.
 *
 * Các ràng buộc này bảo vệ hai thứ:
 *  - Hợp đồng API tường minh: field lạ, Content-Type sai, body quá lớn bị từ chối
 *    thay vì được xử lý ngầm theo cách không có trong tài liệu.
 *  - Tài nguyên hệ thống: truy vấn tra cứu bị chặn khoảng thời gian quá rộng.
 */
class B2bRequestValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected string $secret = 'shape_partner_secret_abcdef123456';
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
            'ma_dai_ly_api' => 'SHAPE_PARTNER_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý kiểm thử ràng buộc request',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner->dichVu()->attach($dichVu->id);
        $this->partner->loaiSanPham()->attach($loaiSp->id);
        $this->partner->sanPham()->attach($this->product->id);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'shape_client_' . strtolower(Str::random(8)),
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

    protected function signHeaders(string $method, string $uri, string $body = '', string $idempotencyKey = '', ?string $contentType = 'application/json'): array
    {
        $ts = time();
        $nc = (string) Str::uuid();

        $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH), '/');
        $query = parse_url($uri, PHP_URL_QUERY);
        $canonicalUri = $query ? "{$path}?{$query}" : $path;

        $rawBody = in_array(strtoupper($method), ['GET', 'HEAD']) ? '' : $body;
        $stringToSign = strtoupper($method) . "\n{$canonicalUri}\n{$ts}\n{$nc}\n{$idempotencyKey}\n" . hash('sha256', $rawBody);

        $headers = [
            'X-Client-Id' => $this->config->client_id,
            'X-Timestamp' => (string) $ts,
            'X-Nonce' => $nc,
            'X-Signature' => hash_hmac('sha256', $stringToSign, $this->secret),
            'Accept' => 'application/json',
        ];

        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        if ($idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /** Gửi POST /orders với body thô tùy ý, chữ ký hợp lệ trên chính body đó. */
    private function postOrders(string $rawBody, ?string $contentType = 'application/json')
    {
        return $this->call(
            'POST',
            '/api/b2b/v1/orders',
            [],
            [],
            [],
            $this->transformHeadersToServerVars(
                $this->signHeaders('POST', '/api/b2b/v1/orders', $rawBody, (string) Str::uuid(), $contentType)
            ),
            $rawBody
        );
    }

    private function bodyDonHang(array $them = []): string
    {
        return json_encode(array_merge([
            'partner_order_id' => 'SHAPE_' . strtoupper(Str::random(10)),
            'product_code' => $this->product->ma_san_pham,
            'account' => '0965657810',
        ], $them), JSON_UNESCAPED_UNICODE);
    }

    public function test_content_type_not_json_is_rejected(): void
    {
        $response = $this->postOrders($this->bodyDonHang(), 'text/plain');

        $response->assertStatus(415)->assertJson([
            'success' => false,
            'error_code' => 'UNSUPPORTED_MEDIA_TYPE',
        ]);
    }

    public function test_missing_content_type_is_rejected(): void
    {
        $response = $this->postOrders($this->bodyDonHang(), null);

        $response->assertStatus(415)->assertJson(['error_code' => 'UNSUPPORTED_MEDIA_TYPE']);
    }

    public function test_unknown_field_is_rejected(): void
    {
        $response = $this->postOrders($this->bodyDonHang(['amount' => 999999]));

        $response->assertStatus(400)->assertJson(['error_code' => 'UNKNOWN_FIELD']);
    }

    public function test_account_and_phone_number_with_conflicting_values_is_rejected(): void
    {
        $response = $this->postOrders($this->bodyDonHang(['phone_number' => '0900000000']));

        $response->assertStatus(400)->assertJson(['error_code' => 'AMBIGUOUS_ACCOUNT_FIELD']);
    }

    public function test_account_and_phone_number_with_same_value_is_accepted(): void
    {
        // Alias legacy với giá trị khớp nhau là hợp lệ (không mơ hồ)
        $response = $this->postOrders($this->bodyDonHang(['phone_number' => '0965657810']));

        $response->assertStatus(202)->assertJson(['success' => true]);
    }

    public function test_malformed_json_body_is_rejected(): void
    {
        $response = $this->postOrders('{"partner_order_id": ');

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_JSON_BODY']);
    }

    public function test_json_array_body_is_rejected(): void
    {
        $response = $this->postOrders('[{"partner_order_id":"X"}]');

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_JSON_BODY']);
    }

    public function test_oversized_body_is_rejected(): void
    {
        $response = $this->postOrders($this->bodyDonHang(['account' => str_repeat('0', 70000)]));

        $response->assertStatus(413)->assertJson(['error_code' => 'PAYLOAD_TOO_LARGE']);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $uri = '/api/b2b/v1/orders?from_date=hom-qua';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_DATE_FORMAT']);
    }

    /**
     * Carbon::parse() chấp nhận 'now', 'tomorrow', '+1 week'. Nếu dùng trực tiếp,
     * hợp đồng API trở nên vô định và kết quả tra cứu phụ thuộc thời điểm gọi.
     */
    public function test_relative_date_expressions_are_rejected(): void
    {
        $uri = '/api/b2b/v1/orders?from_date=now';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_DATE_FORMAT']);
    }

    public function test_rolled_over_calendar_date_is_rejected(): void
    {
        // 2026-02-31 không tồn tại; PHP sẽ tự cuộn thành 2026-03-03 nếu không kiểm tra
        $uri = '/api/b2b/v1/orders?from_date=2026-02-31';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_DATE_FORMAT']);
    }

    public function test_from_date_after_to_date_is_rejected(): void
    {
        $uri = '/api/b2b/v1/orders?from_date=2026-09-30&to_date=2026-09-01';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_DATE_RANGE']);
    }

    public function test_too_wide_date_range_is_rejected(): void
    {
        $uri = '/api/b2b/v1/orders?from_date=1970-01-01&to_date=2026-09-30';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(400)->assertJson(['error_code' => 'DATE_RANGE_TOO_WIDE']);
    }

    /**
     * to_date phải bao trọn ngày kết thúc. Trước đây so sánh với chuỗi ngày trần
     * (00:00:00) nên đơn tạo lúc 14:00 trong ngày to_date bị loại khỏi kết quả.
     */
    public function test_to_date_includes_orders_created_later_in_that_day(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'SHAPE_DATE_' . strtoupper(Str::random(8)),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'SHAPE_DATE_' . strtoupper(Str::random(8)),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0965657810',
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'trang_thai_don_hang' => 'SUCCESS',
        ]);

        // created_at không nằm trong $fillable nên phải gán tường minh, nếu không
        // Eloquent sẽ ghi đè bằng thời điểm chạy test.
        $donHang->forceFill(['created_at' => '2026-09-30 14:30:00'])->save();

        $uri = '/api/b2b/v1/orders?from_date=2026-09-30&to_date=2026-09-30';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('order_id')->all();
        $this->assertContains($donHang->id, $ids, 'Đơn tạo lúc 14:30 ngày to_date phải nằm trong kết quả.');
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $uri = '/api/b2b/v1/orders?status=khong_ton_tai';

        $response = $this->getJson($uri, $this->signHeaders('GET', $uri));

        $response->assertStatus(400)->assertJson(['error_code' => 'INVALID_STATUS_FILTER']);
    }
}
