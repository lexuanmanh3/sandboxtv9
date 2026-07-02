<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quyen;
use App\Models\VaiTro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

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
     * Cập nhật quyền truy cập trang/module cho một vai trò.
     * Các quyền thao tác cũ không hiển thị ở cây này sẽ được giữ nguyên khi lưu.
     */
    public function updatePermissions(Request $request, VaiTro $role): RedirectResponse
    {
        $validated = $request->validate([
            'quyen' => ['nullable', 'array'],
            'quyen.*' => ['integer', 'exists:quyen,id'],
        ]);

        $managedPermissionIds = $this->managedAccessPermissions()->pluck('id');

        $keptPermissionIds = $role->quyen()
            ->whereNotIn('quyen.id', $managedPermissionIds)
            ->pluck('quyen.id');

        $role->quyen()->sync(
            $keptPermissionIds
                ->merge($validated['quyen'] ?? [])
                ->unique()
                ->values()
                ->all()
        );

        return back()->with('success', 'Cập nhật quyền truy cập cho vai trò thành công.');
    }

    /**
     * Chỉ quản lý quyền truy cập trang/module trong màn phân quyền.
     * Các mã page.*, backend.*, frontend.* là node thư mục; *.access là quyền vào trang thật.
     */
    private function managedAccessPermissions(): Collection
    {
        return Quyen::where('trang_thai', 'hoat_dong')
            ->where(function ($query) {
                $query
                    ->where('ma_quyen', 'like', '%.access')
                    ->orWhere('ma_quyen', 'like', 'page.%')
                    ->orWhere('ma_quyen', 'like', 'backend.%')
                    ->orWhere('ma_quyen', 'like', 'frontend.%');
            })
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
