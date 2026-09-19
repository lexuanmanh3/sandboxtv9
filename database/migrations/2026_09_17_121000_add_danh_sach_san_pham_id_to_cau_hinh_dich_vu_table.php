<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cau_hinh_dich_vu', function (Blueprint $table) {
            if (!Schema::hasColumn('cau_hinh_dich_vu', 'danh_sach_san_pham_id')) {
                $table->json('danh_sach_san_pham_id')->nullable()->after('san_pham_id');
            }
            if (!Schema::hasColumn('cau_hinh_dich_vu', 'mo_ta')) {
                $table->text('mo_ta')->nullable()->after('ten_cau_hinh');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cau_hinh_dich_vu', function (Blueprint $table) {
            if (Schema::hasColumn('cau_hinh_dich_vu', 'danh_sach_san_pham_id')) {
                $table->dropColumn('danh_sach_san_pham_id');
            }
            if (Schema::hasColumn('cau_hinh_dich_vu', 'mo_ta')) {
                $table->dropColumn('mo_ta');
            }
        });
    }
};
