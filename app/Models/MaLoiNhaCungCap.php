<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaLoiNhaCungCap extends Model
{
    use HasFactory;

    protected $table = 'ma_loi_nha_cung_cap';

    protected $fillable = [
        'nha_cung_cap_id',
        'ma_loi',
        'loai_ket_qua',
        'thong_bao_goc',
        'thong_bao_hien_thi',
        'hanh_dong_he_thong',
        'mo_ta',
        'trang_thai',
    ];

    public const RESULT_SUCCESS = 'SUCCESS';
    public const RESULT_UNKNOWN_OR_PENDING = 'UNKNOWN_OR_PENDING';
    public const RESULT_DEFINITIVE_FAILURE = 'DEFINITIVE_FAILURE';

    public const ACTION_RETRY_STATUS = 'RETRY_STATUS';
    public const ACTION_REFUND_WALLET = 'REFUND_WALLET';
    public const ACTION_MANUAL_REVIEW = 'MANUAL_REVIEW';
    public const ACTION_NONE = 'NONE';

    public function nhaCungCap(): BelongsTo
    {
        return $this->belongsTo(NhaCungCap::class, 'nha_cung_cap_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('trang_thai', ['hoat_dong', 'ACTIVE']);
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (empty($keyword)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('ma_loi', 'like', "%{$keyword}%")
              ->orWhere('thong_bao_goc', 'like', "%{$keyword}%")
              ->orWhere('thong_bao_hien_thi', 'like', "%{$keyword}%")
              ->orWhere('mo_ta', 'like', "%{$keyword}%");
        });
    }

    public function scopeForProvider(Builder $query, ?int $providerId): Builder
    {
        return $query->where(function (Builder $q) use ($providerId) {
            if ($providerId) {
                $q->where('nha_cung_cap_id', $providerId)
                  ->orWhereNull('nha_cung_cap_id');
            }
        });
    }
}
