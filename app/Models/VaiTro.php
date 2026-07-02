<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Quyen;

class VaiTro extends Model
{
    use HasFactory;

    protected $table = 'vai_tro';

    protected $fillable = [
        'ma_vai_tro',
        'ten_vai_tro',
        'mo_ta',
        'mac_dinh',
        'trang_thai',
    ];

    protected $casts = [
        'mac_dinh' => 'boolean',
    ];

    // Các người dùng đang có vai trò này
public function nguoiDung()
{
    return $this->belongsToMany(
        User::class,
        'nguoi_dung_vai_tro',
        'vai_tro_id',
        'nguoi_dung_id'
    );
}

// Các quyền thuộc vai trò này
public function quyen()
{
    return $this->belongsToMany(
        Quyen::class,
        'vai_tro_quyen',
        'vai_tro_id',
        'quyen_id'
    );
}

    /**
     * Vai trò mặc định dùng để gán cho tài khoản mới khi không chọn vai trò nào
     * (customer tự đăng ký, hoặc admin tạo user không tick vai trò).
     *
     * Ưu tiên vai trò đang hoạt động được đánh dấu mac_dinh=true qua màn quản lý vai trò;
     * nếu chưa vai trò nào được đánh dấu, fallback về ma_vai_tro='user' để hệ thống
     * không bao giờ tạo ra tài khoản không có vai trò nào.
     */
    public static function vaiTroMacDinh(): self
    {
        $macDinh = static::where('mac_dinh', true)
            ->where('trang_thai', 'hoat_dong')
            ->first();

        if ($macDinh) {
            return $macDinh;
        }

        return static::firstOrCreate(
            ['ma_vai_tro' => 'user'],
            [
                'ten_vai_tro' => 'Người dùng',
                'mac_dinh' => true,
                'trang_thai' => 'hoat_dong',
            ]
        );
    }
}
