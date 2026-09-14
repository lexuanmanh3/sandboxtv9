<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoPhatSinhCongNo extends Model
{
    use HasFactory;

    protected $table = 'so_phat_sinh_cong_no';

    public $timestamps = true;

    protected $fillable = [
        'dai_ly_api_id',
        'don_hang_id',
        'thanh_toan_id',
        'dieu_chinh_id',
        'ky_doi_soat_id',
        'loai_phat_sinh',
        'so_tien',
        'so_du_truoc',
        'so_du_sau',
        'ma_tham_chieu',
        'ghi_chu',
        'nguoi_tao_id',
    ];

    protected $casts = [
        'so_tien' => 'decimal:2',
        'so_du_truoc' => 'decimal:2',
        'so_du_sau' => 'decimal:2',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    public function thanhToan()
    {
        return $this->belongsTo(ThanhToanCongNo::class, 'thanh_toan_id');
    }

    public function dieuChinh()
    {
        return $this->belongsTo(DieuChinhCongNo::class, 'dieu_chinh_id');
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
