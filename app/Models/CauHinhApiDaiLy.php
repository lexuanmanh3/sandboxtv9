<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CauHinhApiDaiLy extends Model
{
    use HasFactory;

    /**
     * Model này đại diện cho bảng cau_hinh_api_dai_ly.
     * Dùng để lưu client_id, secret, whitelist IP và giới hạn API của partner.
     */
    protected $table = 'cau_hinh_api_dai_ly';

    /**
     * Các field được phép lưu qua Eloquent.
     */
    protected $fillable = [
        'dai_ly_api_id',
        'client_id',
        'secret_key_ma_hoa',
        'partner_public_key_file',
        'private_key_file_ma_hoa',
        'allowed_grant_types',
        'allowed_scopes',
        'allow_offline_access',
        'require_consent',
        'su_dung_chu_ky_dien_tu',
        'danh_sach_ip_ket_noi',
        'so_luong_kenh_toi_da',
        'han_muc_mua_the',
        'ap_dung_nap_cham',
        'check_phone_khi_nap_cham',
        'han_muc_cong_no',
        'cong_no_hien_tai',
        'nguong_canh_bao_han_muc',
        'cho_phep_nhan_don',
        'san_pham_loai_tru',
        'webhook_url',
        'webhook_secret_ma_hoa',
        'rate_limit_per_minute',
        'key_status',
        'previous_secret_ma_hoa',
        'key_rotated_at',
    ];

    /**
     * Các trường bảo mật cần ẩn khi serialize sang JSON/Array để tránh lộ secret và tránh DecryptException khi MAC cũ.
     */
    protected $hidden = [
        'secret_key_ma_hoa',
        'private_key_file_ma_hoa',
        'previous_secret_ma_hoa',
        'webhook_secret_ma_hoa',
    ];

    /**
     * Cast giúp Laravel tự mã hóa field nhạy cảm và tự chuyển boolean/decimal đúng kiểu.
     * Khi bảo trì, các field secret/private key nên thêm vào encrypted cast tại đây.
     */
    protected $casts = [
        'secret_key_ma_hoa' => 'encrypted',
        'private_key_file_ma_hoa' => 'encrypted',
        'previous_secret_ma_hoa' => 'encrypted',
        'webhook_secret_ma_hoa' => 'encrypted',
        'allow_offline_access' => 'boolean',
        'require_consent' => 'boolean',
        'su_dung_chu_ky_dien_tu' => 'boolean',
        'ap_dung_nap_cham' => 'boolean',
        'check_phone_khi_nap_cham' => 'boolean',
        'cho_phep_nhan_don' => 'boolean',
        'san_pham_loai_tru' => 'array',
        'han_muc_mua_the' => 'decimal:2',
        'han_muc_cong_no' => 'decimal:2',
        'cong_no_hien_tai' => 'decimal:2',
        'nguong_canh_bao_han_muc' => 'decimal:2',
        'rate_limit_per_minute' => 'integer',
        'key_rotated_at' => 'datetime',
    ];

    /**
     * Mỗi cấu hình API thuộc về một API Partner.
     */
    public function daiLyApi()
    {
        return $this->belongsTo(DaiLyApi::class, 'dai_ly_api_id');
    }
}