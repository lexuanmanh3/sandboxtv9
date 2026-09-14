<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'tv9tech Portal')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('backend/css/admin.css') }}">
  @stack('styles')
</head>
<body class="admin-page">
  <x-icon-sprite />

  <div class="admin-shell">
    <div class="admin-backdrop" data-admin-backdrop></div>

    @php
      $currentUser = auth()->user();
      $canAccessDashboard = $currentUser?->coQuyen('dashboard.view');
      $canAccessAccounts = $currentUser?->coQuyen('account.view');
      $canAccessRoles = $currentUser?->coQuyen('role.view');
      $adminHomeUrl = $canAccessDashboard
          ? route('admin.dashboard')
          : ($canAccessAccounts
              ? route('admin.accounts')
              : ($canAccessRoles ? route('admin.roles') : '#'));
    @endphp

    <aside class="admin-sidebar" data-admin-sidebar>
      <div class="admin-sidebar__brand">
        <a class="admin-brand" href="{{ $adminHomeUrl ?? route('admin.dashboard') }}" aria-label="tv9tech Portal">
          <img class="admin-brand__logo" src="{{ asset('backend/img/logo.png') }}" alt="tv9tech" style="height: 38px; max-width: 180px; object-fit: contain; display: block;">
        </a>
      </div>

      @php
        $currentUser = auth()->user();
        $isAdmin = (bool) $currentUser?->isAdmin();

        $canAccessDashboard = $isAdmin || $currentUser?->coQuyen('dashboard.view');
        
        $canAccessServices = $isAdmin || $currentUser?->coQuyen('dich_vu.view');
        $canAccessCategories = $isAdmin || $currentUser?->coQuyen('loai_san_pham.view');
        $canAccessProducts = $isAdmin || $currentUser?->coQuyen('product.view');
        $canAccessCatalogGroup = $canAccessServices || $canAccessCategories || $canAccessProducts;

        $canAccessProviders = $isAdmin || $currentUser?->coQuyen('service_config.view');
        $canAccessProviderProducts = $isAdmin || $currentUser?->coQuyen('provider_product.view');
        $canAccessProviderErrorCodes = $isAdmin || $currentUser?->coQuyen('provider_error_code.view');
        $canAccessProviderGroup = $canAccessProviders || $canAccessProviderProducts || $canAccessProviderErrorCodes;

        $canAccessB2bPartners = $isAdmin || $currentUser?->coQuyen('b2b_partner.view');
        $canAccessB2bOrders = $isAdmin || $currentUser?->coQuyen('b2b_order.view');
        $canAccessB2bCredit = $isAdmin || $currentUser?->coQuyen('b2b_credit.view');
        $canAccessB2bReconciliation = $isAdmin || $currentUser?->coQuyen('b2b_reconciliation.view');
        $canAccessB2bWebhooks = $isAdmin || $currentUser?->coQuyen('b2b_webhook.view');
        $canAccessB2bGroup = $canAccessB2bPartners || $canAccessB2bOrders || $canAccessB2bCredit || $canAccessB2bReconciliation || $canAccessB2bWebhooks;

        $canAccessOrders = $isAdmin || $currentUser?->coQuyen('order.view');

        $canAccessAccounts = $isAdmin || $currentUser?->coQuyen('account.view');
        $canAccessRoles = $isAdmin || $currentUser?->coQuyen('role.view');
        $canAccessAuditLogs = $isAdmin || $currentUser?->coQuyen('audit_log.view');
        $canAccessMaintenance = $isAdmin || $currentUser?->coQuyen('maintenance.view');
        $canAccessTelegram = $isAdmin || $currentUser?->coQuyen('telegram_setting.view');
        $canAccessAdminGroup = $canAccessAccounts || $canAccessRoles || $canAccessAuditLogs || $canAccessMaintenance || $canAccessTelegram;

        $catalogOpen = request()->routeIs('admin.services', 'app.services', 'admin.categories', 'app.categories', 'admin.products');
        $providerOpen = request()->routeIs('admin.providers', 'admin.provider-products', 'admin.provider-error-codes');
        $b2bOpen = request()->routeIs('admin.b2b.*');
        $adminOpen = request()->routeIs('admin.accounts', 'app.users', 'admin.roles', 'app.roles', 'admin.audit-logs', 'admin.maintenance', 'admin.telegram-settings');
      @endphp

      <nav class="admin-nav" aria-label="Menu quản trị">
        {{-- TỔNG QUAN HỆ THỐNG (DASHBOARD) --}}
        @if ($canAccessDashboard)
          <a class="admin-nav__link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">
            <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Tổng quan hệ thống</span>
          </a>
        @endif

        {{-- QUẢN LÝ DANH MỤC --}}
        @if ($canAccessCatalogGroup)
          <div class="admin-nav__group {{ $catalogOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $catalogOpen ? 'true' : 'false' }}">
              <span><x-icon name="sync_alt" /> Quản lý danh mục</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              @if ($canAccessServices)
                <a class="{{ request()->routeIs('admin.services', 'app.services') ? 'is-active' : '' }}" href="{{ route('admin.services') }}">Dịch vụ</a>
              @endif
              @if ($canAccessCategories)
                <a class="{{ request()->routeIs('admin.categories', 'app.categories') ? 'is-active' : '' }}" href="{{ route('admin.categories') }}">Loại sản phẩm</a>
              @endif
              @if ($canAccessProducts)
                <a class="{{ request()->routeIs('admin.products') ? 'is-active' : '' }}" href="{{ route('admin.products') }}">Sản phẩm</a>
              @endif
            </div>
          </div>
        @endif

        {{-- NHÓM QUẢN LÝ NHÀ CUNG CẤP --}}
        @if ($canAccessProviderGroup)
          <div class="admin-nav__group {{ $providerOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $providerOpen ? 'true' : 'false' }}">
              <span><x-icon name="cell_wifi" /> Nhà cung cấp</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              @if ($canAccessProviders)
                <a class="{{ request()->routeIs('admin.providers') ? 'is-active' : '' }}" href="{{ route('admin.providers') }}">Nhà cung cấp &amp; Kết nối</a>
              @endif
              @if ($canAccessProviderProducts)
                <a class="{{ request()->routeIs('admin.provider-products') ? 'is-active' : '' }}" href="{{ route('admin.provider-products') }}">Sản phẩm Nhà Cung Cấp</a>
              @endif
              @if ($canAccessProviderErrorCodes)
                <a class="{{ request()->routeIs('admin.provider-error-codes') ? 'is-active' : '' }}" href="{{ route('admin.provider-error-codes') }}">Mã lỗi Nhà Cung Cấp</a>
              @endif
            </div>
          </div>
        @endif

        {{-- NHÓM QUẢN LÝ ĐẠI LÝ (B2B PARTNER HUB) --}}
        @if ($canAccessB2bGroup)
          <div class="admin-nav__group {{ $b2bOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $b2bOpen ? 'true' : 'false' }}">
              <span><x-icon name="badge" /> Quản lý đại lý</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              @if ($canAccessB2bPartners)
                <a class="{{ request()->routeIs('admin.b2b.partners.*') ? 'is-active' : '' }}" href="{{ route('admin.b2b.partners.index') }}">Đại lý API</a>
              @endif
              @if ($canAccessB2bOrders)
                <a class="{{ request()->routeIs('admin.b2b.orders.*') ? 'is-active' : '' }}" href="{{ route('admin.b2b.orders.index') }}">Đơn hàng B2B</a>
              @endif
              @if ($canAccessB2bCredit)
                <a class="{{ request()->routeIs('admin.b2b.credit-payments.*') ? 'is-active' : '' }}" href="{{ route('admin.b2b.credit-payments.index') }}">Công nợ &amp; Thanh toán</a>
              @endif
              @if ($canAccessB2bReconciliation)
                <a class="{{ request()->routeIs('admin.b2b.reconciliations.*') ? 'is-active' : '' }}" href="{{ route('admin.b2b.reconciliations.index') }}">Kỳ đối soát</a>
              @endif
              @if ($canAccessB2bWebhooks)
                <a class="{{ request()->routeIs('admin.b2b.webhooks.*') ? 'is-active' : '' }}" href="{{ route('admin.b2b.webhooks.index') }}">Lịch sử Webhook</a>
              @endif
              @if ($canAccessAuditLogs)
                <a class="{{ request()->routeIs('admin.audit-logs') && request()->input('search') === 'b2b' ? 'is-active' : '' }}" href="{{ route('admin.audit-logs', ['search' => 'b2b']) }}">Nhật ký thao tác</a>
              @endif
            </div>
          </div>
        @endif

        {{-- CPT QUẢN LÝ HÓA ĐƠN & GIAO DỊCH --}}
        @if ($canAccessOrders)
          <a class="admin-nav__link {{ request()->routeIs('admin.orders') ? 'is-active' : '' }}" href="{{ route('admin.orders') }}">
            <x-icon name="payments" />
            <span>Quản lý Hóa đơn</span>
          </a>
        @endif

        {{-- NHÓM QUẢN TRỊ --}}
        @if ($canAccessAdminGroup)
          <div class="admin-nav__group {{ $adminOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $adminOpen ? 'true' : 'false' }}">
              <span><x-icon name="account_circle" /> Quản trị</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              @if ($canAccessAccounts)
                <a class="{{ request()->routeIs('admin.accounts', 'app.users') ? 'is-active' : '' }}" href="{{ route('admin.accounts') }}">Quản lý tài khoản hệ thống</a>
              @endif
              @if ($canAccessRoles)
                <a class="{{ request()->routeIs('admin.roles', 'app.roles') ? 'is-active' : '' }}" href="{{ route('admin.roles') }}">Vai trò</a>
              @endif
              @if ($canAccessAuditLogs)
                <a class="{{ request()->routeIs('admin.audit-logs') ? 'is-active' : '' }}" href="{{ route('admin.audit-logs') }}">Nhật ký hoạt động</a>
              @endif
              @if ($canAccessMaintenance)
                <a class="{{ request()->routeIs('admin.maintenance') ? 'is-active' : '' }}" href="{{ route('admin.maintenance') }}">Bảo trì</a>
              @endif
              @if ($canAccessTelegram)
                <a class="{{ request()->routeIs('admin.telegram-settings') ? 'is-active' : '' }}" href="{{ route('admin.telegram-settings') }}">Thông báo Telegram</a>
              @endif
            </div>
          </div>
        @endif
      </nav>

      <div class="admin-sidebar__support">
        <strong>Hỗ trợ kỹ thuật</strong>
        <span>Hotline: 1900 1234</span>
      </div>

      <div class="admin-sidebar__resizer" data-sidebar-resizer role="separator" aria-label="Thay đổi chiều rộng menu" aria-orientation="vertical"></div>
    </aside>

    <div class="admin-workspace">
      <header class="admin-topbar">
        <div class="admin-topbar__left">
          <button class="admin-menu-btn" type="button" data-admin-menu aria-label="Mở menu">
            <x-icon name="menu" />
          </button>
          <a class="admin-topbar__brand" href="{{ $adminHomeUrl }}" aria-label="tv9tech">
            <img src="{{ asset('backend/img/logo.png') }}" alt="tv9tech" style="height: 28px; width: auto; max-width: 140px; object-fit: contain; vertical-align: middle;">
          </a>
          <form class="admin-search" role="search">
            <x-icon name="visibility" />
            <input type="search" placeholder="Tìm kiếm...">
          </form>
        </div>

        <div class="admin-topbar__right">
          <button class="admin-icon-btn" type="button" aria-label="Thông báo">
            <x-icon name="notifications" />
            <span></span>
          </button>
          <button class="admin-icon-btn admin-icon-btn--hide-mobile" type="button" aria-label="Ví">
            <x-icon name="account_balance_wallet" />
          </button>

          <div class="admin-user-menu" data-admin-user-menu>
            <button class="admin-user" type="button" data-admin-user-trigger aria-expanded="false" aria-controls="adminUserDropdown">
              <div class="admin-user__text">
                <strong>{{ auth()->user()->ten_hien_thi ?? auth()->user()->name ?? 'Admin' }}</strong>
                <span>{{ optional(auth()->user()->vaiTro()->first())->ten_vai_tro ?? 'Người dùng' }}</span>
              </div>
              <div class="admin-user__avatar">
                {{ mb_substr(auth()->user()->ten_hien_thi ?? auth()->user()->name ?? 'A', 0, 1) }}
              </div>
            </button>

            <div class="admin-user-dropdown" id="adminUserDropdown" data-admin-user-dropdown hidden>
              <div class="admin-user-dropdown__header">
                <div class="admin-user-dropdown__avatar">
                  <x-icon name="account_circle" />
                </div>
                <div>
                  <strong>{{ auth()->user()->ten_hien_thi ?? auth()->user()->name ?? 'Admin' }}</strong>
                  <span>{{ auth()->user()->ten_dang_nhap }}</span>
                </div>
              </div>

              <div class="admin-user-dropdown__divider"></div>

              <a class="admin-user-dropdown__item" href="{{ route('frontend.home') }}">
                <x-icon name="home" />
                <span>Trang người dùng</span>
              </a>
              <a class="admin-user-dropdown__item" href="#">
                <x-icon name="person" />
                <span>Thông tin tài khoản</span>
              </a>
              <a class="admin-user-dropdown__item" href="#">
                <x-icon name="lock_reset" />
                <span>Đổi mật khẩu</span>
              </a>

              <div class="admin-user-dropdown__divider"></div>

              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="admin-user-dropdown__logout" type="submit">
                  <x-icon name="logout" />
                  <span>Đăng xuất</span>
                </button>
              </form>
            </div>
          </div>
        </div>
      </header>

      <main class="admin-content">
        @yield('content')
      </main>
    </div>
  </div>

  <script src="{{ asset('backend/js/admin.js') }}?v={{ filemtime(public_path('backend/js/admin.js')) }}"></script>
  @stack('scripts')
</body>
</html>
