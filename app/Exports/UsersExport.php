<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly Collection $users,
        private readonly array $accountTypes,
        private readonly array $statuses
    ) {
    }

    public function collection(): Collection
    {
        return $this->users;
    }

    public function headings(): array
    {
        return [
            'STT',
            'Tên đăng nhập',
            'Email',
            'Số điện thoại',
            'Tên hiển thị',
            'Loại tài khoản',
            'Trạng thái',
            'Ngày tạo',
            'Ngày cập nhật',
        ];
    }

    /**
     * @param  User  $user
     */
    public function map($user): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $user->ten_dang_nhap ?: '-',
            $user->email ?: '-',
            $user->so_dien_thoai ?: '-',
            $user->ten_hien_thi ?: '-',
            $this->accountTypes[$user->loai_tai_khoan] ?? ($user->loai_tai_khoan ?: '-'),
            $this->resolveStatus($user),
            optional($user->created_at)->format('d/m/Y H:i') ?: '-',
            optional($user->updated_at)->format('d/m/Y H:i') ?: '-',
        ];
    }

    private function resolveStatus(User $user): string
    {
        if ($user->bi_khoa) {
            return 'Bị khóa';
        }

        return $this->statuses[$user->trang_thai] ?? ($user->trang_thai ?: '-');
    }
}
