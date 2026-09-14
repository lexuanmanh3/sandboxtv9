<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>tv9tech - Đăng ký tài khoản</title>

  <link rel="stylesheet" href="{{ asset('frontend/css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/root.css') }}">
  <link rel="stylesheet" href="{{ asset('frontend/css/auth-register.css') }}">
</head>

<body class="register-page">
  <x-icon-sprite />

  <div class="register-bg" aria-hidden="true"></div>

  <main class="register-main">
    <div class="register-shell">
      <header class="register-brand">
        <div class="register-brand__mark">
          <x-icon name="account_balance_wallet" />
          <h1>tv9tech</h1>
        </div>
        <p>Bắt đầu hành trình thịnh vượng cùng chúng tôi</p>
      </header>

      <section class="register-card" aria-labelledby="register-title">
        <h2 id="register-title">Đăng ký tài khoản</h2>

        <form id="registerForm" class="register-form" method="POST" action="{{ route('register.submit') }}">
          @csrf

          @php
            $fullName = old('full_name', trim(old('ho') . ' ' . old('ten')));
          @endphp

          <input type="hidden" id="ho" name="ho" value="{{ old('ho') }}">
          <input type="hidden" id="ten" name="ten" value="{{ old('ten') }}">

          <div class="form-field">
            <label for="full_name">Họ và tên</label>
            <div class="input-box @error('ho') is-invalid @enderror @error('ten') is-invalid @enderror">
              <x-icon name="person" class="input-box__icon" />
              <input
                type="text"
                id="full_name"
                name="full_name"
                value="{{ $fullName }}"
                placeholder="Nguyễn Văn A"
                autocomplete="name"
                required
                autofocus
              />
            </div>
            @if ($errors->has('ho') || $errors->has('ten'))
              <p class="field-error">
                <x-icon name="error" />
                {{ $errors->first('ho') ?: $errors->first('ten') }}
              </p>
            @endif
          </div>

          <div class="form-field">
            <label for="ten_dang_nhap">Tên đăng nhập</label>
            <div class="input-box @error('ten_dang_nhap') is-invalid @enderror">
              <x-icon name="alternate_email" class="input-box__icon" />
              <input
                type="text"
                id="ten_dang_nhap"
                name="ten_dang_nhap"
                value="{{ old('ten_dang_nhap') }}"
                placeholder="nguyen.vana"
                autocomplete="username"
                required
              />
            </div>
            @error('ten_dang_nhap')
              <p class="field-error">
                <x-icon name="error" />
                {{ $message }}
              </p>
            @enderror
          </div>

          <div class="form-field">
            <label for="email">Email</label>
            <div class="input-box @error('email') is-invalid @enderror">
              <x-icon name="mail" class="input-box__icon" />
              <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="example@tv9tech.vn"
                autocomplete="email"
              />
            </div>
            @error('email')
              <p class="field-error">
                <x-icon name="error" />
                {{ $message }}
              </p>
            @enderror
          </div>

          <div class="form-field">
            <label for="so_dien_thoai">Số điện thoại</label>
            <div class="input-box @error('so_dien_thoai') is-invalid @enderror">
              <x-icon name="phone" class="input-box__icon" />
              <input
                type="tel"
                id="so_dien_thoai"
                name="so_dien_thoai"
                value="{{ old('so_dien_thoai') }}"
                placeholder="09xx xxx xxx"
                autocomplete="tel"
              />
            </div>
            @error('so_dien_thoai')
              <p class="field-error">
                <x-icon name="error" />
                {{ $message }}
              </p>
            @enderror
          </div>

          <div class="form-field">
            <label for="password">Mật khẩu</label>
            <div class="input-box @error('password') is-invalid @enderror">
              <x-icon name="lock" class="input-box__icon" />
              <input
                type="password"
                id="password"
                name="password"
                placeholder="••••••••"
                autocomplete="new-password"
                required
              />
              <button class="password-toggle" type="button" data-toggle-password="password" aria-label="Hiện mật khẩu">
                <x-icon name="visibility" class="password-toggle__show" />
                <x-icon name="visibility_off" class="password-toggle__hide" />
              </button>
            </div>
            @error('password')
              <p class="field-error">
                <x-icon name="error" />
                {{ $message }}
              </p>
            @enderror
          </div>

          <div class="form-field">
            <label for="password_confirmation">Xác nhận mật khẩu</label>
            <div class="input-box @error('password_confirmation') is-invalid @enderror">
              <x-icon name="lock_reset" class="input-box__icon" />
              <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                placeholder="••••••••"
                autocomplete="new-password"
                required
              />
              <button class="password-toggle" type="button" data-toggle-password="password_confirmation" aria-label="Hiện mật khẩu xác nhận">
                <x-icon name="visibility" class="password-toggle__show" />
                <x-icon name="visibility_off" class="password-toggle__hide" />
              </button>
            </div>
            @error('password_confirmation')
              <p class="field-error">
                <x-icon name="error" />
                {{ $message }}
              </p>
            @enderror
          </div>

          <label class="terms-row" for="terms">
            <input id="terms" type="checkbox" required />
            <span>Tôi đồng ý với các <a href="#">Điều khoản & Chính sách</a> của tv9tech.</span>
          </label>

          <button class="register-submit" type="submit">
            <span>Đăng ký tài khoản</span>
            <x-icon name="login" />
          </button>
        </form>

        <div class="register-login-link">
          <p>
            Đã có tài khoản?
            <a href="{{ route('login') }}">Đăng nhập ngay</a>
          </p>
        </div>
      </section>

      <nav class="register-support" aria-label="Liên kết hỗ trợ">
        <a href="#">Tiếng Việt</a>
        <span>|</span>
        <a href="#">English</a>
        <span>|</span>
        <a href="#">Trung tâm hỗ trợ</a>
      </nav>
    </div>
  </main>

  <footer class="register-footer">
    <strong>tv9tech</strong>
    <div class="register-footer__links">
      <p>© 2024 tv9tech. Tất cả quyền được bảo hộ.</p>
      <a href="#">Điều khoản sử dụng</a>
      <a href="#">Chính sách bảo mật</a>
      <a href="#">Liên hệ</a>
    </div>
  </footer>

  <script src="{{ asset('frontend/js/auth-register.js') }}"></script>
</body>
</html>
