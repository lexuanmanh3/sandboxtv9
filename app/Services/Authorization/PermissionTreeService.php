<?php

namespace App\Services\Authorization;

use App\Models\Quyen;
use Illuminate\Validation\ValidationException;

/** Kiểm tra quyền đã chọn và tự bổ sung tất cả quyền cha đang hoạt động bắt buộc. */
class PermissionTreeService
{
    public function normalize(array $permissionIds): array
    {
        $ids = collect($permissionIds)->map(fn ($id) => (int) $id)->unique()->values();
        $permissions = Quyen::whereIn('id', $ids)->where('trang_thai', 'hoat_dong')->get()->keyBy('id');

        if ($permissions->count() !== $ids->count()) {
            throw ValidationException::withMessages(['quyen' => 'Danh sách quyền chứa quyền không tồn tại hoặc đã bị tắt.']);
        }

        $result = $ids->flip();
        foreach ($permissions as $permission) {
            $parentId = $permission->quyen_cha_id;
            $visited = [];
            while ($parentId !== null) {
                if (isset($visited[$parentId])) {
                    throw ValidationException::withMessages(['quyen' => 'Cây quyền chứa quan hệ cha-con vòng lặp.']);
                }
                $visited[$parentId] = true;
                $parent = Quyen::whereKey($parentId)->where('trang_thai', 'hoat_dong')->first();
                if (! $parent) {
                    throw ValidationException::withMessages(['quyen' => 'Quyền cha bắt buộc không tồn tại hoặc đã bị tắt.']);
                }
                $result->put($parent->id, true);
                $parentId = $parent->quyen_cha_id;
            }
        }

        return $result->keys()->map(fn ($id) => (int) $id)->values()->all();
    }
}
