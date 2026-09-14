<?php

namespace App\Http\Requests\Admin\ProviderErrorCodes;

use App\Models\MaLoiNhaCungCap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateErrorCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $errorCode = $this->route('errorCode') ?? $this->route('error_code') ?? $this->route('provider_error_code');
        $errorCodeId = is_object($errorCode) ? $errorCode->id : $errorCode;
        $providerId = $this->input('nha_cung_cap_id');

        return [
            'nha_cung_cap_id' => ['nullable', 'integer', 'exists:nha_cung_cap,id'],
            'ma_loi' => [
                'required',
                'string',
                'max:50',
                Rule::unique('ma_loi_nha_cung_cap', 'ma_loi')
                    ->ignore($errorCodeId)
                    ->where(function ($query) use ($providerId) {
                        return $providerId
                            ? $query->where('nha_cung_cap_id', $providerId)
                            : $query->whereNull('nha_cung_cap_id');
                    }),
            ],
            'loai_ket_qua' => [
                'required',
                'string',
                Rule::in([
                    MaLoiNhaCungCap::RESULT_SUCCESS,
                    MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING,
                    MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                ]),
            ],
            'thong_bao_goc' => ['nullable', 'string', 'max:500'],
            'thong_bao_hien_thi' => ['nullable', 'string', 'max:500'],
            'hanh_dong_he_thong' => [
                'required',
                'string',
                Rule::in([
                    MaLoiNhaCungCap::ACTION_RETRY_STATUS,
                    MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                    MaLoiNhaCungCap::ACTION_MANUAL_REVIEW,
                    MaLoiNhaCungCap::ACTION_NONE,
                ]),
            ],
            'mo_ta' => ['nullable', 'string', 'max:1000'],
            'trang_thai' => ['required', Rule::in(['hoat_dong', 'tam_dung', 'ACTIVE', 'INACTIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'ma_loi.required' => 'Vui lòng nhập mã lỗi.',
            'ma_loi.unique' => 'Mã lỗi này đã tồn tại đối với nhà cung cấp đã chọn.',
            'loai_ket_qua.required' => 'Vui lòng chọn loại kết quả.',
            'hanh_dong_he_thong.required' => 'Vui lòng chọn hành động hệ thống.',
        ];
    }
}
