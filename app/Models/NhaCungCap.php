<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhaCungCap extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng nha_cung_cap.
     */
    protected $table = 'nha_cung_cap';

    /**
     * fillable cho phép các field này được create/update bằng Eloquent.
     */
    protected $fillable = [
        'nha_cung_cap_cha_id',
        'ma_ncc',
        'ten_ncc',
        'so_dien_thoai',
        'email',
        'trang_thai',
    ];

    /**
     * Accessor tương thích ngược khi gọi $ncc->ten_nha_cung_cap
     */
    public function getTenNhaCungCapAttribute(): string
    {
        return $this->ten_ncc ?? $this->ma_ncc ?? '';
    }

    /**
     * Một NCC có thể có một NCC cha.
     */
    public function nhaCungCapCha()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_cha_id');
    }

    /**
     * Một NCC có thể có nhiều NCC con.
     */
    public function nhaCungCapCon()
    {
        return $this->hasMany(NhaCungCap::class, 'nha_cung_cap_cha_id');
    }

    /**
     * Một NCC có thể có nhiều cấu hình kết nối API.
     */
    public function ketNoi()
    {
        return $this->hasMany(KetNoiNhaCungCap::class, 'nha_cung_cap_id');
    }

    /**
     * Một NCC có thể xử lý nhiều cấu hình dịch vụ/sản phẩm.
     */
    public function cauHinhDichVu()
    {
        return $this->hasMany(CauHinhDichVu::class, 'nha_cung_cap_id');
    }

    /**
     * Một NCC có nhiều sản phẩm NCC.
     */
    public function sanPhamNhaCungCap()
    {
        return $this->hasMany(SanPhamNhaCungCap::class, 'nha_cung_cap_id');
    }
}
