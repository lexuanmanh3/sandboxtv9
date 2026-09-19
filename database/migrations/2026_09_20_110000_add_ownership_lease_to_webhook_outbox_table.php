<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung chủ sở hữu (ownership token) cho lease gửi webhook.
 *
 * Lý do: trước đây webhook_outbox chỉ có khoa_den (thời hạn lease) mà không có
 * chủ sở hữu. Hệ quả:
 *  - Hai worker cùng đọc thấy một bản ghi PENDING và cùng gửi HTTP (gửi trùng).
 *  - Worker đã mất lease vẫn ghi đè kết quả của worker đang giữ lease
 *    (lost update trên so_lan_thu vì đọc-rồi-ghi bằng giá trị cũ trong model).
 *
 * Cột khoa_so_huu lưu token của worker đang giữ lease. Mọi cập nhật kết quả
 * bắt buộc phải kèm điều kiện khoa_so_huu = token của chính mình.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_outbox', function (Blueprint $table) {
            if (!Schema::hasColumn('webhook_outbox', 'khoa_so_huu')) {
                $table->string('khoa_so_huu', 64)->nullable()->after('khoa_den');
            }
        });

        // Index hỗ trợ quét nhanh sự kiện đến hạn / lease hết hạn
        Schema::table('webhook_outbox', function (Blueprint $table) {
            $table->index(['trang_thai', 'khoa_den'], 'idx_webhook_outbox_lease');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_outbox', function (Blueprint $table) {
            $table->dropIndex('idx_webhook_outbox_lease');
            $table->dropColumn('khoa_so_huu');
        });
    }
};
