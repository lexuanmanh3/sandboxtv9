<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('san_pham_nha_cung_cap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('san_pham_id')->constrained('san_pham')->restrictOnDelete();
            $table->foreignId('nha_cung_cap_id')->constrained('nha_cung_cap')->restrictOnDelete();
            $table->foreignId('ket_noi_nha_cung_cap_id')->constrained('ket_noi_nha_cung_cap')->restrictOnDelete();
            $table->string('ma_san_pham_ncc');
            $table->string('ma_nha_mang_ncc')->nullable();
            $table->string('loai_dich_vu_ncc')->nullable();
            $table->string('loai_thue_bao')->nullable();
            $table->decimal('menh_gia_ncc', 18, 2);
            $table->decimal('gia_nhap', 18, 2)->nullable();
            $table->decimal('ty_le_chiet_khau', 8, 4)->nullable();
            $table->unsignedInteger('muc_uu_tien')->default(100);
            $table->string('trang_thai')->default('ACTIVE');
            $table->json('du_lieu_mo_rong_json')->nullable();
            $table->timestamp('dong_bo_luc')->nullable();
            $table->timestamps();

            $table->unique(['ket_noi_nha_cung_cap_id', 'ma_san_pham_ncc'], 'uq_sp_ncc_ket_noi_ma');
            $table->unique(['san_pham_id', 'ket_noi_nha_cung_cap_id'], 'uq_sp_ncc_san_pham_ket_noi');
            $table->index(['san_pham_id', 'trang_thai', 'muc_uu_tien'], 'idx_sp_ncc_dinh_tuyen');
            $table->index(['nha_cung_cap_id', 'trang_thai'], 'idx_sp_ncc_nha_cung_cap');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('san_pham_nha_cung_cap');
    }
};
