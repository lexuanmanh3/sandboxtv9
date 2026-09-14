<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Services\Authorization\PermissionCacheService;

class QuyenSeeder extends Seeder
{
    public function run(): void
    {
        // Cây quyền bên dưới phục vụ quyền truy cập trang/module và các action chi tiết.
        // Node dạng page.*, backend.*, frontend.* là thư mục để phân tầng giao diện.
        // Node dạng *.access là quyền module/trang.
        // Node dạng *.* (view, create, update, delete, v.v.) là quyền hành động chi tiết.
        $accessTree = [
            [
                'ma_quyen' => 'page.root',
                'ten_quyen' => 'Trang',
                'nhom_quyen' => 'Trang',
                'thu_tu' => 1,
                'children' => [
                    [
                        'ma_quyen' => 'frontend.root',
                        'ten_quyen' => '[Giao diện người dùng]',
                        'nhom_quyen' => 'Trang',
                        'thu_tu' => 10,
                        'children' => [
                            [
                                'ma_quyen' => 'frontend.home.access',
                                'ten_quyen' => 'Trang chủ & Danh mục',
                                'nhom_quyen' => 'Front end',
                                'thu_tu' => 10,
                            ],
                            [
                                'ma_quyen' => 'frontend.topup.access',
                                'ten_quyen' => 'Mua hàng & Nạp thẻ',
                                'nhom_quyen' => 'Front end',
                                'thu_tu' => 20,
                            ],
                        ],
                    ],
                    [
                        'ma_quyen' => 'backend.root',
                        'ten_quyen' => '[Trang quản trị Back end]',
                        'nhom_quyen' => 'Trang',
                        'thu_tu' => 20,
                        'children' => [
                            [
                                'ma_quyen' => 'backend.inventory',
                                'ten_quyen' => 'Tổng quan hệ thống',
                                'nhom_quyen' => 'Tổng quan hệ thống',
                                'thu_tu' => 10,
                                'children' => [
                                    [
                                        'ma_quyen' => 'dashboard.access',
                                        'ten_quyen' => 'Tổng quan hệ thống',
                                        'nhom_quyen' => 'Tổng quan hệ thống',
                                        'thu_tu' => 10,
                                    ],
                                ],
                            ],
                            [
                                'ma_quyen' => 'backend.catalog',
                                'ten_quyen' => 'Quản lý danh mục',
                                'nhom_quyen' => 'Quản lý danh mục',
                                'thu_tu' => 20,
                                'children' => [
                                    [
                                        'ma_quyen' => 'dich_vu.access',
                                        'ten_quyen' => 'Dịch vụ',
                                        'nhom_quyen' => 'Quản lý danh mục',
                                        'thu_tu' => 10,
                                    ],
                                    [
                                        'ma_quyen' => 'loai_san_pham.access',
                                        'ten_quyen' => 'Loại sản phẩm',
                                        'nhom_quyen' => 'Quản lý danh mục',
                                        'thu_tu' => 20,
                                    ],
                                    [
                                        'ma_quyen' => 'product.access',
                                        'ten_quyen' => 'Sản phẩm',
                                        'nhom_quyen' => 'Quản lý danh mục',
                                        'thu_tu' => 30,
                                    ],
                                ],
                            ],
                            [
                                'ma_quyen' => 'backend.providers',
                                'ten_quyen' => 'Quản lý nhà cung cấp',
                                'nhom_quyen' => 'Quản lý nhà cung cấp',
                                'thu_tu' => 30,
                                'children' => [
                                    [
                                        'ma_quyen' => 'service_config.access',
                                        'ten_quyen' => 'Nhà cung cấp & Kết nối API',
                                        'nhom_quyen' => 'Quản lý nhà cung cấp',
                                        'thu_tu' => 10,
                                    ],
                                    [
                                        'ma_quyen' => 'provider_product.access',
                                        'ten_quyen' => 'Sản phẩm Nhà Cung Cấp',
                                        'nhom_quyen' => 'Quản lý nhà cung cấp',
                                        'thu_tu' => 20,
                                    ],
                                    [
                                        'ma_quyen' => 'provider_error_code.access',
                                        'ten_quyen' => 'Mã lỗi Nhà Cung Cấp',
                                        'nhom_quyen' => 'Quản lý nhà cung cấp',
                                        'thu_tu' => 30,
                                    ],
                                ],
                            ],
                            [
                                'ma_quyen' => 'backend.b2b',
                                'ten_quyen' => 'Quản lý đại lý (B2B)',
                                'nhom_quyen' => 'Quản lý đại lý',
                                'thu_tu' => 40,
                                'children' => [
                                    [
                                        'ma_quyen' => 'b2b_partner.access',
                                        'ten_quyen' => 'Đại lý API',
                                        'nhom_quyen' => 'Quản lý đại lý',
                                        'thu_tu' => 10,
                                    ],
                                    [
                                        'ma_quyen' => 'b2b_order.access',
                                        'ten_quyen' => 'Đơn hàng B2B',
                                        'nhom_quyen' => 'Quản lý đại lý',
                                        'thu_tu' => 20,
                                    ],
                                    [
                                        'ma_quyen' => 'b2b_credit.access',
                                        'ten_quyen' => 'Công nợ & Thanh toán',
                                        'nhom_quyen' => 'Quản lý đại lý',
                                        'thu_tu' => 30,
                                    ],
                                    [
                                        'ma_quyen' => 'b2b_reconciliation.access',
                                        'ten_quyen' => 'Kỳ đối soát',
                                        'nhom_quyen' => 'Quản lý đại lý',
                                        'thu_tu' => 40,
                                    ],
                                    [
                                        'ma_quyen' => 'b2b_webhook.access',
                                        'ten_quyen' => 'Lịch sử Webhook',
                                        'nhom_quyen' => 'Quản lý đại lý',
                                        'thu_tu' => 50,
                                    ],
                                ],
                            ],
                            [
                                'ma_quyen' => 'order.access',
                                'ten_quyen' => 'Quản lý đơn hàng',
                                'nhom_quyen' => 'Quản lý đơn hàng',
                                'thu_tu' => 50,
                            ],
                            [
                                'ma_quyen' => 'backend.admin',
                                'ten_quyen' => 'Quản trị hệ thống',
                                'nhom_quyen' => 'Quản trị hệ thống',
                                'thu_tu' => 60,
                                'children' => [
                                    [
                                        'ma_quyen' => 'account.access',
                                        'ten_quyen' => 'Tài khoản người dùng',
                                        'nhom_quyen' => 'Quản trị hệ thống',
                                        'thu_tu' => 10,
                                    ],
                                    [
                                        'ma_quyen' => 'role.access',
                                        'ten_quyen' => 'Phân quyền vai trò',
                                        'nhom_quyen' => 'Quản trị hệ thống',
                                        'thu_tu' => 20,
                                    ],
                                    [
                                        'ma_quyen' => 'audit_log.access',
                                        'ten_quyen' => 'Nhật ký hoạt động',
                                        'nhom_quyen' => 'Quản trị hệ thống',
                                        'thu_tu' => 30,
                                    ],
                                    [
                                        'ma_quyen' => 'maintenance.access',
                                        'ten_quyen' => 'Bảo trì & Hiệu năng',
                                        'nhom_quyen' => 'Quản trị hệ thống',
                                        'thu_tu' => 40,
                                    ],
                                    [
                                        'ma_quyen' => 'telegram_setting.access',
                                        'ten_quyen' => 'Cấu hình thông báo Telegram',
                                        'nhom_quyen' => 'Quản trị hệ thống',
                                        'thu_tu' => 50,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->upsertTree($accessTree);

        $this->upsertActionPermissions([
            'frontend.home.access' => [
                ['frontend.home.view', 'Xem trang chủ'],
            ],
            'frontend.topup.access' => [
                ['frontend.topup.view', 'Xem giao diện nạp thẻ'],
                ['frontend.topup.order', 'Đặt hàng nạp thẻ'],
            ],
            'dashboard.access' => [
                ['dashboard.view', 'Xem Dashboard & Thống kê'],
            ],
            'dich_vu.access' => [
                ['dich_vu.view', 'Xem dịch vụ'],
                ['dich_vu.create', 'Thêm dịch vụ'],
                ['dich_vu.update', 'Sửa dịch vụ'],
                ['dich_vu.delete', 'Xóa dịch vụ'],
                ['dich_vu.export', 'Xuất dịch vụ'],
            ],
            'loai_san_pham.access' => [
                ['loai_san_pham.view', 'Xem loại sản phẩm'],
                ['loai_san_pham.create', 'Thêm loại sản phẩm'],
                ['loai_san_pham.update', 'Sửa loại sản phẩm'],
                ['loai_san_pham.delete', 'Xóa loại sản phẩm'],
                ['loai_san_pham.export', 'Xuất loại sản phẩm'],
            ],
            'product.access' => [
                ['product.view', 'Xem sản phẩm'],
                ['product.create', 'Thêm sản phẩm'],
                ['product.update', 'Sửa sản phẩm'],
                ['product.delete', 'Xóa sản phẩm'],
                ['product.map', 'Map sản phẩm NCC'],
                ['product.sync', 'Đồng bộ từ NCC'],
            ],
            'service_config.access' => [
                ['service_config.view', 'Xem nhà cung cấp'],
                ['service_config.create', 'Thêm nhà cung cấp'],
                ['service_config.update', 'Sửa nhà cung cấp'],
                ['service_config.delete', 'Xóa nhà cung cấp'],
                ['service_config.test', 'Kiểm tra kết nối'],
                ['service_config.reset_circuit', 'Đặt lại Circuit Breaker'],
            ],
            'provider_product.access' => [
                ['provider_product.view', 'Xem sản phẩm NCC'],
                ['provider_product.create', 'Thêm sản phẩm NCC'],
                ['provider_product.update', 'Sửa sản phẩm NCC'],
                ['provider_product.delete', 'Xóa sản phẩm NCC'],
                ['provider_product.map', 'Map sản phẩm NCC'],
                ['provider_product.sync', 'Đồng bộ sản phẩm NCC'],
            ],
            'provider_error_code.access' => [
                ['provider_error_code.view', 'Xem mã lỗi NCC'],
                ['provider_error_code.create', 'Thêm mã lỗi NCC'],
                ['provider_error_code.update', 'Sửa mã lỗi NCC'],
                ['provider_error_code.delete', 'Xóa mã lỗi NCC'],
            ],
            'b2b_partner.access' => [
                ['b2b_partner.view', 'Xem đại lý API'],
                ['b2b_partner.create', 'Tạo đại lý API'],
                ['b2b_partner.update', 'Sửa đại lý API'],
                ['b2b_partner.rotate_key', 'Đổi / Thu hồi Key API'],
                ['b2b_partner.pricing', 'Cấu hình bảng giá riêng'],
            ],
            'b2b_order.access' => [
                ['b2b_order.view', 'Xem đơn hàng B2B'],
            ],
            'b2b_credit.access' => [
                ['b2b_credit.view', 'Xem công nợ & giao dịch'],
                ['b2b_credit.payment', 'Ghi nhận thanh toán / nạp tiền'],
                ['b2b_credit.adjustment', 'Điều chỉnh hạn mức / công nợ'],
            ],
            'b2b_reconciliation.access' => [
                ['b2b_reconciliation.view', 'Xem kỳ đối soát'],
                ['b2b_reconciliation.create', 'Tạo kỳ đối soát'],
                ['b2b_reconciliation.lock', 'Khóa / Mở khóa kỳ đối soát'],
                ['b2b_reconciliation.recalculate', 'Tính toán lại kỳ đối soát'],
                ['b2b_reconciliation.export', 'Xuất Excel đối soát'],
            ],
            'b2b_webhook.access' => [
                ['b2b_webhook.view', 'Xem lịch sử webhook'],
                ['b2b_webhook.retry', 'Gửi lại webhook'],
            ],
            'order.access' => [
                ['order.view', 'Xem đơn hàng'],
                ['order.update', 'Cập nhật đơn hàng'],
                ['order.export', 'Xuất đơn hàng'],
                ['order.refund', 'Hoàn tiền đơn hàng'],
            ],
            'account.access' => [
                ['account.view', 'Xem tài khoản'],
                ['account.create', 'Tạo tài khoản'],
                ['account.update', 'Sửa tài khoản'],
                ['account.delete', 'Xóa tài khoản'],
                ['account.export', 'Xuất tài khoản'],
                ['account.lock', 'Khóa / Mở khóa tài khoản'],
            ],
            'role.access' => [
                ['role.view', 'Xem vai trò'],
                ['role.create', 'Thêm vai trò'],
                ['role.update', 'Sửa vai trò'],
                ['role.delete', 'Xóa vai trò'],
                ['role.assign_permission', 'Gán quyền cho vai trò'],
            ],
            'audit_log.access' => [
                ['audit_log.view', 'Xem nhật ký hoạt động'],
                ['audit_log.export', 'Xuất nhật ký hoạt động'],
            ],
            'maintenance.access' => [
                ['maintenance.view', 'Xem trang bảo trì'],
                ['maintenance.clear_cache', 'Xóa bộ nhớ cache'],
                ['maintenance.download_logs', 'Tải xuống tệp log'],
            ],
            'telegram_setting.access' => [
                ['telegram_setting.view', 'Xem cấu hình Telegram'],
                ['telegram_setting.update', 'Cập nhật cấu hình Telegram'],
                ['telegram_setting.test', 'Kiểm tra gửi tin nhắn Telegram'],
            ],
        ]);

        $this->migrateLegacyAccessGrants();

        // Quyền tương thích cũ
        $legacyPermissions = [
            ['ma_quyen' => 'user.view', 'ten_quyen' => 'Xem người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 110],
            ['ma_quyen' => 'user.create', 'ten_quyen' => 'Thêm người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 111],
            ['ma_quyen' => 'user.update', 'ten_quyen' => 'Sửa người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 112],
            ['ma_quyen' => 'user.delete', 'ten_quyen' => 'Xóa người dùng', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 113],
            ['ma_quyen' => 'permission.view', 'ten_quyen' => 'Xem quyền', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 130],
            ['ma_quyen' => 'permission.assign', 'ten_quyen' => 'Gán quyền', 'nhom_quyen' => 'Tương thích cũ', 'thu_tu' => 131],
        ];

        foreach ($legacyPermissions as $permission) {
            $this->upsertPermission($permission, null);
        }

        $this->grantDefaultRolePermissions();
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

    private function upsertActionPermissions(array $groups): void
    {
        foreach ($groups as $parentCode => $actions) {
            $parentId = DB::table('quyen')->where('ma_quyen', $parentCode)->value('id');
            if (! $parentId) {
                continue;
            }
            foreach ($actions as $index => [$code, $name]) {
                $this->upsertPermission([
                    'ma_quyen' => $code,
                    'ten_quyen' => $name,
                    'nhom_quyen' => $parentCode,
                    'thu_tu' => ($index + 1) * 10,
                ], (int) $parentId);
            }
        }
    }

    /** Chuyển quyền truy cập module cũ thành đầy đủ quyền hành động để giữ tương thích ngược. */
    private function migrateLegacyAccessGrants(): void
    {
        $parentCodes = [
            'dashboard.access', 'account.access', 'role.access',
            'dich_vu.access', 'loai_san_pham.access',
            'service_config.access', 'product.access',
            'provider_product.access', 'provider_error_code.access',
            'b2b_partner.access', 'b2b_order.access', 'b2b_credit.access',
            'b2b_reconciliation.access', 'b2b_webhook.access',
            'order.access', 'audit_log.access', 'maintenance.access',
            'telegram_setting.access', 'frontend.home.access', 'frontend.topup.access',
        ];

        foreach (DB::table('quyen')->whereIn('ma_quyen', $parentCodes)->get() as $parent) {
            $roleIds = DB::table('vai_tro_quyen')->where('quyen_id', $parent->id)->pluck('vai_tro_id');
            $childIds = DB::table('quyen')->where('quyen_cha_id', $parent->id)->pluck('id');

            foreach ($roleIds as $roleId) {
                foreach ($childIds as $childId) {
                    DB::table('vai_tro_quyen')->updateOrInsert(
                        ['vai_tro_id' => $roleId, 'quyen_id' => $childId],
                        ['tao_luc' => now()]
                    );
                }
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

    private function grantDefaultRolePermissions(): void
    {
        // 1. ADMIN: Toàn bộ quyền trong hệ thống
        $adminRole = DB::table('vai_tro')->where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $allActiveIds = DB::table('quyen')->where('trang_thai', 'hoat_dong')->pluck('id');
            foreach ($allActiveIds as $pId) {
                DB::table('vai_tro_quyen')->updateOrInsert(
                    ['vai_tro_id' => $adminRole->id, 'quyen_id' => $pId],
                    ['tao_luc' => now()]
                );
            }
        }

        // 2. KẾ TOÁN (ke_toan): Đơn hàng, Công nợ & Thanh toán, Xem Đối soát
        $keToanRole = DB::table('vai_tro')->where('ma_vai_tro', 'ke_toan')->first();
        if ($keToanRole) {
            $keToanCodes = [
                'page.root', 'backend.root',
                'order.access', 'order.view', 'order.export',
                'backend.b2b', 'b2b_order.access', 'b2b_order.view',
                'b2b_credit.access', 'b2b_credit.view', 'b2b_credit.payment', 'b2b_credit.adjustment',
                'b2b_reconciliation.access', 'b2b_reconciliation.view', 'b2b_reconciliation.export',
            ];
            $this->assignCodesToRole((int) $keToanRole->id, $keToanCodes);
        }

        // 3. ĐỐI SOÁT (doi_soat): Đơn hàng B2B, Toàn quyền Kỳ đối soát
        $doiSoatRole = DB::table('vai_tro')->where('ma_vai_tro', 'doi_soat')->first();
        if ($doiSoatRole) {
            $doiSoatCodes = [
                'page.root', 'backend.root',
                'order.access', 'order.view', 'order.export',
                'backend.b2b', 'b2b_order.access', 'b2b_order.view',
                'b2b_reconciliation.access', 'b2b_reconciliation.view', 'b2b_reconciliation.create',
                'b2b_reconciliation.lock', 'b2b_reconciliation.recalculate', 'b2b_reconciliation.export',
            ];
            $this->assignCodesToRole((int) $doiSoatRole->id, $doiSoatCodes);
        }

        // 4. BACKEND (Nhân viên vận hành): Dashboard, Danh mục, Sản phẩm, NCC, Đơn hàng
        $backendRole = DB::table('vai_tro')->where('ma_vai_tro', 'backend')->first();
        if ($backendRole) {
            $backendCodes = [
                'page.root', 'backend.root',
                'backend.inventory', 'dashboard.access', 'dashboard.view',
                'backend.catalog',
                'dich_vu.access', 'dich_vu.view', 'dich_vu.create', 'dich_vu.update', 'dich_vu.export',
                'loai_san_pham.access', 'loai_san_pham.view', 'loai_san_pham.create', 'loai_san_pham.update', 'loai_san_pham.export',
                'product.access', 'product.view', 'product.create', 'product.update', 'product.map', 'product.sync',
                'backend.providers',
                'service_config.access', 'service_config.view', 'service_config.test',
                'provider_product.access', 'provider_product.view', 'provider_product.create', 'provider_product.update', 'provider_product.map', 'provider_product.sync',
                'provider_error_code.access', 'provider_error_code.view', 'provider_error_code.create', 'provider_error_code.update',
                'order.access', 'order.view', 'order.update', 'order.export',
            ];
            $this->assignCodesToRole((int) $backendRole->id, $backendCodes);
        }

        // 5. USER (Khách hàng thông thường): Chỉ có quyền Frontend
        $userRole = DB::table('vai_tro')->where('ma_vai_tro', 'user')->first();
        if ($userRole) {
            $userCodes = [
                'page.root',
                'frontend.root',
                'frontend.home.access',
                'frontend.home.view',
                'frontend.topup.access',
                'frontend.topup.view',
                'frontend.topup.order',
            ];
            $this->assignCodesToRole((int) $userRole->id, $userCodes);
        }

        // Clear permission cache
        try {
            app(PermissionCacheService::class)->clearAll();
        } catch (\Throwable $e) {
            // cache service might be missing during initial seed
        }
    }

    private function assignCodesToRole(int $roleId, array $codes): void
    {
        $permissionIds = DB::table('quyen')->whereIn('ma_quyen', $codes)->pluck('id');
        foreach ($permissionIds as $pId) {
            DB::table('vai_tro_quyen')->updateOrInsert(
                ['vai_tro_id' => $roleId, 'quyen_id' => $pId],
                ['tao_luc' => now()]
            );
        }
    }
}
