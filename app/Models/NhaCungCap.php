<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NhaCungCap extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng nha_cung_cap.
     * Laravel mặc định đoán tên bảng theo tiếng Anh, nên bảng tiếng Việt cần khai báo rõ.
     */
    protected $table = 'nha_cung_cap';

    /**
     * fillable cho phép các field này được create/update bằng Eloquent.
     * Khi thêm field mới trong migration thì nhớ bổ sung ở đây nếu muốn lưu qua form.
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
     * Một NCC có thể có một NCC cha.
     * Dùng khi sau này phân cấp NCC.
     */
    public function nhaCungCapCha()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_cha_id');
    }

    /**
     * Một NCC có thể có nhiều NCC con.
     * Dùng để lấy danh sách nhánh/con của NCC hiện tại.
     */
    public function nhaCungCapCon()
    {
        return $this->hasMany(NhaCungCap::class, 'nha_cung_cap_cha_id');
    }

    /**
     * Một NCC có thể có nhiều cấu hình kết nối API.
     * Ví dụ sandbox, production, hoặc nhiều endpoint khác nhau.
     */
    public function ketNoi()
    {
        return $this->hasMany(KetNoiNhaCungCap::class, 'nha_cung_cap_id');
    }

    /**
     * Một NCC có thể xử lý nhiều cấu hình dịch vụ/sản phẩm.
     * Ví dụ AppotaPay xử lý TOPUP, PIN_CODE, TOPUP_DATA.
     */
    public function cauHinhDichVu()
    {
        return $this->hasMany(CauHinhDichVu::class, 'nha_cung_cap_id');
    }

    public function sanPhamMappings()
    {
        return $this->hasMany(SanPhamNhaCungCap::class, 'nha_cung_cap_id');
    }
}
