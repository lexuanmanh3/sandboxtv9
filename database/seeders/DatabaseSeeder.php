<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            // VaiTroSeeder phải chạy trước QuyenSeeder
            // vì QuyenSeeder cần tra cứu vai_tro để gán quyền
            VaiTroSeeder::class,
            QuyenSeeder::class,
            AdminSeeder::class,
            // DichVuSeeder phải chạy trước LoaiSanPhamSeeder và DaiLyApiSeeder
            // vì cả 2 seeder sau đều cần tra cứu dich_vu theo tên đã có sẵn
            DichVuSeeder::class,
            LoaiSanPhamSeeder::class,
            DaiLyApiSeeder::class,
            SanPhamSeeder::class,
        ]);
        
    }
}
