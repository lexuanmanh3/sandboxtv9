<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VaiTro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // -------------------------------------------------------------------------
    // ĐĂNG NHẬP
    // -------------------------------------------------------------------------

    /**
     * Hiển thị form đăng nhập.
     * Nếu đã đăng nhập rồi thì chuyển thẳng về trang chủ.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            $duongDan = $this->duongDanSauDangNhap(Auth::user());

            // Phiên đăng nhập hiện tại không còn quyền nào (ví dụ vai trò vừa bị gỡ quyền):
            // đăng xuất để không kẹt ở trạng thái "đã login nhưng không vào đâu được".
            if ($duongDan === null) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return view('auth.login');
            }

            return redirect($duongDan);
        }

        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập.
     * Hỗ trợ đăng nhập bằng tên đăng nhập hoặc email.
     */
    public function login(Request $request)
    {
        // Validate: bắt buộc nhập tên đăng nhập và mật khẩu
        $request->validate([
            'ten_dang_nhap' => 'required|string|max:150',
            'password'      => 'required|string',
        ], [
            'ten_dang_nhap.required' => 'Vui lòng nhập tên đăng nhập hoặc email.',
            'ten_dang_nhap.max'      => 'Tên đăng nhập quá dài.',
            'password.required'      => 'Vui lòng nhập mật khẩu.',
        ]);

        $dinhDanh = trim($request->ten_dang_nhap);

        // Tìm tài khoản: thử khớp ten_dang_nhap trước, sau đó thử email
        $user = User::where('ten_dang_nhap', $dinhDanh)
            ->orWhere('email', $dinhDanh)
            ->first();

        // Kiểm tra tài khoản tồn tại và mật khẩu hợp lệ
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()
                ->withInput($request->only('ten_dang_nhap')) 
                ->withErrors(['ten_dang_nhap' => 'Tên đăng nhập hoặc mật khẩu không đúng.']);
        }

        // Chặn tài khoản bị khóa thủ công
        if ($user->bi_khoa) {
            return back()
                ->withInput($request->only('ten_dang_nhap'))
                ->withErrors(['ten_dang_nhap' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.']);
        }

        // Chặn tài khoản không ở trạng thái hoạt động
        if ($user->trang_thai !== 'hoat_dong') {
            return back()
                ->withInput($request->only('ten_dang_nhap'))
                ->withErrors(['ten_dang_nhap' => 'Tài khoản chưa được kích hoạt hoặc đang tạm khóa.']);
        }

        // Đăng nhập thành công — ghi nhớ phiên nếu tick "nhớ tôi"
        Auth::login($user, $request->boolean('nho_mat_khau'));

        // Cập nhật thời gian đăng nhập gần nhất
        $user->update(['lan_dang_nhap_cuoi' => now()]);

        // Tạo lại session ID để tránh session fixation attack
        $request->session()->regenerate();

        $duongDan = $this->duongDanSauDangNhap($user);

        // Tài khoản chưa được cấp bất kỳ quyền truy cập nào (chưa gán vai trò
        // hoặc vai trò chưa có quyền .access nào): không đăng nhập được, báo lỗi rõ ràng
        // thay vì âm thầm đăng xuất.
        if ($duongDan === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('ten_dang_nhap'))
                ->withErrors(['ten_dang_nhap' => 'Tài khoản của bạn chưa được cấp quyền truy cập. Vui lòng liên hệ quản trị viên.']);
        }

        // Chuyển về trang người dùng muốn vào trước đó, hoặc về trang chủ
        return redirect($duongDan);
    }

    // -------------------------------------------------------------------------
    // ĐĂNG KÝ
    // -------------------------------------------------------------------------

    /**
     * Hiển thị form đăng ký tài khoản mới.
     */
    public function showRegisterForm()
    {
        if (Auth::check()) {
            $duongDan = $this->duongDanSauDangNhap(Auth::user());

            if ($duongDan === null) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                return view('auth.register');
            }

            return redirect($duongDan);
        }

        return view('auth.register');
    }

    /**
     * Xử lý đăng ký tài khoản người dùng thường.
     * Tự động gán vai trò "user" sau khi tạo xong.
     */
    public function register(Request $request)
    {
        // Validate toàn bộ thông tin đăng ký
        $request->validate([
            'ho'                     => 'required|string|max:50',
            'ten'                    => 'required|string|max:50',
            'ten_dang_nhap'          => [
                'required', 'string', 'max:100',
                'unique:users,ten_dang_nhap',
                'regex:/^[a-zA-Z0-9_\-\.]+$/',
            ],
            'email'                  => 'nullable|email|max:150|unique:users,email',
            'so_dien_thoai'          => 'nullable|string|max:20',
            'password'               => 'required|string|min:8|confirmed',
            'password_confirmation'  => 'required|string',
        ], [
            'ho.required'                    => 'Vui lòng nhập họ.',
            'ten.required'                   => 'Vui lòng nhập tên.',
            'ten_dang_nhap.required'         => 'Vui lòng nhập tên đăng nhập.',
            'ten_dang_nhap.unique'           => 'Tên đăng nhập đã tồn tại, vui lòng chọn tên khác.',
            'ten_dang_nhap.max'              => 'Tên đăng nhập không được quá 100 ký tự.',
            'ten_dang_nhap.regex'            => 'Tên đăng nhập chỉ được dùng chữ cái, số, dấu gạch dưới, gạch ngang và dấu chấm.',
            'email.email'                    => 'Email không đúng định dạng.',
            'email.unique'                   => 'Email này đã được đăng ký, vui lòng dùng email khác.',
            'password.required'              => 'Vui lòng nhập mật khẩu.',
            'password.min'                   => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed'             => 'Mật khẩu xác nhận không khớp.',
            'password_confirmation.required' => 'Vui lòng nhập lại mật khẩu để xác nhận.',
        ]);

        // Tạo tài khoản người dùng mới với loại "customer" (người dùng thường)
        // Lưu ý: loai_tai_khoan = 'customer' vì enum trong DB không có giá trị 'user'
        $user = User::create([
            'ho'                    => $request->ho,
            'ten'                   => $request->ten,
            'name'                  => trim($request->ho . ' ' . $request->ten),
            'ten_dang_nhap'         => $request->ten_dang_nhap,
            'email'                 => $request->email ?: null,
            'so_dien_thoai'         => $request->so_dien_thoai ?: null,

            // Model User tự hash mật khẩu qua setPasswordAttribute()
            'password'              => $request->password,

            // Loại tài khoản thường — không phải admin
            'loai_tai_khoan'        => 'customer',

            // Trạng thái mặc định: kích hoạt ngay
            'trang_thai'            => 'hoat_dong',
            'bi_khoa'               => false,
            'tai_khoan_da_xac_thuc' => true,
            'email_da_xac_nhan'     => false,
        ]);

        // Gán vai trò mặc định được cấu hình ở màn quản lý vai trò (mac_dinh=true).
        // Không hard-code 'user' ở đây để admin có thể đổi vai trò mặc định qua UI.
        $vaiTroNguoiDung = VaiTro::vaiTroMacDinh();

        // Gán vai trò qua bảng pivot nguoi_dung_vai_tro
        // syncWithoutDetaching: không xóa vai trò cũ nếu đã có
        $user->vaiTro()->syncWithoutDetaching([$vaiTroNguoiDung->id]);

        // Tự động đăng nhập sau khi đăng ký thành công
        Auth::login($user);
        $request->session()->regenerate();

        $duongDan = $this->duongDanSauDangNhap($user);

        // Trường hợp hiếm: vai trò "user" chưa được cấp quyền frontend nào (ví dụ bị admin
        // gỡ quyền trong lúc đăng ký) — báo lỗi rõ ràng thay vì âm thầm đăng xuất.
        if ($duongDan === null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['ten_dang_nhap' => 'Tài khoản của bạn chưa được cấp quyền truy cập. Vui lòng liên hệ quản trị viên.']);
        }

        return redirect($duongDan)
            ->with('dangky_thanh_cong', 'Chào mừng ' . $user->ten_hien_thi . '! Tài khoản đã được tạo thành công.');
    }

    // -------------------------------------------------------------------------
    // ĐĂNG XUẤT
    // -------------------------------------------------------------------------

    /**
     * Đăng xuất người dùng.
     * Hủy session hiện tại và tạo CSRF token mới.
     */
    public function logout(Request $request)
    {
        // Đăng xuất khỏi guard web
        Auth::logout();

        // Hủy toàn bộ dữ liệu session để sạch sẽ
        $request->session()->invalidate();

        // Tạo lại CSRF token mới để tránh CSRF attack sau logout
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Bạn đã đăng xuất thành công.');
    }

    /**
     * Chọn trang đầu tiên sau đăng nhập theo quyền truy cập của vai trò.
     * Backend được ưu tiên vì tài khoản quản trị thường cần vào dashboard quản trị trước.
     *
     * Trả về null nếu user không có bất kỳ quyền .access nào (chưa gán vai trò,
     * hoặc vai trò chưa có quyền) — nơi gọi chịu trách nhiệm đăng xuất và báo lỗi rõ ràng,
     * hàm này chỉ tính toán đường dẫn nên không tự ý Auth::logout() ở đây.
     */
    private function duongDanSauDangNhap(User $user): ?string
    {
        $adminRoutes = [
            'dashboard.access' => route('admin.dashboard'),
            'account.access' => route('admin.accounts'),
            'role.access' => route('admin.roles'),
        ];

        foreach ($adminRoutes as $permission => $url) {
            if ($user->coQuyen($permission)) {
                return $url;
            }
        }

        $frontendRoutes = [
            'frontend.home.access' => route('frontend.home'),
            'frontend.topup.access' => route('frontend.topup'),
        ];

        foreach ($frontendRoutes as $permission => $url) {
            if ($user->coQuyen($permission)) {
                return $url;
            }
        }

        return null;
    }
}
