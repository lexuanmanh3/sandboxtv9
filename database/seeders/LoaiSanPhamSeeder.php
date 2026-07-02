<?php

namespace Database\Seeders;

use App\Models\DichVu;
use App\Models\LoaiSanPham;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class LoaiSanPhamSeeder extends Seeder
{
    /**
     * NOTE:
     * Bang ma nha mang -> ma loai san pham GOC tuong ung (VTE, VNA, VMS, VNM, GMOBILE, WT).
     * Cot "Tên loại sản phẩm cha" trong du lieu goc la TEN hien thi (vd "Viettel"), nhung ma nay
     * dung de tra cuu chinh xac ma_loai_san_pham cua dong cha da tao o buoc 1, tranh truong hop
     * trung ten hien thi giua nhieu nhom khac nhau sau nay.
     */
    private const MA_CHA_THEO_TEN = [
        'Viettel' => 'VTE',
        'Vinaphone' => 'VNA',
        'Mobifone' => 'VMS',
        'Vietnamobile' => 'VNM',
        'Gmobile' => 'GMOBILE',
        'Wintel' => 'WT',
    ];

    /**
     * NOTE:
     * Map trang thai nguon (Active/Init/Lock) sang trang thai dang dung that trong he thong.
     * Da kiem tra source truoc khi map, khong doan:
     * - "khoi_tao" KHONG ton tai o bat ky dau trong source.
     * - "hoat_dong, tam_dung, khoa" la 3 trang thai da duoc document san trong comment cua
     *   migration create_nha_cung_cap_table.php va create_dai_ly_api_table.php
     *   ("Ví dụ: hoat_dong, tam_dung, khoa."), day la convention chung cho nhom bang danh muc/doi tac.
     * => Active = hoat_dong, Init = tam_dung (chua kich hoat/dang cho), Lock = khoa.
     *
     * Truoc khi seeder nay chay, bang loai_san_pham chi co 2 trang thai hoat_dong/tam_dung trong
     * Form Request validate (StoreLoaiSanPhamRequest/UpdateLoaiSanPhamRequest). Da bo sung them
     * 'khoa' vao whitelist cua 2 Form Request va CategoryController::statuses() de dong bo, tranh
     * truong hop admin mo sua lai 1 dong dang bi lock ma form khong nhan dien duoc trang thai nay.
     */
    private const TRANG_THAI_THEO_NGUON = [
        'Active' => 'hoat_dong',
        'Init' => 'tam_dung',
        'Lock' => 'khoa',
    ];

    /**
     * NOTE:
     * Danh sach cac nhom GOC theo nha mang (khong co dich vu rieng, khong co loai cha).
     * Cac nhom nay dung chung cho nhieu dich vu khac nhau (TOPUP, PIN_CODE, PIN_DATA, TOPUP_DATA)
     * nen dich_vu_id de null - cot dich_vu_id da duoc doi sang nullable o migration
     * make_dich_vu_id_nullable_in_loai_san_pham_table.
     *
     * Cau truc moi dong: [ma_loai_san_pham, ten_loai_san_pham, thu_tu, trang_thai_nguon]
     */
    private const NHOM_GOC = [
        ['VNA', 'Vinaphone', 2, 'Active'],
        ['VTE', 'Viettel', 1, 'Active'],
        ['VMS', 'Mobifone', 3, 'Active'],
        ['VNM', 'Vietnamobile', 4, 'Active'],
        ['GMOBILE', 'Gmobile', 5, 'Active'],
        ['WT', 'Wintel', 4, 'Init'],
    ];

    /**
     * NOTE:
     * Danh sach cac loai san pham "la" (khong lam cha cho dong nao khac).
     * Gom 2 nhom:
     * 1. Cac dong con thuoc 1 nha mang (co ten_loai_cha) - vd VTE_PINCODE thuoc cha "Viettel".
     * 2. Cac dong hoa don/game doc lap (khong co ten_loai_cha, khong co nhom goc rieng) - vd
     *    INTERNET_BILL, GARENA_GAME - cac dong nay loai_san_pham_cha_id = null truc tiep,
     *    KHONG phai la con cua VTE/VNA/... du cung khong co dich vu/cha.
     *
     * Cau truc moi dong: [ma_loai_san_pham, ten_loai_san_pham, thu_tu, trang_thai_nguon, ten_loai_cha_hoac_null, ten_dich_vu_hoac_null]
     */
    private const DANH_SACH_CON = [
        // --- Nhom Viettel (cha: VTE) ---
        ['VTE_PINCODE', 'Mã thẻ Viettel', 1, 'Active', 'Viettel', 'Mua mã thẻ'],
        ['VTE_PINDATA', 'Thẻ data Viettel', 1, 'Active', 'Viettel', 'Mua thẻ Data'],
        ['VTE_TOPUPDATA', 'Nạp Data Viettel', 1, 'Active', 'Viettel', 'Nạp Data'],
        ['VTE_TOPUP', 'Nạp tiền Viettel', 1, 'Active', 'Viettel', 'Nạp tiền điện thoại'],

        // --- Nhom Vinaphone (cha: VNA) ---
        ['VNA_PINCODE', 'Mã thẻ Vinaphone', 2, 'Active', 'Vinaphone', 'Mua mã thẻ'],
        ['VNA_PINDATA', 'Thẻ data Vina', 2, 'Active', 'Vinaphone', 'Mua thẻ Data'],
        ['VNA_TOPUPDATA', 'Nạp Data Vina', 2, 'Active', 'Vinaphone', 'Nạp Data'],
        ['VNA_TOPUP', 'Nạp tiền Vinaphone', 2, 'Active', 'Vinaphone', 'Nạp tiền điện thoại'],

        // --- Nhom Mobifone (cha: VMS) ---
        ['VMS_PINCODE', 'Mã thẻ Mobifone', 3, 'Active', 'Mobifone', 'Mua mã thẻ'],
        ['VMS_PINDATA', 'Thẻ data Mobifone', 3, 'Active', 'Mobifone', 'Mua thẻ Data'],
        ['VMS_TOPUPDATA', 'Nạp Data Mobifone', 3, 'Active', 'Mobifone', 'Nạp Data'],
        ['VMS_TOPUP', 'Nạp tiền Mobifone', 3, 'Active', 'Mobifone', 'Nạp tiền điện thoại'],

        // --- Nhom Vietnamobile (cha: VNM) ---
        ['VNM_PINCODE', 'Mã thẻ Vietnamobile', 4, 'Active', 'Vietnamobile', 'Mua mã thẻ'],
        ['VNM_TOPUP', 'Nạp tiền Vietnamobile', 4, 'Active', 'Vietnamobile', 'Nạp tiền điện thoại'],

        // --- Nhom Gmobile (cha: GMOBILE) ---
        ['GMOBILE_PINCODE', 'Mã thẻ Gmobile', 5, 'Active', 'Gmobile', 'Mua mã thẻ'],
        ['GMOBILE_TOPUP', 'Nạp tiền Gmobile', 5, 'Active', 'Gmobile', 'Nạp tiền điện thoại'],

        // --- Nhom Wintel (cha: WT) ---
        ['WT_PINCODE', 'Mã thẻ Wintel', 6, 'Init', 'Wintel', 'Mua mã thẻ'],
        ['WT_TOPUP', 'Nạp tiền Wintel', 6, 'Active', 'Wintel', 'Nạp tiền điện thoại'],

        // --- Hóa đơn (khong co loai cha) ---
        ['EVN_BILL', 'Hóa đơn điện', 1, 'Active', null, 'Thanh toán hóa đơn'],
        ['WATER_BILL', 'Hóa đơn nước', 2, 'Active', null, 'Thanh toán hóa đơn'],
        ['INTERNET_BILL', 'Hóa đơn internet', 3, 'Active', null, 'Thanh toán hóa đơn'],
        ['TV_BILL', 'Hóa đơn truyền hình', 4, 'Active', null, 'Thanh toán hóa đơn'],
        ['MOBILE_BILL', 'Điện thoại trả sau', 5, 'Active', null, 'Thanh toán hóa đơn'],
        ['TELEPHONE_BILL', 'Điện thoại cố định', 6, 'Active', null, 'Thanh toán hóa đơn'],
        ['TICKET_BILL', 'Vé tàu/Vé xe/Máy bay', 7, 'Active', null, 'Thanh toán hóa đơn'],
        ['FINANCE_BILL', 'Thanh toán trả góp/Vay tiêu dùng', 8, 'Active', null, 'Thanh toán hóa đơn'],
        ['INS_BILL', 'Bảo hiểm', 9, 'Init', null, 'Thanh toán hóa đơn'],

        // --- Thẻ game (khong co loai cha) ---
        ['GARENA_GAME', 'Mã thẻ Garena', 1, 'Active', null, 'Mua thẻ game'],
        ['ZING_GAME', 'Mã thẻ Zing', 3, 'Active', null, 'Mua thẻ game'],
        ['GATE_GAME', 'Mã thẻ Gate', 4, 'Active', null, 'Mua thẻ game'],
        ['VCOIN_GAME', 'Mã thẻ Vcoin', 5, 'Active', null, 'Mua thẻ game'],
        ['ONCA_GAME', 'Mã thẻ OnCash', 6, 'Active', null, 'Mua thẻ game'],
        ['SOHA_GAME', 'Mã thẻ Soha Coin', 7, 'Active', null, 'Mua thẻ game'],
        ['BIT_GAME', 'Mã thẻ Bit', 8, 'Active', null, 'Mua thẻ game'],
        ['FUNCA_GAME', 'Mã thẻ Funcash', 9, 'Active', null, 'Mua thẻ game'],
        ['SCOIN_GAME', 'Mã thẻ Scoin', 10, 'Active', null, 'Mua thẻ game'],
        ['APPOTA_GAME', 'Mã thẻ Appota', 11, 'Active', null, 'Mua thẻ game'],
        ['GOSU_GAME', 'Mã thẻ Gosu', 12, 'Active', null, 'Mua thẻ game'],
        ['KUL_GAME', 'Mã thẻ Kul', 13, 'Active', null, 'Mua thẻ game'],
        ['VEGA_GAME', 'Mã thẻ Vega', 14, 'Init', null, 'Mua thẻ game'],
        ['MEGA_GAME', 'Mã thẻ Megacard', 15, 'Lock', null, 'Mua thẻ game'],
        ['VCARD_GAME', 'Mã thẻ VCARD', 16, 'Lock', null, 'Mua thẻ game'],
        ['VINPLAY_GAME', 'Mã thẻ VINPLAY', 17, 'Lock', null, 'Mua thẻ game'],
        ['ANPAY_GAME', 'Mã thẻ ANPAY', 18, 'Lock', null, 'Mua thẻ game'],
        ['VTC_GAME', 'Mã thẻ VTC', 19, 'Active', null, 'Mua thẻ game'],
        ['ON_GAME', 'Mã thẻ ONGAME', 20, 'Active', null, 'Mua thẻ game'],
    ];

    public function run(): void
    {
        // BƯỚC 1: Tạo các nhóm GỐC theo nhà mạng trước (VTE, VNA, VMS, VNM, GMOBILE, WT).
        // Bắt buộc chạy bước này trước bước 2 vì các loại sản phẩm con ở bước 2 cần
        // loai_san_pham_cha_id trỏ về id của các dòng được tạo ở đây.
        foreach (self::NHOM_GOC as [$ma, $ten, $thuTu, $trangThaiNguon]) {
            LoaiSanPham::updateOrCreate(
                ['ma_loai_san_pham' => $ma],
                [
                    'ten_loai_san_pham' => $ten,
                    'thu_tu' => $thuTu,
                    'trang_thai' => self::TRANG_THAI_THEO_NGUON[$trangThaiNguon],
                    // NOTE: Nhóm gốc không thuộc riêng 1 dịch vụ nào (dùng chung cho nhiều dịch vụ)
                    // và không có loại cha (đây là gốc của cây danh mục).
                    'dich_vu_id' => null,
                    'loai_san_pham_cha_id' => null,
                ]
            );
        }

        // BƯỚC 2: Tạo các loại sản phẩm con/độc lập, sau khi nhóm gốc đã tồn tại.
        foreach (self::DANH_SACH_CON as [$ma, $ten, $thuTu, $trangThaiNguon, $tenLoaiCha, $tenDichVu]) {
            $dichVuId = $this->timDichVuId($tenDichVu, $ma);
            $loaiChaId = $this->timLoaiChaId($tenLoaiCha, $ma);

            LoaiSanPham::updateOrCreate(
                ['ma_loai_san_pham' => $ma],
                [
                    'ten_loai_san_pham' => $ten,
                    'thu_tu' => $thuTu,
                    'trang_thai' => self::TRANG_THAI_THEO_NGUON[$trangThaiNguon],
                    'dich_vu_id' => $dichVuId,
                    'loai_san_pham_cha_id' => $loaiChaId,
                ]
            );
        }
    }

    /**
     * NOTE:
     * Tìm dich_vu_id theo đúng ten_dich_vu (KHÔNG insert cứng số id).
     * Nếu $tenDichVu rỗng (dòng không thuộc dịch vụ nào, ví dụ nhóm gốc theo nhà mạng)
     * thì trả về null luôn, không coi là lỗi.
     * Nếu $tenDichVu có giá trị nhưng không tìm thấy trong bảng dich_vu, ghi log cảnh báo
     * và trả về null để KHÔNG insert sai dich_vu_id, thay vì làm sập toàn bộ seeder.
     */
    private function timDichVuId(?string $tenDichVu, string $maLoaiSanPham): ?int
    {
        if (empty($tenDichVu)) {
            return null;
        }

        $dichVu = DichVu::where('ten_dich_vu', $tenDichVu)->first();

        if (! $dichVu) {
            Log::warning("LoaiSanPhamSeeder: Không tìm thấy dịch vụ '{$tenDichVu}' cho loại sản phẩm '{$maLoaiSanPham}'. dich_vu_id sẽ để trống (null).");

            return null;
        }

        return $dichVu->id;
    }

    /**
     * NOTE:
     * Tìm loai_san_pham_cha_id theo tên loại cha (vd "Viettel") thông qua bảng MA_CHA_THEO_TEN
     * để tra ra đúng ma_loai_san_pham của nhóm gốc (vd "VTE"), rồi tìm id thật trong DB.
     * Nhóm gốc luôn được tạo trước ở BƯỚC 1 nên tại đây chắc chắn đã tồn tại.
     */
    private function timLoaiChaId(?string $tenLoaiCha, string $maLoaiSanPham): ?int
    {
        if (empty($tenLoaiCha)) {
            return null;
        }

        $maCha = self::MA_CHA_THEO_TEN[$tenLoaiCha] ?? null;

        if (! $maCha) {
            Log::warning("LoaiSanPhamSeeder: Không xác định được mã loại cha cho tên '{$tenLoaiCha}' (loại sản phẩm '{$maLoaiSanPham}'). loai_san_pham_cha_id sẽ để trống (null).");

            return null;
        }

        $loaiCha = LoaiSanPham::where('ma_loai_san_pham', $maCha)->first();

        if (! $loaiCha) {
            Log::warning("LoaiSanPhamSeeder: Loại cha mã '{$maCha}' chưa tồn tại trong database khi tạo '{$maLoaiSanPham}'. loai_san_pham_cha_id sẽ để trống (null).");

            return null;
        }

        return $loaiCha->id;
    }
}
