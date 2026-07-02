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
            // DichVuSeeder phải chạy trước DaiLyApiSeeder
            // vì DaiLyApiSeeder cần có sẵn dich_vu để cấp quyền test
            DichVuSeeder::class,
            DaiLyApiSeeder::class,
        ]);
        
    }
}
