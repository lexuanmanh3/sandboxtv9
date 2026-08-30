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
        Schema::create('ma_loi_nha_cung_cap', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nha_cung_cap_id')->nullable()->constrained('nha_cung_cap')->cascadeOnDelete();
            $table->string('ma_loi', 50)->comment('Mã lỗi từ NCC: 0, 34, 35, 99, 1, 2...');
            $table->string('loai_ket_qua', 50)->default('DEFINITIVE_FAILURE')->comment('SUCCESS, UNKNOWN_OR_PENDING, DEFINITIVE_FAILURE');
            $table->string('thong_bao_goc', 500)->nullable()->comment('Thông điệp gốc từ phía NCC');
            $table->string('thong_bao_hien_thi', 500)->nullable()->comment('Thông báo thân thiện tiếng Việt');
            $table->string('hanh_dong_he_thong', 50)->default('REFUND_WALLET')->comment('RETRY_STATUS, REFUND_WALLET, MANUAL_REVIEW, NONE');
            $table->text('mo_ta')->nullable()->comment('Ghi chú nội bộ');
            $table->string('trang_thai', 20)->default('ACTIVE')->comment('ACTIVE, INACTIVE');
            $table->timestamps();

            $table->unique(['nha_cung_cap_id', 'ma_loi'], 'uq_ma_loi_ncc_ma');
            $table->index(['loai_ket_qua', 'trang_thai'], 'idx_ma_loi_ket_qua_trang_thai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ma_loi_nha_cung_cap');
    }
};
