<?php

namespace Tests\Feature\B2B;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiểm chứng Test Vector trong tài liệu tích hợp docs/B2B_API_SPECIFICATION_V1.md.
 *
 * Mục đích: biến các con số trong tài liệu từ "lời tuyên bố" thành "bằng chứng kiểm thử được".
 * Nếu tài liệu sai, test này đỏ. Nếu thuật toán trong tài liệu khác với middleware thật,
 * test `documented_algorithm_authenticates_against_live_endpoint` sẽ đỏ.
 *
 * LƯU Ý VỀ TIMESTAMP: test vector dùng timestamp cố định 1726700000 (19/09/2024) để
 * tái lập được kết quả băm, KHÔNG dùng để gọi API thật (nằm ngoài cửa sổ ±300 giây).
 * Vì vậy phần kiểm chứng vector được thực hiện offline, còn phần kiểm chứng thuật toán
 * dùng timestamp hiện tại.
 */
class B2bDocumentedVectorTest extends TestCase
{
    use DatabaseTransactions;

    /** Các giá trị dưới đây phải khớp CHÍNH XÁC với docs/B2B_API_SPECIFICATION_V1.md */
    private const DOC_SECRET = 'test_secret_key_123';
    private const DOC_POST_BODY = '{"partner_order_id":"TEST_VEC_01","product_code":"TOPUP_VTE_10K","account":"0965657810"}';
    private const DOC_POST_BODY_SHA256 = '26e7ec17bf8a772ca51a378431fc8d7b8ece029e386ff2b7f76d3dbe0dfc4ba4';
    private const DOC_POST_SIGNATURE = 'fc6ca89f925aae3f12300b80916010612a6c85e6dfaa9787782a70bf97dc27a1';
    private const DOC_GET_SIGNATURE = '616125c7cad022b4501c0ca01f8a1f86e0d54af72efd2c1c4d81ae4c7f790055';
    private const DOC_EMPTY_BODY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected SanPham $product;
    protected string $secret = 'vector_partner_secret_abcdef123456';

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
            'ma_dai_ly_api' => 'VECTOR_PARTNER_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý kiểm chứng test vector',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner->dichVu()->attach($dichVu->id);
        $this->partner->loaiSanPham()->attach($loaiSp->id);
        $this->partner->sanPham()->attach($this->product->id);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'vector_client_' . strtolower(Str::random(8)),
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

    /**
     * Thuật toán ký ĐÚNG NHƯ MÔ TẢ TRONG TÀI LIỆU (mục 2.2 và 2.3).
     * Cố ý viết lại từ tài liệu chứ không gọi helper của middleware, để nếu tài liệu
     * và mã nguồn lệch nhau thì test phát hiện được.
     */
    private function kyTheoTaiLieu(
        string $secret,
        string $method,
        string $canonicalUri,
        int|string $timestamp,
        string $nonce,
        string $idempotencyKey,
        string $rawBody
    ): string {
        $bodyHash = hash('sha256', $rawBody);

        $canonical = implode("\n", [
            strtoupper($method),
            $canonicalUri,
            $timestamp,
            $nonce,
            $idempotencyKey,
            $bodyHash,
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    public function test_documented_post_test_vector_body_hash_is_correct(): void
    {
        $this->assertSame(
            self::DOC_POST_BODY_SHA256,
            hash('sha256', self::DOC_POST_BODY),
            'Body SHA-256 trong tài liệu không khớp với raw body đã ghi.'
        );
    }

    public function test_documented_empty_body_hash_is_correct(): void
    {
        $this->assertSame(self::DOC_EMPTY_BODY_SHA256, hash('sha256', ''));
    }

    public function test_documented_post_test_vector_signature_is_correct(): void
    {
        $signature = $this->kyTheoTaiLieu(
            self::DOC_SECRET,
            'POST',
            '/api/b2b/v1/orders',
            1726700000,
            'a1b2c3d4e5f60718',
            '8f4b1d64-9a3d-47df-9db2-1e9c9c991a01',
            self::DOC_POST_BODY
        );

        $this->assertSame(self::DOC_POST_SIGNATURE, $signature, 'Chữ ký POST trong tài liệu không khớp.');
    }

    public function test_documented_get_test_vector_signature_is_correct(): void
    {
        $signature = $this->kyTheoTaiLieu(
            self::DOC_SECRET,
            'GET',
            '/api/b2b/v1/orders?page=1&per_page=20',
            1726700100,
            'b2c3d4e5f60718a1',
            '',
            ''
        );

        $this->assertSame(self::DOC_GET_SIGNATURE, $signature, 'Chữ ký GET trong tài liệu không khớp.');
    }

    /**
     * Bằng chứng mạnh nhất: thuật toán mô tả trong tài liệu phải xác thực được
     * với middleware thật đang chạy. Nếu tài liệu mô tả sai thứ tự dòng, sai cách
     * băm body, hay sai quy tắc query string, request sẽ bị 401.
     */
    public function test_documented_algorithm_authenticates_against_live_endpoint(): void
    {
        $body = json_encode([
            'partner_order_id' => 'VEC_' . strtoupper(Str::random(10)),
            'product_code' => $this->product->ma_san_pham,
            'account' => '0965657810',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->call(
            'POST',
            '/api/b2b/v1/orders',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Client-Id' => $this->config->client_id,
                'X-Timestamp' => (string) ($ts = time()),
                'X-Nonce' => ($nonce = bin2hex(random_bytes(8))),
                'X-Signature' => $this->kyTheoTaiLieu(
                    $this->secret,
                    'POST',
                    '/api/b2b/v1/orders',
                    $ts,
                    $nonce,
                    ($idem = (string) Str::uuid()),
                    $body
                ),
                'Idempotency-Key' => $idem,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]),
            $body
        );

        $response->assertStatus(202)->assertJson([
            'success' => true,
            'status' => 'pending',
        ]);

        // Tài liệu mục 3.1 mô tả các field này ở cấp cao nhất của response
        $response->assertJsonStructure([
            'success',
            'order_id',
            'order_code',
            'partner_order_id',
            'status',
            'account',
            'product_code',
            'amount',
            'price',
            'created_at',
            'completed_at',
        ]);
    }

    /**
     * Tài liệu mục 2.2 nói query string phải được ksort trước khi ký.
     * Gửi request với query string KHÔNG sắp xếp nhưng ký theo dạng đã sắp xếp
     * phải thành công — chứng minh middleware thật sự chuẩn hóa như tài liệu.
     */
    public function test_canonical_query_string_is_sorted_as_documented(): void
    {
        $uri = '/api/b2b/v1/orders?per_page=20&page=1';

        $response = $this->getJson($uri, [
            'X-Client-Id' => $this->config->client_id,
            'X-Timestamp' => (string) ($ts = time()),
            'X-Nonce' => ($nonce = bin2hex(random_bytes(8))),
            // Ký trên dạng ĐÃ sắp xếp alphabet: page=1&per_page=20
            'X-Signature' => $this->kyTheoTaiLieu(
                $this->secret,
                'GET',
                '/api/b2b/v1/orders?page=1&per_page=20',
                $ts,
                $nonce,
                '',
                ''
            ),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    /**
     * Nếu ký trên query string chưa sắp xếp thì phải bị từ chối, xác nhận rằng
     * middleware thật sự dùng dạng đã ksort chứ không dùng nguyên văn URL.
     */
    public function test_unsigned_unsorted_query_string_is_rejected(): void
    {
        $uri = '/api/b2b/v1/orders?per_page=20&page=1';

        $response = $this->getJson($uri, [
            'X-Client-Id' => $this->config->client_id,
            'X-Timestamp' => (string) ($ts = time()),
            'X-Nonce' => ($nonce = bin2hex(random_bytes(8))),
            'X-Signature' => $this->kyTheoTaiLieu(
                $this->secret,
                'GET',
                '/api/b2b/v1/orders?per_page=20&page=1',
                $ts,
                $nonce,
                '',
                ''
            ),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(401)->assertJson(['error_code' => 'INVALID_SIGNATURE']);
    }
}
