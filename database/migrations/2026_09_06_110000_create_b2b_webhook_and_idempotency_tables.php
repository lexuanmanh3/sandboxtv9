<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bảng b2b_idempotency_records
        if (!Schema::hasTable('b2b_idempotency_records')) {
            Schema::create('b2b_idempotency_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->string('idempotency_key');
                $table->string('request_hash', 64);
                $table->string('status')->default('PROCESSING'); // PROCESSING | COMPLETED | FAILED
                $table->integer('http_status')->nullable();
                $table->longText('response_json')->nullable();
                $table->unsignedBigInteger('don_hang_id')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->unique(['dai_ly_api_id', 'idempotency_key'], 'uq_b2b_idempotency_key');
                $table->index(['expires_at'], 'idx_b2b_idempotency_expires');
            });
        }

        // 2. Bảng webhook_outbox
        if (!Schema::hasTable('webhook_outbox')) {
            Schema::create('webhook_outbox', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->unsignedBigInteger('don_hang_id')->nullable();
                $table->string('event_type'); // order.success | order.failed | order.status_changed
                $table->longText('payload_json');
                $table->string('trang_thai')->default('PENDING'); // PENDING | PROCESSING | SUCCESS | FAILED
                $table->unsignedInteger('so_lan_thu')->default(0);
                $table->timestamp('lan_thu_tiep_theo')->nullable();
                $table->timestamp('khoa_den')->nullable();
                $table->timestamps();

                $table->index(['trang_thai', 'lan_thu_tiep_theo'], 'idx_webhook_outbox_queue');
                $table->index(['dai_ly_api_id', 'created_at'], 'idx_webhook_outbox_partner');
            });
        }

        // 3. Bảng lich_su_gui_webhook
        if (!Schema::hasTable('lich_su_gui_webhook')) {
            Schema::create('lich_su_gui_webhook', function (Blueprint $table) {
                $table->id();
                $table->foreignId('webhook_outbox_id')->constrained('webhook_outbox')->cascadeOnDelete();
                $table->string('url', 1000);
                $table->integer('http_status')->nullable();
                $table->text('request_headers_json')->nullable();
                $table->longText('request_body')->nullable();
                $table->longText('response_body')->nullable();
                $table->unsignedInteger('thoi_gian_ms')->nullable();
                $table->text('loi')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['webhook_outbox_id', 'created_at'], 'idx_lich_su_webhook_attempt');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_gui_webhook');
        Schema::dropIfExists('webhook_outbox');
        Schema::dropIfExists('b2b_idempotency_records');
    }
};
