<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserAccountRequest extends FormRequest
{
    /**
     * Request nay chi validate du lieu sua thong tin co ban.
     * Quyen truy cap module duoc bao ve bang middleware route.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Rule unique bo qua chinh user dang sua de tranh bao trung du lieu cua no.
     * Route model binding truyen user vao tham so account.
     */
    public function rules(): array
    {
        $userId = optional($this->route('account'))->id;

        return [
            'ten_dang_nhap' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\.]+$/', Rule::unique('users', 'ten_dang_nhap')->ignore($userId)],
            'ho' => ['nullable', 'string', 'max:100'],
            'ten' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            // NOTE: Validate so dien thoai o backend cho flow sua tai khoan.
            // Rule nay dong bo voi StoreUserAccountRequest de khong cho luu chu cai nhu hjbdgfgd.
            'so_dien_thoai' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'loai_tai_khoan' => ['required', Rule::in(array_keys($this->accountTypes()))],
            'trang_thai' => ['required', Rule::in(array_keys($this->statuses()))],
            'bi_khoa' => ['nullable', 'boolean'],
            // NOTE: Mat khau trong form sua la tuy chon; de trong thi controller khong doi mat khau.
            // Neu co nhap thi validate min 8 va confirmed truoc khi luu.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
            'vai_tro' => ['nullable', 'array'],
            'vai_tro.*' => ['integer', 'exists:vai_tro,id'],
        ];
    }

    /**
     * Danh sach loai tai khoan dung chung voi form tao tai khoan.
     */
    public function accountTypes(): array
    {
        return [
            'admin' => 'Quản trị viên',
            'ke_toan' => 'Kế toán',
            'doi_soat' => 'Đối soát',
            'sale' => 'Sale',
            'dai_ly' => 'Đại lý',
            'dai_ly_api' => 'Đại lý API',
            'customer' => 'Khách hàng',
        ];
    }

    /**
     * Trang thai tai khoan dung de loc, sua va xoa mem.
     */
    public function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_khoa' => 'Tạm khóa',
            'cho_duyet' => 'Chờ duyệt',
            'huy' => 'Hủy',
            'da_xoa' => 'Đã xóa',
        ];
    }

    public function messages(): array
    {
        return [
            'so_dien_thoai.regex' => 'Số điện thoại phải bắt đầu bằng 0 và gồm đúng 10 chữ số.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ];
    }
}
