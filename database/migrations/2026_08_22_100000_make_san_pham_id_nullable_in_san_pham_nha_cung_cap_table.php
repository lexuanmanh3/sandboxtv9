<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('san_pham_nha_cung_cap')) {
            // Cho phép san_pham_id nullable để lưu được các mã sản phẩm NCC chưa ánh xạ
            try {
                DB::statement('ALTER TABLE `san_pham_nha_cung_cap` MODIFY `san_pham_id` BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // Fallback nếu SQLite hoặc driver khác
                Schema::table('san_pham_nha_cung_cap', function (Blueprint $table) {
                    $table->unsignedBigInteger('san_pham_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('san_pham_nha_cung_cap')) {
            try {
                DB::statement('ALTER TABLE `san_pham_nha_cung_cap` MODIFY `san_pham_id` BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {
                Schema::table('san_pham_nha_cung_cap', function (Blueprint $table) {
                    $table->unsignedBigInteger('san_pham_id')->nullable(false)->change();
                });
            }
        }
    }
};
