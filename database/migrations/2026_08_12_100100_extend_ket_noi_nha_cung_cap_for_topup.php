<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ket_noi_nha_cung_cap', function (Blueprint $table) {
            $table->string('ten_ket_noi')->nullable();
            $table->string('moi_truong', 20)->default('SANDBOX');
            $table->string('base_url')->nullable();
            $table->string('partner_code')->nullable();
            $table->text('api_key_ma_hoa')->nullable();
            $table->text('secret_key_ma_hoa')->nullable();
            $table->string('auth_type', 30)->default('JWT_HS256');
            $table->unsignedInteger('connect_timeout_seconds')->default(10);
            $table->unsignedInteger('request_timeout_seconds')->default(25);
        });
    }

    public function down(): void
    {
        Schema::table('ket_noi_nha_cung_cap', function (Blueprint $table) {
            $table->dropColumn(['ten_ket_noi', 'moi_truong', 'base_url', 'partner_code', 'api_key_ma_hoa',
                'secret_key_ma_hoa', 'auth_type', 'connect_timeout_seconds', 'request_timeout_seconds']);
        });
    }
};
