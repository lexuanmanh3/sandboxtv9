<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>tv9tech - Đăng nhập hệ thống</title>

  {{-- Tailwind CSS (framework layout cho trang này) --}}
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

  {{-- Font local: Be Vietnam Pro + Material Symbols (không dùng Google CDN) --}}
  <link rel="stylesheet" href="{{ asset('frontend/css/fonts.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/root.css') }}">

  {{-- CSS custom của trang auth --}}
  <link rel="stylesheet" href="{{ asset('frontend/css/auth-login.css') }}">

  {{-- Cấu hình màu sắc và typography theo design system của project --}}
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "on-tertiary-fixed":          "#042014",
            "on-tertiary-fixed-variant":  "#314d3e",
            "outline":                    "#6e7b6c",
            "background":                 "#faf8ff",
            "on-tertiary":                "#ffffff",
            "tertiary-fixed-dim":         "#afceba",
            "on-surface":                 "#131b2e",
            "on-error":                   "#ffffff",
            "surface-tint":               "#006e2d",
            "primary-container":          "#00873a",
            "outline-variant":            "#bdcaba",
            "on-secondary-fixed":         "#00210d",
            "surface-container":          "#eaedff",
            "error":                      "#ba1a1a",
            "primary-fixed":              "#7ffc97",
            "error-container":            "#ffdad6",
            "surface-container-low":      "#f2f3ff",
            "secondary-container":        "#b1f2be",
            "primary":                    "#006b2c",
            "secondary-fixed":            "#b1f2be",
            "surface-container-highest":  "#dae2fd",
            "on-surface-variant":         "#3e4a3d",
            "on-secondary":               "#ffffff",
            "inverse-primary":            "#62df7d",
            "surface-container-high":     "#e2e7ff",
            "on-secondary-fixed-variant": "#12512c",
            "surface-variant":            "#dae2fd",
            "primary-fixed-dim":          "#62df7d",
            "on-primary":                 "#ffffff",
            "tertiary-container":         "#5e7b6a",
            "surface-bright":             "#faf8ff",
            "tertiary-fixed":             "#caead6",
            "secondary-fixed-dim":        "#96d5a3",
            "on-primary-container":       "#f7fff2",
            "inverse-on-surface":         "#eef0ff",
            "secondary":                  "#2e6a41",
            "on-tertiary-container":      "#f6fff6",
            "surface":                    "#faf8ff",
            "on-secondary-container":     "#347047",
            "on-primary-fixed-variant":   "#005320",
            "on-primary-fixed":           "#002109",
            "surface-dim":                "#d2d9f4",
            "surface-container-lowest":   "#ffffff",
            "on-background":              "#131b2e",
            "tertiary":                   "#466252",
            "inverse-surface":            "#283044",
            "on-error-container":         "#93000a"
          },
          borderRadius: {
            DEFAULT: "0.25rem",
            lg:      "0.5rem",
            xl:      "0.75rem",
            full:    "9999px"
          },
          spacing: {
            gutter:           "24px",
            "stack-md":       "16px",
            "margin-mobile":  "16px",
            "stack-sm":       "8px",
            "stack-lg":       "32px",
            "margin-desktop": "32px",
            "container-max":  "1280px",
            unit:             "4px"
          },
          fontFamily: {
            "body-md":           ["Be Vietnam Pro"],
            "body-lg":           ["Be Vietnam Pro"],
            "label-md":          ["Be Vietnam Pro"],
            "headline-sm":       ["Be Vietnam Pro"],
            "display-lg-mobile": ["Be Vietnam Pro"],
            "label-sm":          ["Be Vietnam Pro"],
            "headline-md":       ["Be Vietnam Pro"],
            "display-lg":        ["Be Vietnam Pro"]
          },
          fontSize: {
            "body-md":           ["16px", { lineHeight: "24px",  fontWeight: "400" }],
            "body-lg":           ["18px", { lineHeight: "28px",  fontWeight: "400" }],
            "label-md":          ["14px", { lineHeight: "20px",  letterSpacing: "0.01em", fontWeight: "500" }],
            "headline-sm":       ["20px", { lineHeight: "28px",  fontWeight: "600" }],
            "display-lg-mobile": ["32px", { lineHeight: "40px",  letterSpacing: "-0.02em", fontWeight: "700" }],
            "label-sm":          ["12px", { lineHeight: "16px",  fontWeight: "600" }],
            "headline-md":       ["24px", { lineHeight: "32px",  fontWeight: "600" }],
            "display-lg":        ["48px", { lineHeight: "56px",  letterSpacing: "-0.02em", fontWeight: "700" }]
          }
        }
      }
    }
  </script>
</head>

<body class="bg-background min-h-screen flex flex-col font-body-md text-on-surface overflow-x-hidden">
  <x-icon-sprite />

  {{-- ===== HEADER — LOGO ===== --}}
  <header class="w-full flex justify-center pt-margin-desktop">
    <div class="flex items-center justify-center">
      <img
        src="{{ asset('backend/img/logo.png') }}"
        alt="tv9tech Logo"
        class="h-10 w-auto object-contain"
      />
    </div>
  </header>

  {{-- ===== NỘI DUNG CHÍNH ===== --}}
  <main class="flex-grow flex items-center justify-center px-margin-mobile relative">

    {{-- Hiệu ứng ánh sáng nền --}}
    <div class="ambient-glow -top-20 -left-20 pointer-events-none"></div>
    <div class="ambient-glow -bottom-40 -right-20 pointer-events-none"
         style="background: radial-gradient(circle, rgba(177,242,190,.15) 0%, rgba(255,255,255,0) 70%);">
    </div>

    {{-- ===== CARD ĐĂNG NHẬP ===== --}}
    <div class="w-full max-w-[440px] glass-card rounded-xl shadow-lg p-stack-lg border border-outline-variant/30">

      <div class="text-center mb-stack-lg">
        <h1 class="font-headline-md text-headline-md text-on-surface mb-2">Chào mừng trở lại</h1>
        <p class="font-body-md text-on-surface-variant">Đăng nhập vào tài khoản của bạn</p>
      </div>

      {{-- Thông báo sau khi đăng xuất / đăng ký thành công --}}
      @if (session('success'))
        <div class="alert-success">
          <x-icon name="check_circle" />
          {{ session('success') }}
        </div>
      @endif

      {{-- Banner lỗi tổng (sai tài khoản, bị khóa, v.v.) --}}
      @if ($errors->has('ten_dang_nhap') || $errors->has('password'))
        <div class="alert-error">
          <x-icon name="error" />
          <span>
            {{ $errors->first('ten_dang_nhap') ?: $errors->first('password') }}
          </span>
        </div>
      @endif

      {{-- ===== FORM ĐĂNG NHẬP ===== --}}
      <form
        id="loginForm"
        method="POST"
        action="{{ route('login.submit') }}"
        class="space-y-stack-md"
        novalidate
      >
        @csrf

        {{-- Tên đăng nhập hoặc email --}}
        <div class="relative input-group">
          <label
            class="block font-label-md text-label-md text-on-surface-variant mb-1 ml-1"
            for="ten_dang_nhap"
          >
            Tên đăng nhập hoặc Email
          </label>
          <div class="relative">
            <x-icon name="person" class="input-icon absolute left-3 top-1/2 -translate-y-1/2 text-outline transition-colors duration-200 pointer-events-none" />
            <input
              type="text"
              id="ten_dang_nhap"
              name="ten_dang_nhap"
              value="{{ old('ten_dang_nhap') }}"
              placeholder="name@example.com"
              autocomplete="username"
              autofocus
              class="w-full pl-10 pr-4 py-3 bg-white/50 border rounded-lg font-body-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 placeholder:text-outline/50
                     {{ $errors->has('ten_dang_nhap') ? 'border-red-400' : 'border-outline-variant' }}"
            />
          </div>
        </div>

        {{-- Mật khẩu --}}
        <div class="relative input-group">
          <div class="flex justify-between items-end mb-1">
            <label
              class="block font-label-md text-label-md text-on-surface-variant ml-1"
              for="password"
            >
              Mật khẩu
            </label>
            <a class="font-label-md text-label-md text-primary hover:underline transition-all" href="#">
              Quên mật khẩu?
            </a>
          </div>
          <div class="relative">
            <x-icon name="lock" class="input-icon absolute left-3 top-1/2 -translate-y-1/2 text-outline transition-colors duration-200 pointer-events-none" />
            <input
              type="password"
              id="password"
              name="password"
              placeholder="••••••••"
              autocomplete="current-password"
              class="w-full pl-10 pr-12 py-3 bg-white/50 border rounded-lg font-body-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 placeholder:text-outline/50
                     {{ $errors->has('password') ? 'border-red-400' : 'border-outline-variant' }}"
            />
            {{-- Nút toggle hiển thị mật khẩu --}}
            <button
              type="button"
              onclick="togglePassword()"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors flex items-center justify-center p-1"
              aria-label="Hiện/ẩn mật khẩu"
            >
              <span id="passwordIconShow"><x-icon name="visibility" /></span>
              <span id="passwordIconHide" style="display:none"><x-icon name="visibility_off" /></span>
            </button>
          </div>
        </div>

        {{-- Nhớ đăng nhập --}}
        <div class="flex items-center gap-2 px-1">
          <input
            type="checkbox"
            id="nho_mat_khau"
            name="nho_mat_khau"
            value="1"
            class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary focus:ring-offset-0"
          />
          <label
            class="font-label-md text-label-md text-on-surface-variant cursor-pointer select-none"
            for="nho_mat_khau"
          >
            Nhớ đăng nhập
          </label>
        </div>

        {{-- Nút đăng nhập --}}
        <div class="pt-2">
          <button
            type="submit"
            class="w-full bg-primary text-on-primary font-body-md font-bold py-3 px-4 rounded-lg shadow-md hover:bg-primary-container active:scale-[0.98] transition-all duration-200 flex items-center justify-center gap-2"
          >
            <span>Đăng nhập</span>
            <x-icon name="login" />
          </button>
        </div>
      </form>

      {{-- ===== LINK SANG ĐĂNG KÝ ===== --}}
      <div class="mt-stack-lg text-center">
        <p class="font-body-md text-on-surface-variant">
          Chưa có tài khoản?
          <a
            href="{{ route('register') }}"
            class="text-primary font-bold hover:underline ml-1"
          >
            Đăng ký ngay
          </a>
        </p>
      </div>

    </div>{{-- /card --}}
  </main>

  {{-- ===== FOOTER ===== --}}
  <footer class="w-full py-stack-md flex flex-col items-center gap-stack-sm mt-auto opacity-70">
    <div class="flex gap-margin-mobile">
      <a class="font-label-sm text-label-sm text-outline-variant hover:text-primary transition-colors" href="#">
        Điều khoản sử dụng
      </a>
      <a class="font-label-sm text-label-sm text-outline-variant hover:text-primary transition-colors" href="#">
        Chính sách bảo mật
      </a>
      <a class="font-label-sm text-label-sm text-outline-variant hover:text-primary transition-colors" href="#">
        Liên hệ
      </a>
    </div>
    <p class="font-label-sm text-label-sm text-outline">© 2024 tv9tech. Tất cả quyền được bảo hộ.</p>
  </footer>

  {{-- JS riêng cho trang login (toggle password, animation, loading state) --}}
  <script src="{{ asset('frontend/js/auth-login.js') }}"></script>

</body>
</html>
