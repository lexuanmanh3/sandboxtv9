<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cờ bật/tắt cảnh báo Telegram cho sự kiện IP bị từ chối.
     * Tách riêng khỏi bat_canh_bao_loi vì đây là sự kiện bảo mật, không phải lỗi nghiệp vụ.
     */
    public function up(): void
    {
        if (Schema::hasTable('cau_hinh_thong_bao') && !Schema::hasColumn('cau_hinh_thong_bao', 'bat_canh_bao_ip_rejected')) {
            Schema::table('cau_hinh_thong_bao', function (Blueprint $table) {
                $table->boolean('bat_canh_bao_ip_rejected')->default(true)->after('bat_thong_bao_hoan_tien');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cau_hinh_thong_bao') && Schema::hasColumn('cau_hinh_thong_bao', 'bat_canh_bao_ip_rejected')) {
            Schema::table('cau_hinh_thong_bao', function (Blueprint $table) {
                $table->dropColumn('bat_canh_bao_ip_rejected');
            });
        }
    }
};
