<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DieuChinhCongNo extends Model
{
    use HasFactory;

    protected $table = 'dieu_chinh_cong_no';

    public $timestamps = false;

    protected $fillable = [
        'dai_ly_api_id',
        'ky_doi_soat_id',
        'ma_dieu_chinh',
        'loai_dieu_chinh',
        'so_tien',
        'ly_do',
        'ma_tham_chieu_goc',
        'nguoi_tao_id',
        'created_at',
    ];

    protected $casts = [
        'so_tien' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function kyDoiSoat()
    {
        return $this->belongsTo(KyDoiSoat::class, 'ky_doi_soat_id');
    }

    public function nguoiTao()
    {
        return $this->belongsTo(User::class, 'nguoi_tao_id');
    }
}
