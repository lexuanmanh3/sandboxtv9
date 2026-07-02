<?php

namespace App\Exports;

use App\Models\DichVu;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ServicesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly Collection $services,
        private readonly array $statuses
    ) {
    }

    public function collection(): Collection
    {
        return $this->services;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Mã dịch vụ',
            'Tên dịch vụ',
            'Trạng thái',
            'Thứ tự',
            'Mô tả',
            'Ngày tạo',
            'Ngày cập nhật',
        ];
    }

    /**
     * @param  DichVu  $service
     */
    public function map($service): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $service->ma_dich_vu,
            $service->ten_dich_vu,
            $this->statuses[$service->trang_thai] ?? ($service->trang_thai ?: '-'),
            $service->thu_tu,
            $service->mo_ta ?: '-',
            optional($service->created_at)->format('d/m/Y H:i') ?: '-',
            optional($service->updated_at)->format('d/m/Y H:i') ?: '-',
        ];
    }
}
