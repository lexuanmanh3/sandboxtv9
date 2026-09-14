<?php

namespace App\Http\Controllers\Admin\B2B;

use App\Http\Controllers\Controller;
use App\Models\BangGiaDaiLy;
use App\Models\CauHinhApiDaiLy;
use App\Models\DaiLyApi;
use App\Models\DaiLyApiDichVuDuocPhep;
use App\Models\DaiLyApiLoaiSanPhamDuocPhep;
use App\Models\DaiLyApiSanPhamDuocPhep;
use App\Models\DichVu;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Services\DaiLyApi\B2bCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PartnerManagementController extends Controller
{
    public function __construct(
        protected B2bCreditService $creditService
    ) {}

    public function index(Request $request): View
    {
        $query = DaiLyApi::with(['cauHinhApi', 'dichVu', 'loaiSanPham', 'sanPham', 'nguoiDung']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('ma_dai_ly_api', 'like', "%{$search}%")
                    ->orWhere('ten_dai_ly_api', 'like', "%{$search}%")
                    ->orWhere('so_dien_thoai', 'like', "%{$search}%")
                    ->orWhere('email_ky_thuat', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('trang_thai')) {
            $query->where('trang_thai', $status);
        }

        $partners = $query->orderByDesc('id')->paginate(15)->withQueryString();

        // Tính toán thông tin hạn mức cho từng đại lý
        $partners->getCollection()->transform(function ($partner) {
            $partner->credit_info = $this->creditService->tinhHanMucKhaDung($partner);
            return $partner;
        });

        // Lấy danh mục để chọn trong Modal
        $dichVus = DichVu::where('trang_thai', 'hoat_dong')->get();
        $loaiSanPhams = LoaiSanPham::where('trang_thai', 'hoat_dong')->get();
        $sanPhams = SanPham::whereIn('trang_thai', ['hoat_dong', 'ACTIVE'])->get();

        return view('admin.b2b.partners', compact('partners', 'dichVus', 'loaiSanPhams', 'sanPhams'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ma_dai_ly_api' => 'required|string|max:50|unique:dai_ly_api,ma_dai_ly_api',
            'ten_dai_ly_api' => 'required|string|max:255',
            'so_dien_thoai' => 'required|string|max:20',
            'ho' => 'nullable|string|max:100',
            'ten' => 'nullable|string|max:100',
            'mat_khau' => 'nullable|string|min:6|confirmed',
            'ngay_ky_hop_dong' => 'nullable|date',
            'so_hop_dong' => 'nullable|string|max:100',
            'email_ky_thuat' => 'required|email|max:255',
            'email_doi_soat' => 'nullable|email|max:255',
            'ky_doi_soat' => 'required|integer|min:1',
            'folder_ftp' => 'nullable|string|max:255',
            'telegram_group_id' => 'nullable|string|max:100',
            'trang_thai' => 'required|string|in:hoat_dong,tam_khoa',
            
            // Tab 2: Cấu hình API
            'client_id' => 'nullable|string|max:100|unique:cau_hinh_api_dai_ly,client_id',
            'danh_sach_ip_ket_noi' => 'nullable|string',
            'han_muc_cong_no' => 'required|numeric|min:0',
            'nguong_canh_bao_han_muc' => 'nullable|numeric|min:0',
            'so_luong_kenh_toi_da' => 'nullable|integer|min:1',
            'rate_limit_per_minute' => 'nullable|integer|min:10',
            'webhook_url' => 'nullable|url|max:500',
            'dich_vu_ids' => 'nullable|array',
            'loai_san_pham_ids' => 'nullable|array',
            'san_pham_ids' => 'nullable|array',
            'san_pham_loai_tru' => 'nullable|array',
            'cho_phep_nhan_don' => 'nullable|boolean',

            // Tab 3: Thông tin liên hệ
            'tinh_thanh' => 'nullable|string|max:100',
            'quan_huyen' => 'nullable|string|max:100',
            'phuong_xa' => 'nullable|string|max:100',
            'dia_chi_chi_tiet' => 'nullable|string|max:500',
            'lien_he' => 'nullable|array',
        ]);

        $generatedSecret = Str::random(40);
        $clientId = !empty($validated['client_id']) ? $validated['client_id'] : 'client_' . strtolower(Str::random(12));

        DB::transaction(function () use ($validated, $generatedSecret, $clientId, &$partner) {
            $userId = null;
            if (!empty($validated['mat_khau'])) {
                $user = User::create([
                    'name' => $validated['ten_dai_ly_api'],
                    'ten_dang_nhap' => strtolower($validated['ma_dai_ly_api']),
                    'email' => $validated['email_ky_thuat'],
                    'password' => Hash::make($validated['mat_khau']),
                    'trang_thai' => 'active',
                ]);
                $userId = $user->id;
            }

            $partner = DaiLyApi::create([
                'nguoi_dung_id' => $userId,
                'ma_dai_ly_api' => $validated['ma_dai_ly_api'],
                'ten_dai_ly_api' => $validated['ten_dai_ly_api'],
                'so_dien_thoai' => $validated['so_dien_thoai'],
                'ho' => $validated['ho'] ?? null,
                'ten' => $validated['ten'] ?? null,
                'ngay_ky_hop_dong' => $validated['ngay_ky_hop_dong'] ?? null,
                'so_hop_dong' => $validated['so_hop_dong'] ?? null,
                'email_ky_thuat' => $validated['email_ky_thuat'],
                'email_doi_soat' => $validated['email_doi_soat'] ?? null,
                'ky_doi_soat' => $validated['ky_doi_soat'] ?? 30,
                'folder_ftp' => $validated['folder_ftp'] ?? null,
                'telegram_group_id' => $validated['telegram_group_id'] ?? null,
                'trang_thai' => $validated['trang_thai'],
                'tinh_thanh' => $validated['tinh_thanh'] ?? null,
                'quan_huyen' => $validated['quan_huyen'] ?? null,
                'phuong_xa' => $validated['phuong_xa'] ?? null,
                'dia_chi_chi_tiet' => $validated['dia_chi_chi_tiet'] ?? null,
                'thong_tin_lien_he' => $validated['lien_he'] ?? null,
            ]);

            // Tạo cấu hình API
            CauHinhApiDaiLy::create([
                'dai_ly_api_id' => $partner->id,
                'client_id' => $clientId,
                'secret_key_ma_hoa' => $generatedSecret,
                'allowed_grant_types' => 'client_credentials',
                'allowed_scopes' => 'order:create,order:query,credit:query,service:query',
                'su_dung_chu_ky_dien_tu' => true,
                'danh_sach_ip_ket_noi' => $validated['danh_sach_ip_ket_noi'] ?? null,
                'so_luong_kenh_toi_da' => $validated['so_luong_kenh_toi_da'] ?? 5,
                'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? 60,
                'han_muc_cong_no' => $validated['han_muc_cong_no'] ?? 0,
                'cong_no_hien_tai' => 0,
                'nguong_canh_bao_han_muc' => $validated['nguong_canh_bao_han_muc'] ?? 0,
                'cho_phep_nhan_don' => (bool) ($validated['cho_phep_nhan_don'] ?? true),
                'san_pham_loai_tru' => $validated['san_pham_loai_tru'] ?? [],
                'webhook_url' => $validated['webhook_url'] ?? null,
                'webhook_secret_ma_hoa' => Str::random(32),
                'key_status' => 'active',
            ]);

            // Gán quyền dịch vụ
            if (!empty($validated['dich_vu_ids'])) {
                $partner->dichVu()->sync($validated['dich_vu_ids']);
            }
            if (!empty($validated['loai_san_pham_ids'])) {
                $partner->loaiSanPham()->sync($validated['loai_san_pham_ids']);
            }
            if (!empty($validated['san_pham_ids'])) {
                $partner->sanPham()->sync($validated['san_pham_ids']);
            }
        });

        return redirect()->route('admin.b2b.partners.index')->with([
            'success' => "Đã tạo Đại lý API '{$partner->ten_dai_ly_api}' thành công.",
            'new_credential' => [
                'client_id' => $clientId,
                'secret_key' => $generatedSecret,
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $partner = DaiLyApi::findOrFail($id);

        $validated = $request->validate([
            'ten_dai_ly_api' => 'required|string|max:255',
            'so_dien_thoai' => 'required|string|max:20',
            'ho' => 'nullable|string|max:100',
            'ten' => 'nullable|string|max:100',
            'ngay_ky_hop_dong' => 'nullable|date',
            'so_hop_dong' => 'nullable|string|max:100',
            'email_ky_thuat' => 'required|email|max:255',
            'email_doi_soat' => 'nullable|email|max:255',
            'ky_doi_soat' => 'required|integer|min:1',
            'folder_ftp' => 'nullable|string|max:255',
            'telegram_group_id' => 'nullable|string|max:100',
            'trang_thai' => 'required|string|in:hoat_dong,tam_khoa',
            
            // Tab 2: Cấu hình API
            'danh_sach_ip_ket_noi' => 'nullable|string',
            'han_muc_cong_no' => 'required|numeric|min:0',
            'nguong_canh_bao_han_muc' => 'nullable|numeric|min:0',
            'so_luong_kenh_toi_da' => 'nullable|integer|min:1',
            'rate_limit_per_minute' => 'nullable|integer|min:10',
            'webhook_url' => 'nullable|url|max:500',
            'dich_vu_ids' => 'nullable|array',
            'loai_san_pham_ids' => 'nullable|array',
            'san_pham_ids' => 'nullable|array',
            'san_pham_loai_tru' => 'nullable|array',
            'cho_phep_nhan_don' => 'nullable|boolean',

            // Tab 3: Thông tin liên hệ
            'tinh_thanh' => 'nullable|string|max:100',
            'quan_huyen' => 'nullable|string|max:100',
            'phuong_xa' => 'nullable|string|max:100',
            'dia_chi_chi_tiet' => 'nullable|string|max:500',
            'lien_he' => 'nullable|array',
        ]);

        DB::transaction(function () use ($partner, $validated) {
            $partner->update([
                'ten_dai_ly_api' => $validated['ten_dai_ly_api'],
                'so_dien_thoai' => $validated['so_dien_thoai'],
                'ho' => $validated['ho'] ?? null,
                'ten' => $validated['ten'] ?? null,
                'ngay_ky_hop_dong' => $validated['ngay_ky_hop_dong'] ?? null,
                'so_hop_dong' => $validated['so_hop_dong'] ?? null,
                'email_ky_thuat' => $validated['email_ky_thuat'],
                'email_doi_soat' => $validated['email_doi_soat'] ?? null,
                'ky_doi_soat' => $validated['ky_doi_soat'] ?? 30,
                'folder_ftp' => $validated['folder_ftp'] ?? null,
                'telegram_group_id' => $validated['telegram_group_id'] ?? null,
                'trang_thai' => $validated['trang_thai'],
                'tinh_thanh' => $validated['tinh_thanh'] ?? null,
                'quan_huyen' => $validated['quan_huyen'] ?? null,
                'phuong_xa' => $validated['phuong_xa'] ?? null,
                'dia_chi_chi_tiet' => $validated['dia_chi_chi_tiet'] ?? null,
                'thong_tin_lien_he' => $validated['lien_he'] ?? null,
            ]);

            $cauHinh = $partner->cauHinhApi;
            if ($cauHinh) {
                $cauHinh->update([
                    'danh_sach_ip_ket_noi' => $validated['danh_sach_ip_ket_noi'] ?? null,
                    'so_luong_kenh_toi_da' => $validated['so_luong_kenh_toi_da'] ?? 5,
                    'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? 60,
                    'han_muc_cong_no' => $validated['han_muc_cong_no'] ?? 0,
                    'nguong_canh_bao_han_muc' => $validated['nguong_canh_bao_han_muc'] ?? 0,
                    'cho_phep_nhan_don' => (bool) ($validated['cho_phep_nhan_don'] ?? true),
                    'san_pham_loai_tru' => $validated['san_pham_loai_tru'] ?? [],
                    'webhook_url' => $validated['webhook_url'] ?? null,
                ]);
            }

            // Sync phân quyền 3 tầng
            $partner->dichVu()->sync($validated['dich_vu_ids'] ?? []);
            $partner->loaiSanPham()->sync($validated['loai_san_pham_ids'] ?? []);
            $partner->sanPham()->sync($validated['san_pham_ids'] ?? []);
        });

        return redirect()->route('admin.b2b.partners.index')->with('success', "Đã cập nhật thông tin đại lý '{$partner->ten_dai_ly_api}'.");
    }

    public function rotateKey(int $id): RedirectResponse
    {
        $partner = DaiLyApi::findOrFail($id);
        $cauHinh = $partner->cauHinhApi;

        if (!$cauHinh) {
            return back()->with('error', 'Chưa có cấu hình API cho đại lý.');
        }

        $oldSecret = $cauHinh->secret_key_ma_hoa;
        $newSecret = Str::random(40);

        $cauHinh->update([
            'previous_secret_ma_hoa' => $oldSecret,
            'secret_key_ma_hoa' => $newSecret,
            'key_status' => 'rotating',
            'key_rotated_at' => now(),
        ]);

        return redirect()->route('admin.b2b.partners.index')->with([
            'success' => "Đã xoay Key thành công cho đại lý '{$partner->ten_dai_ly_api}'. Key cũ được duy trì trong 48 giờ chuyển tiếp.",
            'new_credential' => [
                'client_id' => $cauHinh->client_id,
                'secret_key' => $newSecret,
            ],
        ]);
    }

    public function revokeKey(int $id): RedirectResponse
    {
        $partner = DaiLyApi::findOrFail($id);
        $cauHinh = $partner->cauHinhApi;

        if ($cauHinh) {
            $cauHinh->update(['key_status' => 'revoked']);
        }

        return redirect()->route('admin.b2b.partners.index')->with('success', "Đã thu hồi API Key của đại lý '{$partner->ten_dai_ly_api}'. Các yêu cầu API tiếp theo sẽ bị từ chối.");
    }

    public function savePricing(Request $request, int $id): RedirectResponse
    {
        $partner = DaiLyApi::findOrFail($id);
        $prices = (array) $request->input('pricing', []);

        DB::transaction(function () use ($partner, $prices) {
            foreach ($prices as $sanPhamId => $item) {
                if (empty($item['enabled'])) {
                    BangGiaDaiLy::where('dai_ly_api_id', $partner->id)
                        ->where('san_pham_id', $sanPhamId)
                        ->delete();
                    continue;
                }

                $loai = $item['loai'] ?? 'PERCENT';
                $giaTri = (float) ($item['gia_tri'] ?? 0);
                $giaBan = !empty($item['gia_ban_ap_dung']) ? (float) $item['gia_ban_ap_dung'] : null;

                BangGiaDaiLy::updateOrCreate(
                    [
                        'dai_ly_api_id' => $partner->id,
                        'san_pham_id' => $sanPhamId,
                    ],
                    [
                        'loai_chiet_khau' => $loai,
                        'gia_tri_chiet_khau' => $giaTri,
                        'gia_ban_ap_dung' => $giaBan,
                        'trang_thai' => 'hoat_dong',
                    ]
                );
            }
        });

        return redirect()->route('admin.b2b.partners.index')->with('success', "Đã cập nhật bảng giá đại lý thành công.");
    }
}
