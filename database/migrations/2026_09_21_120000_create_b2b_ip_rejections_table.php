<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng audit log cho mọi lần B2B partner bị middleware VerifyPartnerIpAllowlist từ chối.
     * Dùng để admin tra cứu IP mới của reseller khi reseller kết nối lại nhưng bị 403,
     * và có nút "Thêm vào whitelist" để xử lý nhanh không cần hỏi reseller.
     */
    public function up(): void
    {
        if (!Schema::hasTable('b2b_ip_rejections')) {
            Schema::create('b2b_ip_rejections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('dai_ly_api_id')->index();
                $table->string('client_id', 100)->nullable();
                $table->string('ip_address', 45)->index();
                $table->string('endpoint', 255)->nullable();
                $table->string('method', 10)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('error_code', 64);
                $table->json('request_headers_json')->nullable();
                $table->timestamp('attempted_at')->useCurrent();
                $table->boolean('da_xu_ly')->default(false)->index();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['dai_ly_api_id', 'attempted_at'], 'idx_b2b_rej_partner_time');
                $table->index(['ip_address', 'attempted_at'], 'idx_b2b_rej_ip_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_ip_rejections');
    }
};
