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
        'danh_sach_kenh',
        'cau_hinh_kenh_su_kien',
        'bat_thong_bao_don_hang',
        'bat_canh_bao_loi',
        'bat_canh_bao_xu_ly_cham',
        'bat_canh_bao_circuit_breaker',
        'bat_canh_bao_so_du_thap',
        'bat_canh_bao_manual_review',
        'bat_thong_bao_hoan_tien',
    ];

    protected $casts = [
        'danh_sach_kenh' => 'array',
        'cau_hinh_kenh_su_kien' => 'array',
        'bat_thong_bao_don_hang' => 'boolean',
        'bat_canh_bao_loi' => 'boolean',
        'bat_canh_bao_xu_ly_cham' => 'boolean',
        'bat_canh_bao_circuit_breaker' => 'boolean',
        'bat_canh_bao_so_du_thap' => 'boolean',
        'bat_canh_bao_manual_review' => 'boolean',
        'bat_thong_bao_hoan_tien' => 'boolean',
    ];

    /**
     * Lấy danh sách các kênh Telegram đang hoạt động (kèm các kênh mặc định).
     * @return array<int, array{id: string, ten_kenh: string, chat_id: string, ghi_chu?: string}>
     */
    public function layDanhSachKenhHopLe(): array
    {
        $customChannels = is_array($this->danh_sach_kenh) ? $this->danh_sach_kenh : [];
        if (!empty($customChannels)) {
            return $customChannels;
        }

        // Tự động khởi tạo danh sách mặc định nếu chưa có
        $channels = [];
        if (!empty($this->chat_id_alert)) {
            $channels[] = [
                'id' => 'alert_default',
                'ten_kenh' => 'Nhóm Cảnh báo Lỗi & Kỹ thuật',
                'chat_id' => $this->chat_id_alert,
                'ghi_chu' => 'Kênh kỹ thuật mặc định',
            ];
        }
        if (!empty($this->chat_id_order) && $this->chat_id_order !== $this->chat_id_alert) {
            $channels[] = [
                'id' => 'order_default',
                'ten_kenh' => 'Nhóm Đơn hàng Thành công',
                'chat_id' => $this->chat_id_order,
                'ghi_chu' => 'Kênh kinh doanh / đơn hàng',
            ];
        }
        if (!empty($this->chat_id_admin) && $this->chat_id_admin !== $this->chat_id_alert && $this->chat_id_admin !== $this->chat_id_order) {
            $channels[] = [
                'id' => 'admin_default',
                'ten_kenh' => 'Nhóm Quản trị & Hoàn tiền',
                'chat_id' => $this->chat_id_admin,
                'ghi_chu' => 'Kênh kế toán / admin',
            ];
        }

        return $channels;
    }

    /**
     * Lấy Chat ID được chỉ định cho một loại sự kiện cảnh báo cụ thể.
     */
    public function layChatIdChoSuKien(string $suKien): ?string
    {
        $eventMap = is_array($this->cau_hinh_kenh_su_kien) ? $this->cau_hinh_kenh_su_kien : [];
        if (!empty($eventMap[$suKien])) {
            $val = trim((string) $eventMap[$suKien]);
            if (!empty($val)) {
                return $val;
            }
        }

        // Fallback theo nhóm truyền thống
        return match ($suKien) {
            'order_success' => $this->chat_id_order ?: ($this->chat_id_alert ?: config('services.telegram.channel_order')),
            'manual_review', 'order_refunded' => $this->chat_id_admin ?: ($this->chat_id_alert ?: config('services.telegram.channel_admin')),
            default => $this->chat_id_alert ?: config('services.telegram.channel_alert'),
        };
    }

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


