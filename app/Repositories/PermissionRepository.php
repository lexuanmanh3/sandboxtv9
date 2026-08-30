<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Collection;

/** Truy vấn hợp quyền đang hoạt động từ tất cả vai trò đang hoạt động của người dùng. */
class PermissionRepository
{
    public function codesForUser(User $user): Collection
    {
        return $user->vaiTro()
            ->where('vai_tro.trang_thai', 'hoat_dong')
            ->join('vai_tro_quyen', 'vai_tro.id', '=', 'vai_tro_quyen.vai_tro_id')
            ->join('quyen', 'vai_tro_quyen.quyen_id', '=', 'quyen.id')
            ->where('quyen.trang_thai', 'hoat_dong')
            ->distinct()
            ->orderBy('quyen.ma_quyen')
            ->pluck('quyen.ma_quyen');
    }

    public function userIdsForRole(int $roleId): Collection
    {
        return User::whereHas('vaiTro', fn ($query) => $query->where('vai_tro.id', $roleId))
            ->pluck('users.id');
    }
}
