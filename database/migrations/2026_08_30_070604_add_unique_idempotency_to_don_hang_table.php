<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm unique constraint (nguoi_dung_id, idempotency_key) vào bảng don_hang.
     * Đây là lớp bảo vệ thứ 2 ở tầng DB — chặn tạo 2 đơn cùng key ngay cả khi race condition.
     *
     * Tên index: uq_don_hang_user_idempotency
     */
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            // Xóa index cũ nếu đã tồn tại (chạy migration nhiều lần không bị lỗi)
            // idempotency_key có thể null với các đơn không có key (đơn từ hệ thống cũ)
            // → Chỉ unique khi KHÔNG NULL (MySQL tự bỏ qua NULL trong unique index)
            $table->unique(
                ['nguoi_dung_id', 'idempotency_key'],
                'uq_don_hang_user_idempotency'
            );
        });
    }

    /**
     * Xóa unique constraint khi rollback.
     */
    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropUnique('uq_don_hang_user_idempotency');
        });
    }
};

