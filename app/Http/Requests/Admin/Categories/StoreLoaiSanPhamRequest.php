<?php

namespace App\Http\Requests\Admin\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoaiSanPhamRequest extends FormRequest
{
    /**
     * Request nay chi dung cho man tao loai san pham trong module quan tri.
     * Phan middleware o route da chan user khong co quyen truoc khi vao day.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Rule bam theo dung schema bang loai_san_pham hien tai (xem migration create_loai_san_pham_table).
     * Khi doi ten cot trong loai_san_pham, bao tri rule validate tai day.
     */
    public function rules(): array
    {
        return [
            // NOTE: ma_loai_san_pham la ma dinh danh dung trong logic he thong (vd VIETTEL, MOBIFONE)
            // nen chi cho chu hoa, so va gach duoi, tranh nham voi ten hien thi.
            'ma_loai_san_pham' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', 'unique:loai_san_pham,ma_loai_san_pham'],
            'ten_loai_san_pham' => ['required', 'string', 'max:150'],
            // NOTE: dich_vu_id de trong neu day la nhom goc theo nha mang (vd Viettel, Vinaphone)
            // dung chung cho nhieu dich vu khac nhau (TOPUP, PIN_CODE...), khong thuoc rieng 1 dich vu.
            // Cot da duoc doi sang nullable trong migration make_dich_vu_id_nullable_in_loai_san_pham_table.
            'dich_vu_id' => ['nullable', 'integer', 'exists:dich_vu,id'],
            // NOTE: loai_san_pham_cha_id de trong neu day la nhom goc, dung khi can lam cay danh muc cha/con.
            'loai_san_pham_cha_id' => ['nullable', 'integer', 'exists:loai_san_pham,id'],
            'trang_thai' => ['required', Rule::in(array_keys($this->statuses()))],
            'thu_tu' => ['nullable', 'integer', 'min:0'],
            // NOTE: hinh_anh hien luu duong dan/URL dang text vi du an chua co ha tang upload file.
            'hinh_anh' => ['nullable', 'string', 'max:255'],
            'mo_ta' => ['nullable', 'string'],
        ];
    }

    /**
     * Danh sach trang thai loai san pham — dung chung cho filter, form va export.
     */
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
        ];
    }
}
