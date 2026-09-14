<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounts\StoreUserAccountRequest;
use App\Http\Requests\Admin\Accounts\UpdateUserAccountRequest;
use App\Http\Requests\Admin\Accounts\UpdateUserPasswordRequest;
use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use App\Services\Authorization\PermissionCacheService;
use App\Services\Audit\AuditService;

class UserAccountController extends Controller
{
    /**
     * Man danh sach tai khoan he thong.
     * Bao tri truy van, filter va du lieu dropdown modal tai controller nay.
     */
    public function index(Request $request): View
    {
        $query = $this->buildAccountsQuery($request);

        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true) ? (int) $request->query('per_page') : 10;

        $users = $query->latest()->paginate($perPage)->withQueryString();

        $stats = [
            'total' => User::where('trang_thai', '!=', 'da_xoa')->count(),
            'active' => User::where('trang_thai', 'hoat_dong')->where('bi_khoa', false)->count(),
            'locked' => User::where('bi_khoa', true)->where('trang_thai', '!=', 'da_xoa')->count(),
            'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->where('trang_thai', '!=', 'da_xoa')->count(),
        ];

        return view('admin.accounts', [
            'users' => $users,
            'stats' => $stats,
            'roles' => VaiTro::where('trang_thai', 'hoat_dong')->orderBy('ten_vai_tro')->get(),
            'accountTypes' => $this->accountTypes(),
            'statuses' => $this->statuses(),
            'filters' => $request->only(['q', 'loai_tai_khoan', 'trang_thai']),
        ]);
    }

    /**
     * Xuat danh sach tai khoan theo dung bo loc hien tai tren man danh sach.
     */
    public function exportExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $users = $this->buildAccountsQuery($request)
                ->latest()
                ->get();

            $fileName = 'danh-sach-tai-khoan-' . now()->format('Y-m-d-H-i') . '.xlsx';

            return Excel::download(
                new UsersExport($users, $this->accountTypes(), $this->statuses()),
                $fileName
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.accounts', $request->only(['q', 'loai_tai_khoan', 'trang_thai']))
                ->withErrors(['export' => 'Khong the xuat file Excel. Vui long thu lai sau.']);
        }
    }

    /**
     * Tao tai khoan moi tu modal admin.
     * Mat khau luon duoc Hash::make truoc khi luu vao users.
     */
    public function store(StoreUserAccountRequest $request): RedirectResponse
    {
        // NOTE: Chi lay du lieu da qua StoreUserAccountRequest de chan field ngoai y muon.
        // Neu sau nay them/sua field tao tai khoan, bao tri whitelist va rule trong StoreUserAccountRequest.
        $validated = $request->validated();

        $operator = auth()->user();
        if ($validated['loai_tai_khoan'] === 'admin' && (! $operator || ! $this->isRootAdmin($operator))) {
            abort(403, 'Chỉ Quản trị viên hệ thống mới có quyền tạo tài khoản Admin.');
        }

        // NOTE: Tao password tu input da validate hoac sinh ngau nhien theo checkbox.
        // Can lam tai day de password luon co gia tri hop le truoc khi Hash::make.
        $createRandomPassword = (bool) ($validated['tao_mat_khau_ngau_nhien'] ?? false);
        $plainPassword = $createRandomPassword
            ? Str::random(12)
            : (string) $validated['password'];

        DB::transaction(function () use ($validated, $plainPassword) {
            // NOTE: Du lieu tao user duoc lap tu mang validated, khong dung request all/input truc tiep.
            // Khi can bo sung field luu user, them rule validate truoc roi moi them vao mang nay.
            $user = User::create([
                'ten_dang_nhap' => $validated['ten_dang_nhap'],
                'ho' => $validated['ho'] ?? null,
                'ten' => $validated['ten'] ?? null,
                'name' => $this->resolveDisplayName($validated['name'] ?? null, $validated['ho'] ?? null, $validated['ten'] ?? null),
                'email' => ($validated['email'] ?? null) ?: null,
                'so_dien_thoai' => ($validated['so_dien_thoai'] ?? null) ?: null,
                'loai_tai_khoan' => $validated['loai_tai_khoan'],
                'password' => Hash::make($plainPassword),
                'trang_thai' => $validated['trang_thai'],
                'bi_khoa' => $validated['trang_thai'] !== 'hoat_dong',
                'tai_khoan_da_xac_thuc' => true,
                'email_da_xac_nhan' => false,
            ]);

            $this->syncRoles($user, $validated['vai_tro'] ?? []);

            app(AuditService::class)->record('user.created', $user, null, [
                'ten_dang_nhap' => $user->ten_dang_nhap,
                'loai_tai_khoan' => $user->loai_tai_khoan,
            ]);
        });

        return redirect()
            ->route('admin.accounts')
            ->with('success', 'Tạo tài khoản thành công.')
            ->with('temporary_password', $createRandomPassword ? $plainPassword : null);
    }

    /**
     * Cap nhat thong tin co ban cua tai khoan.
     * Mat khau chi duoc doi neu admin nhap password moi trong form sua.
     */
    public function update(UpdateUserAccountRequest $request, User $account): RedirectResponse
    {
        // NOTE: Chi lay du lieu da qua UpdateUserAccountRequest de so dien thoai/password luon dung rule backend.
        // Bao tri cac rule sua tai khoan trong UpdateUserAccountRequest, khong doc request all/input de luu.
        $validated = $request->validated();
        $operator = auth()->user();

        if ($this->isRootAdmin($account)) {
            return back()->withErrors(['account' => 'Không được sửa tài khoản admin gốc.']);
        }

        if ($this->isCurrentUser($account)) {
            if ((bool) ($validated['bi_khoa'] ?? false) || $validated['trang_thai'] !== 'hoat_dong') {
                return back()->withErrors(['account' => 'Không được tự khóa chính tài khoản đang đăng nhập.']);
            }
            if ($validated['loai_tai_khoan'] !== $account->loai_tai_khoan) {
                return back()->withErrors(['account' => 'Không được tự thay đổi loại tài khoản của chính mình.']);
            }
        }

        if ($validated['loai_tai_khoan'] === 'admin' && $account->loai_tai_khoan !== 'admin') {
            if (! $operator || ! $this->isRootAdmin($operator)) {
                abort(403, 'Chỉ Quản trị viên hệ thống mới có quyền gán loại tài khoản Admin.');
            }
        }

        DB::transaction(function () use ($validated, $account) {
            // NOTE: Mang cap nhat thong tin co ban chi gom field da validate.
            // Password duoc gan rieng ben duoi de de trong thi khong lam doi mat khau hien tai.
            $updateData = [
                'ten_dang_nhap' => $validated['ten_dang_nhap'],
                'ho' => $validated['ho'] ?? null,
                'ten' => $validated['ten'] ?? null,
                'name' => $this->resolveDisplayName($validated['name'] ?? null, $validated['ho'] ?? null, $validated['ten'] ?? null),
                'email' => ($validated['email'] ?? null) ?: null,
                'so_dien_thoai' => ($validated['so_dien_thoai'] ?? null) ?: null,
                'loai_tai_khoan' => $validated['loai_tai_khoan'],
                'trang_thai' => $validated['trang_thai'],
                'bi_khoa' => (bool) ($validated['bi_khoa'] ?? false) || $validated['trang_thai'] !== 'hoat_dong',
            ];

            // NOTE: Mat khau trong form sua la tuy chon; rong thi khong ghi de password cu.
            // Neu sau nay doi chinh sach password, sua rule min/confirmed tai UpdateUserAccountRequest.
            if (! empty($validated['password'])) {
                $updateData['password'] = Hash::make((string) $validated['password']);
            }

            $account->update($updateData);

            $this->syncRoles($account, $validated['vai_tro'] ?? []);

            app(PermissionCacheService::class)->forgetUser((int) $account->getKey());
            app(AuditService::class)->record('user.updated', $account, null, [
                'ten_dang_nhap' => $account->ten_dang_nhap,
                'loai_tai_khoan' => $account->loai_tai_khoan,
            ]);
        });

        return back()->with('success', 'Cập nhật tài khoản thành công.');
    }

    /**
     * Doi mat khau tai khoan tu dropdown hanh dong.
     * Khong bao gio tra password hash ra view.
     */
    public function updatePassword(UpdateUserPasswordRequest $request, User $account): RedirectResponse
    {
        // NOTE: Du lieu doi mat khau da duoc UpdateUserPasswordRequest validate min 8 va confirmed.
        // Bao tri chinh sach doi mat khau admin trong request nay, controller chi hash va luu.
        $validated = $request->validated();

        if ($this->isRootAdmin($account)) {
            return back()->withErrors(['account' => 'Không được đổi mật khẩu tài khoản admin gốc từ màn này.']);
        }

        $account->update([
            'password' => Hash::make((string) $validated['password']),
            'bat_buoc_doi_mat_khau' => (bool) ($validated['bat_buoc_doi_mat_khau'] ?? false),
        ]);

        app(PermissionCacheService::class)->forgetUser((int) $account->getKey());
        app(AuditService::class)->record('user.password.updated', $account, null, null);

        return back()->with('success', 'Đổi mật khẩu tài khoản thành công.');
    }

    /**
     * Khoa tai khoan bang field bi_khoa va trang_thai hien co.
     * Chan admin tu khoa chinh minh de tranh mat quyen truy cap.
     */
    public function lock(User $account): RedirectResponse
    {
        if ($this->isRootAdmin($account)) {
            return back()->withErrors(['account' => 'Không được khóa tài khoản admin gốc.']);
        }

        if ($this->isCurrentUser($account)) {
            return back()->withErrors(['account' => 'Không được tự khóa chính tài khoản đang đăng nhập.']);
        }

        $account->update([
            'bi_khoa' => true,
            'trang_thai' => 'tam_khoa',
        ]);

        app(PermissionCacheService::class)->forgetUser((int) $account->getKey());
        app(AuditService::class)->record('user.locked', $account, ['bi_khoa' => false], ['bi_khoa' => true]);

        return back()->with('success', 'Khóa tài khoản thành công.');
    }

    /**
     * Mo khoa tai khoan va dua ve trang thai hoat_dong.
     * Action nay giu nguyen thong tin vai tro va thong tin ca nhan.
     */
    public function unlock(User $account): RedirectResponse
    {
        if ($this->isRootAdmin($account)) {
            return back()->withErrors(['account' => 'Không cần mở khóa tài khoản admin gốc.']);
        }

        $account->update([
            'bi_khoa' => false,
            'trang_thai' => 'hoat_dong',
        ]);

        app(PermissionCacheService::class)->forgetUser((int) $account->getKey());
        app(AuditService::class)->record('user.unlocked', $account, ['bi_khoa' => true], ['bi_khoa' => false]);

        return back()->with('success', 'Mở khóa tài khoản thành công.');
    }

    /**
     * Xoa mem tai khoan bang trang_thai=da_xoa vi model users chua dung SoftDeletes.
     * Khong xoa vat ly de tranh mat du lieu lien quan don hang/log sau nay.
     */
    public function destroy(User $account): RedirectResponse
    {
        if ($this->isRootAdmin($account)) {
            return back()->withErrors(['account' => 'Không được xóa tài khoản admin gốc.']);
        }

        if ($this->isCurrentUser($account)) {
            return back()->withErrors(['account' => 'Không được xóa tài khoản đang đăng nhập.']);
        }

        $account->update([
            'bi_khoa' => true,
            'trang_thai' => 'da_xoa',
        ]);

        app(PermissionCacheService::class)->forgetUser((int) $account->getKey());
        app(AuditService::class)->record('user.deleted', $account, null, ['trang_thai' => 'da_xoa']);

        return back()->with('success', 'Xóa tài khoản thành công.');
    }

    /**
     * Xóa nhiều tài khoản cùng lúc (Xóa mềm và an toàn không xóa tài khoản đang đăng nhập/admin gốc).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $ids = $validated['ids'];
        $currentUserId = auth()->id();
        $deleted = 0;
        $skippedSelf = false;

        foreach ($ids as $id) {
            $user = User::find($id);
            if (!$user) continue;

            if ($this->isCurrentUser($user) || $this->isRootAdmin($user)) {
                $skippedSelf = true;
                continue;
            }

            try {
                $user->update([
                    'bi_khoa' => true,
                    'trang_thai' => 'da_xoa',
                ]);
                app(PermissionCacheService::class)->forgetUser((int) $user->getKey());
                app(AuditService::class)->record('user.deleted', $user, null, ['trang_thai' => 'da_xoa']);
                $deleted++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $msg = "Đã xóa thành công {$deleted} tài khoản đã chọn.";
        if ($skippedSelf) {
            $msg .= " (Hệ thống tự động bỏ qua tài khoản đang đăng nhập hoặc admin gốc).";
        }

        return back()->with('success', $msg);
    }

    /**
     * Sync vai tro bang pivot nguoi_dung_vai_tro da co san.
     * Kiểm tra quyền của operator: chống nâng quyền (privilege escalation).
     * Chỉ admin gốc mới được gán vai trò admin cho tài khoản khác.
     */
    private function syncRoles(User $user, array $roleIds): void
    {
        $operator = auth()->user();

        // Chống tự thay đổi vai trò của chính mình
        if ($operator && $this->isCurrentUser($user) && ! $this->isRootAdmin($operator)) {
            abort(403, 'Không được tự thay đổi vai trò của chính mình.');
        }

        // Kiểm tra quyền gán vai trò
        if ($operator && ! $operator->coQuyen('role.assign_permission') && ! $this->isRootAdmin($operator)) {
            return;
        }

        // Không cho phép tài khoản không phải admin gốc gán vai trò admin
        $adminRole = VaiTro::where('ma_vai_tro', 'admin')->first();
        if ($adminRole && in_array((int) $adminRole->id, array_map('intval', $roleIds), true)) {
            if (! $operator || ! $this->isRootAdmin($operator)) {
                abort(403, 'Chỉ Quản trị viên hệ thống mới có quyền cấp vai trò Admin.');
            }
        }

        if (empty($roleIds)) {
            $roleIds = [VaiTro::vaiTroMacDinh()->id];
        }

        $before = $user->vaiTro()->pluck('vai_tro.id')->all();
        $user->vaiTro()->sync(array_values(array_unique(array_map('intval', $roleIds))));
        app(PermissionCacheService::class)->forgetUser((int) $user->getKey());
        app(AuditService::class)->record('user.roles.updated', $user, ['role_ids' => $before], ['role_ids' => $roleIds]);
    }

    /**
     * Query dung chung cho man danh sach va export de ket qua khong lech bo loc.
     */
    private function buildAccountsQuery(Request $request): Builder
    {
        $query = User::with('vaiTro')
            ->where('trang_thai', '!=', 'da_xoa');

        if ($keyword = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('ten_dang_nhap', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('so_dien_thoai', 'like', "%{$keyword}%")
                    ->orWhere('ho', 'like', "%{$keyword}%")
                    ->orWhere('ten', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        if ($loaiTaiKhoan = $request->query('loai_tai_khoan')) {
            $query->where('loai_tai_khoan', $loaiTaiKhoan);
        }

        if ($trangThai = $request->query('trang_thai')) {
            if ($trangThai === 'bi_khoa') {
                $query->where('bi_khoa', true);
            } elseif ($trangThai === 'hoat_dong') {
                $query->where('trang_thai', 'hoat_dong')->where('bi_khoa', false);
            } else {
                $query->where('trang_thai', $trangThai);
            }
        }

        return $query;
    }

    /**
     * Chuan hoa ten hien thi tu field name hoac ghep ho + ten.
     */
    private function resolveDisplayName(?string $name, ?string $ho, ?string $ten): ?string
    {
        $displayName = trim((string) $name);

        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(trim((string) $ho) . ' ' . trim((string) $ten));

        return $fullName !== '' ? $fullName : null;
    }

    /**
     * Tai khoan admin goc hien duoc nhan dien theo seeder AdminSeeder.
     * Neu sau nay co cot is_root thi thay dieu kien tai day.
     */
    private function isRootAdmin(User $user): bool
    {
        return $user->ten_dang_nhap === 'admin' && ($user->loai_tai_khoan === 'admin' || $user->coVaiTro('admin'));
    }

    /**
     * Kiem tra user dang thao tac co phai chinh tai khoan dang dang nhap khong.
     */
    private function isCurrentUser(User $user): bool
    {
        return auth()->id() === $user->id;
    }

    /**
     * Danh sach loai tai khoan dung cho filter va form.
     */
    private function accountTypes(): array
    {
        return [
            'admin' => 'Quản trị viên',
            'ke_toan' => 'Kế toán',
            'doi_soat' => 'Đối soát',
            'sale' => 'Sale',
            'dai_ly' => 'Đại lý',
            'dai_ly_api' => 'Đại lý API',
            'customer' => 'Khách hàng',
        ];
    }

    /**
     * Danh sach trang thai tai khoan hien co trong bang users.
     */
    private function statuses(): array
    {
        return [
            'hoat_dong' => 'Hoạt động',
            'tam_khoa' => 'Tạm khóa',
            'cho_duyet' => 'Chờ duyệt',
            'huy' => 'Hủy',
            'da_xoa' => 'Đã xóa',
        ];
    }
}
