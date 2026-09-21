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
use App\Http\Controllers\Admin\TelegramSettingController;
use App\Http\Controllers\Admin\DashboardController;

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
    // Đăng nhập — rate limit 5 lần/phút/IP để chống brute-force
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:5,1');

    // Đăng ký — rate limit 3 lần/phút/IP để chống spam tạo tài khoản
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit')->middleware('throttle:3,1');
});

// Đăng xuất — chỉ khi đang đăng nhập
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware(['auth', 'active']);

// ===== QUẢN TRỊ =====
Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard')->middleware(['auth', 'active', 'quyen:dashboard.view']);

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

// NOTE: Module Nha cung cap & Ket noi API dung quyen service_config.*
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/providers', [ProviderController::class, 'index'])->name('admin.providers')->middleware('quyen:service_config.view');
    Route::post('/admin/providers', [ProviderController::class, 'store'])->name('admin.providers.store')->middleware('quyen:service_config.create');
    Route::put('/admin/providers/{provider}', [ProviderController::class, 'update'])->name('admin.providers.update')->middleware('quyen:service_config.update');
    Route::post('/admin/providers/{provider}/test-connection', [ProviderController::class, 'testConnection'])->name('admin.providers.test-connection')->middleware('quyen:service_config.test');
    Route::patch('/admin/providers/{provider}/toggle-status', [ProviderController::class, 'toggleStatus'])->name('admin.providers.toggle-status')->middleware('quyen:service_config.update');
    Route::match(['POST', 'PATCH'], '/admin/providers/{provider}/reset-circuit', [ProviderController::class, 'resetCircuitBreaker'])->name('admin.providers.reset-circuit')->middleware('quyen:service_config.reset_circuit');
    Route::delete('/admin/providers/{provider}', [ProviderController::class, 'destroy'])->name('admin.providers.destroy')->middleware('quyen:service_config.delete');
    Route::post('/admin/providers/bulk-delete', [ProviderController::class, 'bulkDestroy'])->name('admin.providers.bulk-delete')->middleware('quyen:service_config.delete');
});

// NOTE: Module San pham — phân quyền chi tiết theo hành động
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/products', [ProductController::class, 'index'])->name('admin.products')->middleware('quyen:product.view');
    Route::post('/admin/products', [ProductController::class, 'store'])->name('admin.products.store')->middleware('quyen:product.create');
    Route::put('/admin/products/{product}', [ProductController::class, 'update'])->name('admin.products.update')->middleware('quyen:product.update');
    Route::patch('/admin/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('admin.products.toggle-status')->middleware('quyen:product.update');
    Route::delete('/admin/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy')->middleware('quyen:product.delete');
    Route::post('/admin/products/bulk-delete', [ProductController::class, 'bulkDestroy'])->name('admin.products.bulk-delete')->middleware('quyen:product.delete');
    Route::post('/admin/products/{product}/mappings', [ProductController::class, 'saveProviderMapping'])->name('admin.products.mappings.save')->middleware('quyen:product.map');
    Route::delete('/admin/products/mappings/{mapping}', [ProductController::class, 'deleteProviderMapping'])->name('admin.products.mappings.destroy')->middleware('quyen:product.map');
    Route::post('/admin/products/sync-from-provider', [ProductController::class, 'syncFromProvider'])->name('admin.products.sync-from-provider')->middleware('quyen:product.sync');
});

// NOTE: Module San pham Nha Cung Cap — phân quyền chi tiết
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/provider-products', [ProviderProductController::class, 'index'])->name('admin.provider-products')->middleware('quyen:provider_product.view');
    Route::post('/admin/provider-products', [ProviderProductController::class, 'store'])->name('admin.provider-products.store')->middleware('quyen:provider_product.create');
    Route::put('/admin/provider-products/{providerProduct}', [ProviderProductController::class, 'update'])->name('admin.provider-products.update')->middleware('quyen:provider_product.update');
    Route::post('/admin/provider-products/{providerProduct}/map', [ProviderProductController::class, 'mapProduct'])->name('admin.provider-products.map')->middleware('quyen:provider_product.map');
    Route::patch('/admin/provider-products/{providerProduct}/toggle-status', [ProviderProductController::class, 'toggleStatus'])->name('admin.provider-products.toggle-status')->middleware('quyen:provider_product.update');
    Route::delete('/admin/provider-products/{providerProduct}', [ProviderProductController::class, 'destroy'])->name('admin.provider-products.destroy')->middleware('quyen:provider_product.delete');
    Route::post('/admin/provider-products/bulk-delete', [ProviderProductController::class, 'bulkDestroy'])->name('admin.provider-products.bulk-delete')->middleware('quyen:provider_product.delete');
    Route::post('/admin/provider-products/sync', [ProviderProductController::class, 'syncFromProvider'])->name('admin.provider-products.sync')->middleware('quyen:provider_product.sync');
    Route::post('/admin/provider-products/auto-map', [ProviderProductController::class, 'autoMap'])->name('admin.provider-products.auto-map')->middleware('quyen:provider_product.map');
});

// NOTE: Module Ma loi Nha Cung Cap — phân quyền chi tiết
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/provider-error-codes', [ProviderErrorCodeController::class, 'index'])->name('admin.provider-error-codes')->middleware('quyen:provider_error_code.view');
    Route::post('/admin/provider-error-codes', [ProviderErrorCodeController::class, 'store'])->name('admin.provider-error-codes.store')->middleware('quyen:provider_error_code.create');
    Route::put('/admin/provider-error-codes/{errorCode}', [ProviderErrorCodeController::class, 'update'])->name('admin.provider-error-codes.update')->middleware('quyen:provider_error_code.update');
    Route::patch('/admin/provider-error-codes/{errorCode}/toggle-status', [ProviderErrorCodeController::class, 'toggleStatus'])->name('admin.provider-error-codes.toggle-status')->middleware('quyen:provider_error_code.update');
    Route::delete('/admin/provider-error-codes/{errorCode}', [ProviderErrorCodeController::class, 'destroy'])->name('admin.provider-error-codes.destroy')->middleware('quyen:provider_error_code.delete');
    Route::post('/admin/provider-error-codes/bulk-delete', [ProviderErrorCodeController::class, 'bulkDestroy'])->name('admin.provider-error-codes.bulk-delete')->middleware('quyen:provider_error_code.delete');
    Route::post('/admin/provider-error-codes/seed-defaults', [ProviderErrorCodeController::class, 'seedDefaults'])->name('admin.provider-error-codes.seed-defaults')->middleware('quyen:provider_error_code.create');
});

// NOTE: Module Nhat ky hoat dong — phân quyền chi tiết
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs')->middleware('quyen:audit_log.view');
    Route::get('/admin/audit-logs/export', [AuditLogController::class, 'export'])->name('admin.audit-logs.export')->middleware('quyen:audit_log.export');
    Route::get('/admin/audit-logs/{log}', [AuditLogController::class, 'detail'])->name('admin.audit-logs.detail')->middleware('quyen:audit_log.view');
});

// NOTE: Module Bao tri — phân quyền chi tiết
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/maintenance', [MaintenanceController::class, 'index'])->name('admin.maintenance')->middleware('quyen:maintenance.view');
    Route::post('/admin/maintenance/cache/clear-all', [MaintenanceController::class, 'clearAllCache'])->name('admin.maintenance.cache.clear-all')->middleware('quyen:maintenance.clear_cache');
    Route::post('/admin/maintenance/cache/clear/{type}', [MaintenanceController::class, 'clearSpecificCache'])->name('admin.maintenance.cache.clear-type')->middleware('quyen:maintenance.clear_cache');
    Route::get('/admin/maintenance/logs/ajax', [MaintenanceController::class, 'getLogs'])->name('admin.maintenance.logs.ajax')->middleware('quyen:maintenance.view');
    Route::get('/admin/maintenance/logs/download', [MaintenanceController::class, 'downloadLogs'])->name('admin.maintenance.logs.download')->middleware('quyen:maintenance.download_logs');
});

// NOTE: Module Quản lý Hóa đơn — phân quyền chi tiết
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/orders', [OrderController::class, 'index'])->name('admin.orders')->middleware('quyen:order.view');
    Route::get('/admin/orders/export', [OrderController::class, 'exportExcel'])->name('admin.orders.export')->middleware('quyen:order.export');
    Route::get('/admin/orders/{id}', [OrderController::class, 'show'])->name('admin.orders.show')->middleware('quyen:order.view');
    Route::post('/admin/orders/{id}/refund', [OrderController::class, 'refund'])->name('admin.orders.refund')->middleware('quyen:order.refund');
});

// NOTE: Module Cấu hình Thông báo Telegram — phân quyền chi tiết
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/admin/telegram-settings', [TelegramSettingController::class, 'index'])->name('admin.telegram-settings')->middleware('quyen:telegram_setting.view');
    Route::put('/admin/telegram-settings', [TelegramSettingController::class, 'update'])->name('admin.telegram-settings.update')->middleware('quyen:telegram_setting.update');
    Route::post('/admin/telegram-settings/test', [TelegramSettingController::class, 'testConnection'])->name('admin.telegram-settings.test')->middleware('quyen:telegram_setting.test');
});

// ===== MODULE B2B PARTNER HUB =====
Route::middleware(['auth', 'active'])->prefix('admin/b2b')->name('admin.b2b.')->group(function () {
    // Đại lý API
    Route::get('/partners', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'index'])->name('partners.index')->middleware('quyen:b2b_partner.view');
    Route::post('/partners', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'store'])->name('partners.store')->middleware('quyen:b2b_partner.create');
    Route::put('/partners/{id}', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'update'])->name('partners.update')->middleware('quyen:b2b_partner.update');
    Route::post('/partners/{id}/rotate-key', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'rotateKey'])->name('partners.rotate-key')->middleware('quyen:b2b_partner.rotate_key');
    Route::post('/partners/{id}/revoke-key', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'revokeKey'])->name('partners.revoke-key')->middleware('quyen:b2b_partner.rotate_key');
    Route::post('/partners/{id}/pricing', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'savePricing'])->name('partners.pricing')->middleware('quyen:b2b_partner.pricing');

    // IP rejection tracking — tra cứu IP bị IP allowlist từ chối
    Route::get('/partners/{id}/ip-rejections', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'ipRejections'])->name('partners.ip-rejections')->middleware('quyen:b2b_partner.view');
    Route::post('/partners/{id}/ip-rejections/{rejectionId}/add-to-whitelist', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'addIpToWhitelist'])->name('partners.ip-rejections.add-to-whitelist')->middleware('quyen:b2b_partner.update');
    Route::post('/partners/{id}/ip-rejections/{rejectionId}/mark-resolved', [\App\Http\Controllers\Admin\B2B\PartnerManagementController::class, 'markResolved'])->name('partners.ip-rejections.mark-resolved')->middleware('quyen:b2b_partner.update');

    // Cấu hình tuyến dịch vụ
    Route::get('/routing-configs', [\App\Http\Controllers\Admin\B2B\RoutingConfigController::class, 'index'])->name('routing-configs.index')->middleware('quyen:b2b_routing.view');
    Route::get('/routing-configs/create', [\App\Http\Controllers\Admin\B2B\RoutingConfigController::class, 'create'])->name('routing-configs.create')->middleware('quyen:b2b_routing.create');
    Route::post('/routing-configs', [\App\Http\Controllers\Admin\B2B\RoutingConfigController::class, 'store'])->name('routing-configs.store')->middleware('quyen:b2b_routing.create');
    Route::get('/routing-configs/{routing_config}/edit', [\App\Http\Controllers\Admin\B2B\RoutingConfigController::class, 'edit'])->name('routing-configs.edit')->middleware('quyen:b2b_routing.update');
    Route::put('/routing-configs/{routing_config}', [\App\Http\Controllers\Admin\B2B\RoutingConfigController::class, 'update'])->name('routing-configs.update')->middleware('quyen:b2b_routing.update');
    Route::delete('/routing-configs/{routing_config}', [\App\Http\Controllers\Admin\B2B\RoutingConfigController::class, 'destroy'])->name('routing-configs.destroy')->middleware('quyen:b2b_routing.delete');


    // Đơn hàng B2B
    Route::get('/orders', [\App\Http\Controllers\Admin\B2B\B2bOrderManagementController::class, 'index'])->name('orders.index')->middleware('quyen:b2b_order.view');
    Route::get('/orders/{id}', [\App\Http\Controllers\Admin\B2B\B2bOrderManagementController::class, 'show'])->name('orders.show')->middleware('quyen:b2b_order.view');

    // Công nợ & Thanh toán
    Route::get('/credit-payments', [\App\Http\Controllers\Admin\B2B\CreditPaymentController::class, 'index'])->name('credit-payments.index')->middleware('quyen:b2b_credit.view');
    Route::post('/credit-payments/payment', [\App\Http\Controllers\Admin\B2B\CreditPaymentController::class, 'storePayment'])->name('credit-payments.payment')->middleware('quyen:b2b_credit.payment');
    Route::post('/credit-payments/adjustment', [\App\Http\Controllers\Admin\B2B\CreditPaymentController::class, 'storeAdjustment'])->name('credit-payments.adjustment')->middleware('quyen:b2b_credit.adjustment');

    // Vận hành & Xử lý sự cố
    Route::get('/operations/provider-calls', [\App\Http\Controllers\Admin\B2B\SystemOperationController::class, 'providerCalls'])->name('operations.provider-calls')->middleware('quyen:b2b_operation.view');
    Route::post('/operations/provider-calls/{id}/recheck', [\App\Http\Controllers\Admin\B2B\SystemOperationController::class, 'recheckProviderCall'])->name('operations.provider-calls.recheck')->middleware('quyen:b2b_operation.view');
    Route::get('/operations/credit-holds', [\App\Http\Controllers\Admin\B2B\SystemOperationController::class, 'creditHolds'])->name('operations.credit-holds')->middleware('quyen:b2b_operation.view');
    Route::post('/operations/credit-holds/{id}/release', [\App\Http\Controllers\Admin\B2B\SystemOperationController::class, 'releaseCreditHold'])->name('operations.credit-holds.release')->middleware('quyen:b2b_operation.release_hold');

    // Kỳ đối soát
    Route::get('/reconciliations', [\App\Http\Controllers\Admin\B2B\ReconciliationController::class, 'index'])->name('reconciliations.index')->middleware('quyen:b2b_reconciliation.view');
    Route::post('/reconciliations', [\App\Http\Controllers\Admin\B2B\ReconciliationController::class, 'store'])->name('reconciliations.store')->middleware('quyen:b2b_reconciliation.create');
    Route::get('/reconciliations/{id}', [\App\Http\Controllers\Admin\B2B\ReconciliationController::class, 'show'])->name('reconciliations.show')->middleware('quyen:b2b_reconciliation.view');
    Route::post('/reconciliations/{id}/lock', [\App\Http\Controllers\Admin\B2B\ReconciliationController::class, 'lock'])->name('reconciliations.lock')->middleware('quyen:b2b_reconciliation.lock');
    Route::post('/reconciliations/{id}/recalculate', [\App\Http\Controllers\Admin\B2B\ReconciliationController::class, 'recalculate'])->name('reconciliations.recalculate')->middleware('quyen:b2b_reconciliation.recalculate');
    Route::get('/reconciliations/{id}/export', [\App\Http\Controllers\Admin\B2B\ReconciliationController::class, 'exportExcel'])->name('reconciliations.export')->middleware('quyen:b2b_reconciliation.export');

    // Lịch sử webhook
    Route::get('/webhooks', [\App\Http\Controllers\Admin\B2B\WebhookManagementController::class, 'index'])->name('webhooks.index')->middleware('quyen:b2b_webhook.view');
    Route::post('/webhooks/{id}/retry', [\App\Http\Controllers\Admin\B2B\WebhookManagementController::class, 'retry'])->name('webhooks.retry')->middleware('quyen:b2b_webhook.retry');
});


