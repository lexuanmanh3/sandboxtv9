<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'VietFin Portal')</title>

  <link rel="stylesheet" href="{{ asset('frontend/css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/root.css') }}">
  <link rel="stylesheet" href="{{ asset('backend/css/admin.css') }}">
  @stack('styles')
</head>
<body class="admin-page">
  <x-icon-sprite />

  <div class="admin-shell">
    <div class="admin-backdrop" data-admin-backdrop></div>

    @php
      $currentUser = auth()->user();
      $canAccessDashboard = $currentUser?->coQuyen('dashboard.access');
      $canAccessAccounts = $currentUser?->coQuyen('account.access');
      $canAccessRoles = $currentUser?->coQuyen('role.access');
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
        // Quyền ở đây là quyền vào trang/chức năng, không phải quyền sửa/xóa dữ liệu.
        $currentUser = auth()->user();
        $canAccessDashboard = $currentUser?->coQuyen('dashboard.access');
        $canAccessInventory = $currentUser?->coQuyen('inventory.access');
        $canAccessPolicy = $currentUser?->coQuyen('policy.access');
        $canAccessReport = $currentUser?->coQuyen('report.access');
        $canAccessAccounts = $currentUser?->coQuyen('account.access');
        $canAccessRoles = $currentUser?->coQuyen('role.access');
        $canAccessServiceConfig = $currentUser?->coQuyen('service_config.access');
        $canAccessAdminGroup = $currentUser?->coMotTrongCacQuyen(['account.access', 'role.access', 'service_config.access']);
        $adminHomeUrl = $canAccessDashboard
            ? route('admin.dashboard')
            : ($canAccessAccounts
                ? route('admin.accounts')
                : ($canAccessRoles ? route('admin.roles') : '#'));

        $inventoryOpen = request()->routeIs('admin.dashboard');
        $adminOpen = request()->routeIs('admin.accounts', 'app.users', 'admin.roles', 'app.roles');
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
              @if ($canAccessServiceConfig)
                <a href="#">Cấu hình dịch vụ</a>
              @endif
              @if ($canAccessRoles)
                <a class="{{ request()->routeIs('admin.roles', 'app.roles') ? 'is-active' : '' }}" href="{{ route('admin.roles') }}">Vai trò</a>
              @endif
            </div>
          </div>
        @endif
      </nav>

      <div class="admin-sidebar__support">
        <strong>Hỗ trợ kỹ thuật</strong>
        <span>Hotline: 1900 1234</span>
      </div>
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

          <div class="admin-user">
            <div class="admin-user__text">
              <strong>{{ auth()->user()->ten_hien_thi ?? auth()->user()->name ?? 'Admin' }}</strong>
              <span>{{ optional(auth()->user()->vaiTro()->first())->ten_vai_tro ?? 'Người dùng' }}</span>
            </div>
            <div class="admin-user__avatar">
              {{ mb_substr(auth()->user()->ten_hien_thi ?? auth()->user()->name ?? 'A', 0, 1) }}
            </div>
          </div>
        </div>
      </header>

      <main class="admin-content">
        @yield('content')
      </main>
    </div>
  </div>

  <script src="{{ asset('backend/js/admin.js') }}"></script>
  @stack('scripts')
</body>
</html>
