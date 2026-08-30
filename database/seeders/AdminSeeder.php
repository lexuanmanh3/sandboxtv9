<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // NOTE: Chỉ tạo admin lần đầu nếu chưa tồn tại.
        // KHÔNG bao giờ ghi đè mật khẩu của admin đã có (tránh reset mật khẩu mỗi lần deploy).
        $exists = DB::table('users')->where('ten_dang_nhap', 'admin')->exists();

        if ($exists) {
            $this->command->info('[AdminSeeder] Tài khoản admin đã tồn tại — bỏ qua, KHÔNG đặt lại mật khẩu.');
        } else {
            // Lấy mật khẩu từ biến môi trường — KHÔNG hard-code
            $initialPassword = env('ADMIN_INITIAL_PASSWORD');

            if (empty($initialPassword)) {
                if (app()->environment('production')) {
                    // Production: dừng hẳn nếu thiếu biến — an toàn hơn là tạo với mật khẩu yếu
                    $this->command->error('[AdminSeeder] Thiếu biến ADMIN_INITIAL_PASSWORD trên production. Seeder dừng lại.');
                    return;
                }
                // Local/testing: dùng giá trị tạm thời để dev thuận tiện
                $initialPassword = 'ChangeMe@Local!';
                $this->command->warn('[AdminSeeder] Dùng mật khẩu mặc định local. Đặt ADMIN_INITIAL_PASSWORD trong .env để kiểm soát.');
            }

            DB::table('users')->insert([
                'ten_dang_nhap'          => 'admin',
                'email'                  => env('ADMIN_INITIAL_EMAIL', 'admin@example.com'),
                'so_dien_thoai'          => null,
                'password'               => Hash::make($initialPassword),
                'ho'                     => 'System',
                'ten'                    => 'Admin',
                'name'                   => 'System Admin',
                'loai_tai_khoan'         => 'admin',
                'email_verified_at'      => now(),
                'email_da_xac_nhan'      => true,
                'tai_khoan_da_xac_thuc'  => true,
                // Bắt buộc đổi mật khẩu lần đăng nhập đầu tiên
                'bat_buoc_doi_mat_khau'  => true,
                'tu_dong_khoa_tai_khoan' => false,
                'bi_khoa'                => false,
                'trang_thai'             => 'hoat_dong',
                'lan_dang_nhap_cuoi'     => null,
                'remember_token'         => null,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // Không ghi mật khẩu vào log — chỉ thông báo tài khoản đã được tạo
            $this->command->info('[AdminSeeder] Tạo tài khoản admin thành công. Vui lòng đổi mật khẩu ngay sau lần đăng nhập đầu tiên.');
        }

        // Gán vai trò admin (luôn thực hiện, dù vừa tạo hay đã tồn tại)
        $adminUser = DB::table('users')
            ->where('ten_dang_nhap', 'admin')
            ->first();

        $adminRole = DB::table('vai_tro')
            ->where('ma_vai_tro', 'admin')
            ->first();

        if (!$adminUser || !$adminRole) {
            return;
        }

        DB::table('nguoi_dung_vai_tro')->updateOrInsert(
            [
                'nguoi_dung_id' => $adminUser->id,
                'vai_tro_id'    => $adminRole->id,
            ],
            [
                'tao_luc' => now(),
            ]
        );
    }
}
