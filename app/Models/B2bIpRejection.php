<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2bIpRejection extends Model
{
    use HasFactory;

    protected $table = 'b2b_ip_rejections';

    protected $fillable = [
        'dai_ly_api_id',
        'client_id',
        'ip_address',
        'endpoint',
        'method',
        'user_agent',
        'error_code',
        'request_headers_json',
        'attempted_at',
        'da_xu_ly',
        'resolved_at',
        'resolved_by_user_id',
    ];

    protected $casts = [
        'request_headers_json' => 'array',
        'attempted_at' => 'datetime',
        'da_xu_ly' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * Scope: chỉ các bản ghi chưa xử lý.
     */
    public function scopeChuaXuLy($query)
    {
        return $query->where('da_xu_ly', false);
    }

    /**
     * Scope: chỉ các bản ghi trong N ngày gần nhất.
     */
    public function scopeGanDay($query, int $days = 7)
    {
        return $query->where('attempted_at', '>=', now()->subDays($days));
    }
}
