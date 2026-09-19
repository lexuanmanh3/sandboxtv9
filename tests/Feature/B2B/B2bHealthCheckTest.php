<?php

namespace Tests\Feature\B2B;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Services\DaiLyApi\B2bCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiểm thử lưới an toàn vận hành: lệnh b2b:health-check phải phát hiện được
 * các bất biến bị vi phạm và trả mã thoát khác 0 để monitoring bắt được.
 */
class B2bHealthCheckTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected SanPham $product;

    protected function setUp(): void
    {
        parent::setUp();

        $dichVu = DichVu::firstOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại',
            'trang_thai' => 'hoat_dong',
        ]);

        $loaiSp = LoaiSanPham::firstOrCreate(['ma_loai_san_pham' => 'VTE_TOPUP'], [
            'ten_loai_san_pham' => 'Viettel Topup',
            'dich_vu_id' => $dichVu->id,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->product = SanPham::firstOrCreate(['ma_san_pham' => 'TEST_HEALTH_VT_10K'], [
            'ten_san_pham' => 'Viettel 10.000đ Health Test',
            'dich_vu_id' => $dichVu->id,
            'loai_san_pham_id' => $loaiSp->id,
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'HEALTH_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý Health Check Test',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'health_client_' . strtolower(Str::random(8)),
            'secret_key_ma_hoa' => 'sec_' . Str::random(16),
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => 5000000,
            'cong_no_hien_tai' => 0,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
        ]);
    }

    private function chayHealthCheck(): array
    {
        $exitCode = Artisan::call('b2b:health-check', ['--stale-hours' => 1, '--json' => true]);

        return [
            'exit_code' => $exitCode,
            'output' => Artisan::output(),
        ];
    }

    private function taoDonHang(string $trangThai): DonHang
    {
        return DonHang::create([
            'ma_don_hang' => 'ORD_HEALTH_' . strtoupper(Str::random(8)),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'HEALTH_REF_' . strtoupper(Str::random(8)),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0965657810',
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai_don_hang' => $trangThai,
        ]);
    }

    /**
     * Đơn đã kết thúc nhưng khoản giữ vẫn HOLDING: hạn mức của đại lý bị chiếm dụng sai
     * và không có gì khác trong hệ thống phát hiện ra.
     */
    public function test_health_check_detects_stuck_credit_hold(): void
    {
        $donHang = $this->taoDonHang('SUCCESS');

        $hold = KhoanGiuHanMuc::create([
            'dai_ly_api_id' => $this->partner->id,
            'don_hang_id' => $donHang->id,
            'so_tien_giu' => 9700,
            'trang_thai' => 'HOLDING',
        ]);
        $hold->forceFill(['created_at' => now()->subHours(3)])->save();

        $kq = $this->chayHealthCheck();

        $this->assertSame(1, $kq['exit_code'], 'Phải trả mã thoát khác 0 khi có báo động.');
        $this->assertStringContainsString('STUCK_CREDIT_HOLD', $kq['output']);
        $this->assertStringContainsString($donHang->ma_don_hang, $kq['output']);
    }

    /**
     * Số dư tổng hợp lệch khỏi sổ phát sinh bất biến: dấu hiệu nghiêm trọng nhất
     * về sai lệch tiền. Lệnh kiểm tra phải phát hiện và TUYỆT ĐỐI không tự sửa.
     */
    public function test_health_check_detects_debt_ledger_drift(): void
    {
        $creditService = app(B2bCreditService::class);

        $creditService->ghiNhanDieuChinh($this->partner, 'TANG_NO', 50000, 'Ghi nợ hợp lệ ban đầu');
        $this->assertEquals(50000.0, (float) $this->config->fresh()->cong_no_hien_tai);

        // Ghi lệch số dư tổng hợp bằng đường vòng (mô phỏng can thiệp tay / lỗi dữ liệu)
        DB::table('cau_hinh_api_dai_ly')
            ->where('id', $this->config->id)
            ->update(['cong_no_hien_tai' => 12345]);

        $kq = $this->chayHealthCheck();

        $this->assertSame(1, $kq['exit_code']);
        $this->assertStringContainsString('DEBT_LEDGER_DRIFT', $kq['output']);
        $this->assertStringContainsString($this->partner->ma_dai_ly_api, $kq['output']);

        // Lệnh kiểm tra chỉ BÁO CÁO, không được tự ý sửa số dư
        $this->assertEquals(12345.0, (float) $this->config->fresh()->cong_no_hien_tai, 'Health check không được tự sửa số dư.');
    }

    /**
     * Đại lý sạch (sổ cái khớp số dư, không có khoản giữ treo) không được báo động.
     */
    public function test_health_check_does_not_flag_consistent_partner(): void
    {
        $creditService = app(B2bCreditService::class);
        $creditService->ghiNhanDieuChinh($this->partner, 'TANG_NO', 50000, 'Ghi nợ hợp lệ ban đầu');

        $kq = $this->chayHealthCheck();

        $this->assertStringNotContainsString($this->partner->ma_dai_ly_api, $kq['output']);
        $this->assertStringNotContainsString('DEBT_LEDGER_DRIFT', $kq['output']);
        $this->assertStringNotContainsString('STUCK_CREDIT_HOLD', $kq['output']);
    }
}
