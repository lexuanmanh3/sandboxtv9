<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nhat_ky_kiem_tra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nguoi_thuc_hien_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hanh_dong')->index();
            $table->nullableMorphs('doi_tuong');
            $table->json('du_lieu_truoc')->nullable();
            $table->json('du_lieu_sau')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('route')->nullable();
            $table->string('trang_thai', 20)->default('thanh_cong')->index();
            $table->text('thong_tin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nhat_ky_kiem_tra');
    }
};
