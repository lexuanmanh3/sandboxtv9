<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LichSuVi extends Model
{
    use HasFactory;

    protected $table = 'lich_su_vi';

    protected $fillable = [
        'vi_nguoi_dung_id',
        'don_hang_id',
        'loai_giao_dich',
        'so_tien',
        'so_du_truoc',
        'so_du_sau',
        'mo_ta',
        'nguon',
    ];

    protected $casts = [
        'so_tien'    => 'decimal:2',
        'so_du_truoc' => 'decimal:2',
        'so_du_sau'  => 'decimal:2',
    ];

    /**
     * Lịch sử thuộc về ví nào.
     */
    public function vi(): BelongsTo
    {
        return $this->belongsTo(ViNguoiDung::class, 'vi_nguoi_dung_id');
    }

    /**
     * Lịch sử liên quan đến đơn hàng nào (nếu có).
     */
    public function donHang(): BelongsTo
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    /**
     * Nhãn loại giao dịch tiếng Việt.
     */
    public function getNhanLoaiAttribute(): string
    {
        return match ($this->loai_giao_dich) {
            'debit'  => 'Thanh toán',
            'credit' => 'Nạp tiền',
            'refund' => 'Hoàn tiền',
            default  => 'Không xác định',
        };
    }
}
