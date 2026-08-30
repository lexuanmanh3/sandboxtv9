<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nhat_ky_kiem_tra', function (Blueprint $table) {
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'dich_vu')) {
                $table->string('dich_vu', 100)->nullable()->index()->after('hanh_dong');
            }
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'hoat_dong')) {
                $table->string('hoat_dong', 100)->nullable()->index()->after('dich_vu');
            }
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'thoi_gian_thuc_thi_ms')) {
                $table->integer('thoi_gian_thuc_thi_ms')->nullable()->after('hoat_dong');
            }
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip');
            }
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'khach_hang')) {
                $table->string('khach_hang', 150)->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'tham_so')) {
                $table->json('tham_so')->nullable()->after('du_lieu_sau');
            }
            if (!Schema::hasColumn('nhat_ky_kiem_tra', 'loi_ngoai_le')) {
                $table->longText('loi_ngoai_le')->nullable()->after('thong_tin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nhat_ky_kiem_tra', function (Blueprint $table) {
            $table->dropColumn([
                'dich_vu',
                'hoat_dong',
                'thoi_gian_thuc_thi_ms',
                'user_agent',
                'khach_hang',
                'tham_so',
                'loi_ngoai_le',
            ]);
        });
    }
};
