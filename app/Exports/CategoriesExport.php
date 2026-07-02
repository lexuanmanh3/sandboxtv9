<?php

namespace App\Exports;

use App\Models\LoaiSanPham;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategoriesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly Collection $categories,
        private readonly array $statuses
    ) {
    }

    public function collection(): Collection
    {
        return $this->categories;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Mã loại sản phẩm',
            'Tên loại sản phẩm',
            'Dịch vụ',
            'Loại cha',
            'Trạng thái',
            'Thứ tự',
            'Mô tả',
            'Ngày tạo',
            'Ngày cập nhật',
        ];
    }

    /**
     * @param  LoaiSanPham  $category
     */
    public function map($category): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $category->ma_loai_san_pham,
            $category->ten_loai_san_pham,
            optional($category->dichVu)->ten_dich_vu ?: '-',
            optional($category->loaiCha)->ten_loai_san_pham ?: '-',
            $this->statuses[$category->trang_thai] ?? ($category->trang_thai ?: '-'),
            $category->thu_tu,
            $category->mo_ta ?: '-',
            optional($category->created_at)->format('d/m/Y H:i') ?: '-',
            optional($category->updated_at)->format('d/m/Y H:i') ?: '-',
        ];
    }
}
