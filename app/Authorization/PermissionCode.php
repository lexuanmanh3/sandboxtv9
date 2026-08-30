<?php

namespace App\Authorization;

/** Nơi khai báo tập trung các mã quyền được sử dụng trong mã nguồn. */
final class PermissionCode
{
    public const DASHBOARD_VIEW = 'dashboard.view';
    public const ACCOUNT_VIEW = 'account.view';
    public const ACCOUNT_CREATE = 'account.create';
    public const ACCOUNT_UPDATE = 'account.update';
    public const ACCOUNT_DELETE = 'account.delete';
    public const ACCOUNT_EXPORT = 'account.export';
    public const ACCOUNT_LOCK = 'account.lock';
    public const ROLE_VIEW = 'role.view';
    public const ROLE_ASSIGN_PERMISSION = 'role.assign_permission';
    public const SERVICE_VIEW = 'dich_vu.view';
    public const SERVICE_CREATE = 'dich_vu.create';
    public const SERVICE_UPDATE = 'dich_vu.update';
    public const SERVICE_DELETE = 'dich_vu.delete';
    public const SERVICE_EXPORT = 'dich_vu.export';
    public const CATEGORY_VIEW = 'loai_san_pham.view';
    public const CATEGORY_CREATE = 'loai_san_pham.create';
    public const CATEGORY_UPDATE = 'loai_san_pham.update';
    public const CATEGORY_DELETE = 'loai_san_pham.delete';
    public const CATEGORY_EXPORT = 'loai_san_pham.export';

    private function __construct()
    {
    }
}
