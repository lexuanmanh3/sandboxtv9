<?php

namespace App\Services\Topup\Fake;

use App\Contracts\NhaCungCapTopupInterface;
use App\DTOs\KetQuaNhaCungCapDTO;
use App\DTOs\KetQuaSoDuDTO;
use App\DTOs\KetQuaThueBao;
use App\DTOs\YeuCauNapTienDTO;
use App\Enums\KetQuaNhaCungCap;
use App\Models\KetNoiNhaCungCap;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Nhà cung cấp GIẢ LẬP phục vụ kiểm thử tích hợp B2B tại local.
 *
 * Nguyên tắc bắt buộc:
 *  - KHÔNG thực hiện bất kỳ kết nối mạng nào. Toàn bộ kết quả sinh tại chỗ.
 *  - Kết quả XÁC ĐỊNH theo mã tham chiếu gốc (partnerRefId): gọi lại cùng một mã
 *    luôn cho cùng một kịch bản, nhờ đó kiểm chứng được việc chống nạp trùng.
 *  - Mọi lần gọi đều được ghi vết ra file để đếm được số lần nạp thực tế.
 *  - Tự từ chối khởi tạo nếu không nằm trong môi trường local/testing hoặc
 *    chưa bật cờ TOPUP_FAKE_ENABLED. Không phụ thuộc vào tên miền sandbox.
 *
 * Kịch bản được chọn theo thứ tự ưu tiên:
 *  1. Bảng ghi đè theo partnerRefId trong scenarios.json (dùng cho test tự động).
 *  2. Tiền tố số điện thoại (dùng cho test thủ công qua giao diện DailyB2B).
 *  3. Giá trị mặc định trong config('topup.fake.default_scenario').
 */
final class FakeTopupProvider implements NhaCungCapTopupInterface
{
    public const SCENARIO_SUCCESS = 'success';
    public const SCENARIO_FAIL = 'fail';
    public const SCENARIO_PENDING_SUCCESS = 'pending_success';
    public const SCENARIO_PENDING_FAIL = 'pending_fail';
    public const SCENARIO_PENDING_FOREVER = 'pending_forever';

    /** Ánh xạ tiền tố số điện thoại sang kịch bản, phục vụ test thủ công. */
    public const PHONE_PREFIX_MAP = [
        '0900000001' => self::SCENARIO_SUCCESS,
        '0900000002' => self::SCENARIO_FAIL,
        '0900000003' => self::SCENARIO_PENDING_SUCCESS,
        '0900000004' => self::SCENARIO_PENDING_FAIL,
        '0900000005' => self::SCENARIO_PENDING_FOREVER,
    ];

    private const SCENARIOS_HOP_LE = [
        self::SCENARIO_SUCCESS,
        self::SCENARIO_FAIL,
        self::SCENARIO_PENDING_SUCCESS,
        self::SCENARIO_PENDING_FAIL,
        self::SCENARIO_PENDING_FOREVER,
    ];

    public function __construct(private KetNoiNhaCungCap $ketNoi)
    {
        if (!self::duocPhepChay()) {
            throw new RuntimeException(
                'Provider giả lập chỉ được phép chạy khi APP_ENV là local/testing VÀ TOPUP_FAKE_ENABLED=true. ' .
                'Đã từ chối khởi tạo để không giả lập giao dịch ngoài môi trường kiểm thử.'
            );
        }
    }

    /**
     * Điều kiện bật provider giả lập: phải là môi trường kiểm thử VÀ có cờ cấu hình rõ ràng.
     */
    public static function duocPhepChay(): bool
    {
        return app()->environment('local', 'testing') && (bool) config('topup.fake.enabled', false);
    }

    public function kiemTraThueBao(string $soDienThoai): KetQuaThueBao
    {
        $this->ghiVet('kiemTraThueBao', null, null, $soDienThoai);

        return new KetQuaThueBao($soDienThoai, $this->suyNhaMang($soDienThoai), 'tra_truoc', [
            'fake' => true,
            'telco' => $this->suyNhaMang($soDienThoai),
        ]);
    }

    public function layDanhSachSanPham(?string $endpoint = null): Collection
    {
        $this->ghiVet('layDanhSachSanPham', null, null, null);

        return collect();
    }

    public function layGoiDataTheoThueBao(string $soDienThoai, string $loaiThueBao): Collection
    {
        $this->ghiVet('layGoiDataTheoThueBao', null, null, $soDienThoai);

        return collect();
    }

    public function napTien(YeuCauNapTienDTO $dto): KetQuaNhaCungCapDTO
    {
        $kichBan = $this->kichBanCho($dto->partnerRefId, $dto->soDienThoai);

        // Lưu kịch bản theo mã tham chiếu để kiemTraTrangThai() dùng lại đúng kịch bản đó.
        $this->luuKichBanTheoRef($dto->partnerRefId, $kichBan, $dto->soDienThoai);
        $this->ghiVet('napTien', $dto->partnerRefId, $kichBan, $dto->soDienThoai);

        return match ($kichBan) {
            self::SCENARIO_SUCCESS => new KetQuaNhaCungCapDTO(
                KetQuaNhaCungCap::SUCCESS,
                '00',
                'Giả lập: nạp thành công ngay',
                $this->maGiaoDichNcc($dto->partnerRefId),
                duLieu: ['fake' => true, 'scenario' => $kichBan]
            ),
            self::SCENARIO_FAIL => new KetQuaNhaCungCapDTO(
                KetQuaNhaCungCap::DEFINITIVE_FAILURE,
                '02',
                'Giả lập: nhà cung cấp từ chối dứt khoát',
                $this->maGiaoDichNcc($dto->partnerRefId),
                duLieu: ['fake' => true, 'scenario' => $kichBan]
            ),
            default => new KetQuaNhaCungCapDTO(
                KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
                '99',
                'Giả lập: chưa có kết quả cuối từ nhà cung cấp',
                $this->maGiaoDichNcc($dto->partnerRefId),
                duLieu: ['fake' => true, 'scenario' => $kichBan]
            ),
        };
    }

    public function kiemTraTrangThai(string $partnerRefId): KetQuaNhaCungCapDTO
    {
        $daLuu = $this->docKichBanTheoRef($partnerRefId);

        // Ghi đè lúc chạy (scenarios.json) được ưu tiên hơn kịch bản đã chốt lúc nạp tiền.
        // Dùng để mô phỏng "kết quả đến muộn": đổi kịch bản của một mã tham chiếu đang
        // chờ, rồi lần tra cứu kế tiếp sẽ trả kết quả cuối. Kịch bản vẫn xác định theo
        // mã tham chiếu nên gọi lại nhiều lần cho cùng một kết quả.
        $kichBan = $this->docGhiDeTheoRef($partnerRefId)
            ?? $daLuu['scenario']
            ?? (string) config('topup.fake.default_scenario', self::SCENARIO_SUCCESS);

        $soLanPoll = $this->tangDemPoll($partnerRefId);
        $this->ghiVet('kiemTraTrangThai', $partnerRefId, $kichBan, $daLuu['phone'] ?? null);

        $nguong = max(1, (int) config('topup.fake.poll_success_after', 2));

        return match ($kichBan) {
            self::SCENARIO_PENDING_SUCCESS => $soLanPoll >= $nguong
                ? new KetQuaNhaCungCapDTO(
                    KetQuaNhaCungCap::SUCCESS,
                    '00',
                    "Giả lập: thành công muộn sau {$soLanPoll} lần tra cứu",
                    $this->maGiaoDichNcc($partnerRefId),
                    duLieu: ['fake' => true, 'scenario' => $kichBan, 'poll' => $soLanPoll]
                )
                : $this->ketQuaPending($kichBan, $soLanPoll),

            self::SCENARIO_PENDING_FAIL => $soLanPoll >= $nguong
                ? new KetQuaNhaCungCapDTO(
                    KetQuaNhaCungCap::DEFINITIVE_FAILURE,
                    '02',
                    "Giả lập: thất bại muộn sau {$soLanPoll} lần tra cứu",
                    $this->maGiaoDichNcc($partnerRefId),
                    duLieu: ['fake' => true, 'scenario' => $kichBan, 'poll' => $soLanPoll]
                )
                : $this->ketQuaPending($kichBan, $soLanPoll),

            self::SCENARIO_PENDING_FOREVER => $this->ketQuaPending($kichBan, $soLanPoll),

            self::SCENARIO_SUCCESS => new KetQuaNhaCungCapDTO(
                KetQuaNhaCungCap::SUCCESS,
                '00',
                'Giả lập: giao dịch đã thành công',
                $this->maGiaoDichNcc($partnerRefId),
                duLieu: ['fake' => true, 'scenario' => $kichBan]
            ),

            default => new KetQuaNhaCungCapDTO(
                KetQuaNhaCungCap::DEFINITIVE_FAILURE,
                '02',
                'Giả lập: giao dịch đã thất bại',
                $this->maGiaoDichNcc($partnerRefId),
                duLieu: ['fake' => true, 'scenario' => $kichBan]
            ),
        };
    }

    public function kiemTraSoDu(): KetQuaSoDuDTO
    {
        $this->ghiVet('kiemTraSoDu', null, null, null);

        return new KetQuaSoDuDTO((string) config('topup.fake.balance', '999999999'), 'VND');
    }

    private function ketQuaPending(string $kichBan, int $soLanPoll): KetQuaNhaCungCapDTO
    {
        return new KetQuaNhaCungCapDTO(
            KetQuaNhaCungCap::UNKNOWN_OR_PENDING,
            '99',
            "Giả lập: vẫn chưa có kết quả cuối (lần tra cứu {$soLanPoll})",
            null,
            duLieu: ['fake' => true, 'scenario' => $kichBan, 'poll' => $soLanPoll]
        );
    }

    /**
     * Xác định kịch bản cho một mã tham chiếu. Kết quả thuần túy dựa trên dữ liệu đầu vào
     * nên hoàn toàn xác định giữa các lần gọi.
     */
    private function kichBanCho(string $partnerRefId, string $soDienThoai): string
    {
        $ghiDe = $this->docGhiDeTheoRef($partnerRefId);
        if ($ghiDe !== null) {
            return $ghiDe;
        }

        foreach (self::PHONE_PREFIX_MAP as $tienTo => $kichBan) {
            if (str_starts_with($soDienThoai, $tienTo)) {
                return $kichBan;
            }
        }

        $macDinh = (string) config('topup.fake.default_scenario', self::SCENARIO_SUCCESS);

        return in_array($macDinh, self::SCENARIOS_HOP_LE, true) ? $macDinh : self::SCENARIO_SUCCESS;
    }

    /**
     * Mã giao dịch phía NCC, sinh tất định từ mã tham chiếu.
     */
    private function maGiaoDichNcc(string $partnerRefId): string
    {
        return 'FAKE-' . strtoupper(substr(hash('sha256', $partnerRefId), 0, 12));
    }

    private function suyNhaMang(string $soDienThoai): string
    {
        return match (substr($soDienThoai, 0, 3)) {
            '096', '097', '098', '032', '033', '034', '035', '036', '037', '038', '039' => 'VIETTEL',
            '090', '093', '070', '079', '077', '076', '078', '089' => 'MOBIFONE',
            '091', '094', '088', '083', '084', '085', '081', '082' => 'VINAPHONE',
            '092', '056', '058', '052' => 'VIETNAMOBILE',
            default => 'VIETTEL',
        };
    }

    // ---------------------------------------------------------------------
    // Ghi vết phục vụ kiểm chứng (đếm số lần nạp, không dùng cho nghiệp vụ)
    // ---------------------------------------------------------------------

    public static function thuMucLuuVet(): string
    {
        return storage_path('app/fake-topup');
    }

    private function ghiVet(string $phuongThuc, ?string $partnerRefId, ?string $kichBan, ?string $soDienThoai): void
    {
        $this->ghiJson('calls.json', function (array $duLieu) use ($phuongThuc, $partnerRefId, $kichBan, $soDienThoai) {
            $duLieu[] = [
                'at' => now()->toIso8601String(),
                'method' => $phuongThuc,
                'partner_ref_id' => $partnerRefId,
                'scenario' => $kichBan,
                'phone' => $soDienThoai,
            ];

            // Giữ tối đa 5000 bản ghi gần nhất để file không phình vô hạn.
            return array_slice($duLieu, -5000);
        });
    }

    private function luuKichBanTheoRef(string $partnerRefId, string $kichBan, string $soDienThoai): void
    {
        $this->ghiJson('refs.json', function (array $duLieu) use ($partnerRefId, $kichBan, $soDienThoai) {
            $duLieu[$partnerRefId] = [
                'scenario' => $kichBan,
                'phone' => $soDienThoai,
                'created_at' => now()->toIso8601String(),
            ];

            return $duLieu;
        });
    }

    private function docKichBanTheoRef(string $partnerRefId): array
    {
        $duLieu = $this->docJson('refs.json');

        return (array) ($duLieu[$partnerRefId] ?? []);
    }

    private function docGhiDeTheoRef(string $partnerRefId): ?string
    {
        $duLieu = $this->docJson('scenarios.json');
        $kichBan = $duLieu[$partnerRefId] ?? null;

        if (is_array($kichBan)) {
            $kichBan = $kichBan['scenario'] ?? null;
        }

        return (is_string($kichBan) && in_array($kichBan, self::SCENARIOS_HOP_LE, true)) ? $kichBan : null;
    }

    private function tangDemPoll(string $partnerRefId): int
    {
        $soLan = 0;
        $this->ghiJson('polls.json', function (array $duLieu) use ($partnerRefId, &$soLan) {
            $soLan = ((int) ($duLieu[$partnerRefId] ?? 0)) + 1;
            $duLieu[$partnerRefId] = $soLan;

            return $duLieu;
        });

        return $soLan;
    }

    private function docJson(string $tenFile): array
    {
        $duongDan = self::thuMucLuuVet() . DIRECTORY_SEPARATOR . $tenFile;
        if (!is_file($duongDan)) {
            return [];
        }

        $noiDung = @file_get_contents($duongDan);
        if ($noiDung === false || $noiDung === '') {
            return [];
        }

        $duLieu = json_decode($noiDung, true);

        return is_array($duLieu) ? $duLieu : [];
    }

    /**
     * Ghi file JSON dưới khóa flock để worker và tiến trình test không đè nhau.
     */
    private function ghiJson(string $tenFile, callable $bienDoi): void
    {
        $thuMuc = self::thuMucLuuVet();
        if (!is_dir($thuMuc) && !@mkdir($thuMuc, 0775, true) && !is_dir($thuMuc)) {
            return;
        }

        $duongDan = $thuMuc . DIRECTORY_SEPARATOR . $tenFile;
        $handle = @fopen($duongDan, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $noiDung = stream_get_contents($handle);
            $duLieu = json_decode((string) $noiDung, true);
            $duLieu = is_array($duLieu) ? $duLieu : [];

            $duLieu = $bienDoi($duLieu);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($duLieu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }
}
