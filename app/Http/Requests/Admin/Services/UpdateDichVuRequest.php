<?php

namespace App\Http\Requests\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDichVuRequest extends FormRequest
{
    /**
     * Request nay chi validate du lieu sua thong tin dich vu.
     * Quyen truy cap module duoc bao ve bang middleware route.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Rule unique bo qua chinh dich vu dang sua de tranh bao trung du lieu cua no.
     * Route model binding truyen dich vu vao tham so service.
     */
    public function rules(): array
    {
        $serviceId = optional($this->route('service'))->id;

        return [
            'ma_dich_vu' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', Rule::unique('dich_vu', 'ma_dich_vu')->ignore($serviceId)],
            'ten_dich_vu' => ['required', 'string', 'max:150'],
            'trang_thai' => ['required', Rule::in(array_keys($this->statuses()))],
            'thu_tu' => ['nullable', 'integer', 'min:0'],
            'mo_ta' => ['nullable', 'string'],
        ];
    }

    public function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_dung' => 'Tạm dừng',
        ];
    }

    public function messages(): array
    {
        return [
            'ma_dich_vu.regex' => 'Mã dịch vụ chỉ được dùng chữ in hoa, số và dấu gạch dưới (ví dụ: TOPUP, PIN_CODE).',
            'ma_dich_vu.unique' => 'Mã dịch vụ đã tồn tại, vui lòng chọn mã khác.',
        ];
    }
}
