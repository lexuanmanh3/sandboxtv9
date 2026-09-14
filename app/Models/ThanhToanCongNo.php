<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThanhToanCongNo extends Model
{
    use HasFactory;

    protected $table = 'thanh_toan_cong_no';

    protected $fillable = [
        'dai_ly_api_id',
        'ma_thanh_toan',
        'so_tien',
        'ngay_thanh_toan',
        'phuong_thuc',
        'ma_giao_dich_ngan_hang',
        'ghi_chu',
        'trang_thai',
        'nguoi_tao_id',
    ];

    protected $casts = [
        'so_tien' => 'decimal:2',
        'ngay_thanh_toan' => 'datetime',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function nguoiTao()
    {
        return $this->belongsTo(User::class, 'nguoi_tao_id');
    }

    public function soPhatSinh()
    {
        return $this->hasMany(SoPhatSinhCongNo::class, 'thanh_toan_id');
    }
}
