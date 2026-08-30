<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng lich_su_vi lưu toàn bộ lịch sử biến động số dư.
     * Mỗi giao dịch trừ tiền / hoàn tiền / nạp tiền đều tạo 1 dòng.
     * Không bao giờ UPDATE dòng cũ — chỉ INSERT.
     */
    public function up(): void
    {
        Schema::create('lich_su_vi', function (Blueprint $table) {
            $table->id();

            // Ví của ai
            $table->foreignId('vi_nguoi_dung_id')
                ->constrained('vi_nguoi_dung')
                ->cascadeOnDelete();

            // Tham chiếu tới đơn hàng (nếu có)
            $table->foreignId('don_hang_id')
                ->nullable()
                ->constrained('don_hang')
                ->nullOnDelete();

            // Loại giao dịch: debit (trừ) | credit (cộng/nạp) | refund (hoàn)
            $table->enum('loai_giao_dich', ['debit', 'credit', 'refund'])->index();

            // Số tiền thay đổi (luôn dương, loại giao dịch cho biết chiều +/-)
            $table->decimal('so_tien', 15, 2);

            // Số dư trước giao dịch (audit trail)
            $table->decimal('so_du_truoc', 15, 2);

            // Số dư sau giao dịch (audit trail)
            $table->decimal('so_du_sau', 15, 2);

            // Mô tả ngắn gọn
            $table->string('mo_ta', 500)->nullable();

            // Nguồn tạo: user | admin | system | refund_job
            $table->string('nguon', 50)->default('system');

            $table->timestamps();

            // Index phục vụ truy vấn lịch sử theo user
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_vi');
    }
};
