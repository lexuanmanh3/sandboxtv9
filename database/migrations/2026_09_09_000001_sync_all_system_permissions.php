<?php

use Illuminate\Database\Migrations\Migration;
use Database\Seeders\QuyenSeeder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $seeder = new QuyenSeeder();
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Giữ lại dữ liệu quyền
    }
};
