<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm các cột giá bán và chiết khấu vào bảng san_pham.
     * Trước đây chỉ có menh_gia (mệnh giá gốc).
     * Bổ sung gia_ban (giá khách trả) và chiet_khau_phan_tram.
     */
    public function up(): void
    {
        Schema::table('san_pham', function (Blueprint $table) {
            // Giá bán thực tế cho khách (sau chiết khấu)
            // Nếu null → gia_ban = menh_gia (không chiết khấu)
            $table->decimal('gia_ban', 15, 2)->nullable()->after('menh_gia');

            // Phần trăm chiết khấu (0.00 - 100.00)
            $table->decimal('chiet_khau_phan_tram', 5, 2)->default(0.00)->after('gia_ban');
        });
    }

    public function down(): void
    {
        Schema::table('san_pham', function (Blueprint $table) {
            $table->dropColumn(['gia_ban', 'chiet_khau_phan_tram']);
        });
    }
};
