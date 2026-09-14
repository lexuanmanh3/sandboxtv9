<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Mở rộng bảng dai_ly_api
        Schema::table('dai_ly_api', function (Blueprint $table) {
            if (!Schema::hasColumn('dai_ly_api', 'tinh_thanh')) {
                $table->string('tinh_thanh')->nullable()->after('ten');
                $table->string('quan_huyen')->nullable()->after('tinh_thanh');
                $table->string('phuong_xa')->nullable()->after('quan_huyen');
                $table->string('dia_chi_chi_tiet')->nullable()->after('phuong_xa');
                $table->json('thong_tin_lien_he')->nullable()->after('dia_chi_chi_tiet');
            }
        });

        // 2. Mở rộng bảng cau_hinh_api_dai_ly
        Schema::table('cau_hinh_api_dai_ly', function (Blueprint $table) {
            if (!Schema::hasColumn('cau_hinh_api_dai_ly', 'han_muc_cong_no')) {
                $table->decimal('han_muc_cong_no', 18, 2)->default(0)->after('han_muc_mua_the');
                $table->decimal('cong_no_hien_tai', 18, 2)->default(0)->after('han_muc_cong_no');
                $table->decimal('nguong_canh_bao_han_muc', 18, 2)->default(0)->after('cong_no_hien_tai');
                $table->boolean('cho_phep_nhan_don')->default(true)->after('nguong_canh_bao_han_muc');
                $table->json('san_pham_loai_tru')->nullable()->after('cho_phep_nhan_don');
                $table->string('webhook_url')->nullable()->after('san_pham_loai_tru');
                $table->text('webhook_secret_ma_hoa')->nullable()->after('webhook_url');
                $table->unsignedInteger('rate_limit_per_minute')->default(60)->after('webhook_secret_ma_hoa');
                $table->string('key_status')->default('active')->after('rate_limit_per_minute');
                $table->text('previous_secret_ma_hoa')->nullable()->after('key_status');
                $table->timestamp('key_rotated_at')->nullable()->after('previous_secret_ma_hoa');
            }
        });

        // 3. Bảng bang_gia_dai_ly
        if (!Schema::hasTable('bang_gia_dai_ly')) {
            Schema::create('bang_gia_dai_ly', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->foreignId('san_pham_id')->constrained('san_pham')->cascadeOnDelete();
                $table->string('loai_chiet_khau')->default('PERCENT'); // PERCENT | FIXED_PRICE
                $table->decimal('gia_tri_chiet_khau', 18, 2)->default(0);
                $table->decimal('gia_ban_ap_dung', 18, 2)->nullable();
                $table->string('trang_thai')->default('hoat_dong');
                $table->timestamps();

                $table->unique(['dai_ly_api_id', 'san_pham_id'], 'uq_bang_gia_dai_ly_san_pham');
            });
        }

        // 4. Bảng khoan_giu_han_muc (Credit Holds)
        if (!Schema::hasTable('khoan_giu_han_muc')) {
            Schema::create('khoan_giu_han_muc', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->foreignId('don_hang_id')->constrained('don_hang')->cascadeOnDelete();
                $table->decimal('so_tien_giu', 18, 2);
                $table->string('trang_thai')->default('HOLDING'); // HOLDING | COMMITTED | RELEASED
                $table->string('ly_do_giai_phong')->nullable();
                $table->timestamp('giai_phong_luc')->nullable();
                $table->timestamp('chot_cong_no_luc')->nullable();
                $table->timestamps();

                $table->unique(['dai_ly_api_id', 'don_hang_id'], 'uq_khoan_giu_dai_ly_don_hang');
                $table->index(['dai_ly_api_id', 'trang_thai'], 'idx_khoan_giu_dai_ly_trang_thai');
            });
        }

        // 5. Bảng thanh_toan_cong_no (Partner Payments)
        if (!Schema::hasTable('thanh_toan_cong_no')) {
            Schema::create('thanh_toan_cong_no', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->string('ma_thanh_toan')->unique();
                $table->decimal('so_tien', 18, 2);
                $table->dateTime('ngay_thanh_toan');
                $table->string('phuong_thuc')->default('CHUYEN_KHOAN');
                $table->string('ma_giao_dich_ngan_hang')->nullable();
                $table->text('ghi_chu')->nullable();
                $table->string('trang_thai')->default('DA_XAC_NHAN'); // DA_XAC_NHAN | HUY
                $table->foreignId('nguoi_tao_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['dai_ly_api_id', 'ngay_thanh_toan'], 'idx_thanh_toan_dai_ly_ngay');
            });
        }

        // 6. Bảng ky_doi_soat (Reconciliation Periods)
        if (!Schema::hasTable('ky_doi_soat')) {
            Schema::create('ky_doi_soat', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->string('ma_ky')->unique();
                $table->date('tu_ngay');
                $table->date('den_ngay');
                $table->decimal('so_du_dau_ky', 18, 2)->default(0);
                $table->decimal('tong_phat_sinh_tang', 18, 2)->default(0);
                $table->decimal('tong_phat_sinh_giam', 18, 2)->default(0);
                $table->decimal('tong_thanh_toan', 18, 2)->default(0);
                $table->decimal('tong_dieu_chinh', 18, 2)->default(0);
                $table->decimal('so_du_cuoi_ky', 18, 2)->default(0);
                $table->string('trang_thai')->default('DANG_MO'); // DANG_MO | DA_KHOA | DA_THANH_TOAN
                $table->dateTime('ngay_khoa')->nullable();
                $table->foreignId('nguoi_khoa_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('ghi_chu')->nullable();
                $table->timestamps();

                $table->index(['dai_ly_api_id', 'tu_ngay', 'den_ngay'], 'idx_ky_doi_soat_range');
            });
        }

        // 7. Bảng dieu_chinh_cong_no (Debt Adjustments)
        if (!Schema::hasTable('dieu_chinh_cong_no')) {
            Schema::create('dieu_chinh_cong_no', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->foreignId('ky_doi_soat_id')->nullable()->constrained('ky_doi_soat')->nullOnDelete();
                $table->string('ma_dieu_chinh')->unique();
                $table->string('loai_dieu_chinh'); // TANG_NO | GIAM_NO
                $table->decimal('so_tien', 18, 2);
                $table->text('ly_do');
                $table->string('ma_tham_chieu_goc')->nullable();
                $table->foreignId('nguoi_tao_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['dai_ly_api_id', 'created_at'], 'idx_dieu_chinh_dai_ly_time');
            });
        }

        // 8. Bảng chi_tiet_ky_doi_soat
        if (!Schema::hasTable('chi_tiet_ky_doi_soat')) {
            Schema::create('chi_tiet_ky_doi_soat', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ky_doi_soat_id')->constrained('ky_doi_soat')->cascadeOnDelete();
                $table->foreignId('don_hang_id')->constrained('don_hang')->cascadeOnDelete();
                $table->decimal('so_tien', 18, 2);
                $table->string('trang_thai_don_hang');
                $table->dateTime('ngay_tao_don');
                $table->timestamps();

                $table->unique(['ky_doi_soat_id', 'don_hang_id'], 'uq_chi_tiet_ky_don_hang');
            });
        }

        // 9. Bảng so_phat_sinh_cong_no (Partner Debt Ledger - Double-entry immutable)
        if (!Schema::hasTable('so_phat_sinh_cong_no')) {
            Schema::create('so_phat_sinh_cong_no', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dai_ly_api_id')->constrained('dai_ly_api')->cascadeOnDelete();
                $table->foreignId('don_hang_id')->nullable()->constrained('don_hang')->nullOnDelete();
                $table->foreignId('thanh_toan_id')->nullable()->constrained('thanh_toan_cong_no')->nullOnDelete();
                $table->foreignId('dieu_chinh_id')->nullable()->constrained('dieu_chinh_cong_no')->nullOnDelete();
                $table->foreignId('ky_doi_soat_id')->nullable()->constrained('ky_doi_soat')->nullOnDelete();
                $table->string('loai_phat_sinh'); // TANG_CONG_NO_DON_HANG | GIAM_CONG_NO_HOAN_TIEN | GIAM_CONG_NO_THANH_TOAN | DIEU_CHINH_TANG | DIEU_CHINH_GIAM
                $table->decimal('so_tien', 18, 2);
                $table->decimal('so_du_truoc', 18, 2);
                $table->decimal('so_du_sau', 18, 2);
                $table->string('ma_tham_chieu');
                $table->text('ghi_chu')->nullable();
                $table->foreignId('nguoi_tao_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['dai_ly_api_id', 'ma_tham_chieu'], 'uq_so_phat_sinh_dai_ly_tham_chieu');
                $table->index(['dai_ly_api_id', 'created_at'], 'idx_so_phat_sinh_dai_ly_time');
            });
        }

        // 10. Thêm Unique constraint cho don_hang (dai_ly_api_id, ma_don_doi_tac)
        Schema::table('don_hang', function (Blueprint $table) {
            $table->unique(['dai_ly_api_id', 'ma_don_doi_tac'], 'uq_don_hang_partner_order');
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropUnique('uq_don_hang_partner_order');
        });

        Schema::dropIfExists('so_phat_sinh_cong_no');
        Schema::dropIfExists('chi_tiet_ky_doi_soat');
        Schema::dropIfExists('dieu_chinh_cong_no');
        Schema::dropIfExists('ky_doi_soat');
        Schema::dropIfExists('thanh_toan_cong_no');
        Schema::dropIfExists('khoan_giu_han_muc');
        Schema::dropIfExists('bang_gia_dai_ly');

        Schema::table('cau_hinh_api_dai_ly', function (Blueprint $table) {
            $table->dropColumn([
                'han_muc_cong_no',
                'cong_no_hien_tai',
                'nguong_canh_bao_han_muc',
                'cho_phep_nhan_don',
                'san_pham_loai_tru',
                'webhook_url',
                'webhook_secret_ma_hoa',
                'rate_limit_per_minute',
                'key_status',
                'previous_secret_ma_hoa',
                'key_rotated_at',
            ]);
        });

        Schema::table('dai_ly_api', function (Blueprint $table) {
            $table->dropColumn([
                'tinh_thanh',
                'quan_huyen',
                'phuong_xa',
                'dia_chi_chi_tiet',
                'thong_tin_lien_he',
            ]);
        });
    }
};
