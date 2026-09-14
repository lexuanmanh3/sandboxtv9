<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đồng bộ hóa toàn bộ trạng thái thực thể danh mục về chuẩn tiếng Việt thống nhất:
     * - 'hoat_dong': Đang hoạt động (thay cho 'ACTIVE')
     * - 'tam_dung': Tạm dừng (thay cho 'INACTIVE')
     */
    public function up(): void
    {
        // 1. Bảng san_pham
        if (Schema::hasTable('san_pham') && Schema::hasColumn('san_pham', 'trang_thai')) {
            DB::table('san_pham')->where('trang_thai', 'ACTIVE')->update(['trang_thai' => 'hoat_dong']);
            DB::table('san_pham')->where('trang_thai', 'INACTIVE')->update(['trang_thai' => 'tam_dung']);
        }

        // 2. Bảng san_pham_nha_cung_cap
        if (Schema::hasTable('san_pham_nha_cung_cap') && Schema::hasColumn('san_pham_nha_cung_cap', 'trang_thai')) {
            DB::table('san_pham_nha_cung_cap')->where('trang_thai', 'ACTIVE')->update(['trang_thai' => 'hoat_dong']);
            DB::table('san_pham_nha_cung_cap')->where('trang_thai', 'INACTIVE')->update(['trang_thai' => 'tam_dung']);
        }

        // 3. Bảng ma_loi_nha_cung_cap
        if (Schema::hasTable('ma_loi_nha_cung_cap') && Schema::hasColumn('ma_loi_nha_cung_cap', 'trang_thai')) {
            DB::table('ma_loi_nha_cung_cap')->where('trang_thai', 'ACTIVE')->update(['trang_thai' => 'hoat_dong']);
            DB::table('ma_loi_nha_cung_cap')->where('trang_thai', 'INACTIVE')->update(['trang_thai' => 'tam_dung']);
        }
    }

    /**
     * Rollback nếu cần.
     */
    public function down(): void
    {
        if (Schema::hasTable('san_pham') && Schema::hasColumn('san_pham', 'trang_thai')) {
            DB::table('san_pham')->where('trang_thai', 'hoat_dong')->update(['trang_thai' => 'ACTIVE']);
        }

        if (Schema::hasTable('san_pham_nha_cung_cap') && Schema::hasColumn('san_pham_nha_cung_cap', 'trang_thai')) {
            DB::table('san_pham_nha_cung_cap')->where('trang_thai', 'hoat_dong')->update(['trang_thai' => 'ACTIVE']);
        }

        if (Schema::hasTable('ma_loi_nha_cung_cap') && Schema::hasColumn('ma_loi_nha_cung_cap', 'trang_thai')) {
            DB::table('ma_loi_nha_cung_cap')->where('trang_thai', 'hoat_dong')->update(['trang_thai' => 'ACTIVE']);
        }
    }
};
