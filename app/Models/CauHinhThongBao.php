<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CauHinhThongBao extends Model
{
    use HasFactory;

    protected $table = 'cau_hinh_thong_bao';

    protected $fillable = [
        'loai',
        'bot_token',
        'chat_id_alert',
        'chat_id_order',
        'chat_id_admin',
        'bat_thong_bao_don_hang',
        'bat_canh_bao_loi',
        'bat_canh_bao_xu_ly_cham',
        'bat_canh_bao_circuit_breaker',
        'bat_canh_bao_so_du_thap',
        'bat_canh_bao_manual_review',
        'bat_thong_bao_hoan_tien',
    ];

    protected $casts = [
        'bat_thong_bao_don_hang' => 'boolean',
        'bat_canh_bao_loi' => 'boolean',
        'bat_canh_bao_xu_ly_cham' => 'boolean',
        'bat_canh_bao_circuit_breaker' => 'boolean',
        'bat_canh_bao_so_du_thap' => 'boolean',
        'bat_canh_bao_manual_review' => 'boolean',
        'bat_thong_bao_hoan_tien' => 'boolean',
    ];

    /**
     * Lấy cấu hình Telegram (từ DB hoặc fallback sang file .env).
     */
    public static function layCauHinhTelegram(): self
    {
        return Cache::remember('cau_hinh_thong_bao_telegram', 3600, function () {
            $config = self::where('loai', 'telegram')->first();
            if (!$config) {
                // Tạo bản ghi mặc định lấy từ .env
                $config = self::create([
                    'loai' => 'telegram',
                    'bot_token' => env('TELEGRAM_ALERT_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN')),
                    'chat_id_alert' => env('TELEGRAM_ALERT_CHAT_ID'),
                    'chat_id_order' => env('TELEGRAM_ORDER_CHAT_ID', env('TELEGRAM_ALERT_CHAT_ID')),
                    'chat_id_admin' => env('TELEGRAM_ADMIN_CHAT_ID', env('TELEGRAM_ALERT_CHAT_ID')),
                    'bat_thong_bao_don_hang' => true,
                    'bat_canh_bao_loi' => true,
                    'bat_canh_bao_xu_ly_cham' => true,
                    'bat_canh_bao_circuit_breaker' => true,
                    'bat_canh_bao_so_du_thap' => true,
                    'bat_canh_bao_manual_review' => true,
                    'bat_thong_bao_hoan_tien' => true,
                ]);
            }
            return $config;
        });
    }

    /**
     * Xóa cache khi cập nhật cấu hình.
     */
    public static function xoaCache(): void
    {
        Cache::forget('cau_hinh_thong_bao_telegram');
    }
}

