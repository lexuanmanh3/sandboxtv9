<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm unique constraint cho giao dịch refund vào bảng lich_su_vi.
     * Sử dụng virtual generated column để chỉ áp dụng unique khi loai_giao_dich = 'refund'.
     * Các giao dịch 'debit' và 'credit' (hoặc NULL don_hang_id) có giá trị NULL, hoàn toàn không bị ảnh hưởng.
     */
    public function up(): void
    {
        Schema::table('lich_su_vi', function (Blueprint $table) {
            if (!Schema::hasColumn('lich_su_vi', 'refund_don_hang_id')) {
                $table->unsignedBigInteger('refund_don_hang_id')
                    ->virtualAs("IF(loai_giao_dich = 'refund', don_hang_id, NULL)")
                    ->nullable();
                $table->unique('refund_don_hang_id', 'uq_lich_su_vi_refund_don_hang');
            }
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::table('lich_su_vi', function (Blueprint $table) {
            if (Schema::hasColumn('lich_su_vi', 'refund_don_hang_id')) {
                $table->dropUnique('uq_lich_su_vi_refund_don_hang');
                $table->dropColumn('refund_don_hang_id');
            }
        });
    }
};
