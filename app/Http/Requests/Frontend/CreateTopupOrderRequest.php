<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class CreateTopupOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'phone'            => ['required', 'string', 'regex:/^(0|\+84)[3-9][0-9]{8}$/'],
            'carrier'          => ['required', 'string', 'max:30'],
            'product_id'       => ['required', 'integer', 'min:1', 'exists:san_pham,id'],
            // idempotency_key do client tạo — server sẽ validate định dạng
            'idempotency_key'  => ['required', 'string', 'min:8', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required'           => 'Vui lòng nhập số điện thoại.',
            'phone.regex'              => 'Số điện thoại không hợp lệ.',
            'carrier.required'         => 'Vui lòng chọn nhà mạng.',
            'product_id.required'      => 'Vui lòng chọn mệnh giá.',
            'product_id.exists'        => 'Mệnh giá không tồn tại trong hệ thống.',
            'idempotency_key.required' => 'Thiếu mã định danh yêu cầu.',
        ];
    }

    /**
     * Chuẩn hóa số điện thoại trước khi validate.
     * +84xxxxxxxxx → 0xxxxxxxxx
     */
    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/[\s\.\-]/', '', (string) $this->phone);
        if (str_starts_with($phone, '+84')) {
            $phone = '0' . substr($phone, 3);
        }
        $this->merge(['phone' => $phone]);
    }
}
