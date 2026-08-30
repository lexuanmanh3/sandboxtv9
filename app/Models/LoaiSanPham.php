<?php

namespace App\Models;

// NOTE: HasFactory dùng để hỗ trợ tạo dữ liệu test/factory sau này.
// Model giúp class LoaiSanPham đại diện cho bảng loai_san_pham.
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// NOTE: BelongsTo dùng cho quan hệ thuộc về.
// Ví dụ loại sản phẩm thuộc về 1 dịch vụ.
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// NOTE: HasMany dùng cho quan hệ 1-nhiều.
// Ví dụ loại sản phẩm có nhiều sản phẩm con.
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoaiSanPham extends Model
{
    use HasFactory;

    // NOTE: Khai báo tên bảng thật trong database.
    // Nếu không khai báo, Laravel có thể đoán sai tên bảng.
    protected $table = 'loai_san_pham';

    // NOTE: Các cột được phép thêm/sửa bằng create() hoặc update().
    // Sau này làm form thêm/sửa loại sản phẩm thì các field này được ghi vào database.
    protected $fillable = [
        'loai_san_pham_cha_id',
        'dich_vu_id',
        'ma_loai_san_pham',
        'ten_loai_san_pham',
        'thu_tu',
        'trang_thai',
        'hinh_anh',
        'mo_ta',
    ];

    public function getHinhAnhUrlAttribute(): ?string
    {
        if (! empty($this->hinh_anh)) {
            if (str_starts_with($this->hinh_anh, 'http://') || str_starts_with($this->hinh_anh, 'https://') || str_starts_with($this->hinh_anh, 'data:')) {
                return $this->hinh_anh;
            }
            return asset($this->hinh_anh);
        }

        return null;
    }

    // NOTE: Loại sản phẩm này thuộc về dịch vụ nào.
    // Ví dụ Viettel thuộc dịch vụ PIN_CODE.
    public function dichVu(): BelongsTo
    {
        return $this->belongsTo(
            DichVu::class,
            'dich_vu_id'
        );
    }

    // NOTE: Lấy loại sản phẩm cha.
    // Dùng khi muốn làm cây danh mục cha/con.
    public function loaiCha(): BelongsTo
    {
        return $this->belongsTo(
            LoaiSanPham::class,
            'loai_san_pham_cha_id'
        );
    }

    // NOTE: Lấy danh sách loại sản phẩm con.
    // Ví dụ nhóm cha "Thẻ điện thoại" có con Viettel, Mobifone, Vinaphone.
    public function loaiCon(): HasMany
    {
        return $this->hasMany(
            LoaiSanPham::class,
            'loai_san_pham_cha_id'
        );
    }

    // NOTE: Lấy danh sách sản phẩm thuộc loại sản phẩm này.
    // Ví dụ loại Viettel có sản phẩm Viettel 10k, 20k, 50k.
    public function sanPham(): HasMany
    {
        return $this->hasMany(
            SanPham::class,
            'loai_san_pham_id'
        );
    }
        /**
     * Một loại sản phẩm có thể có nhiều cấu hình NCC.
     * Ví dụ loại Viettel có thể cấu hình chạy qua AppotaPay.
     */
    public function cauHinhDichVu()
    {
        return $this->hasMany(CauHinhDichVu::class, 'loai_san_pham_id');
    }
    /**
 * Loại sản phẩm này được cấp cho những API Partner nào.
 */
public function daiLyApiDuocPhep()
{
    return $this->hasMany(DaiLyApiLoaiSanPhamDuocPhep::class, 'loai_san_pham_id');
}

/**
 * Lấy danh sách API Partner được phép dùng loại sản phẩm này.
 */
public function daiLyApi()
{
    return $this->belongsToMany(
        DaiLyApi::class,
        'dai_ly_api_loai_san_pham_duoc_phep',
        'loai_san_pham_id',
        'dai_ly_api_id'
    )->withTimestamps();
}
}