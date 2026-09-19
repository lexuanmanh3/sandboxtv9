<?php

namespace Tests\Feature\B2B;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2bHmacAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected string $secret = 'test_partner_secret_1234567890abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'TEST_PARTNER_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đối Tác Kiểm Thử B2B',
            'so_dien_thoai' => '0912345678',
            'email_ky_thuat' => 'tech@b2btest.local',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'client_' . strtolower(Str::random(10)),
            'secret_key_ma_hoa' => $this->secret,
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => 10000000,
            'cong_no_hien_tai' => 0,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
        ]);
    }

    protected function signHeaders(string $method, string $uri, string $body = '', ?int $timestamp = null, ?string $nonce = null, ?string $overrideSecret = null, ?string $overrideClientId = null, string $idempotencyKey = ''): array
    {
        $ts = $timestamp ?? time();
        $nc = $nonce ?? (string) Str::uuid();
        $cid = $overrideClientId ?? $this->config->client_id;
        $sec = $overrideSecret ?? $this->secret;

        $path = '/' . ltrim(parse_url($uri, PHP_URL_PATH), '/');
        $query = parse_url($uri, PHP_URL_QUERY);
        $canonicalUri = $query ? "{$path}?{$query}" : $path;

        $bodyHash = hash('sha256', in_array(strtoupper($method), ['GET', 'HEAD']) ? '' : $body);
        $stringToSign = strtoupper($method) . "\n{$canonicalUri}\n{$ts}\n{$nc}\n{$idempotencyKey}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $stringToSign, $sec);

        $headers = [
            'X-Client-Id' => $cid,
            'X-Timestamp' => (string) $ts,
            'X-Nonce' => $nc,
            'X-Signature' => $signature,
        ];
        if ($idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    public function test_valid_hmac_signature_allows_access(): void
    {
        $uri = '/api/b2b/v1/credit';
        $headers = $this->signHeaders('GET', $uri);

        $response = $this->getJson($uri, $headers);
        if ($response->status() !== 200) {
            dump($response->json());
        }

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'partner_code' => $this->partner->ma_dai_ly_api,
            ]);
    }

    public function test_missing_headers_returns_401(): void
    {
        $response = $this->getJson('/api/b2b/v1/credit');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'MISSING_AUTHENTICATION_HEADERS',
            ]);
    }

    public function test_expired_timestamp_returns_401(): void
    {
        $uri = '/api/b2b/v1/credit';
        // Timestamp lệch 600 giây (> 300s window)
        $headers = $this->signHeaders('GET', $uri, '', time() - 600);

        $response = $this->getJson($uri, $headers);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'REQUEST_EXPIRED',
            ]);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $uri = '/api/b2b/v1/credit';
        $headers = $this->signHeaders('GET', $uri, '', null, null, 'wrong_secret');

        $response = $this->getJson($uri, $headers);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'INVALID_SIGNATURE',
            ]);
    }

    public function test_duplicate_nonce_is_rejected(): void
    {
        $uri = '/api/b2b/v1/credit';
        $nonce = 'duplicate_nonce_' . Str::random(10);
        $headers = $this->signHeaders('GET', $uri, '', null, $nonce);

        // Lần đầu thành công
        $first = $this->getJson($uri, $headers);
        $first->assertStatus(200);

        // Lần hai cùng nonce bị chặn
        $second = $this->getJson($uri, $headers);
        $second->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'REPLAY_DETECTED',
            ]);
    }

    public function test_ip_not_in_allowlist_is_forbidden(): void
    {
        // Cấu hình IP chỉ cho phép 10.0.0.1
        $this->config->update(['danh_sach_ip_ket_noi' => '10.0.0.1']);

        $uri = '/api/b2b/v1/credit';
        $headers = $this->signHeaders('GET', $uri);

        // Request từ 127.0.0.1 sẽ bị từ chối
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson($uri, $headers);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'IP_NOT_ALLOWED',
            ]);
    }

    public function test_inactive_partner_is_blocked(): void
    {
        $this->partner->update(['trang_thai' => 'tam_khoa']);

        $uri = '/api/b2b/v1/credit';
        $headers = $this->signHeaders('GET', $uri);

        $response = $this->getJson($uri, $headers);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'PARTNER_INACTIVE',
            ]);
    }

    public function test_revoked_key_is_blocked(): void
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

    public function test_key_rotation_grace_period_accepts_previous_key(): void
    {
        $oldSecret = $this->secret;
        $newSecret = 'new_rotated_secret_9876543210';

        // Xoay key: key cũ chuyển thành previous_secret
        $this->config->update([
            'secret_key_ma_hoa' => $newSecret,
            'previous_secret_ma_hoa' => $oldSecret,
            'key_status' => 'rotating',
            'key_rotated_at' => now(),
        ]);

        $uri = '/api/b2b/v1/credit';

        // 1. Ký bằng key mới -> Thành công
        $headersNew = $this->signHeaders('GET', $uri, '', null, null, $newSecret);
        $resNew = $this->getJson($uri, $headersNew);
        $resNew->assertStatus(200);

        // 2. Ký bằng key cũ trong thời gian grace period -> Vẫn thành công
        $headersOld = $this->signHeaders('GET', $uri, '', null, null, $oldSecret);
        $resOld = $this->getJson($uri, $headersOld);
        $resOld->assertStatus(200);
    }
}
