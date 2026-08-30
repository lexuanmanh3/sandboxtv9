<?php

namespace App\Http\Requests\Admin\Providers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // Thông tin chung
            'ma_ncc' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/', 'unique:nha_cung_cap,ma_ncc'],
            'ten_ncc' => ['required', 'string', 'max:150'],
            'nha_cung_cap_cha_id' => ['nullable', 'integer', 'exists:nha_cung_cap,id'],
            'so_dien_thoai' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'trang_thai' => ['required', Rule::in(['hoat_dong', 'tam_dung', 'khoa'])],

            // Thông tin kết nối
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string'],
            'api_user' => ['nullable', 'string', 'max:100'],
            'api_password' => ['nullable', 'string'],
            'api_url' => ['nullable', 'string', 'max:500'],
            'cau_hinh_ma_gd' => ['nullable', 'string', 'max:100'],
            'public_key' => ['nullable', 'string'],
            'public_key_file' => ['nullable', 'string'],
            'private_key_file' => ['nullable', 'string'],
            'timeout_he_thong' => ['nullable', 'integer', 'min:1', 'max:300'],
            'timeout_ncc' => ['nullable', 'integer', 'min:1', 'max:300'],

            // Cấu hình cảnh báo số dư
            'so_du_canh_bao' => ['nullable', 'numeric', 'min:0'],
            'so_du_toi_thieu_nap' => ['nullable', 'numeric', 'min:0'],
            'so_tien_nap_moi_lan' => ['nullable', 'numeric', 'min:0'],
            'chay_nhieu_tk_con' => ['nullable', 'boolean'],
            'tu_dong_nap_tien' => ['nullable', 'boolean'],
            'kich_hoat_nap_cham' => ['nullable', 'boolean'],

            // Cấu hình đóng tự động (Circuit Breaker)
            'so_gd_that_bai_lien_tiep' => ['nullable', 'integer', 'min:0'],
            'thoi_gian_dong' => ['nullable', 'integer', 'min:0'],
            'ma_loi_bo_qua' => ['nullable', 'string', 'max:255'],
            'tinh_gd_loi' => ['nullable', 'boolean'],
            'so_gd_nghi_ngo' => ['nullable', 'integer', 'min:0'],
            'thoi_gian_quet' => ['nullable', 'integer', 'min:0'],
            'tong_so_gd_quet' => ['nullable', 'integer', 'min:0'],
            'so_gd_loi_toi_da' => ['nullable', 'integer', 'min:0'],

            // Cấu hình cảnh báo lỗi & SLA xử lý đơn treo
            'bat_canh_bao' => ['nullable', 'boolean'],
            'canh_bao_xu_ly_cham_giay' => ['nullable', 'integer', 'min:0'],
            'kenh_canh_bao' => ['nullable', 'string', 'max:50'],
            'nhom_canh_bao_chat_id' => ['nullable', 'string', 'max:100'],
            'bo_qua_ma_loi_ncc' => ['nullable', 'string', 'max:255'],
            'bo_qua_message_ncc' => ['nullable', 'string', 'max:255'],
            'so_lan_kiem_tra_lai' => ['nullable', 'integer', 'min:1', 'max:20'],
            'hanh_dong_khi_het_gio' => ['nullable', 'string', Rule::in(['TU_DONG_HOAN_TIEN', 'MANUAL_REVIEW'])],
        ];
    }

    public function messages(): array
    {
        return [
            'ma_ncc.required' => 'Mã nhà cung cấp không được để trống.',
            'ma_ncc.unique' => 'Mã nhà cung cấp đã tồn tại trong hệ thống.',
            'ma_ncc.regex' => 'Mã nhà cung cấp chỉ được chứa chữ cái, số và dấu gạch dưới.',
            'ten_ncc.required' => 'Tên nhà cung cấp không được để trống.',
            'api_url.url' => 'ApiUrl phải là định dạng URL hợp lệ (ví dụ: http://...).',
        ];
    }
}
