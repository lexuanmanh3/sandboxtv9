<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaiLyApi extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng dai_ly_api.
     * Bảng này lưu thông tin API Partner gửi đơn qua API.
     */
    protected $table = 'dai_ly_api';

    /**
     * Các field được phép create/update qua Eloquent.
     */
    protected $fillable = [
        'nguoi_dung_id',
        'ma_dai_ly_api',
        'ten_dai_ly_api',
        'so_dien_thoai',
        'ho',
        'ten',
        'tinh_thanh',
        'quan_huyen',
        'phuong_xa',
        'dia_chi_chi_tiet',
        'thong_tin_lien_he',
        'ngay_ky_hop_dong',
        'so_hop_dong',
        'email_ky_thuat',
        'email_doi_soat',
        'ky_doi_soat',
        'folder_ftp',
        'telegram_group_id',
        'trang_thai',
    ];

    protected $casts = [
        'thong_tin_lien_he' => 'array',
        'ky_doi_soat' => 'integer',
        'ngay_ky_hop_dong' => 'date',
    ];

    /**
     * Một API Partner có một cấu hình API.
     * Dùng để lấy client_id, secret, IP whitelist, hạn mức.
     */
    public function cauHinhApi()
    {
        return $this->hasOne(CauHinhApiDaiLy::class, 'dai_ly_api_id');
    }

    public function bangGia()
    {
        return $this->hasMany(BangGiaDaiLy::class, 'dai_ly_api_id');
    }

    public function khoanGiuHanMuc()
    {
        return $this->hasMany(KhoanGiuHanMuc::class, 'dai_ly_api_id');
    }

    public function soPhatSinhCongNo()
    {
        return $this->hasMany(SoPhatSinhCongNo::class, 'dai_ly_api_id');
    }

    public function thanhToanCongNo()
    {
        return $this->hasMany(ThanhToanCongNo::class, 'dai_ly_api_id');
    }

    public function kyDoiSoat()
    {
        return $this->hasMany(KyDoiSoat::class, 'dai_ly_api_id');
    }

    public function dieuChinhCongNo()
    {
        return $this->hasMany(DieuChinhCongNo::class, 'dai_ly_api_id');
    }

    public function webhookOutbox()
    {
        return $this->hasMany(WebhookOutbox::class, 'dai_ly_api_id');
    }

    public function donHang()
    {
        return $this->hasMany(DonHang::class, 'dai_ly_api_id');
    }

    public function nguoiDung()
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }

    /**
     * Một API Partner có nhiều dịch vụ được phép dùng.
     * Đây là bảng trung gian để kiểm soát partner được gọi API nào.
     */
    public function dichVuDuocPhep()
    {
        return $this->hasMany(DaiLyApiDichVuDuocPhep::class, 'dai_ly_api_id');
    }

    /**
     * Quan hệ many-to-many giúp lấy thẳng danh sách dịch vụ partner được dùng.
     * Ví dụ: $partner->dichVu()->get()
     */
    public function dichVu()
    {
        return $this->belongsToMany(
            DichVu::class,
            'dai_ly_api_dich_vu_duoc_phep',
            'dai_ly_api_id',
            'dich_vu_id'
        )->withTimestamps();
    }
    
    /**
     * Các loại sản phẩm mà API Partner được phép dùng.
     */
    public function loaiSanPhamDuocPhep()
    {
        return $this->hasMany(DaiLyApiLoaiSanPhamDuocPhep::class, 'dai_ly_api_id');
    }

    /**
     * Lấy thẳng danh sách loại sản phẩm được phép dùng.
     * Ví dụ: $partner->loaiSanPham()->get()
     */
    public function loaiSanPham()
    {
        return $this->belongsToMany(
            LoaiSanPham::class,
            'dai_ly_api_loai_san_pham_duoc_phep',
            'dai_ly_api_id',
            'loai_san_pham_id'
        )->withTimestamps();
    }

    /**
     * Các sản phẩm cụ thể mà API Partner được phép dùng.
     *
     * Ví dụ:
     * Partner A được phép mua Viettel 20k, Viettel 50k.
     */
    public function sanPhamDuocPhep()
    {
        return $this->hasMany(DaiLyApiSanPhamDuocPhep::class, 'dai_ly_api_id');
    }

    /**
     * Lấy thẳng danh sách sản phẩm được phép dùng.
     *
     * Ví dụ:
     * $partner->sanPham()->get();
     */
    public function sanPham()
    {
        return $this->belongsToMany(
            SanPham::class,
            'dai_ly_api_san_pham_duoc_phep',
            'dai_ly_api_id',
            'san_pham_id'
        )->withTimestamps();
    }
}