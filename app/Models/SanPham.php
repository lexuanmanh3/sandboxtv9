<?php

namespace App\Models;

// NOTE: HasFactory dùng để hỗ trợ tạo dữ liệu test/factory sau này.
// Model giúp SanPham đại diện cho bảng san_pham trong database.
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// NOTE: BelongsTo dùng cho quan hệ sản phẩm thuộc về dịch vụ và loại sản phẩm.
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanPham extends Model
{
    use HasFactory;

    // NOTE: Khai báo tên bảng thật trong database.
    // Nếu thiếu dòng này, Laravel sẽ tìm bảng san_phams và báo lỗi không tồn tại.
    protected $table = 'san_pham';

    // NOTE: Các cột được phép thêm/sửa bằng create(), updateOrCreate().
    // Nếu thiếu field nào, Laravel sẽ báo lỗi MassAssignmentException.
    protected $fillable = [
        'dich_vu_id',
        'loai_san_pham_id',
        'ma_san_pham',
        'ten_san_pham',
        'menh_gia',
        'gia_ban',
        'chiet_khau_phan_tram',
        'don_vi',
        'thu_tu',
        'trang_thai',
        'hinh_anh',
        'mo_ta',
        'ho_tro_khach_hang',
        'huong_dan_su_dung',
        'so_tien_toi_thieu',
        'so_tien_toi_da',
        'hien_thi_web_app',
    ];

    // NOTE: Ép kiểu dữ liệu cho các cột tiền và boolean.
    // Giúp Laravel đọc dữ liệu đúng kiểu khi lấy từ database.
    protected $casts = [
        'menh_gia' => 'decimal:2',
        'gia_ban' => 'decimal:2',
        'chiet_khau_phan_tram' => 'decimal:2',
        'so_tien_toi_thieu' => 'decimal:2',
        'so_tien_toi_da' => 'decimal:2',
        'hien_thi_web_app' => 'boolean',
    ];

    // NOTE: Lấy dịch vụ của sản phẩm hiện tại.
    // Ví dụ Viettel 20k thuộc dịch vụ PIN_CODE.
    public function dichVu(): BelongsTo
    {
        return $this->belongsTo(
            DichVu::class,
            'dich_vu_id'
        );
    }

    // NOTE: Lấy loại sản phẩm của sản phẩm hiện tại.
    // Ví dụ Viettel 20k thuộc loại sản phẩm Viettel.
    public function loaiSanPham(): BelongsTo
    {
        return $this->belongsTo(
            LoaiSanPham::class,
            'loai_san_pham_id'
        );
    }

    /**
     * Một sản phẩm cụ thể có thể có nhiều cấu hình NCC.
     * Ví dụ Viettel 20k có thể có NCC chính và NCC dự phòng.
     */
    public function cauHinhDichVu()
    {
        return $this->hasMany(CauHinhDichVu::class, 'san_pham_id');
    }

    public function nhaCungCapMappings()
    {
        return $this->hasMany(SanPhamNhaCungCap::class, 'san_pham_id');
    }

    /**
     * Các dòng cấp quyền API Partner cho sản phẩm này.
     */
    public function daiLyApiDuocPhep()
    {
        return $this->hasMany(DaiLyApiSanPhamDuocPhep::class, 'san_pham_id');
    }

    /**
     * Lấy danh sách API Partner được phép dùng sản phẩm này.
     *
     * Ví dụ:
     * $sanPham->daiLyApi()->get();
     */
    public function daiLyApi()
    {
        return $this->belongsToMany(
            DaiLyApi::class,
            'dai_ly_api_san_pham_duoc_phep',
            'san_pham_id',
            'dai_ly_api_id'
        )->withTimestamps();
    }
}
