<?php

namespace App\Services\Topup;

use App\Contracts\NhaCungCapTopupInterface;
use App\Models\KetNoiNhaCungCap;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NhaCungCapResolver
{
    /**
     * Mảng lưu trữ các Custom Driver Creator do developer đăng ký động.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    public function __construct(
        protected ?Application $app = null
    ) {
        $this->app = $app ?: app();
    }

    /**
     * Đăng ký thêm một Driver NCC mới (Runtime Extension).
     * Thuận tiện để mở rộng hoặc test mà không cần sửa core resolver.
     *
     * Ví dụ:
     * $resolver->extend('vnpay', function ($ketNoi, $app) {
     *     return new VNPayTopupProvider($ketNoi, ...);
     * });
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[strtolower(trim($driver))] = $callback;
        return $this;
    }

    /**
     * Khởi tạo Provider phù hợp cho kết nối nhà cung cấp.
     */
    public function resolve(KetNoiNhaCungCap $ketNoi): NhaCungCapTopupInterface
    {
        // 1. Xác định driver key từ trường 'driver' hoặc mã 'ma_ncc'
        $driverKey = strtolower(trim((string) ($ketNoi->driver ?: $ketNoi->nhaCungCap?->ma_ncc)));

        if (empty($driverKey)) {
            throw new InvalidArgumentException("Kết nối NCC ID #{$ketNoi->id} chưa cấu hình trường 'driver' hoặc 'ma_ncc'.");
        }

        // 2. Ưu tiên custom creator đăng ký động qua extend()
        if (isset($this->customCreators[$driverKey])) {
            return $this->customCreators[$driverKey]($ketNoi, $this->app);
        }

        // 3. Tra cứu trong file cấu hình config/topup.php
        $drivers = config('topup.drivers', []);
        if (isset($drivers[$driverKey])) {
            $providerClass = $drivers[$driverKey];
            if (class_exists($providerClass)) {
                return $this->app->make($providerClass, ['ketNoi' => $ketNoi]);
            }
        }

        // 4. Tra cứu theo phương thức custom nội bộ create{Driver}Driver()
        $method = 'create' . Str::studly($driverKey) . 'Driver';
        if (method_exists($this, $method)) {
            return $this->{$method}($ketNoi);
        }

        // 5. Tra cứu theo quy ước chuẩn: App\Services\Topup\{Studly}\{Studly}TopupProvider
        $studlyName = Str::studly($driverKey);
        $conventionalClass = "App\\Services\\Topup\\{$studlyName}\\{$studlyName}TopupProvider";
        if (class_exists($conventionalClass)) {
            return $this->app->make($conventionalClass, ['ketNoi' => $ketNoi]);
        }

        throw new InvalidArgumentException(
            "Chưa có adapter cho nhà cung cấp/driver '{$driverKey}'. " .
            "Vui lòng khai báo class provider trong config/topup.php hoặc dùng NhaCungCapResolver::extend('{$driverKey}', ...)."
        );
    }
}
