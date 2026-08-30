<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'VietFin Portal')</title>

  <link rel="stylesheet" href="{{ asset('frontend/css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/root.css') }}">
  <link rel="stylesheet" href="{{ asset('backend/css/admin.css') }}?v={{ filemtime(public_path('backend/css/admin.css')) }}">
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
        <a class="admin-brand" href="{{ $adminHomeUrl ?? route('admin.dashboard') }}">
          <span class="admin-brand__icon"><x-icon name="home" /></span>
          <span>
            <strong>VietFin Portal</strong>
            <small>Hệ thống quản lý</small>
          </span>
        </a>
      </div>

      @php
        // NOTE: Các biến canAccess* dùng để ẩn/hiện menu theo quyền truy cập module.
        $currentUser = auth()->user();
        $canAccessDashboard = $currentUser?->coQuyen('dashboard.view');
        $canAccessInventory = $currentUser?->coQuyen('inventory.access');
        $canAccessPolicy = $currentUser?->coQuyen('policy.access');
        $canAccessReport = $currentUser?->coQuyen('report.access');
        $canAccessAccounts = $currentUser?->coQuyen('account.view');
        $canAccessProviderGroup = $currentUser?->coQuyen('service_config.access');
        $canAccessServices = $currentUser?->coQuyen('dich_vu.view');
        $canAccessCategories = $currentUser?->coQuyen('loai_san_pham.view');
        $canAccessCatalogGroup = $currentUser?->coMotTrongCacQuyen(['dich_vu.view', 'loai_san_pham.view']);
        $canAccessAdminGroup = $currentUser?->coMotTrongCacQuyen(['account.view', 'role.view']);

        $inventoryOpen = request()->routeIs('admin.dashboard');
        $catalogOpen = request()->routeIs('admin.services', 'app.services', 'admin.categories', 'app.categories', 'admin.products');
        $providerOpen = request()->routeIs('admin.providers', 'admin.provider-products', 'admin.provider-error-codes');
        $adminOpen = request()->routeIs('admin.accounts', 'app.users', 'admin.roles', 'app.roles', 'admin.audit-logs', 'admin.maintenance', 'admin.telegram-settings');
      @endphp

      <nav class="admin-nav" aria-label="Menu quản trị">
        @if ($canAccessInventory || $canAccessDashboard)
          <div class="admin-nav__group {{ $inventoryOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $inventoryOpen ? 'true' : 'false' }}">
              <span><x-icon name="description" /> Quản lý kho</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              @if ($canAccessDashboard)
                <a class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">Quản lý lô hàng</a>
              @endif
              @if ($canAccessInventory)
                <a href="#">Kho mã thẻ</a>
                <a href="#">Chi tiết kho thẻ</a>
                <a href="#">DS mã GD lấy thẻ từ kho</a>
              @endif
            </div>
          </div>
        @endif

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
              <a class="{{ request()->routeIs('admin.products') ? 'is-active' : '' }}" href="{{ route('admin.products') }}">Sản phẩm</a>
            </div>
          </div>
        @endif

        {{-- NHÓM QUẢN LÝ NHÀ CUNG CẤP (CPT RIÊNG) --}}
        @if ($canAccessProviderGroup || $currentUser?->coMotTrongCacQuyen(['account.view', 'role.view']) || $currentUser?->vai_tro === 'admin')
          <div class="admin-nav__group {{ $providerOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $providerOpen ? 'true' : 'false' }}">
              <span><x-icon name="cell_wifi" /> Nhà cung cấp</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              <a class="{{ request()->routeIs('admin.providers') ? 'is-active' : '' }}" href="{{ route('admin.providers') }}">Nhà cung cấp &amp; Kết nối</a>
              <a class="{{ request()->routeIs('admin.provider-products') ? 'is-active' : '' }}" href="{{ route('admin.provider-products') }}">Sản phẩm Nhà Cung Cấp</a>
              <a class="{{ request()->routeIs('admin.provider-error-codes') ? 'is-active' : '' }}" href="{{ route('admin.provider-error-codes') }}">Mã lỗi Nhà Cung Cấp</a>
            </div>
          </div>
        @endif

        {{-- CPT QUẢN LÝ HÓA ĐƠN & GIAO DỊCH --}}
        <a class="admin-nav__link {{ request()->routeIs('admin.orders') ? 'is-active' : '' }}" href="{{ route('admin.orders') }}">
          <x-icon name="payments" />
          <span>Quản lý Hóa đơn</span>
        </a>

        @if ($canAccessPolicy)
          <a class="admin-nav__link" href="#">
            <x-icon name="badge" />
            <span>Quản lý chính sách</span>
          </a>
        @endif

        @if ($canAccessReport)
          <a class="admin-nav__link" href="#">
            <x-icon name="history" />
            <span>Báo cáo</span>
          </a>
        @endif

        {{-- NHÓM QUẢN TRỊ --}}
        @if ($canAccessAdminGroup || $currentUser?->vai_tro === 'admin')
          <div class="admin-nav__group {{ $adminOpen ? 'is-open' : '' }}" data-nav-group>
            <button class="admin-nav__trigger" type="button" data-nav-trigger aria-expanded="{{ $adminOpen ? 'true' : 'false' }}">
              <span><x-icon name="account_circle" /> Quản trị</span>
              <svg class="admin-nav__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                <path d="M5 7.5 10 12.5 15 7.5" />
              </svg>
            </button>
            <div class="admin-nav__submenu">
              @if ($canAccessAccounts || $currentUser?->vai_tro === 'admin')
                <a class="{{ request()->routeIs('admin.accounts', 'app.users') ? 'is-active' : '' }}" href="{{ route('admin.accounts') }}">Quản lý tài khoản hệ thống</a>
              @endif
              @if ($canAccessRoles || $currentUser?->vai_tro === 'admin')
                <a class="{{ request()->routeIs('admin.roles', 'app.roles') ? 'is-active' : '' }}" href="{{ route('admin.roles') }}">Vai trò</a>
              @endif
              <a class="{{ request()->routeIs('admin.audit-logs') ? 'is-active' : '' }}" href="{{ route('admin.audit-logs') }}">Nhật ký hoạt động</a>
              <a class="{{ request()->routeIs('admin.maintenance') ? 'is-active' : '' }}" href="{{ route('admin.maintenance') }}">Bảo trì</a>
              @if ($currentUser?->coQuyen('telegram_setting.view') || $currentUser?->vai_tro === 'admin')
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
          <a class="admin-topbar__brand" href="{{ $adminHomeUrl }}">VietFin</a>
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
