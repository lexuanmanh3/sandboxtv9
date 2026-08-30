<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            $table->string('nha_mang_yeu_cau')->nullable();
            $table->string('nha_mang_thuc_te')->nullable();
            $table->string('loai_thue_bao_yeu_cau', 20)->nullable();
            $table->string('loai_thue_bao_thuc_te', 20)->nullable();
            $table->string('ma_san_pham_snapshot')->nullable();
            $table->string('ten_san_pham_snapshot')->nullable();
            $table->decimal('menh_gia_snapshot', 18, 2)->nullable();
            $table->decimal('gia_ban_snapshot', 18, 2)->nullable();
            $table->decimal('gia_von_thuc_te', 18, 2)->nullable();
            $table->foreignId('nha_cung_cap_thanh_cong_id')->nullable()->constrained('nha_cung_cap')->restrictOnDelete();
            $table->unsignedBigInteger('lan_goi_thanh_cong_id')->nullable();
            $table->timestamp('thanh_toan_luc')->nullable();
            $table->timestamp('bat_dau_xu_ly_luc')->nullable();
            $table->timestamp('hoan_thanh_luc')->nullable();
            $table->timestamp('that_bai_luc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropForeign(['nha_cung_cap_thanh_cong_id']);
            $table->dropColumn(['nha_mang_yeu_cau', 'nha_mang_thuc_te', 'loai_thue_bao_yeu_cau',
                'loai_thue_bao_thuc_te', 'ma_san_pham_snapshot', 'ten_san_pham_snapshot',
                'menh_gia_snapshot', 'gia_ban_snapshot', 'gia_von_thuc_te', 'nha_cung_cap_thanh_cong_id',
                'lan_goi_thanh_cong_id', 'thanh_toan_luc', 'bat_dau_xu_ly_luc', 'hoan_thanh_luc', 'that_bai_luc']);
        });
    }
};
