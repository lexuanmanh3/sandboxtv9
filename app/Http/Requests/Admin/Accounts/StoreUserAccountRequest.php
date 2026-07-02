<?php

namespace App\Http\Requests\Admin\Accounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserAccountRequest extends FormRequest
{
    /**
     * Request nay chi dung cho man tao tai khoan trong module quan tri.
     * Phan middleware o route da chan user khong co quyen truoc khi vao day.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Cac rule nay bam theo schema bang users hien tai.
     * Khi doi ten cot trong users, bao tri rule validate tai day.
     */
    public function rules(): array
    {
        return [
            'ten_dang_nhap' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\.]+$/', 'unique:users,ten_dang_nhap'],
            'ho' => ['nullable', 'string', 'max:100'],
            'ten' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'],
            // NOTE: Validate so dien thoai o backend de chan du lieu sai khi submit truc tiep, khong phu thuoc HTML/JS.
            // Neu sau nay doi dinh dang so dien thoai, bao tri regex tai rule nay va UpdateUserAccountRequest.
            'so_dien_thoai' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'loai_tai_khoan' => ['required', Rule::in(array_keys($this->accountTypes()))],
            'trang_thai' => ['required', Rule::in(array_keys($this->statuses()))],
            'tao_mat_khau_ngau_nhien' => ['nullable', 'boolean'],
            // NOTE: Mat khau tao moi toi thieu 8 ky tu; controller se chi lay du lieu da validated de luu.
            // Khi checkbox tao mat khau ngau nhien duoc tick, password duoc phep rong.
            'password' => [Rule::requiredIf(! $this->boolean('tao_mat_khau_ngau_nhien')), 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['nullable', 'string'],
            'vai_tro' => ['nullable', 'array'],
            'vai_tro.*' => ['integer', 'exists:vai_tro,id'],
        ];
    }

    /**
     * Danh sach loai tai khoan lay theo comment/schema users hien co.
     * Neu sau nay them loai moi thi cap nhat mot noi tai day va controller.
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
     * Trang thai tai khoan dung cac gia tri dang ton tai trong migration users.
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
        ];
    }
}
