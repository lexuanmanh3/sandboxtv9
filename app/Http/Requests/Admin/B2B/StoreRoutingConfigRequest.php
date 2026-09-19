<?php

namespace App\Http\Requests\Admin\B2B;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoutingConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dai_ly_api_id' => 'nullable|exists:dai_ly_api,id',
            'dich_vu_id' => 'required|exists:dich_vu,id',
            'loai_san_pham_id' => 'nullable|exists:loai_san_pham,id',
            'san_pham_id' => 'nullable|exists:san_pham,id',
            'san_pham_ids' => 'nullable|array',
            'san_pham_ids.*' => 'nullable|integer|exists:san_pham,id',
            'nha_cung_cap_id' => 'required|exists:nha_cung_cap,id',
            'muc_uu_tien' => 'required|integer|min:0',
            'che_do_chay' => 'nullable|string|in:api,kho_the,thu_cong',
            'ket_thuc_cau_hinh' => 'nullable',
            'dang_mo' => 'nullable',
            'trang_thai' => 'nullable|string',
            'mo_ta' => 'nullable|string|max:500',
            'ten_cau_hinh' => 'nullable|string|max:255',
            'timeout_he_thong_giay' => 'nullable|integer|min:1',
            'timeout_gui_ncc_giay' => 'nullable|integer|min:1',
            'thoi_gian_tra_ket_qua_giay' => 'nullable|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'dich_vu_id.required' => 'Vui lòng chọn dịch vụ.',
            'dich_vu_id.exists' => 'Dịch vụ đã chọn không hợp lệ.',
            'nha_cung_cap_id.required' => 'Vui lòng chọn nhà cung cấp.',
            'nha_cung_cap_id.exists' => 'Nhà cung cấp đã chọn không hợp lệ.',
            'muc_uu_tien.required' => 'Vui lòng nhập mức độ ưu tiên.',
            'muc_uu_tien.integer' => 'Mức ưu tiên phải là số nguyên.',
            'muc_uu_tien.min' => 'Mức ưu tiên tối thiểu là 0.',
        ];
    }
}
