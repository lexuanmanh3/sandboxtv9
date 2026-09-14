<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KyDoiSoat extends Model
{
    use HasFactory;

    protected $table = 'ky_doi_soat';

    protected $fillable = [
        'dai_ly_api_id',
        'ma_ky',
        'tu_ngay',
        'den_ngay',
        'so_du_dau_ky',
        'tong_phat_sinh_tang',
        'tong_phat_sinh_giam',
        'tong_thanh_toan',
        'tong_dieu_chinh',
        'so_du_cuoi_ky',
        'trang_thai',
        'ngay_khoa',
        'nguoi_khoa_id',
        'ghi_chu',
    ];

    protected $casts = [
        'tu_ngay' => 'date',
        'den_ngay' => 'date',
        'so_du_dau_ky' => 'decimal:2',
        'tong_phat_sinh_tang' => 'decimal:2',
        'tong_phat_sinh_giam' => 'decimal:2',
        'tong_thanh_toan' => 'decimal:2',
        'tong_dieu_chinh' => 'decimal:2',
        'so_du_cuoi_ky' => 'decimal:2',
        'ngay_khoa' => 'datetime',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function nguoiKhoa()
    {
        return $this->belongsTo(User::class, 'nguoi_khoa_id');
    }

    public function chiTiet()
    {
        return $this->hasMany(ChiTietKyDoiSoat::class, 'ky_doi_soat_id');
    }

    public function soPhatSinh()
    {
        return $this->hasMany(SoPhatSinhCongNo::class, 'ky_doi_soat_id');
    }
}
