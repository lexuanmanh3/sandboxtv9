<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng don_hang.
     */
    protected $table = 'don_hang';

    /**
     * Các field được phép create/update.
     */
    protected $fillable = [
        'ma_don_hang',
        'nguon_don',
        'nguoi_dung_id',
        'dai_ly_id',
        'dai_ly_api_id',
        'ma_don_doi_tac',
        'idempotency_key',
        'dich_vu_id',
        'loai_san_pham_id',
        'san_pham_id',
        'cau_hinh_dich_vu_id',
        'nha_cung_cap_id',
        'tai_khoan_nhan',
        'so_luong',
        'menh_gia',
        'gia_ban',
        'gia_von',
        'chiet_khau',
        'loi_nhuan',
        'phuong_thuc_thanh_toan',
        'trang_thai_thanh_toan',
        'trang_thai_don_hang',
        'trang_thai_doi_soat',
        'ma_loi_he_thong',
        'thong_bao_loi_he_thong',
        'nha_mang_yeu_cau', 'nha_mang_thuc_te', 'loai_thue_bao_yeu_cau', 'loai_thue_bao_thuc_te',
        'ma_san_pham_snapshot', 'ten_san_pham_snapshot', 'menh_gia_snapshot', 'gia_ban_snapshot',
        'gia_von_thuc_te', 'nha_cung_cap_thanh_cong_id', 'lan_goi_thanh_cong_id',
        'thanh_toan_luc', 'bat_dau_xu_ly_luc', 'hoan_thanh_luc', 'that_bai_luc',
        'xu_ly_owner', 'xu_ly_lease_den',
    ];

    /**
     * Cast tiền về decimal và số lượng về integer.
     */
    protected $casts = [
        'so_luong' => 'integer',
        'menh_gia' => 'decimal:2',
        'gia_ban' => 'decimal:2',
        'gia_von' => 'decimal:2',
        'chiet_khau' => 'decimal:2',
        'loi_nhuan' => 'decimal:2',
        'menh_gia_snapshot' => 'decimal:2',
        'gia_ban_snapshot' => 'decimal:2',
        'gia_von_thuc_te' => 'decimal:2',
        'thanh_toan_luc' => 'datetime',
        'bat_dau_xu_ly_luc' => 'datetime',
        'hoan_thanh_luc' => 'datetime',
        'that_bai_luc' => 'datetime',
        'xu_ly_lease_den' => 'datetime',
    ];

    /**
     * Lease sở hữu xử lý còn hiệu lực hay không.
     * Dùng để phân biệt "worker còn sống" với "worker đã chết" trước khi recovery
     * quyết định dispatch lại — tuyệt đối không dùng tuổi đơn làm bằng chứng.
     */
    public function dangCoLeaseXuLy(): bool
    {
        return $this->xu_ly_lease_den !== null && $this->xu_ly_lease_den->isFuture();
    }

    /**
     * Đơn hàng thuộc một người dùng / khách hàng.
     */
    public function nguoiDung()
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }

    /**
     * Đơn hàng có thể thuộc API Partner.
     */
    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    /**
     * Đơn hàng thuộc một dịch vụ.
     */
    public function dichVu()
    {
        return $this->belongsTo(DichVu::class, 'dich_vu_id');
    }

    /**
     * Đơn hàng thuộc một loại sản phẩm.
     */
    public function loaiSanPham()
    {
        return $this->belongsTo(LoaiSanPham::class, 'loai_san_pham_id');
    }

    /**
     * Đơn hàng thuộc một sản phẩm cụ thể.
     */
    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'san_pham_id');
    }

    /**
     * Đơn hàng có thể được xử lý bằng một cấu hình dịch vụ.
     */
    public function cauHinhDichVu()
    {
        return $this->belongsTo(CauHinhDichVu::class, 'cau_hinh_dich_vu_id');
    }

    /**
     * Đơn hàng có thể được xử lý bởi một NCC.
     */
    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id');
    }

    /**
     * Một đơn hàng có nhiều lịch sử đổi trạng thái.
     */
    public function lichSuTrangThai()
    {
        return $this->hasMany(LichSuTrangThaiDonHang::class, 'don_hang_id');
    }

    /**
     * Một đơn hàng có thể có nhiều log API.
     */
    public function logApi()
    {
        return $this->hasMany(LogApi::class, 'don_hang_id');
    }

    /**
     * Một đơn hàng có thể có nhiều lần gọi NCC.
     */
    public function lanGoiNhaCungCap()
    {
        return $this->hasMany(LanGoiNhaCungCap::class, 'don_hang_id');
    }

    public function nhaCungCapThanhCong()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_thanh_cong_id');
    }

    public function khoanGiuHanMuc()
    {
        return $this->hasOne(KhoanGiuHanMuc::class, 'don_hang_id');
    }

    public function soPhatSinhCongNo()
    {
        return $this->hasMany(SoPhatSinhCongNo::class, 'don_hang_id');
    }

    public function webhookOutbox()
    {
        return $this->hasMany(WebhookOutbox::class, 'don_hang_id');
    }
}
