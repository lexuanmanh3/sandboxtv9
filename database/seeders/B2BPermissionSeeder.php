<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quyen;
use App\Models\VaiTro;

class B2BPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Cấu hình tuyến dịch vụ
            ['ma_quyen' => 'b2b_routing.view', 'ten_quyen' => 'Xem cấu hình tuyến', 'nhom_quyen' => 'B2B Partner'],
            ['ma_quyen' => 'b2b_routing.create', 'ten_quyen' => 'Thêm cấu hình tuyến', 'nhom_quyen' => 'B2B Partner'],
            ['ma_quyen' => 'b2b_routing.update', 'ten_quyen' => 'Sửa cấu hình tuyến', 'nhom_quyen' => 'B2B Partner'],
            ['ma_quyen' => 'b2b_routing.delete', 'ten_quyen' => 'Xóa cấu hình tuyến', 'nhom_quyen' => 'B2B Partner'],
            
            // Vận hành & Xử lý sự cố
            ['ma_quyen' => 'b2b_operation.view', 'ten_quyen' => 'Xem nhật ký vận hành & khoản giữ', 'nhom_quyen' => 'B2B Partner'],
            ['ma_quyen' => 'b2b_operation.release_hold', 'ten_quyen' => 'Giải phóng hạn mức thủ công', 'nhom_quyen' => 'B2B Partner'],
        ];

        foreach ($permissions as $perm) {
            $quyen = Quyen::firstOrCreate(
                ['ma_quyen' => $perm['ma_quyen']],
                ['ten_quyen' => $perm['ten_quyen'], 'nhom_quyen' => $perm['nhom_quyen']]
            );
        }

        // Tự động cấp quyền cho Admin nếu vai trò Admin tồn tại
        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole) {
            $quyenIds = Quyen::whereIn('ma_quyen', array_column($permissions, 'ma_quyen'))->pluck('id');
            $adminRole->quyen()->syncWithoutDetaching($quyenIds);
        }
    }
}
