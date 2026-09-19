<?php

namespace Tests\Feature\B2B;

use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\KhoanGiuHanMuc;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\SoPhatSinhCongNo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kiểm thử TƯƠNG TRANH THẬT (§13) — các tiến trình PHP riêng biệt, kết nối DB riêng,
 * lao vào cùng một thời điểm qua cơ chế barrier file.
 *
 * VÌ SAO KHÔNG DÙNG DatabaseTransactions:
 * trait đó bọc toàn bộ test trong MỘT transaction. Dữ liệu chưa commit thì các tiến
 * trình khác KHÔNG nhìn thấy, nên mọi tranh chấp khoá/unique-index sẽ biến mất và
 * test sẽ "xanh giả". Ở đây dữ liệu được COMMIT thật và dọn dẹp tường minh ở tearDown.
 *
 * Đây là bằng chứng cho nguyên tắc #4: chống xử lý lặp ở cấp database.
 */
class B2bConcurrencyTest extends TestCase
{
    protected DaiLyApi $partner;
    protected CauHinhApiDaiLy $config;
    protected SanPham $product;

    /** @var string[] */
    private array $tienTrinhDangChay = [];
    private array $fileTam = [];

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

        $this->product = SanPham::firstOrCreate(['ma_san_pham' => 'TOPUP_VTE_10K'], [
            'ten_san_pham' => 'Viettel 10.000đ Chuẩn',
            'dich_vu_id' => $dichVu->id,
            'loai_san_pham_id' => $loaiSp->id,
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'chiet_khau' => 0,
            'gia_nhap' => 9500,
            'trang_thai' => 'hoat_dong',
        ]);

        $this->taoDoiTac(5000000);
    }

    protected function tearDown(): void
    {
        foreach ($this->tienTrinhDangChay as $p) {
            @proc_terminate($p);
            @proc_close($p);
        }
        foreach ($this->fileTam as $f) {
            @unlink($f);
        }
        $this->donDepDuLieu();
        parent::tearDown();
    }

    private function taoDoiTac(float $hanMuc): void
    {
        $this->partner = DaiLyApi::create([
            'ma_dai_ly_api' => 'CONC_' . strtoupper(Str::random(8)),
            'ten_dai_ly_api' => 'Đại lý kiểm thử tương tranh',
            'trang_thai' => 'hoat_dong',
        ]);

        $this->config = CauHinhApiDaiLy::create([
            'dai_ly_api_id' => $this->partner->id,
            'client_id' => 'conc_client_' . strtolower(Str::random(8)),
            'secret_key_ma_hoa' => 'sec_' . Str::random(20),
            'danh_sach_ip_ket_noi' => '127.0.0.1',
            'han_muc_cong_no' => $hanMuc,
            'cong_no_hien_tai' => 0,
            'cho_phep_nhan_don' => true,
            'key_status' => 'active',
            'dich_vu_duoc_phep' => ['MOBILE_TOPUP'],
            'san_pham_loai_tru' => [],
        ]);

        $this->partner->dichVu()->attach($this->product->dich_vu_id);
        $this->partner->loaiSanPham()->attach($this->product->loai_san_pham_id);
        $this->partner->sanPham()->attach($this->product->id);
    }

    /**
     * Dọn dẹp tường minh — bắt buộc vì dữ liệu đã được COMMIT thật.
     * Chỉ xoá dữ liệu của đại lý do test này tạo, không đụng dữ liệu khác.
     */
    private function donDepDuLieu(): void
    {
        if (!isset($this->partner)) {
            return;
        }

        DB::transaction(function () {
            $donIds = DonHang::where('dai_ly_api_id', $this->partner->id)->pluck('id');
            SoPhatSinhCongNo::where('dai_ly_api_id', $this->partner->id)->delete();
            KhoanGiuHanMuc::where('dai_ly_api_id', $this->partner->id)->delete();
            DB::table('b2b_idempotency_records')->where('dai_ly_api_id', $this->partner->id)->delete();
            DB::table('b2b_order_outbox')->whereIn('don_hang_id', $donIds)->delete();
            DB::table('lan_goi_nha_cung_cap')->whereIn('don_hang_id', $donIds)->delete();
            DonHang::where('dai_ly_api_id', $this->partner->id)->delete();
            DB::table('cau_hinh_api_dai_ly')->where('dai_ly_api_id', $this->partner->id)->delete();
            $this->partner->dichVu()->detach();
            $this->partner->loaiSanPham()->detach();
            $this->partner->sanPham()->detach();
            $this->partner->delete();
        });
    }

    /**
     * Phóng N tiến trình PHP riêng biệt, tất cả chờ cùng một barrier file rồi
     * lao vào đồng thời. Trả về mảng kết quả JSON của từng tiến trình.
     *
     * @param  array<int, array{0:string,1:string,2:string}>  $kichBan  [idemKey, partnerOrderId, account]
     * @return array<int, array<string,mixed>>
     */
    private function chaySongSong(array $kichBan): array
    {
        $barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'b2b_barrier_' . Str::random(12);
        $this->fileTam[] = $barrier;

        $worker = base_path('tests/Support/b2b_concurrent_worker.php');
        $tenDb = DB::connection()->getDatabaseName();
        $procs = [];
        $pipes = [];

        foreach ($kichBan as $i => [$idemKey, $partnerOrderId, $account]) {
            $cmd = [
                PHP_BINARY,
                $worker,
                $this->config->client_id,
                $idemKey,
                $partnerOrderId,
                $account,
                $barrier,
                $tenDb,
            ];

            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open($cmd, $descriptors, $procPipes, base_path());

            if (!is_resource($proc)) {
                $this->fail('Không khởi động được tiến trình con thứ ' . $i);
            }

            $procs[$i] = $proc;
            $pipes[$i] = $procPipes;
            $this->tienTrinhDangChay[] = $proc;
        }

        // Hiệu lệnh xuất phát: mọi tiến trình đã sẵn sàng và đang chờ
        usleep(300000);
        touch($barrier);

        $ketQua = [];
        foreach ($procs as $i => $proc) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            proc_close($proc);

            $decoded = json_decode(trim((string) $stdout), true);
            if (!is_array($decoded)) {
                $this->fail("Tiến trình #{$i} không trả JSON hợp lệ. stdout=[{$stdout}] stderr=[{$stderr}]");
            }
            $ketQua[$i] = $decoded;
        }

        $this->tienTrinhDangChay = [];

        return $ketQua;
    }

    /**
     * Bổ trợ TẤT ĐỊNH cho test tương tranh ở trên.
     *
     * VÌ SAO CẦN: test tương tranh thật có bản chất xác suất — khi gỡ bỏ khoá bảo vệ,
     * nó chỉ phát hiện lỗi ở một phần số lần chạy (đo được ~2/5 lần với 16 tiến trình),
     * vì cửa sổ tranh chấp rất hẹp. Một test chỉ đỏ 40% số lần là một lưới an toàn yếu.
     *
     * Test này khẳng định TRỰC TIẾP rằng đường ghi hạn mức có phát câu lệnh khoá bi quan
     * (SELECT ... FOR UPDATE) trên cả dòng cấu hình đối tác LẪN tập khoản giữ đang HOLDING.
     * Đây là điều kiện tiên quyết để bất biến "tổng giữ <= hạn mức" đúng dưới tương tranh.
     * Nó tất định: gỡ khoá là đỏ ngay, không phụ thuộc thời điểm.
     */
    public function test_credit_hold_path_issues_pessimistic_locks(): void
    {
        $cauLenh = [];
        DB::listen(function ($query) use (&$cauLenh) {
            $cauLenh[] = $query->sql;
        });

        $donHang = DonHang::create([
            'ma_don_hang' => 'LOCK_' . strtoupper(Str::random(10)),
            'nguon_don' => 'b2b',
            'dai_ly_api_id' => $this->partner->id,
            'ma_don_doi_tac' => 'LOCK_REF_' . strtoupper(Str::random(8)),
            'dich_vu_id' => $this->product->dich_vu_id,
            'loai_san_pham_id' => $this->product->loai_san_pham_id,
            'san_pham_id' => $this->product->id,
            'tai_khoan_nhan' => '0965657810',
            'menh_gia' => 10000,
            'gia_ban' => 10000,
            'trang_thai_don_hang' => 'QUEUED',
        ]);

        DB::transaction(function () use ($donHang) {
            app(\App\Services\DaiLyApi\B2bCreditService::class)
                ->kiemTraVaGiuHanMuc($this->partner, $donHang, 10000.0);
        });

        $coKhoaCauHinh = false;
        $coKhoaKhoanGiu = false;

        foreach ($cauLenh as $sql) {
            $laSelect = stripos($sql, 'select') === 0;
            $coForUpdate = stripos($sql, 'for update') !== false;
            if (!$laSelect || !$coForUpdate) {
                continue;
            }
            if (stripos($sql, 'cau_hinh_api_dai_ly') !== false) {
                $coKhoaCauHinh = true;
            }
            if (stripos($sql, 'khoan_giu_han_muc') !== false) {
                $coKhoaKhoanGiu = true;
            }
        }

        $this->assertTrue(
            $coKhoaCauHinh,
            "Đường giữ hạn mức PHẢI khoá bi quan dòng cấu hình đối tác (SELECT ... FOR UPDATE trên cau_hinh_api_dai_ly).\n"
                . "Thiếu khoá này, nhiều request song song cùng đọc số dư cũ và cùng giữ hạn mức => BÁN VƯỢT HẠN MỨC.\n"
                . 'Câu lệnh ghi nhận được: ' . json_encode($cauLenh, JSON_UNESCAPED_UNICODE)
        );

        $this->assertTrue(
            $coKhoaKhoanGiu,
            "Đường giữ hạn mức PHẢI khoá bi quan tập khoản giữ đang HOLDING (SELECT ... FOR UPDATE trên khoan_giu_han_muc).\n"
                . 'Câu lệnh ghi nhận được: ' . json_encode($cauLenh, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * §13: N tiến trình gửi CÙNG Idempotency-Key + CÙNG payload.
     * Kỳ vọng: đúng MỘT đơn hàng, đúng MỘT khoản giữ hạn mức, hạn mức chỉ bị trừ một lần.
     */
    public function test_concurrent_same_key_same_payload_creates_exactly_one_order_and_one_hold(): void
    {
        $idemKey = (string) Str::uuid();
        $partnerOrderId = 'CONC_SAME_' . strtoupper(Str::random(8));

        $ketQua = $this->chaySongSong(array_fill(0, 5, [$idemKey, $partnerOrderId, '0965657810']));

        $thanhCong = array_filter($ketQua, fn ($r) => $r['ok'] === true);
        $this->assertNotEmpty($thanhCong, 'Ít nhất một tiến trình phải thành công. Kết quả: ' . json_encode($ketQua, JSON_UNESCAPED_UNICODE));

        // BẤT BIẾN TIỀN: đúng 1 đơn, đúng 1 khoản giữ
        $soDon = DonHang::where('dai_ly_api_id', $this->partner->id)
            ->where('ma_don_doi_tac', $partnerOrderId)->count();
        $this->assertSame(1, $soDon, 'Tương tranh cùng key phải tạo ĐÚNG 1 đơn, thực tế: ' . $soDon);

        $soGiu = KhoanGiuHanMuc::where('dai_ly_api_id', $this->partner->id)->count();
        $this->assertSame(1, $soGiu, 'Tương tranh cùng key phải tạo ĐÚNG 1 khoản giữ, thực tế: ' . $soGiu);

        // Mọi tiến trình thành công phải trỏ về CÙNG một order_id
        $cacOrderId = array_unique(array_filter(array_map(fn ($r) => $r['order_id'] ?? null, $thanhCong)));
        $this->assertCount(1, $cacOrderId, 'Mọi tiến trình thành công phải trả về cùng order_id: ' . json_encode($cacOrderId));

        // Sổ công nợ không được sinh bút toán khi đơn mới chỉ đang giữ hạn mức
        $this->assertSame(0, SoPhatSinhCongNo::where('dai_ly_api_id', $this->partner->id)->count(),
            'Đơn mới ở trạng thái giữ hạn mức KHÔNG được ghi công nợ.');
    }

    /**
     * §13: N tiến trình gửi CÙNG partner_order_id nhưng Idempotency-Key KHÁC nhau.
     * Kỳ vọng: chỉ một đơn được tạo; các tiến trình còn lại bị từ chối (409/422),
     * và TUYỆT ĐỐI không có tiến trình nào tạo thêm khoản giữ thứ hai.
     */
    public function test_concurrent_different_keys_same_partner_order_id_creates_one_order(): void
    {
        $partnerOrderId = 'CONC_DIFF_' . strtoupper(Str::random(8));

        $kichBan = [];
        for ($i = 0; $i < 4; $i++) {
            $kichBan[] = [(string) Str::uuid(), $partnerOrderId, '0965657810'];
        }

        $ketQua = $this->chaySongSong($kichBan);

        $soDon = DonHang::where('dai_ly_api_id', $this->partner->id)
            ->where('ma_don_doi_tac', $partnerOrderId)->count();
        $this->assertSame(1, $soDon, 'Chỉ được tạo ĐÚNG 1 đơn cho một partner_order_id, thực tế: ' . $soDon);

        $soGiu = KhoanGiuHanMuc::where('dai_ly_api_id', $this->partner->id)->count();
        $this->assertSame(1, $soGiu, 'Chỉ được có ĐÚNG 1 khoản giữ, thực tế: ' . $soGiu);

        $thanhCong = array_filter($ketQua, fn ($r) => $r['ok'] === true);
        $this->assertCount(1, $thanhCong, 'Chỉ một tiến trình được thành công: ' . json_encode($ketQua, JSON_UNESCAPED_UNICODE));
    }

    /**
     * §13: N tiến trình tạo N đơn KHÁC NHAU, tổng giá trị VƯỢT hạn mức.
     * Kỳ vọng: tổng tiền giữ KHÔNG BAO GIỜ vượt hạn mức — đây là bất biến sống còn
     * chống bán vượt hạn mức khi đối tác bắn đơn song song.
     *
     * LƯU Ý VỀ CÁCH ĐO: worker dùng queue 'null' để job nạp tiền KHÔNG chạy, nhờ đó
     * khoản giữ giữ nguyên HOLDING. Nếu để queue 'sync', đơn thất bại sẽ giải phóng
     * hạn mức và mở đường cho đơn mới — khi đó "số đơn tạo được" không còn là bất biến
     * quan sát được (nó phụ thuộc thời điểm, không phải phụ thuộc tính đúng đắn).
     * Bất biến thật cần khẳng định là: TỔNG TIỀN ĐANG GIỮ <= HẠN MỨC.
     */
    public function test_concurrent_orders_never_exceed_credit_limit(): void
    {
        $hanMuc = 30000.0;
        $this->config->update(['han_muc_cong_no' => $hanMuc]);
        $this->config->refresh();

        $soTienTrinh = 16;

        $kichBan = [];
        for ($i = 0; $i < $soTienTrinh; $i++) {
            $kichBan[] = [
                (string) Str::uuid(),
                'CONC_LIM_' . strtoupper(Str::random(6)) . '_' . $i,
                '096565781' . $i,
            ];
        }

        $ketQua = $this->chaySongSong($kichBan);

        $holds = KhoanGiuHanMuc::where('dai_ly_api_id', $this->partner->id)->get();
        $tongGiu = (float) $holds->where('trang_thai', 'HOLDING')->sum('so_tien_giu');

        // BẤT BIẾN SỐNG CÒN #1: không bao giờ giữ quá hạn mức
        $this->assertLessThanOrEqual(
            $hanMuc,
            $tongGiu,
            sprintf(
                'TỔNG TIỀN GIỮ (%s) VƯỢT HẠN MỨC (%s)! Đây là lỗi bán vượt hạn mức. Kết quả: %s',
                $tongGiu,
                $hanMuc,
                json_encode($ketQua, JSON_UNESCAPED_UNICODE)
            )
        );

        // BẤT BIẾN #2: mỗi đơn có ĐÚNG một khoản giữ — không đơn nào lọt lưới không giữ tiền
        $soDon = DonHang::where('dai_ly_api_id', $this->partner->id)->count();
        $this->assertSame(
            $soDon,
            $holds->count(),
            "Số đơn ({$soDon}) phải bằng số khoản giữ ({$holds->count()}): mọi đơn phải giữ hạn mức."
        );

        // BẤT BIẾN #3: mọi khoản giữ phải gắn với một đơn có thật của chính đại lý này
        $donIds = DonHang::where('dai_ly_api_id', $this->partner->id)->pluck('id')->all();
        foreach ($holds as $h) {
            $this->assertContains($h->don_hang_id, $donIds, 'Khoản giữ phải trỏ tới đơn hàng có thật.');
        }

        // BẤT BIẾN #4: số tiền giữ mỗi đơn phải bằng đúng giá bán của đơn đó
        foreach ($holds as $h) {
            $don = DonHang::find($h->don_hang_id);
            $this->assertEquals(
                (float) $don->gia_ban,
                (float) $h->so_tien_giu,
                "Số tiền giữ phải bằng giá bán của đơn #{$don->id}."
            );
        }

        // BẤT BIẾN #5: số dư tổng hợp phải khớp sổ cái
        $canDoi = app(\App\Services\DaiLyApi\B2bCreditService::class)
            ->kiemTraCanDoiCongNo($this->partner->fresh());
        $this->assertTrue($canDoi['can_doi'], 'Sổ công nợ phải cân đối: ' . json_encode($canDoi, JSON_UNESCAPED_UNICODE));

        // BẤT BIẾN #6: hạn mức khả dụng không được âm
        $khaDung = app(\App\Services\DaiLyApi\B2bCreditService::class)
            ->tinhHanMucKhaDung($this->partner->fresh());
        $this->assertGreaterThanOrEqual(0, $khaDung['han_muc_kha_dung'], 'Hạn mức khả dụng không được âm.');

        // Có tiến trình bị từ chối vì hạn mức => chứng tỏ giới hạn thực sự có hiệu lực
        $biTuChoi = array_filter($ketQua, fn ($r) => $r['ok'] === false);
        $this->assertNotEmpty(
            $biTuChoi,
            'Với 8 đơn trong khi hạn mức chỉ đủ 3, PHẢI có tiến trình bị từ chối. Kết quả: '
                . json_encode($ketQua, JSON_UNESCAPED_UNICODE)
        );
    }
}
