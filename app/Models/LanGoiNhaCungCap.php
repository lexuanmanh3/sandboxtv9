<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LanGoiNhaCungCap extends Model
{
    use HasFactory;

    protected $table = 'lan_goi_nha_cung_cap';

    protected $fillable = [
        'don_hang_id',
        'nha_cung_cap_id',
        'cau_hinh_dich_vu_id',
        'lan_thu',
        'ma_giao_dich_ncc',
        'request_json',
        'response_json',
        'ma_loi_ncc',
        'ma_loi_he_thong',
        'trang_thai',
        'thoi_gian_xu_ly_ms',
        'ket_noi_nha_cung_cap_id', 'san_pham_nha_cung_cap_id', 'loai_yeu_cau',
        'partner_ref_id', 'ma_san_pham_ncc', 'endpoint', 'http_status', 'response_signature',
        'chu_ky_hop_le', 'so_du_ncc_sau_giao_dich', 'thoi_gian_giao_dich_ncc',
        'ket_qua_xac_dinh', 'bat_dau_luc', 'ket_thuc_luc', 'kiem_tra_lai_luc',
    ];

    /**
     * Cast JSON và số lần gọi.
     */
    protected $casts = [
        'lan_thu' => 'integer',
        'request_json' => 'array',
        'response_json' => 'array',
        'chu_ky_hop_le' => 'boolean',
        'thoi_gian_giao_dich_ncc' => 'datetime',
        'bat_dau_luc' => 'datetime', 'ket_thuc_luc' => 'datetime', 'kiem_tra_lai_luc' => 'datetime',
    ];

    /**
     * Lần gọi NCC này thuộc về một đơn hàng.
     */
    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    /**
     * Lần gọi này gửi tới một NCC.
     */
    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id');
    }

    /**
     * Lần gọi này dùng một cấu hình dịch vụ.
     */
    public function cauHinhDichVu()
    {
        return $this->belongsTo(CauHinhDichVu::class, 'cau_hinh_dich_vu_id');
    }

    public function ketNoi()
    {
        return $this->belongsTo(KetNoiNhaCungCap::class, 'ket_noi_nha_cung_cap_id');
    }

    public function ketNoiNhaCungCap()
    {
        return $this->belongsTo(KetNoiNhaCungCap::class, 'ket_noi_nha_cung_cap_id');
    }

    public function sanPhamNhaCungCap()
    {
        return $this->belongsTo(SanPhamNhaCungCap::class, 'san_pham_nha_cung_cap_id');
    }
}
