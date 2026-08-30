<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung các trường cấu hình nâng cao cho kết nối Nhà Cung Cấp:
     * - Cấu hình mã giao dịch, public key
     * - Cấu hình cảnh báo số dư NCC (JSON)
     * - Cấu hình đóng tự động / Circuit Breaker (JSON)
     * - Cấu hình cảnh báo lỗi / Telegram (JSON)
     */
    public function up(): void
    {
        Schema::table('ket_noi_nha_cung_cap', function (Blueprint $table) {
            $table->string('cau_hinh_ma_gd')->nullable()->after('api_url');
            $table->text('public_key')->nullable()->after('cau_hinh_ma_gd');
            $table->json('cau_hinh_canh_bao_so_du')->nullable()->after('request_timeout_seconds');
            $table->json('cau_hinh_dong_tu_dong')->nullable()->after('cau_hinh_canh_bao_so_du');
            $table->json('cau_hinh_canh_bao_loi')->nullable()->after('cau_hinh_dong_tu_dong');
        });
    }

    public function down(): void
    {
        Schema::table('ket_noi_nha_cung_cap', function (Blueprint $table) {
            $table->dropColumn([
                'cau_hinh_ma_gd',
                'public_key',
                'cau_hinh_canh_bao_so_du',
                'cau_hinh_dong_tu_dong',
                'cau_hinh_canh_bao_loi',
            ]);
        });
    }
};
