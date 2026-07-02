<?php

namespace App\Http\Requests\Admin\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDichVuRequest extends FormRequest
{
    /**
     * Request nay chi dung cho man tao dich vu trong module quan tri.
     * Phan middleware o route da chan user khong co quyen truoc khi vao day.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Rule bam theo dung schema bang dich_vu hien tai (xem migration create_dich_vu_table).
     * Khi doi ten cot trong dich_vu, bao tri rule validate tai day.
     */
    public function rules(): array
    {
        return [
            // NOTE: ma_dich_vu la ma dinh danh dung trong logic he thong (vd TOPUP, PIN_CODE)
            // nen chi cho chu hoa, so va gach duoi, tranh nham voi ten hien thi.
            'ma_dich_vu' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/', 'unique:dich_vu,ma_dich_vu'],
            'ten_dich_vu' => ['required', 'string', 'max:150'],
            'trang_thai' => ['required', Rule::in(array_keys($this->statuses()))],
            // NOTE: thu_tu de trong thi controller se tu dat mac dinh 0 truoc khi luu.
            'thu_tu' => ['nullable', 'integer', 'min:0'],
            'mo_ta' => ['nullable', 'string'],
        ];
    }

    /**
     * Danh sach trang thai dich vu — dung chung cho filter, form va export.
     */
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
