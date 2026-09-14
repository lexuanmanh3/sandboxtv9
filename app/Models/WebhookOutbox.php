<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookOutbox extends Model
{
    use HasFactory;

    protected $table = 'webhook_outbox';

    protected $fillable = [
        'event_id',
        'dai_ly_api_id',
        'don_hang_id',
        'event_type',
        'payload_json',
        'trang_thai',
        'so_lan_thu',
        'lan_thu_tiep_theo',
        'khoa_den',
    ];

    protected $casts = [
        'lan_thu_tiep_theo' => 'datetime',
        'khoa_den' => 'datetime',
        'so_lan_thu' => 'integer',
    ];

    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'don_hang_id');
    }

    public function lichSuGui()
    {
        return $this->hasMany(LichSuGuiWebhook::class, 'webhook_outbox_id');
    }
}
