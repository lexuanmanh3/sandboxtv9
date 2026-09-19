<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2bOrderOutbox extends Model
{
    use HasFactory;

    protected $table = 'b2b_order_outbox';

    protected $fillable = [
        'don_hang_id',
        'dai_ly_api_id',
        'partner_order_id',
        'product_code',
        'account',
        'payload_json',
        'status',
        'attempts',
        'locked_until',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }
}
