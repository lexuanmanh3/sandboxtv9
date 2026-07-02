<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPasswordRequest extends FormRequest
{
    /**
     * Request nay dung rieng cho thao tac doi mat khau tai khoan tu admin.
     * Viec kiem tra admin/quyen nam o route va controller.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Mat khau moi toi thieu 8 ky tu de dong bo voi chinh sach tao/sua tai khoan.
     * Field bat_buoc_doi_mat_khau chi luu neu bang users co cot nay.
     */
    public function rules(): array
    {
        return [
            // NOTE: Modal doi mat khau bat buoc nhap password moi, khong chap nhan de trong.
            // Controller se lay password da validated roi hash truoc khi luu.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'bat_buoc_doi_mat_khau' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ];
    }
}
