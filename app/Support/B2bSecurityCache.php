<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Truy cập cache store dùng cho trạng thái bảo mật B2B (nonce replay, rate limit).
 *
 * Toàn bộ trạng thái bảo mật B2B phải nằm trên MỘT store dùng chung giữa các app server.
 * Xem config/b2b.php và lệnh `php artisan b2b:health-check`.
 */
class B2bSecurityCache
{
    /** Các driver chỉ tồn tại trong phạm vi một tiến trình hoặc một máy. */
    public const NON_SHARED_DRIVERS = ['array', 'file', 'null'];

    public static function storeName(): ?string
    {
        $name = config('b2b.security_cache_store');

        return ($name === null || $name === '') ? null : (string) $name;
    }

    public static function store(): Repository
    {
        return Cache::store(self::storeName());
    }

    /**
     * Tên driver thực tế đang được dùng cho trạng thái bảo mật B2B.
     */
    public static function driver(): string
    {
        $name = self::storeName() ?: config('cache.default');

        return (string) config("cache.stores.{$name}.driver", $name);
    }

    /**
     * Trạng thái bảo mật B2B có dùng chung giữa nhiều app server hay không.
     */
    public static function isShared(): bool
    {
        return !in_array(self::driver(), self::NON_SHARED_DRIVERS, true);
    }
}
