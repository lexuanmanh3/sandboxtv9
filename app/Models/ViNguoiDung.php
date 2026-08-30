<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViNguoiDung extends Model
{
    use HasFactory;

    protected $table = 'vi_nguoi_dung';

    protected $fillable = [
        'nguoi_dung_id',
        'so_du',
        'tong_nap',
        'tong_chi',
        'tong_hoan',
        'trang_thai',
        'ghi_chu',
    ];

    protected $casts = [
        'so_du'     => 'decimal:2',
        'tong_nap'  => 'decimal:2',
        'tong_chi'  => 'decimal:2',
        'tong_hoan' => 'decimal:2',
    ];

    /**
     * Ví thuộc về một người dùng.
     */
    public function nguoiDung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_dung_id');
    }

    /**
     * Lịch sử biến động số dư.
     */
    public function lichSu(): HasMany
    {
        return $this->hasMany(LichSuVi::class, 'vi_nguoi_dung_id');
    }

    /**
     * Kiểm tra ví có đang hoạt động không.
     */
    public function dangHoatDong(): bool
    {
        return $this->trang_thai === 'hoat_dong';
    }

    /**
     * Kiểm tra số dư có đủ để chi không.
     */
    public function duSoDu(float $soTien): bool
    {
        return $this->so_du >= $soTien;
    }
}
