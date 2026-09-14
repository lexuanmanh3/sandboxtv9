<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CauHinhDichVu extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng cau_hinh_dich_vu.
     */
    protected $table = 'cau_hinh_dich_vu';

    /**
     * Các field được phép create/update.
     * Khi làm form admin cấu hình NCC, dữ liệu sẽ lưu vào các field này.
     */
    protected $fillable = [
        'ten_cau_hinh',
        'nha_cung_cap_id',
        'dich_vu_id',
        'loai_san_pham_id',
        'san_pham_id',
        'dai_ly_ap_dung_id',
        'tai_khoan_ap_dung_id',
        'muc_uu_tien',
        'dang_mo',
        'timeout_he_thong_giay',
        'timeout_gui_ncc_giay',
        'thoi_gian_tra_ket_qua_giay',
        'che_do_chay',
        'ket_thuc_cau_hinh',
    ];

    /**
     * Cast boolean để Laravel tự hiểu true/false.
     * Nếu không cast, dữ liệu boolean trong DB có thể trả về 0/1.
     */
    protected $casts = [
        'dang_mo' => 'boolean',
        'ket_thuc_cau_hinh' => 'boolean',
    ];

    /**
     * Cấu hình này thuộc về một NCC.
     */
    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id');
    }

    /**
     * Cấu hình này thuộc về một dịch vụ lớn.
     * Ví dụ: PIN_CODE, TOPUP, PAY_BILL.
     */
    public function dichVu()
    {
        return $this->belongsTo(DichVu::class, 'dich_vu_id');
    }

    /**
     * Cấu hình này có thể áp dụng cho một loại sản phẩm.
     * Ví dụ: Viettel, Mobifone, Garena.
     */
    public function loaiSanPham()
    {
        return $this->belongsTo(LoaiSanPham::class, 'loai_san_pham_id');
    }

    /**
     * Cấu hình này có thể áp dụng cho một sản phẩm cụ thể.
     * Ví dụ: Viettel 20k.
     */
    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'san_pham_id');
    }

    /**
     * Cấu hình áp dụng cho đại lý API cụ thể.
     */
    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_ap_dung_id');
    }

    public function daiLyApDung()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_ap_dung_id');
    }

    /**
     * Cấu hình áp dụng cho tài khoản người dùng cụ thể.
     */
    public function taiKhoan()
    {
        return $this->belongsTo(User::class, 'tai_khoan_ap_dung_id');
    }
}