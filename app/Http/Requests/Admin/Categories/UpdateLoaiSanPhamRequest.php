<?php

namespace App\Http\Requests\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoaiSanPhamRequest extends FormRequest
{
    /**
     * Request nay chi validate du lieu sua thong tin loai san pham.
     * Quyen truy cap module duoc bao ve bang middleware route.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Rule unique bo qua chinh loai san pham dang sua de tranh bao trung du lieu cua no.
     * Route model binding truyen loai san pham vao tham so category.
     */
    public function rules(): array
    {
        $categoryId = optional($this->route('category'))->id;

        return [
            'ma_loai_san_pham' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', Rule::unique('loai_san_pham', 'ma_loai_san_pham')->ignore($categoryId)],
            'ten_loai_san_pham' => ['required', 'string', 'max:150'],
            // NOTE: dich_vu_id de trong neu day la nhom goc theo nha mang, xem giai thich
            // chi tiet trong StoreLoaiSanPhamRequest va migration make_dich_vu_id_nullable_in_loai_san_pham_table.
            'dich_vu_id' => ['nullable', 'integer', 'exists:dich_vu,id'],
            // NOTE: Khong cho chon chinh no lam loai cha (Rule::notIn) de tranh vong lap cha/con 1 cap.
            // Vong lap nhieu cap sau nay neu can se xu ly rieng khi thuc su dung den cay danh muc.
            'loai_san_pham_cha_id' => ['nullable', 'integer', 'exists:loai_san_pham,id', Rule::notIn([$categoryId])],
            'trang_thai' => ['required', Rule::in(array_keys($this->statuses()))],
            'thu_tu' => ['nullable', 'integer', 'min:0'],
            'hinh_anh' => ['nullable', 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string'],
        ];
    }

    /**
     * Danh sach trang thai — dong bo voi convention "hoat_dong, tam_dung, khoa" da dung
     * o nha_cung_cap va dai_ly_api (xem migration create_nha_cung_cap_table/create_dai_ly_api_table).
     */
    public function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_dung' => 'Tạm dừng',
            'khoa' => 'Khóa',
        ];
    }

    public function messages(): array
    {
        return [
            'ma_loai_san_pham.regex' => 'Mã loại sản phẩm chỉ được dùng chữ in hoa, số và dấu gạch dưới (ví dụ: VIETTEL, MOBIFONE).',
            'ma_loai_san_pham.unique' => 'Mã loại sản phẩm đã tồn tại, vui lòng chọn mã khác.',
            'dich_vu_id.exists' => 'Dịch vụ đã chọn không tồn tại.',
            'loai_san_pham_cha_id.exists' => 'Loại sản phẩm cha đã chọn không tồn tại.',
            'loai_san_pham_cha_id.not_in' => 'Không thể chọn chính loại sản phẩm này làm loại cha.',
        ];
    }
}
