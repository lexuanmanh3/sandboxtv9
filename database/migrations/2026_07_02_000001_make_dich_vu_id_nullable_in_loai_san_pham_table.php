<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * NOTE:
     * Migration nay cho phep cot dich_vu_id trong bang loai_san_pham duoc NULL.
     *
     * Ly do bat buoc phai doi: cac loai san pham goc theo nha mang (vi du Viettel, Vinaphone,
     * Mobifone, Vietnamobile, Gmobile, Wintel) la nhom CHA dung chung cho nhieu dich vu khac nhau
     * (TOPUP, PIN_CODE, PIN_DATA, TOPUP_DATA...), khong thuoc rieng 1 dich vu nao ca, nen khong the
     * ep bat buoc phai chon dich vu cho cac dong nay. Truoc day cot dich_vu_id dang NOT NULL nen
     * seed du lieu nhom cha se bi loi rang buoc (foreign key/NOT NULL) neu khong sua.
     *
     * Dung raw SQL (khong dung Schema::table()->change()) vi du an hien chua cai package
     * doctrine/dbal - can package nay moi dung duoc cu phap ->nullable()->change() cua Laravel.
     * NULL o cot FK khong bi rang buoc restrictOnDelete kiem tra (rang buoc chi ap dung khi
     * dich_vu_id co gia tri that tro toi 1 dong dich_vu), nen doi sang nullable an toan, khong
     * anh huong hanh vi restrictOnDelete da co.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE loai_san_pham MODIFY dich_vu_id BIGINT UNSIGNED NULL');
    }

    /**
     * NOTE:
     * Rollback ve lai NOT NULL nhu ban dau.
     * Luu y: neu trong bang dang co dong nao dich_vu_id = NULL (vi du cac nhom cha nha mang),
     * lenh rollback nay se loi cho toi khi nhung dong do duoc cap nhat lai dich_vu_id hop le
     * hoac bi xoa truoc. Day la hanh vi dung mong doi cua rollback, khong phai bug.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE loai_san_pham MODIFY dich_vu_id BIGINT UNSIGNED NOT NULL');
    }
};
