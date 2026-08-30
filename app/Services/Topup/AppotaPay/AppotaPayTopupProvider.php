<?php

namespace App\Services\Topup\AppotaPay;

use App\Contracts\NhaCungCapTopupInterface;
use App\DTOs\KetQuaNhaCungCapDTO;
use App\DTOs\KetQuaSoDuDTO;
use App\DTOs\KetQuaThueBao;
use App\DTOs\YeuCauNapTienDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Models\KetNoiNhaCungCap;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AppotaPayTopupProvider implements NhaCungCapTopupInterface
{
    public function __construct(
        private KetNoiNhaCungCap $ketNoi,
        private AppotaPayJwt $jwt,
        private AppotaPaySignature $signature,
        private AppotaPayErrorMapper $errorMapper
    ) {
    }

    public function kiemTraThueBao(string $soDienThoai): KetQuaThueBao
    {
        $data = $this->get('/api/v1/service/topup/'.rawurlencode($soDienThoai).'/info')->json();
        return new KetQuaThueBao($soDienThoai, data_get($data, 'data.telco'), data_get($data, 'data.telcoServiceType'), $data);
    }

    public function layDanhSachSanPham(?string $endpoint = null): Collection
    {
        $path = $endpoint ?: '/api/v2/service/topup/productCodes';
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = $parsedPath ?: '/api/v2/service/topup/productCodes';
        }

        // Tự động chuẩn hóa nếu người dùng dán endpoint thực hiện giao dịch POST (/buy hoặc /charging)
        if (str_contains($path, 'shopcard/buy')) {
            $path = '/api/v1/service/shopcard/products';
        } elseif (str_contains($path, 'topup/charging') || $path === '/' || $path === '') {
            $path = '/api/v2/service/topup/productCodes';
        }

        try {
            $data = $this->get($path)->json();
        } catch (\Throwable $e) {
            // Nếu endpoint phụ bị lỗi (ví dụ shopcard/products 404), tự động fallback sang endpoint productCodes chuẩn
            if ($path !== '/api/v2/service/topup/productCodes') {
                $data = $this->get('/api/v2/service/topup/productCodes')->json();
            } else {
                throw $e;
            }
        }
        $rawList = $data['data'] ?? [];

        // Nếu dữ liệu trả về theo nhóm products (chuẩn topup)
        if (isset($rawList[0]['products']) || (is_array($rawList) && count($rawList) > 0 && isset(array_values($rawList)[0]['products']))) {
            return collect($rawList)->flatMap(function ($group) {
                return collect($group['products'] ?? [])->map(fn ($product) => array_merge($product, ['telco' => $group['telco'] ?? null]));
            })->values();
        }

        // Nếu dữ liệu dạng mảng phẳng (shopcard hoặc các API danh mục khác)
        return collect($rawList)->map(function ($item) {
            if (is_array($item)) {
                return $item;
            }
            return ['productCode' => (string) $item, 'telco' => 'viettel', 'amount' => 0];
        })->values();
    }

    public function layGoiDataTheoThueBao(string $soDienThoai, string $loaiThueBao): Collection
    {
        $data = $this->get('/api/v1/service/topup/'.rawurlencode($soDienThoai).'/products', ['telco_service_type' => strtolower($loaiThueBao)])->json();
        return collect($data['data'] ?? []);
    }

    public function napTien(YeuCauNapTienDTO $dto): KetQuaNhaCungCapDTO
    {
        $payload = [
            'partnerRefId' => $dto->partnerRefId,
            'telco' => strtolower($dto->nhaMang),
            'telcoServiceType' => strtolower($dto->loaiThueBao),
            'productCode' => $dto->maSanPhamNcc,
            'phoneNumber' => $dto->soDienThoai,
        ];
        $payload['signature'] = $this->signature->charging($payload, $this->secret());

        try {
            $response = $this->client()->post('/api/v2/service/topup/charging', $payload);
            return $this->normalize($response, true);
        } catch (ConnectionException $exception) {
            return new KetQuaNhaCungCapDTO(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, null, 'Không xác định do lỗi kết nối');
        } catch (\Throwable $exception) {
            return new KetQuaNhaCungCapDTO(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, null, $exception->getMessage() ?: 'Lỗi ngoại lệ khi gọi AppotaPay');
        }
    }

    public function kiemTraTrangThai(string $partnerRefId): KetQuaNhaCungCapDTO
    {
        try {
            $response = $this->client()->get('/api/v1/service/topup/transaction/'.rawurlencode($partnerRefId));
            return $this->normalize($response, false);
        } catch (ConnectionException $exception) {
            return new KetQuaNhaCungCapDTO(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, null, 'Không xác định do lỗi kết nối');
        } catch (\Throwable $exception) {
            return new KetQuaNhaCungCapDTO(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, null, $exception->getMessage() ?: 'Lỗi ngoại lệ khi kiểm tra trạng thái AppotaPay');
        }
    }

    public function kiemTraSoDu(): KetQuaSoDuDTO
    {
        throw new RuntimeException('Kết nối này chưa cấu hình endpoint kiểm tra số dư.');
    }

    private function normalize(Response $response, bool $charging): KetQuaNhaCungCapDTO
    {
        $data = (array) $response->json();
        $code = isset($data['errorCode']) ? (string) $data['errorCode'] : ($response->status() ? (string) $response->status() : null);

        if ($response->serverError() || (!$response->successful() && $response->status() === 0)) {
            return new KetQuaNhaCungCapDTO(KetQuaNhaCungCap::UNKNOWN_OR_PENDING, $code ?: (string) $response->status(), $data['message'] ?? 'HTTP không xác định', httpStatus: $response->status(), duLieu: $data);
        }

        $code = isset($data['errorCode']) ? (string) $data['errorCode'] : null;
        $result = $code === null ? KetQuaNhaCungCap::UNKNOWN_OR_PENDING : $this->errorMapper->map($code, $this->ketNoi->nha_cung_cap_id);
        $validSignature = null;
        if (isset($data['transaction'])) {
            $validSignature = $charging
                ? $this->signature->verifyChargingResponse($data, $this->secret())
                : $this->signature->verifyStatusResponse($data, $this->secret());
            if (!$validSignature) {
                $result = KetQuaNhaCungCap::UNKNOWN_OR_PENDING;
            }
        }

        return new KetQuaNhaCungCapDTO(
            $result,
            $code,
            $data['message'] ?? null,
            data_get($data, 'transaction.appotapayTransId'),
            $validSignature,
            $data['signature'] ?? null,
            isset($data['account']['balance']) ? (string) $data['account']['balance'] : null,
            data_get($data, 'transaction.time'),
            $data,
            $response->status()
        );
    }

    private function get(string $path, array $query = []): Response
    {
        $response = $this->client()->get($path, $query);
        $data = (array) $response->json();
        $errorCode = $data['errorCode'] ?? null;

        if (!$response->successful() || ($errorCode !== null && (int) $errorCode !== 0)) {
            $msg = $data['message'] ?? ('Lỗi từ AppotaPay (Mã lỗi: ' . ($errorCode ?? $response->status()) . ')');
            throw new RuntimeException($msg);
        }

        return $response;
    }

    private function client()
    {
        $partnerCode = (string) ($this->ketNoi->partner_code ?: $this->ketNoi->api_user ?: $this->ketNoi->username);
        $apiKey = (string) ($this->ketNoi->api_key_ma_hoa ?: $this->ketNoi->api_user ?: $this->ketNoi->username);
        $token = $this->jwt->tao($partnerCode, $apiKey, $this->secret());

        $rawUrl = (string) ($this->ketNoi->base_url ?: $this->ketNoi->api_url);
        $scheme = parse_url($rawUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($rawUrl, PHP_URL_HOST);
        $baseUrl = $host ? ($scheme . '://' . $host) : rtrim($rawUrl, '/');

        return Http::baseUrl($baseUrl)
            ->connectTimeout((int) ($this->ketNoi->connect_timeout_seconds ?: $this->ketNoi->timeout_he_thong ?: 10))
            ->timeout((int) ($this->ketNoi->request_timeout_seconds ?: $this->ketNoi->timeout_ncc ?: 25))
            ->acceptJson()
            ->withHeaders(['X-APPOTAPAY-AUTH' => $token, 'Language' => 'vi']);
    }

    private function secret(): string
    {
        return (string) ($this->ketNoi->secret_key_ma_hoa ?: $this->ketNoi->api_password_ma_hoa ?: $this->ketNoi->password_ma_hoa);
    }
}
