<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KhoanGiuHanMuc extends Model
{
    use HasFactory;

    protected $table = 'khoan_giu_han_muc';

    protected $fillable = [
        'dai_ly_api_id',
        'don_hang_id',
        'so_tien_giu',
        'trang_thai',
        'ly_do_giai_phong',
        'giai_phong_luc',
        'chot_cong_no_luc',
    ];

    protected $casts = [
        'so_tien_giu' => 'decimal:2',
        'giai_phong_luc' => 'datetime',
        'chot_cong_no_luc' => 'datetime',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }
}
