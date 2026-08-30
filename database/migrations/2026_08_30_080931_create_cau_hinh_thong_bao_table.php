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
        Schema::create('cau_hinh_thong_bao', function (Blueprint $table) {
            $table->id();
            $table->string('loai', 50)->default('telegram')->unique(); // 'telegram'
            $table->text('bot_token')->nullable();                      // Token Bot Telegram
            $table->string('chat_id_alert')->nullable();               // Nhóm cảnh báo kỹ thuật / lỗi
            $table->string('chat_id_order')->nullable();               // Nhóm thông báo đơn hàng
            $table->string('chat_id_admin')->nullable();               // Nhóm quản trị / hoàn tiền

            // Bật/tắt từng loại thông báo
            $table->boolean('bat_thong_bao_don_hang')->default(true);
            $table->boolean('bat_canh_bao_loi')->default(true);
            $table->boolean('bat_canh_bao_xu_ly_cham')->default(true);
            $table->boolean('bat_canh_bao_circuit_breaker')->default(true);
            $table->boolean('bat_canh_bao_so_du_thap')->default(true);
            $table->boolean('bat_canh_bao_manual_review')->default(true);
            $table->boolean('bat_thong_bao_hoan_tien')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cau_hinh_thong_bao');
    }
};

