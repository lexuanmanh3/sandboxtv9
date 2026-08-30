<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cau_hinh_thong_bao', function (Blueprint $table) {
            $table->json('danh_sach_kenh')->nullable()->after('chat_id_admin')->comment('Danh sách các nhóm/kênh Telegram động do Admin tạo');
            $table->json('cau_hinh_kenh_su_kien')->nullable()->after('danh_sach_kenh')->comment('Bản đồ chỉ định kênh nhận cho từng loại sự kiện cảnh báo');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cau_hinh_thong_bao', function (Blueprint $table) {
            $table->dropColumn(['danh_sach_kenh', 'cau_hinh_kenh_su_kien']);
        });
    }
};
