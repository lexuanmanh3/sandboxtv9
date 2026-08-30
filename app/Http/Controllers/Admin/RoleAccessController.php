<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quyen;
use App\Models\VaiTro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\Authorization\PermissionCacheService;
use App\Services\Authorization\PermissionTreeService;
use App\Services\Audit\AuditService;

class RoleAccessController extends Controller
{
    /**
     * Màn danh sách vai trò và cây quyền truy cập.
     * Quyền ở màn này là quyền vào module/trang, không phải quyền thao tác từng nút.
     */
    public function index(): View
    {
        $permissions = $this->managedAccessPermissions();

        return view('admin.roles', [
            'roles' => VaiTro::with('quyen')->orderBy('ten_vai_tro')->paginate(15),
            'permissionTreeRows' => $this->buildPermissionTreeRows($permissions),
            'managedPermissionIds' => $permissions->pluck('id')->values(),
        ]);
    }

    /**
     * Cập nhật quyền truy cập trang/module và cờ "vai trò mặc định" cho một vai trò.
     * Các quyền thao tác cũ không hiển thị ở cây này sẽ được giữ nguyên khi lưu.
     */
    public function updatePermissions(
        Request $request,
        VaiTro $role,
        PermissionTreeService $tree,
        PermissionCacheService $cache,
        AuditService $audit
    ): RedirectResponse
    {
        $validated = $request->validate([
            'quyen' => ['nullable', 'array'],
            'quyen.*' => ['integer', 'exists:quyen,id'],
            'mac_dinh' => ['nullable', 'boolean'],
        ]);

        $managedPermissionIds = $this->managedAccessPermissions()->pluck('id');
        $selectedIds = $tree->normalize($validated['quyen'] ?? []);

        $keptPermissionIds = $role->quyen()
            ->whereNotIn('quyen.id', $managedPermissionIds)
            ->pluck('quyen.id');

        $before = ['permission_ids' => $role->quyen()->pluck('quyen.id')->all(), 'mac_dinh' => $role->mac_dinh];

        DB::transaction(function () use ($role, $validated, $keptPermissionIds, $selectedIds) {
            $role->quyen()->sync(
                $keptPermissionIds
                    ->merge($selectedIds)
                    ->unique()
                    ->values()
                    ->all()
            );

            $macDinh = (bool) ($validated['mac_dinh'] ?? false);

            if ($macDinh) {
                // NOTE: Chỉ được đúng 1 vai trò mặc định tại một thời điểm — bỏ tick
                // vai trò mặc định cũ trước khi gán cho vai trò đang sửa.
                VaiTro::where('id', '!=', $role->id)
                    ->where('mac_dinh', true)
                    ->update(['mac_dinh' => false]);
            }

            $role->update(['mac_dinh' => $macDinh]);
        });

        $cache->forgetRole((int) $role->getKey());
        $role->refresh();
        $audit->record('role.permissions.updated', $role, $before, [
            'permission_ids' => $role->quyen()->pluck('quyen.id')->all(),
            'mac_dinh' => $role->mac_dinh,
        ]);

        return back()->with('success', 'Cập nhật quyền truy cập cho vai trò thành công.');
    }

    /**
     * Chỉ quản lý quyền truy cập trang/module trong màn phân quyền.
     * Các mã page.*, backend.*, frontend.* là node thư mục; *.access là quyền vào trang thật.
     */
    private function managedAccessPermissions(): Collection
    {
        return Quyen::where('trang_thai', 'hoat_dong')
            ->where('nhom_quyen', '!=', 'Tương thích cũ')
            ->orderBy('thu_tu')
            ->orderBy('ten_quyen')
            ->get();
    }

    /**
     * Dựng cây quyền dạng hàng phẳng để Blade render được nhiều tầng ổn định.
     * Muốn thêm page mới: thêm quyền vào bảng quyen với quyen_cha_id trỏ đúng node cha.
     */
    private function buildPermissionTreeRows(Collection $permissions): array
    {
        $childrenByParent = $permissions
            ->groupBy(fn (Quyen $permission) => $permission->quyen_cha_id ?: 0)
            ->map(fn (Collection $children) => $children->sortBy([
                ['thu_tu', 'asc'],
                ['ten_quyen', 'asc'],
            ])->values());

        $rows = [];

        $walk = function ($parentId, int $depth) use (&$walk, &$rows, $childrenByParent): void {
            foreach ($childrenByParent->get($parentId, collect()) as $permission) {
                $hasChildren = $childrenByParent->has($permission->id);

                $rows[] = [
                    'permission' => $permission,
                    'depth' => $depth,
                    'has_children' => $hasChildren,
                    'parent_id' => $permission->quyen_cha_id,
                ];

                if ($hasChildren) {
                    $walk($permission->id, $depth + 1);
                }
            }
        };

        $walk(0, 0);

        return $rows;
    }
}
