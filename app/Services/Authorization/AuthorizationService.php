<?php

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Support\Collection;

/** Cung cấp đầu mối kiểm tra quyền dùng chung cho middleware, giao diện và service. */
class AuthorizationService
{
    public function __construct(private PermissionCacheService $cache)
    {
    }

    public function codes(User $user): Collection
    {
        return $this->cache->codes($user);
    }

    public function allows(User $user, string $permission): bool
    {
        return $this->codes($user)->containsStrict($permission);
    }

    public function allowsAny(User $user, array $permissions): bool
    {
        return $this->codes($user)->intersect($permissions)->isNotEmpty();
    }
}
