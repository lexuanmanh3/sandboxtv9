<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SanPhamNhaCungCap extends Model
{
    use HasFactory;

    protected $table = 'san_pham_nha_cung_cap';
    protected $guarded = ['id'];
    protected $casts = [
        'menh_gia_ncc' => 'decimal:2', 'gia_nhap' => 'decimal:2', 'ty_le_chiet_khau' => 'decimal:4',
        'muc_uu_tien' => 'integer', 'du_lieu_mo_rong_json' => 'array', 'dong_bo_luc' => 'datetime',
    ];

    public function sanPham() { return $this->belongsTo(SanPham::class, 'san_pham_id'); }
    public function nhaCungCap() { return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id'); }
    public function ketNoi() { return $this->belongsTo(KetNoiNhaCungCap::class, 'ket_noi_nha_cung_cap_id'); }
}
