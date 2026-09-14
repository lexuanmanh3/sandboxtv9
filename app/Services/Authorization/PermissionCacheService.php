<?php

namespace App\Services\Authorization;

use App\Models\User;
use App\Repositories\PermissionRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

/** Quản lý khóa cache quyền, thời gian tồn tại và quy tắc xóa cache. */
class PermissionCacheService
{
    public const TTL_SECONDS = 900;

    public function __construct(
        private CacheRepository $cache,
        private PermissionRepository $permissions
    ) {
    }

    public function codes(User $user): Collection
    {
        if (! $user->dangHoatDong()) {
            return collect();
        }

        return collect($this->cache->remember(
            $this->userKey($user->getKey()),
            self::TTL_SECONDS,
            fn () => $this->permissions->codesForUser($user)->all()
        ));
    }

    public function forgetUser(int $userId): void
    {
        $this->cache->forget($this->userKey($userId));
    }

    public function forgetRole(int $roleId): void
    {
        $this->permissions->userIdsForRole($roleId)
            ->each(fn ($userId) => $this->forgetUser((int) $userId));
    }

    public function clearAll(): void
    {
        try {
            $userIds = User::pluck('id');
            foreach ($userIds as $userId) {
                $this->forgetUser((int) $userId);
            }
        } catch (\Throwable $e) {
            // ignore if database table doesn't exist yet
        }
    }

    public function userKey(int $userId): string
    {
        return "user_permissions:{$userId}";
    }
}
