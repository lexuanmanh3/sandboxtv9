<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

class PreviewTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'phone'      => ['required', 'string', 'regex:/^(0|\+84)[3-9][0-9]{8}$/'],
            'carrier'    => ['required', 'string', 'max:30'],
            'product_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required'      => 'Vui lòng nhập số điện thoại.',
            'phone.regex'         => 'Số điện thoại không hợp lệ (phải là số Việt Nam 10 số).',
            'carrier.required'    => 'Vui lòng chọn nhà mạng.',
            'product_id.required' => 'Vui lòng chọn mệnh giá.',
            'product_id.integer'  => 'Mệnh giá không hợp lệ.',
        ];
    }
}
