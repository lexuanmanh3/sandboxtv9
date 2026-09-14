<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'tv9tech - Cổng thanh toán viễn thông')</title>
  <link rel="stylesheet" href="{{ asset('assets/css/root.css') }}">
  <link rel="stylesheet" href="{{ asset('frontend/css/home.css') }}">
  <link rel="stylesheet" href="{{ asset('frontend/css/fonts.css') }}">
  @stack('styles')
</head>
<body>
  <x-icon-sprite />

  @php
    $currentUser = auth()->user();
    $canAccessFrontendHome = $currentUser?->coQuyen('frontend.home.access');
    $canAccessFrontendTopup = $currentUser?->coQuyen('frontend.topup.access');
    $canAccessAdmin = $currentUser?->hasAnyBackendAccess() ?? false;
  @endphp

  <header class="site-header">
    <nav class="topbar container">
      <a class="brand" href="{{ $canAccessFrontendHome ? route('frontend.home') : '#' }}" aria-label="tv9tech trang chủ">
        <img src="{{ asset('backend/img/logo.png') }}" alt="tv9tech">
      </a>

      <button class="menu-toggle" type="button" aria-label="Mở menu" aria-expanded="false">
        <x-icon name="menu" class="menu-icon" />
        <x-icon name="close" class="close-icon" />
      </button>

      <div class="nav-menu" id="mainMenu">
        @if ($canAccessFrontendHome)
          <a class="nav-link {{ request()->routeIs('frontend.home') ? 'active' : '' }}" href="{{ route('frontend.home') }}">TRANG CHỦ</a>
        @endif
        @if ($canAccessFrontendTopup)
          <a class="nav-link {{ request()->routeIs('frontend.topup') ? 'active' : '' }}" href="{{ route('frontend.topup') }}">NẠP TIỀN ĐIỆN THOẠI</a>
        @endif
        <a class="nav-link" href="#">MUA MÃ THẺ</a>
        <a class="nav-link" href="#">THANH TOÁN HÓA ĐƠN</a>
        <a class="nav-link" href="#">TOPUP DATA</a>
      </div>

      <button class="icon-btn" type="button" aria-label="Thông báo">
        <x-icon name="notifications" />
      </button>

      <div class="user-dropdown" id="userDropdown">
        <button
          class="icon-btn"
          type="button"
          id="accountBtn"
          aria-label="Tài khoản"
          aria-expanded="false"
          aria-controls="accountMenu"
        >
          <x-icon name="account_circle" />
        </button>

        <div class="dropdown-menu" id="accountMenu" role="menu" aria-hidden="true">
          <div class="dropdown-header">
            <x-icon name="account_circle" class="dropdown-header__icon" />
            <div>
              <strong>{{ auth()->user()->ten_hien_thi ?? auth()->user()->name ?? 'Người dùng' }}</strong>
              <span>{{ auth()->user()->ten_dang_nhap ?? auth()->user()->email ?? 'user' }}</span>
            </div>
          </div>

          <hr class="dropdown-divider" />

          @if ($canAccessAdmin)
            <a href="{{ route('admin.dashboard') }}" class="dropdown-item" role="menuitem" style="color: #0284c7; font-weight: 600;">
              <x-icon name="sync_alt" />
              Trang Quản trị (Admin)
            </a>
            <hr class="dropdown-divider" />
          @endif

          <a href="#" class="dropdown-item" role="menuitem">
            <x-icon name="person" />
            Thông tin tài khoản
          </a>
          <a href="#" class="dropdown-item" role="menuitem">
            <x-icon name="lock_reset" />
            Đổi mật khẩu
          </a>

          <hr class="dropdown-divider" />

          <button
            type="button"
            class="dropdown-item dropdown-item--danger"
            id="logoutBtn"
            role="menuitem"
          >
            <x-icon name="logout" />
            Đăng xuất
          </button>
        </div>
      </div>
    </nav>
  </header>

  @if(request()->routeIs('frontend.home'))
    <section class="hero">
      <div class="hero-bg"></div>
      <div class="hero-overlay"></div>

      <div class="hero-content container">
        <div class="hero-title">
          <x-icon name="home" class="hero-icon" />
          <h1>WELCOME</h1>
        </div>

        <div class="balance-card">
          <p>Mã ĐL: <strong>A6844870</strong></p>
          <p>Số dư: <strong>55.630.069.900đ</strong></p>
          <button class="outline-btn" type="button">NẠP TIỀN TÀI KHOẢN</button>
        </div>
      </div>
    </section>
  @endif

  @yield('content')

  <footer class="site-footer">
    <div class="footer-inner container">
      <div class="footer-brand">
        <strong>tv9tech</strong>
        <p>© 2024 tv9tech. Cổng thanh toán tài chính chuyên nghiệp.</p>
      </div>

      <div class="footer-links">
        <div>
          <a href="#">Về chúng tôi</a>
          <a href="#">Điều khoản sử dụng</a>
        </div>
        <div>
          <a href="#">Chính sách bảo mật</a>
          <a href="#">Liên hệ hỗ trợ</a>
        </div>
      </div>
    </div>
  </footer>

  <form id="logoutForm" method="POST" action="{{ route('logout') }}" style="display:none">
    @csrf
  </form>

  <script src="{{ asset('frontend/js/home.js') }}"></script>
  @stack('scripts')
</body>
</html>
