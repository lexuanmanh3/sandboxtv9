<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BangGiaDaiLy extends Model
{
    use HasFactory;

    protected $table = 'bang_gia_dai_ly';

    protected $fillable = [
        'dai_ly_api_id',
        'san_pham_id',
        'loai_chiet_khau',
        'gia_tri_chiet_khau',
        'gia_ban_ap_dung',
        'trang_thai',
    ];

    protected $casts = [
        'gia_tri_chiet_khau' => 'decimal:2',
        'gia_ban_ap_dung' => 'decimal:2',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'san_pham_id');
    }
}

