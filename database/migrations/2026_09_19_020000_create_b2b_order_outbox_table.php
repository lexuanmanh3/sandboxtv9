<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('b2b_order_outbox')) {
            Schema::create('b2b_order_outbox', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('don_hang_id')->index();
                $table->unsignedBigInteger('dai_ly_api_id')->index();
                $table->string('partner_order_id', 64)->nullable()->index();
                $table->string('product_code', 64)->index();
                $table->string('account', 64)->index();
                $table->longText('payload_json');
                $table->string('status', 32)->default('PENDING')->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('locked_until')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'locked_until', 'created_at'], 'idx_b2b_outbox_pending');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b2b_order_outbox');
    }
};
