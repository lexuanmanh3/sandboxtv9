# PROJECT AUDIT DOCUMENTATION

> **Phạm vi:** Đọc và phân tích source code (read-only) — không sửa logic, không refactor, không đổi tên/xóa file.
> **Cấp độ:** Audit Level 2 — tập trung sâu vào HTTP method, CRUD user, phân quyền, validate và bảo mật.
> **Dự án:** `sandbox` (tên hiển thị trong UI: *VietFin Portal*) — Laravel 9.11, PHP ^8.0.2.
> **Nhánh:** `main`.

Chú thích nhãn dùng trong tài liệu: `CRITICAL` (nghiêm trọng), `HTTP_METHOD_ISSUE` (sai HTTP method), `MISSING_MIDDLEWARE` (thiếu middleware bảo vệ), `PERMISSION_RISK` (rủi ro phân quyền), `CẦN KIỂM TRA THÊM` (chưa đủ căn cứ để kết luận chắc chắn).

---

## 1. Tổng quan dự án

- **Framework:** Laravel `^9.11`, PHP `^8.0.2`. Kiến trúc MVC truyền thống (Controller + Blade + Eloquent), không phải SPA.
- **Mục tiêu chính:** Cổng trung gian nạp tiền điện thoại / thẻ cào / thanh toán hóa đơn (`dich_vu`: TOPUP, PIN_CODE, PAY_BILL), vận hành qua mạng lưới đại lý thường và đại lý API (partner tích hợp), có tầng nhà cung cấp thật xử lý giao dịch (retry/fallback), đối soát và log API.
- **Phân quyền:** Tự viết RBAC 3 tầng (`users – vai_tro – quyen`) bằng 2 bảng trung gian, **không dùng** package có sẵn (spatie/laravel-permission…), **không dùng** Gate/Policy của Laravel (`AuthServiceProvider::$policies` rỗng).
- **Module chính hiện có:** Đăng nhập/Đăng ký/Đăng xuất, Quản lý tài khoản hệ thống (admin), Quản lý vai trò & cây quyền module, Trang chủ + trang nạp tiền (khách hàng, giao diện tĩnh), Dashboard "Quản lý lô hàng" (giao diện tĩnh), API `/api/user` và `/api/customers`.
- **Tình trạng tổng quan:** Khung xác thực + quản trị tài khoản/phân quyền đã chạy được và khá chỉn chu. Toàn bộ lõi nghiệp vụ nạp tiền thật (dịch vụ, sản phẩm, nhà cung cấp, đơn hàng, log) mới dừng ở tầng database (migration + model), **chưa có Controller/Route/View nào**.

---

## 2. Cấu trúc thư mục dự án

| Thư mục | Vai trò |
|---|---|
| `routes/` | `web.php` (giao diện, session, CSRF) và `api.php` (JSON, prefix `/api`, throttle 60 req/phút) |
| `app/Http/Controllers/` | `AuthController`, `CustomerController`, `Controller.php` (base rỗng); con: `Admin/` (UserAccountController, RoleAccessController), `Frontend/` (HomeController) |
| `app/Http/Requests/Admin/` | 3 Form Request validate cho tạo/sửa tài khoản, đổi mật khẩu |
| `app/Http/Middleware/` | `KiemTraQuyen` (alias `quyen`, đang dùng), `KiemTraVaiTro` (alias `vai_tro`, **không route nào dùng**) |
| `app/Models/` | `User`, `Customer`, `VaiTro`, `Quyen` + 15 model nghiệp vụ nạp tiền (dịch vụ, sản phẩm, nhà cung cấp, đơn hàng, log…) |
| `app/Providers/` | 4 provider, giữ nguyên mặc định Laravel 9 — chưa đăng ký Gate/Policy/binding riêng |
| `database/migrations/` | 26 bảng (chi tiết mục 4) |
| `database/seeders/` | `VaiTroSeeder` (7 vai trò) → `QuyenSeeder` (cây quyền + gán mặc định) → `AdminSeeder` (tài khoản admin gốc) → `DaiLyApiSeeder` |
| `resources/views/` | `layout`, `home`, `loaisanpham` (khách hàng); `auth/login`, `auth/register`; `admin/layout`, `admin/dashboard`, `admin/accounts`, `admin/roles`; `components/icon*` |
| `resources/js/`, `resources/css/` | Boilerplate mặc định Laravel/Vite, không được view nào dùng thực tế |
| `config/` | Giữ nguyên mặc định Laravel 9 (timezone UTC, locale `en`) — chưa customize dù ứng dụng hướng người dùng Việt Nam |

---

## 3. Danh sách file quan trọng và nhiệm vụ từng file

| File | Nhiệm vụ | Liên quan đến chức năng | Ghi chú |
|---|---|---|---|
| `routes/web.php` | Khai báo toàn bộ route giao diện | Auth, Admin, Frontend | Có 2 route trùng (`/App/Users`, `/App/Roles`) — xem mục 9 |
| `routes/api.php` | Khai báo route JSON | API user, API customers | `/api/customers` không có middleware — `MISSING_MIDDLEWARE` |
| `app/Http/Controllers/AuthController.php` | Đăng nhập, đăng ký, đăng xuất, điều hướng theo quyền | Auth | Có bug điều hướng khi user không có quyền nào — xem mục 8.7 |
| `app/Http/Controllers/Admin/UserAccountController.php` | CRUD tài khoản, khóa/mở khóa, đổi mật khẩu, export Excel | Quản lý user | 5 TODO audit log chưa làm; bảo vệ admin gốc bằng hard-code |
| `app/Http/Controllers/Admin/RoleAccessController.php` | Danh sách vai trò, cập nhật cây quyền module cho vai trò | Phân quyền | Logic `sync` giữ quyền ngoài phạm vi — an toàn |
| `app/Http/Controllers/CustomerController.php` | Resource controller cho bảng `customers` | API customers | 5/6 method rỗng (stub Artisan) |
| `app/Http/Controllers/Frontend/HomeController.php` | Trang chủ, trang nạp tiền, trang thông tin tài khoản | Frontend | `thongtintaikhoan()` không có view, không có route — code chết |
| `app/Http/Middleware/KiemTraQuyen.php` | Chặn theo quyền `.access`, hỗ trợ nhiều mã cách nhau bởi dấu phẩy | Phân quyền route | Chưa đăng nhập → redirect login; thiếu quyền → abort 403 |
| `app/Http/Middleware/KiemTraVaiTro.php` | Chặn theo 1 mã vai trò cụ thể | Phân quyền route | Không được gắn vào route nào hiện tại |
| `app/Http/Requests/Admin/StoreUserAccountRequest.php` | Validate tạo tài khoản | Tạo user | `loai_tai_khoan`/`trang_thai` giới hạn bằng `Rule::in`, không giới hạn "ai được tạo loại nào" |
| `app/Http/Requests/Admin/UpdateUserAccountRequest.php` | Validate sửa tài khoản | Sửa user | Unique bỏ qua chính user đang sửa |
| `app/Http/Requests/Admin/UpdateUserPasswordRequest.php` | Validate đổi mật khẩu | Đổi mật khẩu | required + min 8 + confirmed |
| `app/Models/User.php` | Model auth, chứa `coQuyen()`, `coVaiTro()`, `coMotTrongCacQuyen()`, tự hash password | Auth + phân quyền | Comment nói "admin bypass toàn quyền" nhưng **code không có bypass này** |
| `app/Models/VaiTro.php`, `Quyen.php` | Model vai trò và quyền, quan hệ nhiều-nhiều qua bảng trung gian | Phân quyền | `Quyen` tắt `$timestamps` |
| `database/seeders/QuyenSeeder.php` | Dựng cây quyền + gán quyền mặc định cho vai trò `admin` và `user` | Phân quyền | Chỉ 2/7 vai trò được gán quyền mặc định — xem mục 8 |
| `database/seeders/AdminSeeder.php` | Tạo tài khoản admin gốc (`ten_dang_nhap=admin`) và gán vai trò `admin` | Auth gốc | Mật khẩu mặc định `12345678` — cần đổi ngay ở môi trường thật |
| `database/seeders/VaiTroSeeder.php` | Seed 7 vai trò cố định | Phân quyền | `admin, backend, ke_toan, doi_soat, agent, agent_api, user` |

---

## 4. Database hiện tại

26 bảng, **không bảng nào dùng soft delete** (`deleted_at`).

| Bảng | Chức năng | Cột quan trọng | Liên quan đến |
|---|---|---|---|
| `users` | Tài khoản đăng nhập mọi loại | `ten_dang_nhap`, `loai_tai_khoan`, `trang_thai`, `bi_khoa`, `bat_buoc_doi_mat_khau` | ⇄ `vai_tro` qua `nguoi_dung_vai_tro` |
| `vai_tro` | Vai trò (role) | `ma_vai_tro`, `mac_dinh`, `trang_thai` | ⇄ `users`, ⇄ `quyen` |
| `quyen` | Quyền, dạng cây cha-con | `quyen_cha_id`, `ma_quyen`, `trang_thai` | ⇄ `vai_tro` qua `vai_tro_quyen` |
| `nguoi_dung_vai_tro` | Trung gian user ↔ vai trò | `nguoi_dung_id`, `vai_tro_id` | FK cascade tới `users`, `vai_tro` |
| `vai_tro_quyen` | Trung gian vai trò ↔ quyền | `vai_tro_id`, `quyen_id` | FK cascade tới `vai_tro`, `quyen` |
| `personal_access_tokens` | Token API (Sanctum) | `tokenable_*`, `token` | polymorphic → `users` |
| `password_resets`, `failed_jobs` | Bảng mặc định Laravel | — | — |
| `customers` | Bảng khách hàng **độc lập, mới** | `name`, `email`, `phone` | **Không FK** tới `users` hay vai trò/quyền |
| `dich_vu` | Dịch vụ lớn: TOPUP, PIN_CODE, PAY_BILL | `ma_dich_vu`, `trang_thai` | gốc của `loai_san_pham`, `san_pham` |
| `loai_san_pham` | Nhóm sản phẩm, cây cha-con | `loai_san_pham_cha_id`, `dich_vu_id` | FK → `dich_vu`, tự tham chiếu |
| `san_pham` | Sản phẩm/mệnh giá | `menh_gia`, `dich_vu_id`, `loai_san_pham_id` | FK → `dich_vu`, `loai_san_pham` |
| `nha_cung_cap` | Nhà cung cấp xử lý giao dịch thật | `ma_ncc`, cây cha-con | tự tham chiếu |
| `ket_noi_nha_cung_cap` | Kết nối API tới NCC (field mã hóa) | `password_ma_hoa`, `api_password_ma_hoa` (cast `encrypted`) | FK → `nha_cung_cap` |
| `cau_hinh_dich_vu` | Chọn NCC xử lý cho dịch vụ/sản phẩm | `muc_uu_tien`, `dang_mo` | FK → `nha_cung_cap`, `dich_vu`, `loai_san_pham`, `san_pham` |
| `dai_ly_api` | Đối tác tích hợp qua API | `ma_dai_ly_api`, `nguoi_dung_id` | FK → `users` (nullable) |
| `cau_hinh_api_dai_ly` | Cấu hình kiểu OAuth client | `client_id`, `secret_key_ma_hoa` (encrypted) | FK → `dai_ly_api` |
| `dai_ly_api_*_duoc_phep` (3 bảng) | Dịch vụ/sản phẩm/loại sp đại lý API được phép dùng | — | Trung gian → `dai_ly_api` |
| `don_hang` | **Bảng trung tâm** đơn giao dịch | `ma_don_hang`, `idempotency_key`, `trang_thai_*` | FK → `users`, `dai_ly_api`, `dich_vu`, `san_pham`, `nha_cung_cap` |
| `lich_su_trang_thai_don_hang` | Lịch sử đổi trạng thái đơn | `trang_thai_cu/moi`, `thay_doi_boi` | FK → `don_hang`, `users` |
| `log_api` | Log request/response API | `request_body_json`, `response_body_json` | FK → `don_hang` (nullable) |
| `lan_goi_nha_cung_cap` | Từng lần gọi NCC (retry/fallback) | `lan_thu`, `trang_thai` | FK → `don_hang`, `nha_cung_cap` |

### Phân tích chi tiết bảng `users`

| Cột | Kiểu / mặc định | Ghi chú |
|---|---|---|
| `ten_dang_nhap` | string, unique | Dùng để đăng nhập |
| `email`, `so_dien_thoai` | nullable, `email` unique | Đăng nhập cũng chấp nhận email |
| `password` | string | Tự hash qua `setPasswordAttribute()` (mutator), dùng `Hash::needsRehash()` để tránh hash lại giá trị đã hash |
| `ho`, `ten`, `name` | nullable | 3 field tên khác nhau — `name` ưu tiên hiển thị nếu có |
| `loai_tai_khoan` | string, default `customer`, **không phải enum DB thật** | Giá trị dự kiến: `admin, ke_toan, doi_soat, sale, dai_ly, dai_ly_api, customer` — chỉ là nhãn phân loại, **không dùng để chặn route** |
| `trang_thai` | string, default `hoat_dong` | `hoat_dong, tam_khoa, cho_duyet, huy, da_xoa` (giá trị `da_xoa` dùng cho xóa mềm tự chế) |
| `bi_khoa` | boolean, default false | Cờ khóa thủ công, độc lập với `trang_thai` |
| `bat_buoc_doi_mat_khau` | boolean | **Được set bởi admin khi đổi mật khẩu, nhưng KHÔNG có logic nào ở `AuthController::login()` đọc/áp dụng cờ này** — field tồn tại nhưng chưa có tác dụng thật |
| `tu_dong_khoa_tai_khoan`, `tai_khoan_da_xac_thuc`, `email_da_xac_nhan` | boolean | Chưa thấy logic nghiệp vụ nào sử dụng các cờ này (không có luồng xác minh email/tự động khóa) |

**Không có** cột `role`, `is_admin`, `quyen_id` trực tiếp trên `users` — toàn bộ kiểm soát truy cập route dựa vào 2 bảng trung gian `nguoi_dung_vai_tro` và `vai_tro_quyen`.

---

## 5. Danh sách endpoint `web.php`

| Method | Endpoint | Route Name | Controller@Method | Chức năng | Middleware |
|---|---|---|---|---|---|
| GET | `/` | `frontend.home` | `HomeController@index` | Trang chủ khách hàng | `auth, quyen:frontend.home.access` |
| GET | `/nap-tien-dien-thoai` | `frontend.topup` | `HomeController@loaisanpham` | Trang nạp tiền điện thoại | `auth, quyen:frontend.topup.access` |
| GET | `/login` | `login` | `AuthController@showLoginForm` | Form đăng nhập | `guest` |
| POST | `/login` | `login.submit` | `AuthController@login` | Xử lý đăng nhập | `guest` |
| GET | `/register` | `register` | `AuthController@showRegisterForm` | Form đăng ký | `guest` |
| POST | `/register` | `register.submit` | `AuthController@register` | Xử lý đăng ký | `guest` |
| POST | `/logout` | `logout` | `AuthController@logout` | Đăng xuất | `auth` |
| GET | `/admin` | `admin.dashboard` | Closure → `view('admin.dashboard')` | Dashboard "quản lý lô hàng" (mock) | `auth, quyen:dashboard.access` |
| GET | `/admin/accounts` | `admin.accounts` | `Admin\UserAccountController@index` | Danh sách tài khoản | `auth, quyen:account.access` |
| GET | `/admin/accounts/export` | `admin.accounts.export` | `Admin\UserAccountController@exportExcel` | Export Excel | `auth, quyen:account.access` |
| GET | `/App/Users` | `app.users` | `Admin\UserAccountController@index` | **Trùng** với `admin.accounts` | `auth, quyen:account.access` |
| POST | `/admin/accounts` | `admin.accounts.store` | `Admin\UserAccountController@store` | Tạo tài khoản | `auth, quyen:account.access` |
| PUT | `/admin/accounts/{account}` | `admin.accounts.update` | `Admin\UserAccountController@update` | Sửa tài khoản | `auth, quyen:account.access` |
| PUT | `/admin/accounts/{account}/password` | `admin.accounts.password` | `Admin\UserAccountController@updatePassword` | Đổi mật khẩu tài khoản | `auth, quyen:account.access` |
| PATCH | `/admin/accounts/{account}/lock` | `admin.accounts.lock` | `Admin\UserAccountController@lock` | Khóa tài khoản | `auth, quyen:account.access` |
| PATCH | `/admin/accounts/{account}/unlock` | `admin.accounts.unlock` | `Admin\UserAccountController@unlock` | Mở khóa tài khoản | `auth, quyen:account.access` |
| DELETE | `/admin/accounts/{account}` | `admin.accounts.destroy` | `Admin\UserAccountController@destroy` | Xóa mềm tài khoản | `auth, quyen:account.access` |
| GET | `/admin/roles` | `admin.roles` | `Admin\RoleAccessController@index` | Danh sách vai trò + cây quyền | `auth, quyen:role.access` |
| GET | `/App/Roles` | `app.roles` | `Admin\RoleAccessController@index` | **Trùng** với `admin.roles` | `auth, quyen:role.access` |
| PUT | `/admin/roles/{role}/permissions` | `admin.roles.permissions` | `Admin\RoleAccessController@updatePermissions` | Cập nhật quyền cho vai trò | `auth, quyen:role.access` |
| GET | `/admin/services` | `admin.services` | `Admin\ServiceController@index` | Danh sách dịch vụ | `auth, quyen:dich_vu.access` |
| GET | `/admin/services/export` | `admin.services.export` | `Admin\ServiceController@exportExcel` | Export Excel danh sách dịch vụ | `auth, quyen:dich_vu.access` |
| GET | `/App/Services` | `app.services` | `Admin\ServiceController@index` | Alias URL, theo đúng convention `/App/Users`, `/App/Roles` | `auth, quyen:dich_vu.access` |
| POST | `/admin/services` | `admin.services.store` | `Admin\ServiceController@store` | Tạo dịch vụ | `auth, quyen:dich_vu.access` |
| PUT | `/admin/services/{service}` | `admin.services.update` | `Admin\ServiceController@update` | Sửa dịch vụ | `auth, quyen:dich_vu.access` |
| DELETE | `/admin/services/{service}` | `admin.services.destroy` | `Admin\ServiceController@destroy` | Xóa dịch vụ (chặn nếu còn dữ liệu liên kết) | `auth, quyen:dich_vu.access` |

---

## 6. Danh sách endpoint `api.php`

| Method | Endpoint | Route Name | Controller@Method | Chức năng | Middleware |
|---|---|---|---|---|---|
| GET | `/api/user` | — | Closure → `$request->user()` | Trả thông tin user hiện tại | `auth:sanctum` |
| GET | `/api/customers` | — | `CustomerController@index` | Danh sách khách hàng | **Không có** — `MISSING_MIDDLEWARE`, `CRITICAL` |

---

## 7. Flow chức năng hiện tại

```text
Đăng nhập:
auth/login.blade.php (#loginForm, POST)
  -> route login.submit (POST /login, middleware: guest)
  -> AuthController@login
  -> Model User::where(ten_dang_nhap/email)->first() + Hash::check()
  -> update users.lan_dang_nhap_cuoi, session()->regenerate()
  -> Response: redirect() theo duongDanSauDangNhap($user) (dò quyền admin trước, rồi frontend, rỗng thì logout + về /login)

Đăng xuất:
layout.blade.php (#logoutForm, POST) / admin/layout.blade.php
  -> route logout (POST /logout, middleware: auth)
  -> AuthController@logout
  -> Auth::logout() + session()->invalidate() + regenerateToken()
  -> Response: redirect('/login') kèm session('success')

Đăng ký:
auth/register.blade.php (#registerForm, POST)
  -> route register.submit (POST /register, middleware: guest)
  -> AuthController@register (validate inline)
  -> Model User::create(loai_tai_khoan cứng = 'customer') + VaiTro::firstOrCreate('user') + syncWithoutDetaching
  -> Response: Auth::login() tự động + redirect theo duongDanSauDangNhap()

Xem danh sách user (admin):
admin/accounts.blade.php (filter form, GET)
  -> route admin.accounts (GET /admin/accounts, middleware: auth, quyen:account.access)
  -> Admin\UserAccountController@index -> buildAccountsQuery() + stats
  -> Model User::with('vaiTro')->paginate(10)
  -> Response: view('admin.accounts', [...])

Tạo user (admin):
admin/accounts.blade.php (modal tạo, POST)
  -> route admin.accounts.store (POST /admin/accounts, middleware: auth, quyen:account.access)
  -> Admin\UserAccountController@store (StoreUserAccountRequest validate)
  -> Model User::create() trong DB::transaction + syncRoles()
  -> Response: redirect()->route('admin.accounts')->with('success', ...)->with('temporary_password', ...)

Sửa user (admin):
admin/accounts.blade.php (modal sửa, PUT qua @method('PUT'))
  -> route admin.accounts.update (PUT /admin/accounts/{account}, middleware: auth, quyen:account.access)
  -> Admin\UserAccountController@update (UpdateUserAccountRequest validate + chặn isRootAdmin/isCurrentUser)
  -> Model $account->update() trong DB::transaction + syncRoles()
  -> Response: back()->with('success', ...)

Đổi mật khẩu user (admin):
admin/accounts.blade.php (modal đổi mật khẩu, PUT qua @method('PUT'))
  -> route admin.accounts.password (PUT /admin/accounts/{account}/password, middleware: auth, quyen:account.access)
  -> Admin\UserAccountController@updatePassword (UpdateUserPasswordRequest validate + chặn isRootAdmin)
  -> Model $account->update(['password' => Hash::make(...)])
  -> Response: back()->with('success', ...)

Khóa/Mở khóa user (admin):
admin/accounts.blade.php (#accountQuickActionForm, PATCH qua @method('PATCH'))
  -> route admin.accounts.lock / admin.accounts.unlock (PATCH, middleware: auth, quyen:account.access)
  -> Admin\UserAccountController@lock / @unlock (chặn isRootAdmin/isCurrentUser cho lock)
  -> Model $account->update(['bi_khoa'=>.., 'trang_thai'=>..])
  -> Response: back()->with('success', ...)

Xóa user (admin, xóa mềm):
admin/accounts.blade.php (modal xóa, DELETE qua @method('DELETE'))
  -> route admin.accounts.destroy (DELETE /admin/accounts/{account}, middleware: auth, quyen:account.access)
  -> Admin\UserAccountController@destroy (chặn isRootAdmin/isCurrentUser)
  -> Model $account->update(['bi_khoa'=>true, 'trang_thai'=>'da_xoa'])  -- KHÔNG dùng SoftDeletes/deleted_at thật
  -> Response: back()->with('success', ...)

Phân quyền vai trò (admin):
admin/roles.blade.php (modal cây quyền, PUT qua @method('PUT'))
  -> route admin.roles.permissions (PUT /admin/roles/{role}/permissions, middleware: auth, quyen:role.access)
  -> Admin\RoleAccessController@updatePermissions (validate mảng ID quyền tồn tại)
  -> Model $role->quyen()->sync(giữ quyền ngoài phạm vi + quyền mới chọn)
  -> Response: back()->with('success', ...)

Dashboard "lô hàng" (mock, chưa nối DB):
Route::get('/admin', closure) -- KHÔNG qua Controller
  -> Response: view('admin.dashboard') với $rows hard-code ngay trong Blade

Trang chủ / Nạp tiền điện thoại (khách hàng, chưa nối DB):
route frontend.home / frontend.topup -> HomeController@index / @loaisanpham
  -> Response: view('home') / view('loaisanpham') -- không truyền dữ liệu nào

Customer API (chưa hoàn thiện):
route GET /api/customers (KHÔNG middleware) -> CustomerController@index
  -> Model Customer::all()
  -> Response: JSON toàn bộ bảng customers, không phân trang
```

---

## 8. Phân tích user, auth và phân quyền

### 8.1 Cơ chế đăng nhập
- Guard `web`, driver `session`, provider `users` = `App\Models\User`.
- Field đăng nhập: `ten_dang_nhap` **hoặc** `email` (cùng 1 ô input, controller tự `where(...)->orWhere(...)`).
- Kiểm tra thêm sau khi xác thực đúng mật khẩu: `bi_khoa` (chặn nếu true) và `trang_thai !== 'hoat_dong'` (chặn nếu khác `hoat_dong`) — 2 lớp chặn độc lập với hệ quyền.
- Chống session fixation: gọi `$request->session()->regenerate()` sau khi đăng nhập/đăng ký — **đúng chuẩn bảo mật**.
- Đăng xuất: `invalidate()` + `regenerateToken()` — đúng chuẩn.

### 8.2 Hash mật khẩu
- `Hash::make()` dùng nhất quán ở: `AuthController::register()` (qua mutator), `UserAccountController::store/update/updatePassword` (gọi `Hash::make()` trực tiếp).
- `User::setPasswordAttribute()` dùng `Hash::needsRehash()` để tránh hash 2 lần khi giá trị gán vào đã là hash hợp lệ theo cấu hình hiện tại — thiết kế hợp lý, nhưng **nếu ai đó set `password` trực tiếp bằng SQL thô (không qua Eloquent), tài khoản đó sẽ không đăng nhập được** vì `Hash::check()` sẽ luôns false trên chuỗi không phải hash bcrypt hợp lệ (`CẦN KIỂM TRA THÊM` nếu có quy trình vận hành nào làm việc này).

### 8.3 Cơ chế phân quyền
- RBAC 3 tầng: `users ⇄ vai_tro` (qua `nguoi_dung_vai_tro`) và `vai_tro ⇄ quyen` (qua `vai_tro_quyen`).
- Middleware `KiemTraQuyen` (`quyen:ma_quyen`) gọi `$user->coMotTrongCacQuyen([...])`: chỉ tính vai trò và quyền đang `trang_thai = hoat_dong`.
- **Không dùng** Gate/Policy của Laravel, không dùng `$request->user()->can(...)`.
- Cột `loai_tai_khoan` trên `users` **không** tham gia vào quyết định phân quyền route — chỉ dùng cho các hàm nhãn `laAdmin(), laKeToan()`... (hiện các hàm này **không được gọi ở bất kỳ đâu** trong controller/middleware đã đọc — `CẦN KIỂM TRA THÊM` xem có dự định dùng ở module tương lai không).

### 8.4 Danh sách vai trò (`vai_tro`) và quyền mặc định — bảng ma trận

| Vai trò (`ma_vai_tro`) | Quyền được seed mặc định (`QuyenSeeder`) | Route được phép truy cập | Route sẽ bị 403 | Vấn đề phát hiện | Đề xuất |
|---|---|---|---|---|---|
| `admin` | `dashboard.access`, `inventory.access`, `account.access`, `role.access`, `service_config.access` | `/admin`, `/admin/accounts*`, `/admin/roles*` | `/`, `/nap-tien-dien-thoai` (không được seed `frontend.*.access`) | `PERMISSION_RISK` (mức thấp, có thể là chủ ý): admin không tự vào được trang khách hàng nếu gõ thẳng URL | Xác nhận có chủ ý hay cần seed thêm `frontend.home.access` cho admin |
| `user` (khách hàng đăng ký) | `frontend.home.access`, `frontend.topup.access` | `/`, `/nap-tien-dien-thoai` | Toàn bộ `/admin*` | Không phát hiện vấn đề | — |
| `backend`, `ke_toan`, `doi_soat`, `agent`, `agent_api` | **Không có quyền nào được seed mặc định** | **Không route nào** (mọi route đều yêu cầu ít nhất 1 quyền `.access`) | Toàn bộ route trong hệ thống | `CRITICAL` — xem chi tiết mục 8.7 (đăng nhập thành công nhưng bị đá về login không rõ lý do) | Phải gán quyền qua `/admin/roles` trước khi cấp tài khoản dùng các vai trò này, hoặc bổ sung xử lý lỗi rõ ràng khi không có quyền nào |
| *(không có vai trò nào)* — user được tạo nhưng không tick `vai_tro[]` | Không quyền | Không route nào | Toàn bộ | `CRITICAL`, giống trên | UserAccountController nên cảnh báo/validate bắt buộc chọn ít nhất 1 vai trò khi tạo tài khoản |

Ghi chú thêm: quyền `policy.access` và `report.access` được khai báo trong cây quyền (`QuyenSeeder`) nhưng **không được gán mặc định cho bất kỳ vai trò nào**, và cũng chưa có route/controller nào bảo vệ bằng 2 mã quyền này — nhất quán với việc các module "Báo cáo", "Quản lý chính sách" chưa được lập trình.

### 8.5 Audit CRUD user chi tiết

**Tạo user**
- Method: `POST /admin/accounts`, form đúng chuẩn (`method="POST"` + `@csrf`).
- Validate: `StoreUserAccountRequest` — unique `ten_dang_nhap`/`email`, regex username, SĐT đúng 10 số bắt đầu bằng 0, `loai_tai_khoan`/`trang_thai` giới hạn `Rule::in` (client không thể truyền giá trị tùy ý ngoài whitelist).
- Password: `Hash::make()` trước khi lưu — đúng.
- Kiểm tra quyền: chỉ ở middleware route (`quyen:account.access`); **controller không kiểm tra thêm ai được phép tạo loại tài khoản nào**.
- `PERMISSION_RISK`: bất kỳ tài khoản nào có quyền `account.access` đều có thể tạo tài khoản mới với `loai_tai_khoan = 'admin'` — không có ràng buộc "chỉ admin gốc mới được tạo admin khác". Hiện tại rủi ro thấp vì mặc định chỉ vai trò `admin` có quyền này, nhưng nếu `account.access` bị gán nhầm cho vai trò khác (qua `/admin/roles`) thì xảy ra leo thang đặc quyền.
- User thường (không có `account.access`) **không thể** truy cập route này — middleware chặn đúng.

**Sửa user**
- Method: `PUT /admin/accounts/{account}` — form dùng `@method('PUT')` đúng chuẩn.
- Validate: `UpdateUserAccountRequest`, unique bỏ qua chính user đang sửa.
- Chặn nghiệp vụ: `isRootAdmin()` (không cho sửa tài khoản `ten_dang_nhap=admin`), chặn tự khóa chính mình (`isCurrentUser` + đổi `trang_thai`/`bi_khoa`).
- `PERMISSION_RISK`: cũng như trên, ai có `account.access` có thể sửa **loai_tai_khoan của bất kỳ user nào khác** (trừ root admin) thành `admin` — không có kiểm tra tầng quyền giữa các admin với nhau.
- Field được phép sửa: toàn bộ field cơ bản + `loai_tai_khoan`, `trang_thai`, `bi_khoa`, `vai_tro[]`, `password` (tùy chọn) — không có field nào bị "khóa cứng" khỏi việc sửa ngoài các điều kiện `isRootAdmin`/`isCurrentUser` nêu trên.

**Xóa user**
- Method: `DELETE /admin/accounts/{account}` — form dùng `@method('DELETE')` + `@csrf` đúng chuẩn.
- Là **xóa mềm tự chế** (`trang_thai = 'da_xoa'`, `bi_khoa = true`), không dùng trait `SoftDeletes`/cột `deleted_at` — dữ liệu vẫn còn nguyên trong bảng `users`, các FK liên quan (`don_hang.nguoi_dung_id`, `dai_ly_api.nguoi_dung_id`...) đều `nullOnDelete` nhưng vì đây không phải xóa thật nên **không bị ảnh hưởng gì** — an toàn cho dữ liệu lịch sử.
- Chặn: không cho xóa root admin, không cho tự xóa chính mình.
- Lưu ý kỹ thuật (`CẦN KIỂM TRA THÊM`): route `PATCH .../unlock` không kiểm tra `trang_thai` hiện tại trước khi set về `hoat_dong` — về lý thuyết có thể gọi trực tiếp URL để "phục hồi" một tài khoản đã ở trạng thái `da_xoa`, dù UI danh sách đã lọc bỏ các tài khoản này khỏi màn hình.

**Đổi mật khẩu**
- Method: `PUT /admin/accounts/{account}/password`.
- Validate: `required|min:8|confirmed` — đầy đủ.
- Hash: `Hash::make()` — đúng.
- Chặn: không cho đổi mật khẩu root admin từ màn này.
- **Thiếu (gap):** không có flow tự-đổi-mật-khẩu cho chính người dùng đang đăng nhập (không có route/màn hình "Đổi mật khẩu của tôi") — chỉ admin đổi hộ người khác qua màn quản lý tài khoản.
- **Thiếu (gap):** cờ `bat_buoc_doi_mat_khau` được set nhưng `AuthController::login()` không đọc/áp dụng cờ này ở bất kỳ đâu — tính năng "bắt buộc đổi mật khẩu ở lần đăng nhập kế tiếp" **chưa hoạt động thật**.
- **Thiếu (gap bảo mật nhẹ):** sau khi admin đổi mật khẩu một user, phiên đăng nhập hiện tại của user đó trên thiết bị khác **không bị vô hiệu hóa** (không gọi `Auth::logoutOtherDevices()` hay xoay `remember_token`).

### 8.6 Mass assignment & lộ dữ liệu
- `User::$fillable` liệt kê tường minh, không có `$guarded = []`. An toàn.
- `UserAccountController::store/update` chỉ đưa từng field cụ thể từ `$validated` vào mảng `create()/update()` (không spread `...$validated`) — chống ghi đè field ngoài ý muốn kể cả khi rule bị thêm nhầm sau này.
- `password`, `remember_token` nằm trong `$hidden` → không lộ ra JSON (`/api/user`) hay view.
- `AuthController::register()` gán `loai_tai_khoan = 'customer'` **cứng trong code**, không lấy từ `$request` → không có đường cho người dùng tự đăng ký tài khoản admin.

### 8.7 `CRITICAL` — Bug điều hướng khi vai trò không có quyền nào

`AuthController::duongDanSauDangNhap()` (dòng 208-236): nếu user đăng nhập đúng mật khẩu nhưng **không có bất kỳ quyền `.access` nào** (ví dụ vai trò `backend`, `ke_toan`, `doi_soat`, `agent`, `agent_api` chưa được cấp quyền qua `/admin/roles`, hoặc tài khoản không được gán `vai_tro` nào), hàm này sẽ:
1. Không tìm thấy quyền nào khớp trong `adminRoutes` lẫn `frontendRoutes`.
2. Gọi `Auth::logout()` ngay trong lúc đang xử lý.
3. Trả về `route('login')`.

→ Người dùng thấy: nhập đúng tài khoản/mật khẩu, có vẻ đăng nhập thành công trong chốc lát, rồi **bị đá ngược về trang đăng nhập mà không có bất kỳ thông báo lỗi nào giải thích lý do**. Đây vừa là lỗi UX vừa là rủi ro vận hành (5/7 vai trò được seed sẵn hiện đang ở trạng thái này).

---

## 9. Audit HTTP Method

### 9.1 Bảng chi tiết theo từng endpoint

| Method | Endpoint | Route Name | Controller@Method | Chức năng | Middleware | Đánh giá | Ghi chú |
|---|---|---|---|---|---|---|---|
| GET | `/` | frontend.home | HomeController@index | Xem trang chủ | auth, quyen | Đúng | — |
| GET | `/nap-tien-dien-thoai` | frontend.topup | HomeController@loaisanpham | Xem trang nạp tiền | auth, quyen | Đúng | — |
| GET | `/login` | login | AuthController@showLoginForm | Xem form | guest | Đúng | — |
| POST | `/login` | login.submit | AuthController@login | Xử lý đăng nhập (đổi state: session) | guest | Đúng | — |
| GET | `/register` | register | AuthController@showRegisterForm | Xem form | guest | Đúng | — |
| POST | `/register` | register.submit | AuthController@register | Tạo user mới | guest | Đúng | — |
| POST | `/logout` | logout | AuthController@logout | Hủy session | auth | Đúng | Nhiều dự án hay sai dùng GET cho logout — dự án này **làm đúng** |
| GET | `/admin` | admin.dashboard | Closure | Xem dashboard | auth, quyen | Đúng | Nên chuyển closure thành Controller thật khi có dữ liệu thật |
| GET | `/admin/accounts` | admin.accounts | UserAccountController@index | Xem danh sách | auth, quyen | Đúng | — |
| GET | `/admin/accounts/export` | admin.accounts.export | UserAccountController@exportExcel | Tải file (không đổi state) | auth, quyen | Đúng | — |
| GET | `/App/Users` | app.users | UserAccountController@index | Trùng chức năng với `admin.accounts` | auth, quyen | `CẦN KIỂM TRA THÊM` | Route trùng — làm rõ có còn dùng ở đâu (link cứng, tài liệu cũ...) không rồi gỡ bỏ |
| POST | `/admin/accounts` | admin.accounts.store | UserAccountController@store | Tạo mới (state mới) | auth, quyen | Đúng | — |
| PUT | `/admin/accounts/{account}` | admin.accounts.update | UserAccountController@update | Cập nhật toàn bộ resource | auth, quyen | Đúng | — |
| PUT | `/admin/accounts/{account}/password` | admin.accounts.password | UserAccountController@updatePassword | Cập nhật 1 field (mật khẩu) | auth, quyen | Chấp nhận được | Về chuẩn REST nghiêm ngặt nên dùng PATCH vì chỉ cập nhật 1 phần resource; PUT ở đây không sai nghiêm trọng vì thực chất thay thế toàn bộ giá trị mật khẩu |
| PATCH | `/admin/accounts/{account}/lock` | admin.accounts.lock | UserAccountController@lock | Cập nhật 1 phần (khóa) | auth, quyen | Đúng | — |
| PATCH | `/admin/accounts/{account}/unlock` | admin.accounts.unlock | UserAccountController@unlock | Cập nhật 1 phần (mở khóa) | auth, quyen | Đúng | — |
| DELETE | `/admin/accounts/{account}` | admin.accounts.destroy | UserAccountController@destroy | Xóa (mềm) | auth, quyen | Đúng | Dùng DELETE cho thao tác thực chất là UPDATE trạng thái — chấp nhận được về mặt ngữ nghĩa REST (xóa khỏi tập hợp "tài khoản đang hoạt động") |
| GET | `/admin/roles` | admin.roles | RoleAccessController@index | Xem danh sách | auth, quyen | Đúng | — |
| GET | `/App/Roles` | app.roles | RoleAccessController@index | Trùng chức năng với `admin.roles` | auth, quyen | `CẦN KIỂM TRA THÊM` | Route trùng, tương tự `/App/Users` |
| PUT | `/admin/roles/{role}/permissions` | admin.roles.permissions | RoleAccessController@updatePermissions | Cập nhật quyền của vai trò | auth, quyen | Đúng | — |
| GET | `/api/user` | — | Closure | Đọc user hiện tại | auth:sanctum | Đúng | — |
| GET | `/api/customers` | — | CustomerController@index | Đọc danh sách khách hàng | **không có** | `HTTP_METHOD_ISSUE` không áp dụng (method GET đúng cho đọc dữ liệu) nhưng `MISSING_MIDDLEWARE` + `CRITICAL` | Cần thêm `auth`/`auth:sanctum` trước khi dùng thật |

### 9.2 Kết luận audit HTTP method

- **Không phát hiện route nào dùng GET để thay đổi dữ liệu.** Toàn bộ thao tác tạo/sửa/khóa/mở khóa/xóa đều dùng đúng POST/PUT/PATCH/DELETE.
- **Form HTML dùng đúng `@method('PUT')`, `@method('PATCH')`, `@method('DELETE')`** ở toàn bộ modal trong `admin/accounts.blade.php` và `admin/roles.blade.php` (đã đối chiếu với route tương ứng).
- **2 route trùng chức năng:** `/App/Users` ≡ `/admin/accounts` (GET, cùng controller/method/middleware), `/App/Roles` ≡ `/admin/roles`. Không rõ mục đích (có thể dự phòng cho tích hợp khác) — `CẦN KIỂM TRA THÊM`.
- **Không có route nào khai báo nhưng hoàn toàn không dùng** trong web.php/api.php (tất cả đều được form/link tham chiếu tới), ngoại trừ 2 route trùng nói trên có thể là dư thừa.
- **1 endpoint bị hở hoàn toàn** không có middleware đăng nhập/phân quyền: `GET /api/customers`.

---

## 10. Audit bảo mật user

| Vấn đề | Mức độ | File liên quan | Mô tả | Đề xuất xử lý |
|---|---|---|---|---|
| API khách hàng không xác thực | `CRITICAL` / `MISSING_MIDDLEWARE` | `routes/api.php`, `CustomerController.php` | `GET /api/customers` không có middleware `auth`/`auth:sanctum`, ai cũng gọi được, trả toàn bộ bảng `customers` không phân trang | Thêm `middleware('auth:sanctum')` (hoặc middleware phù hợp) trước khi đưa vào dùng thật |
| Đăng nhập thành công nhưng bị đăng xuất âm thầm | `CRITICAL` / `PERMISSION_RISK` | `AuthController.php::duongDanSauDangNhap()` | User có vai trò không được cấp quyền `.access` nào bị `Auth::logout()` ngay sau khi đăng nhập, không có thông báo lỗi | Hiển thị lỗi rõ ràng ("Tài khoản chưa được cấp quyền truy cập, vui lòng liên hệ quản trị viên") thay vì logout âm thầm; đồng thời rà soát seed quyền cho 5 vai trò còn thiếu |
| "Admin bypass toàn quyền" chỉ có trong comment | `HIGH` / `PERMISSION_RISK` | `app/Models/User.php::coQuyen()/coMotTrongCacQuyen()` | Comment nói admin gốc được qua tất cả quyền nhưng code không có nhánh xử lý này | Quyết định rõ: bổ sung logic bypass thật cho admin gốc, hoặc xóa comment gây hiểu lầm nếu chủ đích là admin cũng phải được seed đầy đủ |
| Leo thang đặc quyền qua màn quản lý tài khoản | `HIGH` / `PERMISSION_RISK` | `Admin/UserAccountController.php::store()/update()` | Bất kỳ ai có quyền `account.access` đều có thể tạo/sửa tài khoản khác thành `loai_tai_khoan = admin` mà không cần kiểm tra thêm | Thêm kiểm tra: chỉ tài khoản đang có vai trò `admin` (hoặc root admin) mới được gán `loai_tai_khoan=admin`/vai trò `admin` cho người khác |
| Thiếu audit log thao tác nhạy cảm | `MEDIUM` | `Admin/UserAccountController.php` (5 vị trí TODO) | Tạo/sửa/đổi mật khẩu/khóa/mở khóa/xóa tài khoản chưa được ghi log | Bổ sung bảng/ cơ chế audit log trước khi lên production |
| Nhận diện admin gốc hard-code | `MEDIUM` | `Admin/UserAccountController.php::isRootAdmin()` | So sánh cứng `ten_dang_nhap === 'admin'` | Thêm cột `is_root` (boolean) thay vì so sánh chuỗi |
| Middleware `vai_tro` không được dùng | `LOW` | `app/Http/Middleware/KiemTraVaiTro.php` | Không route nào gắn `vai_tro:...` | Xác nhận giữ lại có chủ đích (dự phòng) hay gỡ bỏ |
| Cờ `bat_buoc_doi_mat_khau` chưa có tác dụng | `MEDIUM` | `AuthController.php::login()` | Field được set nhưng không được kiểm tra khi đăng nhập | Thêm điều kiện trong `login()`: nếu cờ bật thì redirect sang màn bắt buộc đổi mật khẩu |
| Thiếu flow tự đổi mật khẩu | `MEDIUM` | Toàn hệ thống (không có route) | Người dùng thường không có cách tự đổi mật khẩu của chính mình | Bổ sung route/màn hình "Đổi mật khẩu của tôi" cho user đã đăng nhập |
| CSRF | Không phát hiện vấn đề | tất cả form trong `resources/views` | Mọi form POST/PUT/PATCH/DELETE đều có `@csrf`, nhóm middleware `web` bật `VerifyCsrfToken` mặc định | — |
| Validate đầu vào | Không phát hiện vấn đề nghiêm trọng | `AuthController`, 3 Form Request | Có validate đầy đủ, dùng Form Request cho phần admin, dùng `$request->validate()` inline cho auth | Cân nhắc tách `AuthController` sang Form Request riêng để đồng bộ chuẩn code |
| Mass assignment | Không phát hiện vấn đề | `User.php`, `UserAccountController.php` | `$fillable` tường minh; controller build mảng field cụ thể từ `$validated`, không spread toàn bộ | — |
| Lộ password qua JSON/view | Không phát hiện vấn đề | `User.php` (`$hidden`) | `password`, `remember_token` bị ẩn khi serialize | — |
| Route model binding & 404 | Không phát hiện vấn đề | `admin.accounts.*`, `admin.roles.*` | Laravel tự trả 404 khi `{account}`/`{role}` không tồn tại | — |

---

## 11. Tiến độ hiện tại của dự án

### Đã hoàn thành
- Đăng nhập / đăng ký / đăng xuất (trừ bug điều hướng ở mục 8.7 khi vai trò không có quyền).
- Quản lý tài khoản hệ thống: tạo, sửa, khóa, mở khóa, xóa mềm, đổi mật khẩu, export Excel.
- Quản lý vai trò & cây quyền truy cập module (gán/gỡ quyền `.access` cho vai trò, bao gồm checkbox "Mặc định" xác định vai trò gán tự động cho tài khoản mới).
- CSRF, hash mật khẩu, chống session fixation, mass-assignment protection cho toàn bộ luồng user.
- **Quản lý dịch vụ** (`/admin/services`, alias `/App/Services`) — CRUD đầy đủ (tạo/sửa/xóa/xem chi tiết), filter theo mã/tên/trạng thái, export Excel, quyền riêng `dich_vu.access` dưới nhóm menu "Quản lý danh mục". `ServiceController`, `StoreDichVuRequest`/`UpdateDichVuRequest`, `ServicesExport`, view `admin/services.blade.php`, JS `admin-services.js`.

### Đang làm dở
- Trang chủ & "Nạp tiền điện thoại" — có giao diện, controller chỉ trả view trơn, chưa nối `dich_vu/san_pham/don_hang`.
- Dashboard "Quản lý lô hàng" — route là closure, dữ liệu bảng hard-code trong Blade.
- Cờ `bat_buoc_doi_mat_khau` — có field, có nơi set, nhưng chưa được đọc/áp dụng ở đăng nhập.
- 5 TODO audit log trong `UserAccountController`.

### Chưa có
- Toàn bộ Controller/Route/View cho: loại sản phẩm, sản phẩm, nhà cung cấp, kết nối nhà cung cấp, cấu hình dịch vụ (`cau_hinh_dich_vu` — khác với module "Dịch vụ" vừa làm), đại lý API, cấu hình API đại lý, đơn hàng, lịch sử trạng thái đơn hàng, log API, lần gọi nhà cung cấp (dù đã có đầy đủ migration + model). Module "Dịch vụ" (`dich_vu`) đã hoàn thành, xem mục "Đã hoàn thành".
- 5/6 method của `CustomerController` (create/store/show/edit/update/destroy).
- Flow tự đổi mật khẩu của chính người dùng.
- Flow xác minh email (dù có cột `email_da_xac_nhan`).
- View `thongtintaikhoan.blade.php` cho method `HomeController::thongtintaikhoan()` (method mồ côi, không route).

### Có rủi ro
- `GET /api/customers` không middleware — `CRITICAL`.
- Bug đăng xuất âm thầm khi vai trò thiếu quyền — `CRITICAL`.
- Leo thang đặc quyền qua form tạo/sửa tài khoản — `HIGH`.
- "Admin bypass" chỉ có trong comment — `HIGH`.
- 2 route trùng (`/App/Users`, `/App/Roles`) — rủi ro thấp, chủ yếu gây nhiễu code.
- 3 file rác Composer ở thư mục gốc (`composer`, `composer-setup.php`, `composer-install.log`) — untracked, nên dọn trước khi commit.

---

## 12. Checklist việc cần làm tiếp theo

- [ ] `CRITICAL`: Thêm middleware xác thực cho `GET /api/customers`.
- [x] `CRITICAL`: Sửa `AuthController::duongDanSauDangNhap()` để không âm thầm đăng xuất — hiển thị lỗi rõ ràng khi user không có quyền nào. *(Đã sửa: hàm trả `?string` (null khi không có quyền), `login()`/`register()`/`showLoginForm()`/`showRegisterForm()` tự xử lý đăng xuất + `withErrors(['ten_dang_nhap' => ...])` thay vì `Auth::logout()` âm thầm ngay trong hàm điều hướng.)*
- [x] `MISSING_DEFAULT_ROLE`: Dropdown "Loại tài khoản" trong modal tạo tài khoản (`admin/accounts.blade.php`) mặc định chọn option đầu tiên (`admin`) do thiếu `@selected()`. *(Đã sửa: thêm `@selected($value === 'customer')` ở Blade + `setField('loai_tai_khoan', 'customer')` trong `prepareCreateForm()` của `admin-accounts.js` để ép mặc định đúng `customer` khi mở modal tạo mới.)*
- [x] `MISSING_DEFAULT_ROLE` (vai trò): `UserAccountController::syncRoles()` lưu mảng `vai_tro[]` rỗng nếu admin không tick vai trò nào khi tạo/sửa tài khoản. *(Đã sửa: triển khai đầy đủ checkbox "Mặc định" trong màn sửa vai trò (`admin/roles.blade.php`) — vai trò nào được tick `mac_dinh=true` sẽ dùng làm vai trò mặc định qua `VaiTro::vaiTroMacDinh()`, áp dụng cho cả `AuthController::register()` và `UserAccountController::syncRoles()`. `RoleAccessController::updatePermissions()` đảm bảo luôn chỉ có đúng 1 vai trò mặc định. **CẢNH BÁO ĐÃ XỬ LÝ:** dữ liệu seed cũ (`VaiTroSeeder.php`) từng đánh dấu `mac_dinh=true` cho vai trò `admin` thay vì `user` — đã sửa seeder + đồng bộ dữ liệu DB thật + backfill 2 tài khoản cũ có vai trò rỗng (`lxmanh3`, `thevy`) về vai trò mặc định `user`.)*
- [ ] `CRITICAL`: Rà soát và seed quyền tối thiểu cho 5 vai trò `backend, ke_toan, doi_soat, agent, agent_api` trước khi cấp tài khoản dùng các vai trò này.
- [ ] `HIGH`: Bổ sung kiểm tra chặn leo thang đặc quyền khi tạo/sửa tài khoản (chỉ admin/root admin được gán `loai_tai_khoan=admin` hoặc vai trò `admin`).
- [ ] `HIGH`: Quyết định và lập trình đúng phần "admin bypass toàn quyền" (hoặc xóa comment gây hiểu lầm).
- [ ] Bổ sung audit log cho 5 thao tác nhạy cảm đã đánh dấu TODO trong `UserAccountController`.
- [ ] Thêm cột `is_root` thay cho so sánh chuỗi `ten_dang_nhap === 'admin'`.
- [ ] Làm rõ mục đích 2 route trùng `/App/Users`, `/App/Roles` — giữ hay gỡ.
- [ ] Bổ sung flow tự đổi mật khẩu cho người dùng đã đăng nhập.
- [ ] Kích hoạt thật cờ `bat_buoc_doi_mat_khau` trong luồng đăng nhập.
- [ ] Xử lý code chết `HomeController::thongtintaikhoan()` (thêm view + route, hoặc gỡ method).
- [ ] Xác nhận middleware `KiemTraVaiTro` còn cần giữ không.
- [ ] Dọn 3 file rác Composer ở thư mục gốc, thêm vào `.gitignore`.
- [ ] Sau khi vá xong nền tảng user/phân quyền: mới bắt đầu triển khai Controller/View cho nhóm nghiệp vụ lõi (dịch vụ/sản phẩm/nhà cung cấp/đơn hàng).

---

## 13. Kết luận

Dự án hiện đang ở giai đoạn **hoàn thiện nền tảng xác thực và quản trị tài khoản/phân quyền**, phần này được viết khá cẩn thận (transaction, validate qua Form Request, chống mass-assignment, bảo vệ admin gốc, CSRF, hash mật khẩu). Tuy nhiên audit Level 2 phát hiện **2 lỗi mức `CRITICAL`** ảnh hưởng trực tiếp đến tính đúng đắn và an toàn của hệ thống phân quyền: (1) API khách hàng bị hở hoàn toàn, và (2) người dùng thuộc 5/7 vai trò mặc định sẽ đăng nhập "thành công" rồi bị âm thầm đăng xuất do chưa được seed quyền — đây không chỉ là thiếu sót mà là lỗi có thể khiến người vận hành hiểu nhầm là "tài khoản/đăng nhập bị hỏng".

**Phần quan trọng nhất cần xử lý tiếp** là vá 2 lỗi `CRITICAL` và điểm `HIGH` về leo thang đặc quyền ở mục 12, vì đây là nền tảng auth/phân quyền mà toàn bộ các module nghiệp vụ sau này (dịch vụ, đơn hàng, đại lý...) sẽ dựa vào. Toàn bộ phần lõi nghiệp vụ nạp tiền hiện mới có schema, chưa có logic — **không nên bắt đầu code tính năng mới trước khi nền tảng user/phân quyền được vá các lỗi trên**, vì mọi Controller mới sẽ tiếp tục dùng lại middleware `quyen:...` và mô hình phân quyền hiện tại; nếu nền tảng còn lỗi, các module mới sẽ kế thừa luôn các rủi ro này.
