<?php

namespace App\Services\Topup;

use App\Contracts\NhaCungCapTopupInterface;
use App\DTOs\KetQuaSoDuDTO;
use App\Models\KetNoiNhaCungCap;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractTopupProvider implements NhaCungCapTopupInterface
{
    public function __construct(
        protected KetNoiNhaCungCap $ketNoi
    ) {
    }

    /**
     * Lấy Timeout kết nối (giây).
     */
    protected function getConnectTimeout(): int
    {
        return (int) ($this->ketNoi->connect_timeout_seconds 
            ?: $this->ketNoi->timeout_he_thong 
            ?: config('topup.defaults.connect_timeout', 10));
    }

    /**
     * Lấy Timeout phản hồi (giây).
     */
    protected function getRequestTimeout(): int
    {
        return (int) ($this->ketNoi->request_timeout_seconds 
            ?: $this->ketNoi->timeout_ncc 
            ?: config('topup.defaults.request_timeout', 25));
    }

    /**
     * Lấy Base URL đã chuẩn hóa.
     */
    protected function getBaseUrl(): string
    {
        $rawUrl = (string) ($this->ketNoi->base_url ?: $this->ketNoi->api_url);
        $scheme = parse_url($rawUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($rawUrl, PHP_URL_HOST);

        return $host ? ($scheme . '://' . $host) : rtrim($rawUrl, '/');
    }

    /**
     * Mặc định kiểm tra số dư (nếu NCC không hỗ trợ API số dư thì ném ngoại lệ rõ ràng).
     */
    public function kiemTraSoDu(): KetQuaSoDuDTO
    {
        $tenNcc = $this->ketNoi->nhaCungCap?->ten_ncc ?: $this->ketNoi->ten_ket_noi;
        throw new RuntimeException("Nhà cung cấp '{$tenNcc}' chưa hỗ trợ endpoint kiểm tra số dư.");
    }

    /**
     * Log thông tin tích hợp an toàn không lộ secret.
     */
    protected function logIntegration(string $level, string $message, array $context = []): void
    {
        $context['ket_noi_id'] = $this->ketNoi->id;
        $context['nha_cung_cap'] = $this->ketNoi->nhaCungCap?->ma_ncc;
        Log::log($level, "TopupProvider: {$message}", DuLieuNhayCam::che($context));
    }
}
