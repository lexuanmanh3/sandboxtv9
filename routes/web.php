<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\TopupApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\RoleAccessController;
use App\Http\Controllers\Admin\UserAccountController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProviderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProviderProductController;
use App\Http\Controllers\Admin\ProviderErrorCodeController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MaintenanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ===== TRANG CHỦ (yêu cầu đăng nhập) =====
Route::get('/', [HomeController::class, 'index'])
    ->name('frontend.home')
    ->middleware(['auth', 'active', 'quyen:frontend.home.access']);

Route::get('/nap-tien-dien-thoai', [HomeController::class, 'loaisanpham'])
    ->name('frontend.topup')
    ->middleware(['auth', 'active', 'quyen:frontend.topup.access']);

Route::get('/nap-tien-dien-thoai/lich-su', [HomeController::class, 'lichSuGiaoDich'])
    ->name('frontend.topup.history')
    ->middleware(['auth', 'active', 'quyen:frontend.topup.access']);

// ===== TOPUP AJAX API (Dùng chung session với Web) =====
Route::prefix('api/topup')->name('api.topup.')->group(function () {
    Route::get('/carriers', [TopupApiController::class, 'carriers'])->name('carriers');
    Route::get('/denominations', [TopupApiController::class, 'denominations'])->name('denominations');

    Route::middleware(['auth', 'active'])->group(function () {
        Route::post('/preview', [TopupApiController::class, 'preview'])->name('preview');
        Route::post('/orders', [TopupApiController::class, 'createOrder'])->name('orders.create')->middleware('throttle:5,1');
        Route::get('/orders/{id}', [TopupApiController::class, 'getOrder'])->name('orders.get');
        Route::get('/orders', [TopupApiController::class, 'orderHistory'])->name('orders.history');
    });
});

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
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware(['auth', 'active']);

// ===== QUẢN TRỊ =====
Route::get('/admin', function () {
    return view('admin.dashboard');
})->name('admin.dashboard')->middleware(['auth', 'active', 'quyen:dashboard.view']);

// NOTE: Module quan ly tai khoan he thong dung quyen account.access.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/accounts', [UserAccountController::class, 'index'])->name('admin.accounts')->middleware('quyen:account.view');
    Route::get('/admin/accounts/export', [UserAccountController::class, 'exportExcel'])->name('admin.accounts.export')->middleware('quyen:account.export');
    Route::get('/App/Users', [UserAccountController::class, 'index'])->name('app.users')->middleware('quyen:account.view');
    Route::post('/admin/accounts', [UserAccountController::class, 'store'])->name('admin.accounts.store')->middleware('quyen:account.create');
    Route::put('/admin/accounts/{account}', [UserAccountController::class, 'update'])->name('admin.accounts.update')->middleware('quyen:account.update');
    Route::put('/admin/accounts/{account}/password', [UserAccountController::class, 'updatePassword'])->name('admin.accounts.password')->middleware('quyen:account.update');
    Route::patch('/admin/accounts/{account}/lock', [UserAccountController::class, 'lock'])->name('admin.accounts.lock')->middleware('quyen:account.lock');
    Route::patch('/admin/accounts/{account}/unlock', [UserAccountController::class, 'unlock'])->name('admin.accounts.unlock')->middleware('quyen:account.lock');
    Route::delete('/admin/accounts/{account}', [UserAccountController::class, 'destroy'])->name('admin.accounts.destroy')->middleware('quyen:account.delete');
    Route::post('/admin/accounts/bulk-delete', [UserAccountController::class, 'bulkDestroy'])->name('admin.accounts.bulk-delete')->middleware('quyen:account.delete');
});

// NOTE: Module vai tro dung quyen role.access de quan ly viec role nao duoc vao module/trang nao.
Route::middleware(['auth', 'active', 'quyen:role.view'])->group(function () {
    Route::get('/admin/roles', [RoleAccessController::class, 'index'])->name('admin.roles');
    Route::get('/App/Roles', [RoleAccessController::class, 'index'])->name('app.roles');
    Route::put('/admin/roles/{role}/permissions', [RoleAccessController::class, 'updatePermissions'])->name('admin.roles.permissions')->middleware('quyen:role.assign_permission');
});

// NOTE: Module Dich vu dung quyen dich_vu.access - quan ly danh muc dich vu lon
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/services', [ServiceController::class, 'index'])->name('admin.services')->middleware('quyen:dich_vu.view');
    Route::get('/admin/services/export', [ServiceController::class, 'exportExcel'])->name('admin.services.export')->middleware('quyen:dich_vu.export');
    Route::get('/App/Services', [ServiceController::class, 'index'])->name('app.services')->middleware('quyen:dich_vu.view');
    Route::post('/admin/services', [ServiceController::class, 'store'])->name('admin.services.store')->middleware('quyen:dich_vu.create');
    Route::put('/admin/services/{service}', [ServiceController::class, 'update'])->name('admin.services.update')->middleware('quyen:dich_vu.update');
    Route::delete('/admin/services/{service}', [ServiceController::class, 'destroy'])->name('admin.services.destroy')->middleware('quyen:dich_vu.delete');
    Route::post('/admin/services/bulk-delete', [ServiceController::class, 'bulkDestroy'])->name('admin.services.bulk-delete')->middleware('quyen:dich_vu.delete');
});

// NOTE: Module Loai san pham dung quyen loai_san_pham.access
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/categories', [CategoryController::class, 'index'])->name('admin.categories')->middleware('quyen:loai_san_pham.view');
    Route::get('/admin/categories/export', [CategoryController::class, 'exportExcel'])->name('admin.categories.export')->middleware('quyen:loai_san_pham.export');
    Route::get('/App/Categories', [CategoryController::class, 'index'])->name('app.categories')->middleware('quyen:loai_san_pham.view');
    Route::post('/admin/categories', [CategoryController::class, 'store'])->name('admin.categories.store')->middleware('quyen:loai_san_pham.create');
    Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update')->middleware('quyen:loai_san_pham.update');
    Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy')->middleware('quyen:loai_san_pham.delete');
    Route::post('/admin/categories/bulk-delete', [CategoryController::class, 'bulkDestroy'])->name('admin.categories.bulk-delete')->middleware('quyen:loai_san_pham.delete');
});

// NOTE: Module Nha cung cap & Ket noi API dung quyen service_config.access
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/providers', [ProviderController::class, 'index'])->name('admin.providers')->middleware('quyen:service_config.access');
    Route::post('/admin/providers', [ProviderController::class, 'store'])->name('admin.providers.store')->middleware('quyen:service_config.access');
    Route::put('/admin/providers/{provider}', [ProviderController::class, 'update'])->name('admin.providers.update')->middleware('quyen:service_config.access');
    Route::post('/admin/providers/{provider}/test-connection', [ProviderController::class, 'testConnection'])->name('admin.providers.test-connection')->middleware('quyen:service_config.access');
    Route::patch('/admin/providers/{provider}/toggle-status', [ProviderController::class, 'toggleStatus'])->name('admin.providers.toggle-status')->middleware('quyen:service_config.access');
    Route::post('/admin/providers/{provider}/reset-circuit', [ProviderController::class, 'resetCircuitBreaker'])->name('admin.providers.reset-circuit')->middleware('quyen:service_config.access');
    Route::delete('/admin/providers/{provider}', [ProviderController::class, 'destroy'])->name('admin.providers.destroy')->middleware('quyen:service_config.access');
    Route::post('/admin/providers/bulk-delete', [ProviderController::class, 'bulkDestroy'])->name('admin.providers.bulk-delete')->middleware('quyen:service_config.access');
});

// NOTE: Module San pham
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/products', [ProductController::class, 'index'])->name('admin.products');
    Route::post('/admin/products', [ProductController::class, 'store'])->name('admin.products.store');
    Route::put('/admin/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::patch('/admin/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('admin.products.toggle-status');
    Route::delete('/admin/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');
    Route::post('/admin/products/bulk-delete', [ProductController::class, 'bulkDestroy'])->name('admin.products.bulk-delete');
    Route::post('/admin/products/{product}/mappings', [ProductController::class, 'saveProviderMapping'])->name('admin.products.mappings.save');
    Route::delete('/admin/products/mappings/{mapping}', [ProductController::class, 'deleteProviderMapping'])->name('admin.products.mappings.destroy');
    Route::post('/admin/products/sync-from-provider', [ProductController::class, 'syncFromProvider'])->name('admin.products.sync-from-provider');
});

// NOTE: Module San pham Nha Cung Cap (CPT riêng)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/provider-products', [ProviderProductController::class, 'index'])->name('admin.provider-products');
    Route::post('/admin/provider-products', [ProviderProductController::class, 'store'])->name('admin.provider-products.store');
    Route::put('/admin/provider-products/{providerProduct}', [ProviderProductController::class, 'update'])->name('admin.provider-products.update');
    Route::post('/admin/provider-products/{providerProduct}/map', [ProviderProductController::class, 'mapProduct'])->name('admin.provider-products.map');
    Route::patch('/admin/provider-products/{providerProduct}/toggle-status', [ProviderProductController::class, 'toggleStatus'])->name('admin.provider-products.toggle-status');
    Route::delete('/admin/provider-products/{providerProduct}', [ProviderProductController::class, 'destroy'])->name('admin.provider-products.destroy');
    Route::post('/admin/provider-products/bulk-delete', [ProviderProductController::class, 'bulkDestroy'])->name('admin.provider-products.bulk-delete');
    Route::post('/admin/provider-products/sync', [ProviderProductController::class, 'syncFromProvider'])->name('admin.provider-products.sync');
    Route::post('/admin/provider-products/auto-map', [ProviderProductController::class, 'autoMap'])->name('admin.provider-products.auto-map');
});

// NOTE: Module Ma loi Nha Cung Cap (Provider Error Codes Management)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/provider-error-codes', [ProviderErrorCodeController::class, 'index'])->name('admin.provider-error-codes');
    Route::post('/admin/provider-error-codes', [ProviderErrorCodeController::class, 'store'])->name('admin.provider-error-codes.store');
    Route::put('/admin/provider-error-codes/{errorCode}', [ProviderErrorCodeController::class, 'update'])->name('admin.provider-error-codes.update');
    Route::patch('/admin/provider-error-codes/{errorCode}/toggle-status', [ProviderErrorCodeController::class, 'toggleStatus'])->name('admin.provider-error-codes.toggle-status');
    Route::delete('/admin/provider-error-codes/{errorCode}', [ProviderErrorCodeController::class, 'destroy'])->name('admin.provider-error-codes.destroy');
    Route::post('/admin/provider-error-codes/bulk-delete', [ProviderErrorCodeController::class, 'bulkDestroy'])->name('admin.provider-error-codes.bulk-delete');
    Route::post('/admin/provider-error-codes/seed-defaults', [ProviderErrorCodeController::class, 'seedDefaults'])->name('admin.provider-error-codes.seed-defaults');
});

// NOTE: Module Nhat ky hoat dong (Audit Logs)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs');
    Route::get('/admin/audit-logs/export', [AuditLogController::class, 'export'])->name('admin.audit-logs.export');
    Route::get('/admin/audit-logs/{log}', [AuditLogController::class, 'detail'])->name('admin.audit-logs.detail');
});

// NOTE: Module Bao tri (Maintenance - Cache & Logs)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/maintenance', [MaintenanceController::class, 'index'])->name('admin.maintenance');
    Route::post('/admin/maintenance/cache/clear-all', [MaintenanceController::class, 'clearAllCache'])->name('admin.maintenance.cache.clear-all');
    Route::post('/admin/maintenance/cache/clear/{type}', [MaintenanceController::class, 'clearSpecificCache'])->name('admin.maintenance.cache.clear-type');
    Route::get('/admin/maintenance/logs/ajax', [MaintenanceController::class, 'getLogs'])->name('admin.maintenance.logs.ajax');
    Route::get('/admin/maintenance/logs/download', [MaintenanceController::class, 'downloadLogs'])->name('admin.maintenance.logs.download');
});

// NOTE: Module Quản lý Hóa đơn & Giao dịch (CPT Hóa đơn)
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/orders', [OrderController::class, 'index'])->name('admin.orders');
    Route::get('/admin/orders/export', [OrderController::class, 'exportExcel'])->name('admin.orders.export');
    Route::get('/admin/orders/{id}', [OrderController::class, 'show'])->name('admin.orders.show');
    Route::post('/admin/orders/{id}/refund', [OrderController::class, 'refund'])->name('admin.orders.refund');
});

