<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lan_goi_nha_cung_cap', function (Blueprint $table) {
            $table->foreignId('ket_noi_nha_cung_cap_id')->nullable()->constrained('ket_noi_nha_cung_cap')->restrictOnDelete();
            $table->foreignId('san_pham_nha_cung_cap_id')->nullable()->constrained('san_pham_nha_cung_cap')->restrictOnDelete();
            $table->string('loai_yeu_cau', 30)->default('CHARGING');
            $table->string('partner_ref_id', 50)->nullable();
            $table->string('ma_san_pham_ncc')->nullable();
            $table->string('endpoint')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('response_signature')->nullable();
            $table->boolean('chu_ky_hop_le')->nullable();
            $table->decimal('so_du_ncc_sau_giao_dich', 18, 2)->nullable();
            $table->timestamp('thoi_gian_giao_dich_ncc')->nullable();
            $table->string('ket_qua_xac_dinh', 30)->nullable();
            $table->timestamp('bat_dau_luc')->nullable();
            $table->timestamp('ket_thuc_luc')->nullable();
            $table->timestamp('kiem_tra_lai_luc')->nullable();

            $table->unique(['ket_noi_nha_cung_cap_id', 'partner_ref_id'], 'uq_lan_goi_ket_noi_partner_ref');
            $table->index(['don_hang_id', 'ket_qua_xac_dinh'], 'idx_lan_goi_ket_qua');
        });
    }

    public function down(): void
    {
        Schema::table('lan_goi_nha_cung_cap', function (Blueprint $table) {
            $table->dropUnique('uq_lan_goi_ket_noi_partner_ref');
            $table->dropIndex('idx_lan_goi_ket_qua');
            $table->dropForeign(['ket_noi_nha_cung_cap_id']);
            $table->dropForeign(['san_pham_nha_cung_cap_id']);
            $table->dropColumn(['ket_noi_nha_cung_cap_id', 'san_pham_nha_cung_cap_id', 'loai_yeu_cau',
                'partner_ref_id', 'ma_san_pham_ncc', 'endpoint', 'http_status', 'response_signature',
                'chu_ky_hop_le', 'so_du_ncc_sau_giao_dich', 'thoi_gian_giao_dich_ncc',
                'ket_qua_xac_dinh', 'bat_dau_luc', 'ket_thuc_luc', 'kiem_tra_lai_luc']);
        });
    }
};
