<?php

namespace App\Models;

// NOTE: HasFactory dùng để hỗ trợ tạo dữ liệu test/factory sau này.
// Model là class gốc của Eloquent, giúp Quyen đại diện cho bảng quyen.
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// NOTE: VaiTro model dùng để khai báo quan hệ quyền thuộc nhiều vai trò.
use App\Models\VaiTro;

class Quyen extends Model
{
    use HasFactory;

    // NOTE: Khai báo tên bảng thật trong database.
    // Nếu không khai báo, Laravel có thể đoán sai tên bảng.
    protected $table = 'quyen';

    // NOTE: Các cột được phép thêm/sửa bằng create() hoặc update().
    // Sau này làm màn quản lý quyền thì Laravel chỉ cho ghi các field này.
    protected $fillable = [
        'quyen_cha_id',
        'ma_quyen',
        'ten_quyen',
        'nhom_quyen',
        'thu_tu',
        'trang_thai',
    ];

    // NOTE: Lấy quyền cha của quyền hiện tại.
    // Ví dụ quyền user.view có thể thuộc nhóm cha user.
    public function quyenCha()
    {
        return $this->belongsTo(Quyen::class, 'quyen_cha_id');
    }

    // NOTE: Lấy danh sách quyền con của quyền hiện tại.
    // Dùng khi sau này hiển thị quyền dạng cây cha/con.
    public function quyenCon()
    {
        return $this->hasMany(Quyen::class, 'quyen_cha_id');
    }

    // NOTE: Lấy danh sách vai trò đang có quyền này.
    // Quan hệ đi qua bảng trung gian vai_tro_quyen.
    public function vaiTro()
    {
        return $this->belongsToMany(
            VaiTro::class,
            'vai_tro_quyen',
            'quyen_id',
            'vai_tro_id'
        );
    }
}
