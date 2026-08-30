<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng vi_nguoi_dung lưu số dư ví của từng người dùng.
     * Mỗi user chỉ có 1 bản ghi ví.
     * Dùng lockForUpdate() khi đọc số dư để chống race condition.
     */
    public function up(): void
    {
        Schema::create('vi_nguoi_dung', function (Blueprint $table) {
            $table->id();

            // Khóa ngoại tới bảng users
            $table->foreignId('nguoi_dung_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // Số dư hiện tại (đơn vị: VND, tối đa 999 tỷ)
            // decimal(15,2) để tránh lỗi float
            $table->decimal('so_du', 15, 2)->default(0.00);

            // Tổng tiền đã nạp vào ví (audit)
            $table->decimal('tong_nap', 15, 2)->default(0.00);

            // Tổng tiền đã chi (audit)
            $table->decimal('tong_chi', 15, 2)->default(0.00);

            // Tổng tiền đã hoàn lại (audit)
            $table->decimal('tong_hoan', 15, 2)->default(0.00);

            // Trạng thái ví: hoat_dong | bi_khoa
            $table->string('trang_thai', 20)->default('hoat_dong')->index();

            // Ghi chú nội bộ (admin khóa ví thì note lý do)
            $table->text('ghi_chu')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vi_nguoi_dung');
    }
};
