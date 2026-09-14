<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LichSuGuiWebhook extends Model
{
    use HasFactory;

    protected $table = 'lich_su_gui_webhook';

    public $timestamps = false;

    protected $fillable = [
        'webhook_outbox_id',
        'url',
        'http_status',
        'request_headers_json',
        'request_body',
        'response_body',
        'thoi_gian_ms',
        'loi',
        'created_at',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'thoi_gian_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function outbox()
    {
        return $this->belongsTo(WebhookOutbox::class, 'webhook_outbox_id');
    }
}
