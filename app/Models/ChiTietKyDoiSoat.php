<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietKyDoiSoat extends Model
{
    use HasFactory;

    protected $table = 'chi_tiet_ky_doi_soat';

    protected $fillable = [
        'ky_doi_soat_id',
        'don_hang_id',
        'so_tien',
        'trang_thai_don_hang',
        'ngay_tao_don',
    ];

    protected $casts = [
        'so_tien' => 'decimal:2',
        'ngay_tao_don' => 'datetime',
    ];

    public function kyDoiSoat()
    {
        return $this->belongsTo(KyDoiSoat::class, 'ky_doi_soat_id');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }
}
