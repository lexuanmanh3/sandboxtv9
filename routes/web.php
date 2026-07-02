<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\RoleAccessController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\Admin\ServiceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ===== TRANG CHỦ (yêu cầu đăng nhập) =====
Route::get('/', [HomeController::class, 'index'])
    ->name('frontend.home')
    ->middleware(['auth', 'quyen:frontend.home.access']);

Route::get('/nap-tien-dien-thoai', [HomeController::class, 'loaisanpham'])
    ->name('frontend.topup')
    ->middleware(['auth', 'quyen:frontend.topup.access']);

// ===== XÁC THỰC — Chỉ truy cập khi CHƯA đăng nhập =====
Route::middleware('guest')->group(function () {
    // Đăng nhập
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

    // Đăng ký
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

// Đăng xuất — chỉ khi đang đăng nhập
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ===== QUẢN TRỊ =====
Route::get('/admin', function () {
    return view('admin.dashboard');
})->name('admin.dashboard')->middleware(['auth', 'quyen:dashboard.access']);

// NOTE: Module quan ly tai khoan he thong dung quyen account.access.
// Day la quyen truy cap man hinh/module, khong phai quyen sua/xoa tung field.
Route::middleware(['auth', 'quyen:account.access'])->group(function () {
    Route::get('/admin/accounts', [UserAccountController::class, 'index'])->name('admin.accounts');
    Route::get('/admin/accounts/export', [UserAccountController::class, 'exportExcel'])->name('admin.accounts.export');
    Route::get('/App/Users', [UserAccountController::class, 'index'])->name('app.users');
    Route::post('/admin/accounts', [UserAccountController::class, 'store'])->name('admin.accounts.store');
    Route::put('/admin/accounts/{account}', [UserAccountController::class, 'update'])->name('admin.accounts.update');
    Route::put('/admin/accounts/{account}/password', [UserAccountController::class, 'updatePassword'])->name('admin.accounts.password');
    Route::patch('/admin/accounts/{account}/lock', [UserAccountController::class, 'lock'])->name('admin.accounts.lock');
    Route::patch('/admin/accounts/{account}/unlock', [UserAccountController::class, 'unlock'])->name('admin.accounts.unlock');
    Route::delete('/admin/accounts/{account}', [UserAccountController::class, 'destroy'])->name('admin.accounts.destroy');
});

// NOTE: Module vai tro dung quyen role.access de quan ly viec role nao duoc vao module/trang nao.
Route::middleware(['auth', 'quyen:role.access'])->group(function () {
    Route::get('/admin/roles', [RoleAccessController::class, 'index'])->name('admin.roles');
    Route::get('/App/Roles', [RoleAccessController::class, 'index'])->name('app.roles');
    Route::put('/admin/roles/{role}/permissions', [RoleAccessController::class, 'updatePermissions'])->name('admin.roles.permissions');
});

// NOTE: Module Dich vu dung quyen dich_vu.access - quan ly danh muc dich vu lon
// (TOPUP, PIN_CODE, PAY_BILL...), khac voi service_config.access (cau hinh NCC xu ly).
Route::middleware(['auth', 'quyen:dich_vu.access'])->group(function () {
    Route::get('/admin/services', [ServiceController::class, 'index'])->name('admin.services');
    Route::get('/admin/services/export', [ServiceController::class, 'exportExcel'])->name('admin.services.export');
    Route::get('/App/Services', [ServiceController::class, 'index'])->name('app.services');
    Route::post('/admin/services', [ServiceController::class, 'store'])->name('admin.services.store');
    Route::put('/admin/services/{service}', [ServiceController::class, 'update'])->name('admin.services.update');
    Route::delete('/admin/services/{service}', [ServiceController::class, 'destroy'])->name('admin.services.destroy');
});


