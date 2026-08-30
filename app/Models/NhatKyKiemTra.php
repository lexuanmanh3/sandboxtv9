<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NhatKyKiemTra extends Model
{
    protected $table = 'nhat_ky_kiem_tra';

    protected $guarded = [];

    protected $casts = [
        'du_lieu_truoc' => 'array',
        'du_lieu_sau' => 'array',
        'tham_so' => 'array',
        'thoi_gian_thuc_thi_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function nguoiThucHien(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_thuc_hien_id');
    }
}
