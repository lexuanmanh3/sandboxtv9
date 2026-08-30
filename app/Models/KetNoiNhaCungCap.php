<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KetNoiNhaCungCap extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng ket_noi_nha_cung_cap.
     */
    protected $table = 'ket_noi_nha_cung_cap';

    /**
     * Các field được phép lưu bằng Eloquent.
     */
    protected $fillable = [
        'nha_cung_cap_id',
        'username',
        'password_ma_hoa',
        'api_user',
        'api_password_ma_hoa',
        'api_url',
        'cau_hinh_ma_gd',
        'public_key',
        'public_key_file',
        'private_key_file_ma_hoa',
        'timeout_he_thong',
        'timeout_ncc',
        'trang_thai',
        'ten_ket_noi',
        'moi_truong',
        'base_url',
        'partner_code',
        'api_key_ma_hoa',
        'secret_key_ma_hoa',
        'auth_type',
        'connect_timeout_seconds',
        'request_timeout_seconds',
        'cau_hinh_canh_bao_so_du',
        'cau_hinh_dong_tu_dong',
        'cau_hinh_canh_bao_loi',
    ];

    /**
     * Các field nhạy cảm sẽ được Laravel tự mã hóa khi lưu
     * và tự giải mã khi đọc ra.
     *
     * Lưu ý: Không echo các field này ra giao diện admin nếu không cần thiết.
     */
    protected $casts = [
        'password_ma_hoa' => 'encrypted',
        'api_password_ma_hoa' => 'encrypted',
        'private_key_file_ma_hoa' => 'encrypted',
        'api_key_ma_hoa' => 'encrypted',
        'secret_key_ma_hoa' => 'encrypted',
        'cau_hinh_canh_bao_so_du' => 'array',
        'cau_hinh_dong_tu_dong' => 'array',
        'cau_hinh_canh_bao_loi' => 'array',
    ];

    /**
     * Không serialize các trường mật khẩu/khóa bảo mật ra Array/JSON
     * để tránh lỗi giải mã MAC invalid và bảo mật an toàn.
     */
    protected $hidden = [
        'password_ma_hoa',
        'api_password_ma_hoa',
        'private_key_file_ma_hoa',
        'api_key_ma_hoa',
        'secret_key_ma_hoa',
    ];

    /**
     * Mỗi kết nối API thuộc về một NCC.
     * Dùng để lấy thông tin NCC từ cấu hình kết nối.
     */
    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id');
    }

    public function sanPhamMappings()
    {
        return $this->hasMany(SanPhamNhaCungCap::class, 'ket_noi_nha_cung_cap_id');
    }

    /**
     * Kiểm tra có mật khẩu/secret key mà không kích hoạt cast giải mã (tránh lỗi MAC invalid).
     */
    public function hasPassword(): bool
    {
        $rawPwd = $this->getRawOriginal('password_ma_hoa');
        $rawSecret = $this->getRawOriginal('secret_key_ma_hoa');
        return !empty($rawPwd) || !empty($rawSecret);
    }

    /**
     * Kiểm tra có API password/API key mà không kích hoạt cast giải mã.
     */
    public function hasApiPassword(): bool
    {
        $rawApiPwd = $this->getRawOriginal('api_password_ma_hoa');
        $rawApiKey = $this->getRawOriginal('api_key_ma_hoa');
        return !empty($rawApiPwd) || !empty($rawApiKey);
    }

    /**
     * Kiểm tra có Private Key mà không kích hoạt cast giải mã.
     */
    public function hasPrivateKey(): bool
    {
        $rawPriv = $this->getRawOriginal('private_key_file_ma_hoa');
        return !empty($rawPriv);
    }
}
