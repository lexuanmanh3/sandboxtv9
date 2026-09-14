<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2bIdempotencyRecord extends Model
{
    use HasFactory;

    protected $table = 'b2b_idempotency_records';

    protected $fillable = [
        'dai_ly_api_id',
        'idempotency_key',
        'request_hash',
        'status',
        'http_status',
        'response_json',
        'don_hang_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'http_status' => 'integer',
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
