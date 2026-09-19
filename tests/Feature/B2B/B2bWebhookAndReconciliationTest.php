<?php

namespace Tests\Feature\B2B;

use App\Exports\ReconciliationExport;
use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KyDoiSoat;
use App\Models\LichSuGuiWebhook;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\SoPhatSinhCongNo;
use App\Models\WebhookOutbox;
use App\Services\DaiLyApi\B2bCreditService;
use App\Services\DaiLyApi\B2bReconciliationService;
use App\Services\DaiLyApi\B2bWebhookService;
use App\Services\Security\UrlSafetyValidator;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class B2bWebhookAndReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected SanPham $product;
    protected string $webhookSecret = 'webhook_secret_key_testing_987654321';
    protected B2bCreditService $creditService;
    protected B2bWebhookService $webhookService;
    protected B2bReconciliationService $reconciliationService;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->creditService = app(B2bCreditService::class);
        $this->webhookService = app(B2bWebhookService::class);
        $this->reconciliationService = app(B2bReconciliationService::class);

        $dichVu = DichVu::firstOrCreate(['ma_dich_vu' => 'MOBILE_TOPUP'], [
            'ten_dich_vu' => 'Nạp tiền điện thoại',
            'trang_thai' => 'hoat_dong',
        ]);

        $loaiSp = LoaiSanPham::firstOrCreate(['ma_loai_san_pham' => 'VTE_TOPUP'], [
            'ten_loai_san_pham' => 'Viettel Topup',
            'dich_vu_id' => $dichVu->id,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->product = SanPham::firstOrCreate(['ma_san_pham' => 'TEST_RECON_VT_10K'], [
            'ten_san_pham' => 'Viettel 10.000đ Recon Test',
            'dich_vu_id' => $dichVu->id,
            'loai_san_pham_id' => $loaiSp->id,
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'RECON_' . strtoupper(Str::random(6)),
            'ten_dai_ly_api' => 'Đại lý Recon Webhook Test',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'client_' . strtolower(Str::random(10)),
            'secret_key_ma_hoa' => 'sec_' . Str::random(16),
            'webhook_url' => 'https://partner.example.com/api/webhook',
            'webhook_secret_ma_hoa' => $this->webhookSecret,
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => 5000000,
            'cong_no_hien_tai' => 500000,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
        ]);
    }

    public function test_webhook_event_creation_on_order(): void
    {
        $donHang = DonHang::create([
            'ma_don_hang' => 'ORD_WH_' . time(),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'PARTNER_WH_' . time(),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0988123456',
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai_don_hang' => 'SUCCESS',
            'hoan_thanh_luc' => now(),
        ]);

        $outbox = $this->webhookService->taoSuKien($donHang, 'ORDER_COMPLETED');

        $this->assertNotNull($outbox);
        $this->assertEquals('PENDING', $outbox->trang_thai);
        $this->assertEquals('ORDER_COMPLETED', $outbox->event_type);
        $this->assertEquals($donHang->id, $outbox->don_hang_id);

        $payload = json_decode($outbox->payload_json, true);
        $this->assertEquals('ORDER_COMPLETED', $payload['event_type']);
        $this->assertEquals($donHang->id, $payload['data']['order_id']);

        // Hợp đồng webhook dùng CHUNG bộ ánh xạ trạng thái công khai với API tra cứu.
        // Trước đây payload trả thẳng trạng thái nội bộ (SUCCESS/PROVIDER_PENDING/...),
        // rò rỉ chi tiết triển khai ra hợp đồng đối tác. Nay trả trạng thái công khai.
        $this->assertEquals('success', $payload['data']['status']);
        $this->assertEquals('00', $payload['data']['result_code']);
        $this->assertNotNull($payload['data']['completed_at']);
    }

    public function test_ssrf_validator_rejects_internal_and_dangerous_urls(): void
    {
        $dangerousUrls = [
            'http://169.254.169.254/latest/meta-data',
            'http://10.0.0.1/hook',
            'http://192.168.1.1/hook',
            'http://172.16.0.1/hook',
            'file:///etc/passwd',
            'gopher://127.0.0.1:11211',
            'ftp://partner.example.com/test',
        ];

        foreach ($dangerousUrls as $url) {
            $threw = false;
            try {
                UrlSafetyValidator::validate($url);
            } catch (InvalidArgumentException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "URL '{$url}' should have been rejected by SSRF validator.");
        }

        // HTTPS URL công khai hợp lệ
        $valid = UrlSafetyValidator::validate('https://api.partner.example.com/webhook');
        $this->assertTrue($valid);
    }

    public function test_webhook_delivery_success_and_signature(): void
    {
        Http::fake([
            'partner.example.com/*' => Http::response(['status' => 'received'], 200),
        ]);

        $outbox = WebhookOutbox::create([
            'event_id' => (string) Str::uuid(),
            'dai_ly_api_id' => $this->partner->id,
            'event_type' => 'ORDER_COMPLETED',
            'payload_json' => json_encode(['event' => 'test', 'data' => ['foo' => 'bar']]),
            'trang_thai' => 'PENDING',
            'so_lan_thu' => 0,
        ]);

        $success = $this->webhookService->guiWebhook($outbox);

        $this->assertTrue($success);
        $this->assertEquals('SUCCESS', $outbox->fresh()->trang_thai);

        // Kiểm tra chữ ký HMAC trong request headers
        Http::assertSent(function ($request) use ($outbox) {
            $ts = $request->header('X-Webhook-Timestamp')[0] ?? '';
            $sig = $request->header('X-Webhook-Signature')[0] ?? '';
            $expectedSig = hash_hmac('sha256', "{$ts}\n{$outbox->payload_json}", $this->webhookSecret);
            return $sig === $expectedSig && $request->url() === 'https://partner.example.com/api/webhook';
        });

        // Kiểm tra lịch sử gửi được lưu
        $log = LichSuGuiWebhook::where('webhook_outbox_id', $outbox->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(200, $log->http_status);
    }

    public function test_webhook_delivery_failure_schedules_exponential_backoff(): void
    {
        Http::fake([
            'partner.example.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $outbox = WebhookOutbox::create([
            'event_id' => (string) Str::uuid(),
            'dai_ly_api_id' => $this->partner->id,
            'event_type' => 'ORDER_COMPLETED',
            'payload_json' => json_encode(['event' => 'test']),
            'trang_thai' => 'PENDING',
            'so_lan_thu' => 0,
        ]);

        $success = $this->webhookService->guiWebhook($outbox);

        $this->assertFalse($success);
        $fresh = $outbox->fresh();
        $this->assertEquals('PENDING', $fresh->trang_thai);
        $this->assertEquals(1, $fresh->so_lan_thu);
        $this->assertNotNull($fresh->lan_thu_tiep_theo);
        $this->assertTrue($fresh->lan_thu_tiep_theo->isFuture());

        // Lịch sử ghi nhận mã lỗi 500
        $log = LichSuGuiWebhook::where('webhook_outbox_id', $outbox->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(500, $log->http_status);
    }

    public function test_credit_payment_reduces_debt_and_records_ledger(): void
    {
        $this->assertEquals(500000, (float) $this->config->fresh()->cong_no_hien_tai);

        // Ghi nhận thanh toán 200,000 VND
        $payment = $this->creditService->ghiNhanThanhToan(
            $this->partner,
            200000,
            'CHUYEN_KHOAN',
            'MB999888777',
            'Đối tác thanh toán kỳ 1'
        );

        $this->assertNotNull($payment);
        $this->assertEquals(200000, (float) $payment->so_tien);
        $this->assertEquals('DA_XAC_NHAN', $payment->trang_thai);

        // Công nợ hiện tại giảm còn 300,000
        $this->assertEquals(300000, (float) $this->config->fresh()->cong_no_hien_tai);

        // Sổ phát sinh công nợ ghi giảm
        $ledger = SoPhatSinhCongNo::where('dai_ly_api_id', $this->partner->id)
            ->where('loai_phat_sinh', 'GIAM_CONG_NO_THANH_TOAN')
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(200000, (float) $ledger->so_tien);
        $this->assertEquals(500000, (float) $ledger->so_du_truoc);
        $this->assertEquals(300000, (float) $ledger->so_du_sau);
    }

    public function test_credit_adjustment_increases_and_decreases_debt(): void
    {
        $initialDebt = (float) $this->config->fresh()->cong_no_hien_tai; // 500,000

        // 1. Tăng nợ 50,000
        $adjTang = $this->creditService->ghiNhanDieuChinh(
            $this->partner,
            'TANG_NO',
            50000,
            'Phí phụ trợ phát sinh'
        );
        $this->assertEquals('TANG_NO', $adjTang->loai_dieu_chinh);
        $this->assertEquals($initialDebt + 50000, (float) $this->config->fresh()->cong_no_hien_tai);

        // 2. Giảm nợ 20,000
        $adjGiam = $this->creditService->ghiNhanDieuChinh(
            $this->partner,
            'GIAM_NO',
            20000,
            'Giảm trừ chiết khấu sự kiện'
        );
        $this->assertEquals('GIAM_NO', $adjGiam->loai_dieu_chinh);
        $this->assertEquals($initialDebt + 50000 - 20000, (float) $this->config->fresh()->cong_no_hien_tai);
    }

    /**
     * Tạo một đơn B2B đã thành công và ĐÃ GHI NỢ qua đúng nghiệp vụ
     * (giữ hạn mức -> chốt công nợ), để sổ phát sinh công nợ có bút toán
     * TANG_CONG_NO_DON_HANG tương ứng.
     *
     * Không được tạo DonHang/ThanhToanCongNo trực tiếp trong test đối soát: làm vậy
     * sẽ bỏ qua sổ cái và test chỉ còn kiểm chứng một đường tính toán không tồn tại
     * trong production.
     *
     * @param string $thoiDiemTaoDon Thời điểm tạo đơn (created_at của don_hang)
     * @param string $thoiDiemGhiNo  Thời điểm NCC báo thành công và công nợ được ghi nhận
     */
    private function taoDonHangDaGhiNo(
        string $thoiDiemTaoDon,
        string $thoiDiemGhiNo,
        string $taiKhoan,
        string $hauTo
    ): DonHang {
        Carbon::setTestNow($thoiDiemTaoDon);

        $don = DonHang::create([
            'ma_don_hang' => 'ORD_RECON_' . $hauTo . '_' . strtoupper(Str::random(6)),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'PARTNER_' . $hauTo . '_' . strtoupper(Str::random(6)),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => $taiKhoan,
            'menh_gia' => 10000,
            'gia_ban' => 9700,
            'trang_thai_don_hang' => 'PROCESSING',
        ]);

        Carbon::setTestNow($thoiDiemGhiNo);

        DB::transaction(function () use ($don) {
            $this->creditService->kiemTraVaGiuHanMuc($this->partner, $don, 9700);
            $don->update(['trang_thai_don_hang' => 'SUCCESS']);
            $this->creditService->chotCongNoThanhCong($don);
        });

        Carbon::setTestNow();

        return $don->fresh();
    }

    public function test_periodic_reconciliation_calculation_and_lock(): void
    {
        $tuNgay = '2026-09-01';
        $denNgay = '2026-09-30';

        // Đại lý khởi đầu với dư nợ bằng 0 để số liệu kỳ suy ra hoàn toàn từ sổ phát sinh,
        // nhờ đó kiểm chứng được cả bất biến cân đối công nợ ở cuối test.
        $this->config->update(['cong_no_hien_tai' => 0]);

        // 1. Hai đơn hàng thành công trong kỳ (mỗi đơn 9,700), ghi nợ qua B2bCreditService
        $this->taoDonHangDaGhiNo('2026-09-10 10:00:00', '2026-09-10 10:00:00', '0988111222', 'R1');
        $this->taoDonHangDaGhiNo('2026-09-15 11:00:00', '2026-09-15 11:00:00', '0988333444', 'R2');

        // 2. Thanh toán 10,000 trong kỳ, ghi nhận qua đúng nghiệp vụ
        Carbon::setTestNow('2026-09-20 14:00:00');
        $this->creditService->ghiNhanThanhToan(
            $this->partner,
            10000,
            'CHUYEN_KHOAN',
            null,
            'Thanh toán công nợ kỳ đối soát test'
        );
        Carbon::setTestNow();

        // 3. Tạo kỳ đối soát
        $ky = $this->reconciliationService->taoKyDoiSoat($this->partner, $tuNgay, $denNgay);

        $this->assertEquals(0, (float) $ky->so_du_dau_ky);
        $this->assertEquals(19400, (float) $ky->tong_phat_sinh_tang); // 9700 * 2
        $this->assertEquals(10000, (float) $ky->tong_thanh_toan);
        $this->assertEquals(9400, (float) $ky->so_du_cuoi_ky); // 0 + 19400 - 10000
        $this->assertEquals('DANG_MO', $ky->trang_thai);
        $this->assertEquals(2, $ky->chiTiet()->count());

        // 3b. Số liệu kỳ phải khớp với số dư tổng hợp trên cấu hình đại lý
        $canDoi = $this->creditService->kiemTraCanDoiCongNo($this->partner->fresh());
        $this->assertTrue(
            $canDoi['can_doi'],
            'Sổ công nợ phải cân đối với số dư tổng hợp: ' . json_encode($canDoi)
        );
        $this->assertEquals((float) $ky->so_du_cuoi_ky, $canDoi['so_du_tong_hop']);

        // 4. Khóa kỳ đối soát
        $this->reconciliationService->khoaKy($ky, null);
        $this->assertEquals('DA_KHOA', $ky->fresh()->trang_thai);

        // 5. Thử tính toán lại kỳ đã khóa sẽ ném ngoại lệ
        $this->expectException(DomainException::class);
        $this->reconciliationService->tinhToanLaiKy($ky->fresh());
    }

    /**
     * Đơn tạo cuối tháng trước nhưng công nợ chỉ được ghi nhận đầu tháng này
     * phải thuộc kỳ phát sinh nợ THỰC TẾ, không phải kỳ theo ngày tạo đơn.
     */
    public function test_reconciliation_counts_by_ledger_date_not_order_creation_date(): void
    {
        $this->config->update(['cong_no_hien_tai' => 0]);

        $donTre = $this->taoDonHangDaGhiNo(
            '2026-08-28 23:30:00',  // đơn được tạo trong tháng 8
            '2026-09-05 09:00:00',  // NCC báo thành công, ghi nợ trong tháng 9
            '0988777666',
            'LATE'
        );

        // Kỳ tháng 8 KHÔNG được chứa đơn này dù đơn được tạo trong tháng 8
        $kyThang8 = $this->reconciliationService->taoKyDoiSoat($this->partner, '2026-08-01', '2026-08-31');
        $this->assertEquals(0, (float) $kyThang8->tong_phat_sinh_tang);
        $this->assertEquals(0, $kyThang8->chiTiet()->count());

        // Kỳ tháng 9 phải chứa đúng đơn này
        $kyThang9 = $this->reconciliationService->taoKyDoiSoat($this->partner, '2026-09-01', '2026-09-30');
        $this->assertEquals(9700, (float) $kyThang9->tong_phat_sinh_tang);
        $this->assertEquals(9700, (float) $kyThang9->so_du_cuoi_ky);
        $this->assertEquals(1, $kyThang9->chiTiet()->count());

        $chiTiet = $kyThang9->chiTiet()->first();
        $this->assertEquals($donTre->id, $chiTiet->don_hang_id);
        // Chi tiết vẫn lưu ngày tạo đơn gốc để tra cứu, nhưng kỳ được tính theo ngày ghi nợ
        $this->assertEquals('2026-08-28', $chiTiet->ngay_tao_don->toDateString());
    }

    /**
     * Hai kỳ của cùng một đại lý không được chồng lấn, nếu không cùng một bút toán
     * sẽ bị tính vào hai kỳ và công nợ bị nhân đôi khi đối chiếu.
     */
    public function test_reconciliation_rejects_overlapping_periods(): void
    {
        $this->reconciliationService->taoKyDoiSoat($this->partner, '2026-09-01', '2026-09-30');

        $this->expectException(DomainException::class);
        $this->reconciliationService->taoKyDoiSoat($this->partner, '2026-09-15', '2026-10-15');
    }

    /**
     * Kỳ đã khóa là bất biến: không được tạo lại, không được tính lại.
     */
    public function test_locked_reconciliation_period_cannot_be_recreated(): void
    {
        $ky = $this->reconciliationService->taoKyDoiSoat($this->partner, '2026-09-01', '2026-09-30');
        $this->reconciliationService->khoaKy($ky, null);

        $this->expectException(DomainException::class);
        $this->reconciliationService->taoKyDoiSoat($this->partner, '2026-09-01', '2026-09-30');
    }

    public function test_reconciliation_excel_export(): void
    {
        $ky = KyDoiSoat::create([
            'dai_ly_api_id' => $this->partner->id,
            'ma_ky' => 'DS_EXPORT_' . time(),
            'tu_ngay' => '2026-09-01',
            'den_ngay' => '2026-09-30',
            'so_du_dau_ky' => 100000,
            'tong_phat_sinh_tang' => 50000,
            'tong_phat_sinh_giam' => 0,
            'tong_thanh_toan' => 30000,
            'tong_dieu_chinh' => 0,
            'so_du_cuoi_ky' => 120000,
            'trang_thai' => 'DA_KHOA',
        ]);

        $export = new ReconciliationExport($ky);
        $headings = $export->headings();
        $this->assertContains('Mã đơn hệ thống', $headings);
        $this->assertContains('Mã đơn đối tác', $headings);
        $this->assertContains('Giá đại lý (VNĐ)', $headings);

        $collection = $export->collection();
        $this->assertNotNull($collection);
    }
}
