<?php

namespace App\Exports;

use App\Models\KyDoiSoat;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReconciliationExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(protected KyDoiSoat $kyDoiSoat) {}

    public function collection()
    {
        return $this->kyDoiSoat->chiTiet()->with('donHang.sanPham')->get();
    }

    public function headings(): array
    {
        return [
            'ID Đơn',
            'Mã đơn hệ thống',
            'Mã đơn đối tác',
            'Sản phẩm',
            'Tài khoản nhận',
            'Mệnh giá (VNĐ)',
            'Giá đại lý (VNĐ)',
            'Trạng thái đơn',
            'Thời gian tạo',
        ];
    }

    public function map($row): array
    {
        $don = $row->donHang;
        return [
            $don?->id,
            $don?->ma_don_hang,
            $don?->ma_don_doi_tac,
            $don?->ten_san_pham_snapshot ?: $don?->sanPham?->ten_san_pham,
            $don?->tai_khoan_nhan,
            number_format((float) ($don?->menh_gia ?? 0)),
            number_format((float) ($row->so_tien ?? 0)),
            $row->trang_thai_don_hang,
            $row->ngay_tao_don ? $row->ngay_tao_don->format('d/m/Y H:i:s') : '',
        ];
    }

    public function title(): string
    {
        return 'Bảng kê ' . $this->kyDoiSoat->ma_ky;
    }
}
