<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuyenSeeder extends Seeder
{
    public function run(): void
    {
        // Cây quyền bên dưới chỉ phục vụ quyền truy cập trang/module.
        // Node dạng page.*, backend.*, frontend.* là thư mục để phân tầng giao diện.
        // Node dạng *.access là quyền thật được middleware dùng để cho phép vào trang.
        $accessTree = [
            [
                'ma_quyen' => 'page.root',
                'ten_quyen' => 'Trang',
                'nhom_quyen' => 'Trang',
                'thu_tu' => 1,
                'children' => [
                    [
                        'ma_quyen' => 'backend.root',
                        'ten_quyen' => '[Back end]',
                        'nhom_quyen' => 'Trang',
                        'thu_tu' => 10,
                        'children' => [
                            [
                                'ma_quyen' => 'backend.inventory',
                                'ten_quyen' => 'Quản lý kho',
                                'nhom_quyen' => 'Quản lý kho',
                                'thu_tu' => 10,
                                'children' => [
                                    [
                                        'ma_quyen' => 'dashboard.access',
                                        'ten_quyen' => 'Quản lý lô hàng',
                                        'nhom_quyen' => 'Quản lý kho',
                                        'thu_tu' => 10,
                                    ],
                                    [
                                        'ma_quyen' => 'inventory.access',
                                        'ten_quyen' => 'Kho mã thẻ',
                                        'nhom_quyen' => 'Quản lý kho',
                                        'thu_tu' => 20,
                                    ],
                                ],
                            ],
                            [
                                'ma_quyen' => 'backend.catalog',
                                'ten_quyen' => 'Quản lý danh mục',
                                'nhom_quyen' => 'Quản lý danh mục',
                                'thu_tu' => 15,
                                'children' => [
                                    [
                                        'ma_quyen' => 'dich_vu.access',
                                        'ten_quyen' => 'Dịch vụ',
                                        'nhom_quyen' => 'Quản lý danh mục',
                                        'thu_tu' => 10,
                                    ],
                                ],
                            ],
                            [
                                'ma_quyen' => 'policy.access',
                                'ten_quyen' => 'Quản lý chính sách',
                                'nhom_quyen' => 'Quản lý chính sách',
                                'thu_tu' => 20,
                            ],
                            [
                                'ma_quyen' => 'report.access',
                                'ten_quyen' => 'Báo cáo',
                                'nhom_quyen' => 'Báo cáo',
                                'thu_tu' => 30,
                            ],
                            [
                                'ma_quyen' => 'backend.admin',
                                'ten_quyen' => 'Quản trị',
                                'nhom_quyen' => 'Quản trị',
                                'thu_tu' => 40,
                                'children' => [
                                    [
                                        'ma_quyen' => 'account.access',
                                        'ten_quyen' => 'Quản lý tài khoản hệ thống',
                                        'nhom_quyen' => 'Quản trị',
                                        'thu_tu' => 10,
                                    ],
                                    [
                                        'ma_quyen' => 'role.access',
                                        'ten_quyen' => 'Vai trò',
                                        'nhom_quyen' => 'Quản trị',
                                        'thu_tu' => 20,
                                    ],
                                    [
                                        'ma_quyen' => 'service_config.access',
                                        'ten_quyen' => 'Cấu hình dịch vụ',
                                        'nhom_quyen' => 'Quản trị',
                                        'thu_tu' => 30,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'ma_quyen' => 'frontend.root',
                        'ten_quyen' => '[Front end]',
                        'nhom_quyen' => 'Trang',
                        'thu_tu' => 20,
                        'children' => [
                            [
                                'ma_quyen' => 'frontend.home.access',
                                'ten_quyen' => 'Trang chủ người dùng',
                                'nhom_quyen' => 'Front end',
                                'thu_tu' => 10,
                            ],
                            [
                                'ma_quyen' => 'frontend.topup.access',
                                'ten_quyen' => 'Nạp tiền điện thoại',
                                'nhom_quyen' => 'Front end',
                                'thu_tu' => 20,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->upsertTree($accessTree);

        // Các quyền cũ được giữ lại để không phá những nơi đã tham chiếu trước đó.
        // Chúng không hiện trong cây phân quyền truy cập mới.
        $legacyPermissions = [
            ['ma_quyen' => 'dashboard.view', 'ten_quyen' => 'Xem dashboard', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 100],
            ['ma_quyen' => 'user.view', 'ten_quyen' => 'Xem người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 110],
            ['ma_quyen' => 'user.create', 'ten_quyen' => 'Thêm người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 111],
            ['ma_quyen' => 'user.update', 'ten_quyen' => 'Sửa người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 112],
            ['ma_quyen' => 'user.delete', 'ten_quyen' => 'Xóa người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 113],
            ['ma_quyen' => 'role.view', 'ten_quyen' => 'Xem vai trò', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 120],
            ['ma_quyen' => 'role.create', 'ten_quyen' => 'Thêm vai trò', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 121],
            ['ma_quyen' => 'role.update', 'ten_quyen' => 'Sửa vai trò', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 122],
            ['ma_quyen' => 'role.delete', 'ten_quyen' => 'Xóa vai trò', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 123],
            ['ma_quyen' => 'permission.view', 'ten_quyen' => 'Xem quyền', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 130],
            ['ma_quyen' => 'permission.assign', 'ten_quyen' => 'Gán quyền', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 131],
        ];

        foreach ($legacyPermissions as $permission) {
            $this->upsertPermission($permission, null);
        }

        $this->grantDefaultPermissionsToAdmin();
        $this->grantDefaultPermissionsToUser();
    }

    private function upsertTree(array $nodes, ?int $parentId = null): void
    {
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);

            $permissionId = $this->upsertPermission($node, $parentId);

            if ($children) {
                $this->upsertTree($children, $permissionId);
            }
        }
    }

    private function upsertPermission(array $permission, ?int $parentId): int
    {
        DB::table('quyen')->updateOrInsert(
            ['ma_quyen' => $permission['ma_quyen']],
            array_merge($permission, [
                'quyen_cha_id' => $parentId,
                'trang_thai' => 'hoat_dong',
                'created_at' => now(),
                'updated_at' => now(),
            ])
        );

        return (int) DB::table('quyen')
            ->where('ma_quyen', $permission['ma_quyen'])
            ->value('id');
    }

    private function grantDefaultPermissionsToAdmin(): void
    {
        $adminRole = DB::table('vai_tro')
            ->where('ma_vai_tro', 'admin')
            ->first();

        if (! $adminRole) {
            return;
        }

        $defaultCodes = [
            'page.root',
            'backend.root',
            'backend.inventory',
            'dashboard.access',
            'inventory.access',
            'backend.catalog',
            'dich_vu.access',
            'backend.admin',
            'account.access',
            'role.access',
            'service_config.access',
        ];

        $this->removeManagedAccessPermissions((int) $adminRole->id);

        foreach (DB::table('quyen')->whereIn('ma_quyen', $defaultCodes)->get() as $permission) {
            DB::table('vai_tro_quyen')->updateOrInsert(
                [
                    'vai_tro_id' => $adminRole->id,
                    'quyen_id' => $permission->id,
                ],
                ['tao_luc' => now()]
            );
        }
    }

    private function grantDefaultPermissionsToUser(): void
    {
        $userRole = DB::table('vai_tro')
            ->where('ma_vai_tro', 'user')
            ->first();

        if (! $userRole) {
            return;
        }

        $defaultCodes = [
            'page.root',
            'frontend.root',
            'frontend.home.access',
            'frontend.topup.access',
        ];

        $this->removeManagedAccessPermissions((int) $userRole->id);

        foreach (DB::table('quyen')->whereIn('ma_quyen', $defaultCodes)->get() as $permission) {
            DB::table('vai_tro_quyen')->updateOrInsert(
                [
                    'vai_tro_id' => $userRole->id,
                    'quyen_id' => $permission->id,
                ],
                ['tao_luc' => now()]
            );
        }
    }

    private function removeManagedAccessPermissions(int $roleId): void
    {
        $managedPermissionIds = DB::table('quyen')
            ->where(function ($query) {
                $query
                    ->where('ma_quyen', 'like', '%.access')
                    ->orWhere('ma_quyen', 'like', 'page.%')
                    ->orWhere('ma_quyen', 'like', 'backend.%')
                    ->orWhere('ma_quyen', 'like', 'frontend.%');
            })
            ->pluck('id');

        DB::table('vai_tro_quyen')
            ->where('vai_tro_id', $roleId)
            ->whereIn('quyen_id', $managedPermissionIds)
            ->delete();
    }
}
