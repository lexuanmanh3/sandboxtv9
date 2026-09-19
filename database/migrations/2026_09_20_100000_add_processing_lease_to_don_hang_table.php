<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bổ sung lease sở hữu xử lý (processing lease) cho đơn hàng.
 *
 * Lý do: trước đây không có cơ chế nào chứng minh "không còn worker nào đang sở hữu đơn".
 * Tiến trình recovery chỉ dựa vào tuổi đơn (>= 2 phút) để quyết định dispatch lại
 * ProcessMobileTopupJob. Khi worker còn sống nhưng bị treo (môi trường không có pcntl
 * nên $timeout của queue không được thực thi), recovery có thể dispatch thêm một worker
 * thứ hai. Hai worker cùng thấy "chưa có lần gọi NCC nào" và cùng tạo một lần gọi CHARGING
 * với partner_ref_id mới -> nạp trùng sang nhà cung cấp.
 *
 * Lease dưới đây biến việc "nhận xử lý một đơn" thành một thao tác nguyên tử có chủ sở hữu
 * và thời hạn, cho phép recovery phân biệt chính xác worker còn sống hay đã chết.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('don_hang', 'xu_ly_owner')) {
                $table->string('xu_ly_owner', 64)->nullable()->after('bat_dau_xu_ly_luc');
            }
            if (!Schema::hasColumn('don_hang', 'xu_ly_lease_den')) {
                $table->timestamp('xu_ly_lease_den')->nullable()->after('xu_ly_owner');
            }
        });

        // Index phục vụ truy vấn "đơn đang có lease còn hiệu lực"
        Schema::table('don_hang', function (Blueprint $table) {
            $table->index(['trang_thai_don_hang', 'xu_ly_lease_den'], 'idx_don_hang_processing_lease');
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropIndex('idx_don_hang_processing_lease');
            $table->dropColumn(['xu_ly_owner', 'xu_ly_lease_den']);
        });
    }
};
