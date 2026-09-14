<?php

namespace Database\Seeders;

use App\Models\MaLoiNhaCungCap;
use App\Models\NhaCungCap;
use Illuminate\Database\Seeder;

class MaLoiNhaCungCapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $appota = NhaCungCap::where('ma_ncc', 'APPOTAPAY')->orWhere('ma_ncc', 'appotapay')->first();
        $appotaId = $appota?->id;

        $errorCodes = [
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '0',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_SUCCESS,
                'thong_bao_goc' => 'Thành công (Success)',
                'thong_bao_hien_thi' => 'Nạp tiền thành công',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_NONE,
                'mo_ta' => 'Giao dịch nạp tiền thành công từ cổng AppotaPay.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '34',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING,
                'thong_bao_goc' => 'Giao dịch đang chờ xử lý vui lòng kiểm tra lại sau',
                'thong_bao_hien_thi' => 'Giao dịch đang chờ nhà mạng xử lý, hệ thống đang tự động kiểm tra lại',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_RETRY_STATUS,
                'mo_ta' => 'Nhà mạng đang xử lý nạp tiền, cần hẹn giờ query lại trạng thái.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '35',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING,
                'thong_bao_goc' => 'Giao dịch nghi vấn đang được xử lý',
                'thong_bao_hien_thi' => 'Giao dịch đang được rà soát, hệ thống đang tự động kiểm tra lại',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_RETRY_STATUS,
                'mo_ta' => 'Giao dịch nghi vấn cần kiểm tra trạng thái lại sau.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '99',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_UNKNOWN_OR_PENDING,
                'thong_bao_goc' => 'Trạng thái giao dịch chưa xác định / Timeout kết nối',
                'thong_bao_hien_thi' => 'Chưa nhận được phản hồi từ nhà mạng, đang chờ truy vấn lại',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_RETRY_STATUS,
                'mo_ta' => 'Lỗi mất kết nối hoặc timeout khi gọi NCC, không chắc chắn thuê bao đã nạp chưa.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '1',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Giao dịch thất bại / Lỗi hệ thống NCC',
                'thong_bao_hien_thi' => 'Nạp tiền thất bại do lỗi hệ thống nhà cung cấp',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'NCC từ chối giao dịch do lỗi hệ thống, tự động hoàn tiền ví cho khách.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '2',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Sai chữ ký bảo mật (Invalid signature)',
                'thong_bao_hien_thi' => 'Lỗi xác thực chữ ký bảo mật với nhà cung cấp',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Chữ ký HMAC SHA256 không khớp, cần kiểm tra lại secret key.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '3',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Tham số yêu cầu không hợp lệ (Invalid parameters)',
                'thong_bao_hien_thi' => 'Thông tin yêu cầu nạp tiền không hợp lệ',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Thiếu trường dữ liệu hoặc dữ liệu gửi đi không đúng chuẩn.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '4',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Tài khoản đối tác không tồn tại hoặc bị khóa',
                'thong_bao_hien_thi' => 'Tài khoản kết nối nhà cung cấp chưa được kích hoạt',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Partner code hoặc API Key bị vô hiệu hóa trên cổng NCC.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '5',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Địa chỉ IP máy chủ chưa được cho phép (IP not in whitelist)',
                'thong_bao_hien_thi' => 'IP máy chủ chưa được cấp quyền gọi API nhà cung cấp',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Cần thêm IP máy chủ vào Whitelist trên cổng AppotaPay.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '10',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Số dư tài khoản nhà cung cấp không đủ',
                'thong_bao_hien_thi' => 'Số dư tài khoản tại nhà cung cấp không đủ để nạp',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Tài khoản đại lý tại NCC hết tiền, cần nạp thêm.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '11',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Số điện thoại nhận tiền không hợp lệ hoặc bị chặn',
                'thong_bao_hien_thi' => 'Số điện thoại nhận không hợp lệ hoặc bị nhà mạng từ chối',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Thuê bao không tồn tại, thuê bao bị khóa nạp tiền.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '12',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Nhà mạng đang bảo trì hoặc tạm ngưng dịch vụ',
                'thong_bao_hien_thi' => 'Nhà mạng đang bảo trì hoặc tạm ngưng dịch vụ nạp tiền',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Nhà mạng Viettel/Vina/Mobi đang bảo trì kênh nạp.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '13',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Mã sản phẩm hoặc mệnh giá nạp không hợp lệ',
                'thong_bao_hien_thi' => 'Mệnh giá nạp không tồn tại hoặc đã ngừng hỗ trợ',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'Gói cước hoặc mệnh giá không khớp với nhà mạng.',
                'trang_thai' => 'hoat_dong',
            ],
            [
                'nha_cung_cap_id' => $appotaId,
                'ma_loi' => '20',
                'loai_ket_qua' => MaLoiNhaCungCap::RESULT_DEFINITIVE_FAILURE,
                'thong_bao_goc' => 'Trùng mã giao dịch partnerRefId',
                'thong_bao_hien_thi' => 'Mã tham chiếu giao dịch đã tồn tại trên nhà cung cấp',
                'hanh_dong_he_thong' => MaLoiNhaCungCap::ACTION_REFUND_WALLET,
                'mo_ta' => 'partnerRefId bị trùng lặp.',
                'trang_thai' => 'hoat_dong',
            ],
        ];

        foreach ($errorCodes as $data) {
            MaLoiNhaCungCap::updateOrCreate(
                [
                    'nha_cung_cap_id' => $data['nha_cung_cap_id'],
                    'ma_loi' => $data['ma_loi'],
                ],
                $data
            );
        }
    }
}
