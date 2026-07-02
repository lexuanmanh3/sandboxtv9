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
     * Sau này khi làm bảng vai_tro, mình sẽ dùng quan hệ này.
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
        // Kiểm tra người dùng có vai trò theo mã vai trò không
    public function coVaiTro(string $maVaiTro): bool
    {
        return $this->vaiTro()
            ->where('ma_vai_tro', $maVaiTro)
            ->exists();
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
        return $this->vaiTro()
            ->where('vai_tro.trang_thai', 'hoat_dong')
            ->whereHas('quyen', function ($query) use ($maQuyen) {
                $query->where('ma_quyen', $maQuyen)
                    ->where('quyen.trang_thai', 'hoat_dong');
            })
            ->exists();
    }

    /**
     * NOTE: Kiem tra user co it nhat mot quyen trong danh sach hay khong.
     * Ham nay dung cho sidebar/menu, noi chi can biet co duoc hien nhom menu hay khong.
     */
    public function coMotTrongCacQuyen(array $maQuyen): bool
    {
        return $this->vaiTro()
            ->where('vai_tro.trang_thai', 'hoat_dong')
            ->whereHas('quyen', function ($query) use ($maQuyen) {
                $query->whereIn('ma_quyen', $maQuyen)
                    ->where('quyen.trang_thai', 'hoat_dong');
            })
            ->exists();
    }

    // /**
    //  * Sau này khi làm bảng dai_ly, 1 user có thể gắn với 1 đại lý.
    //  */
    // public function daiLy()
    // {
    //     return $this->hasOne(DaiLy::class, 'user_id');
    // }

    // /**
    //  * Sau này khi làm bảng dai_ly_api, 1 user có thể gắn với 1 đại lý API.
    //  */
    // public function daiLyApi()
    // {
    //     return $this->hasOne(DaiLyApi::class, 'user_id');
    // }

    // Các vai trò của người dùng


}
