<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use App\Models\VaiTro;
use App\Models\Quyen;
use App\Services\Authorization\AuthorizationService;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Tên bảng trong database.
     * Laravel mặc định đã hiểu model User dùng bảng users,
     * nhưng mình khai báo rõ để bạn dễ học.
     */
    protected $table = 'users';

    /**
     * Các cột được phép thêm/sửa bằng User::create() hoặc $user->update().
     */
    protected $fillable = [
        'ten_dang_nhap',
        'email',
        'so_dien_thoai',
        'password',

        'ho',
        'ten',
        'name',

        'loai_tai_khoan',

        'email_verified_at',
        'email_da_xac_nhan',
        'tai_khoan_da_xac_thuc',

        'bat_buoc_doi_mat_khau',
        'tu_dong_khoa_tai_khoan',
        'bi_khoa',

        'trang_thai',
        'lan_dang_nhap_cuoi',
    ];

    /**
     * Các cột bị ẩn khi trả dữ liệu user ra ngoài.
     * Mật khẩu và remember_token không nên hiển thị.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Ép kiểu dữ liệu.
     * Ví dụ: bi_khoa là true/false, email_verified_at là ngày giờ.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_da_xac_nhan' => 'boolean',
        'tai_khoan_da_xac_thuc' => 'boolean',
        'bat_buoc_doi_mat_khau' => 'boolean',
        'tu_dong_khoa_tai_khoan' => 'boolean',
        'bi_khoa' => 'boolean',
        'lan_dang_nhap_cuoi' => 'datetime',
    ];

    /**
     * Tự động mã hóa mật khẩu khi gán password.
     *
     * Ví dụ:
     * $user->password = '12345678';
     *
     * Laravel sẽ tự hash trước khi lưu.
     */
    public function setPasswordAttribute($value)
    {
        if (! empty($value)) {
            $this->attributes['password'] = Hash::needsRehash($value)
                ? Hash::make($value)
                : $value;
        }
    }

    /**
     * Lấy tên hiển thị của user.
     *
     * Ưu tiên:
     * 1. name
     * 2. ho + ten
     * 3. ten_dang_nhap
     * 4. email
     */
    public function getTenHienThiAttribute()
    {
        if (! empty($this->name)) {
            return $this->name;
        }

        $hoTen = trim(($this->ho ?? '') . ' ' . ($this->ten ?? ''));

        if (! empty($hoTen)) {
            return $hoTen;
        }

        return $this->ten_dang_nhap ?? $this->email;
    }

    /**
     * Kiểm tra tài khoản có đang hoạt động không.
     */
    public function dangHoatDong()
    {
        return $this->trang_thai === 'hoat_dong' && ! $this->bi_khoa;
    }

    /**
     * Kiểm tra user có phải admin không.
     */
    public function laAdmin()
    {
        return $this->loai_tai_khoan === 'admin';
    }

    /**
     * Kiểm tra user có phải kế toán không.
     */
    public function laKeToan()
    {
        return $this->loai_tai_khoan === 'ke_toan';
    }

    /**
     * Kiểm tra user có phải đối soát không.
     */
    public function laDoiSoat()
    {
        return $this->loai_tai_khoan === 'doi_soat';
    }

    /**
     * Kiểm tra user có phải sale không.
     */
    public function laSale()
    {
        return $this->loai_tai_khoan === 'sale';
    }

    /**
     * Kiểm tra user có phải đại lý thường không.
     */
    public function laDaiLy()
    {
        return $this->loai_tai_khoan === 'dai_ly';
    }

    /**
     * Kiểm tra user có phải đại lý API không.
     */
    public function laDaiLyApi()
    {
        return $this->loai_tai_khoan === 'dai_ly_api';
    }

    /**
     * Quan hệ bảng vai_tro.
     *
     * 1 user có thể có nhiều vai trò.
     */
    public function vaiTro()
    {
        return $this->belongsToMany(
            VaiTro::class,
            'nguoi_dung_vai_tro',
            'nguoi_dung_id',
            'vai_tro_id'
        );
    }

    /**
     * Ví tiền của người dùng.
     */
    public function vi()
    {
        return $this->hasOne(ViNguoiDung::class, 'nguoi_dung_id');
    }

    public function coVaiTro(string $maVaiTro): bool
    {
        return $this->vaiTro()
            ->where('ma_vai_tro', $maVaiTro)
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->coVaiTro('admin') || $this->coVaiTro('quan_tri_vien') || $this->loai_tai_khoan === 'admin';
    }

    /**
     * NOTE: Kiem tra nguoi dung co quyen truy cap theo ma quyen hay khong.
     *
     * Trong project nay "quyen" duoc dung chinh cho quyen truy cap module/trang,
     * vi du: account.access, role.access, report.access.
     * Admin goc duoc phep qua tat ca quyen de tranh bi khoa he thong khi chua seed du lieu.
     */
    public function coQuyen(string $maQuyen): bool
    {
        return app(AuthorizationService::class)->allows($this, $maQuyen);
    }

    /**
     * NOTE: Kiem tra user co it nhat mot quyen trong danh sach hay khong.
     * Ham nay dung cho sidebar/menu, noi chi can biet co duoc hien nhom menu hay khong.
     */
    public function coMotTrongCacQuyen(array $maQuyen): bool
    {
        return app(AuthorizationService::class)->allowsAny($this, $maQuyen);
    }

    public function hasAnyBackendAccess(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $backendCodes = [
            'dashboard.access', 'dashboard.view',
            'dich_vu.access', 'dich_vu.view',
            'loai_san_pham.access', 'loai_san_pham.view',
            'product.access', 'product.view',
            'service_config.access', 'service_config.view',
            'provider_product.access', 'provider_product.view',
            'provider_error_code.access', 'provider_error_code.view',
            'b2b_partner.access', 'b2b_partner.view',
            'b2b_order.access', 'b2b_order.view',
            'b2b_credit.access', 'b2b_credit.view',
            'b2b_reconciliation.access', 'b2b_reconciliation.view',
            'b2b_webhook.access', 'b2b_webhook.view',
            'order.access', 'order.view',
            'account.access', 'account.view',
            'role.access', 'role.view',
            'audit_log.access', 'audit_log.view',
            'maintenance.access', 'maintenance.view',
            'telegram_setting.access', 'telegram_setting.view',
            'backend.root',
        ];

        return $this->coMotTrongCacQuyen($backendCodes);
    }

    public function maQuyenHieuLuc(): \Illuminate\Support\Collection
    {
        return app(AuthorizationService::class)->codes($this);
    }
}
