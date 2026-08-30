@extends('layout')

@section('title', 'Nạp Tiền Điện Thoại')

@push('styles')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('frontend/css/topup.css') }}?v={{ time() }}">
@endpush

@section('content')
  {{-- Toast container (notifications) --}}
  <div class="topup-toast-container" id="toastContainer"></div>

  {{-- Modal xác nhận giao dịch --}}
  <div class="topup-modal-backdrop" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="topup-modal">
      <div class="topup-modal__header" id="modalHeader">
        <div class="topup-modal__icon is-confirm" id="modalIcon">
          <i class="fa-solid fa-circle-question" id="modalIconI"></i>
        </div>
        <div class="topup-modal__title" id="modalTitle">Xác nhận nạp tiền</div>
        <div class="topup-modal__subtitle" id="modalSubtitle">Kiểm tra thông tin trước khi xác nhận</div>
      </div>

      <div class="topup-modal__body" id="modalBody">
        <div class="topup-modal__row">
          <span class="topup-modal__row-label">Số điện thoại</span>
          <span class="topup-modal__row-value" id="modalPhone">—</span>
        </div>
        <div class="topup-modal__row">
          <span class="topup-modal__row-label">Nhà mạng</span>
          <span class="topup-modal__row-value" id="modalCarrier">—</span>
        </div>
        <div class="topup-modal__row">
          <span class="topup-modal__row-label">Mệnh giá</span>
          <span class="topup-modal__row-value" id="modalDenomination">—</span>
        </div>
        <div class="topup-modal__row" id="modalDiscountRow" style="display:none">
          <span class="topup-modal__row-label">Chiết khấu</span>
          <span class="topup-modal__row-value" id="modalDiscount" style="color:#059669">—</span>
        </div>
        <div class="topup-modal__row" style="border-top:1px dashed #cfe0d4; padding-top:8px; margin-top:4px;">
          <span class="topup-modal__row-label"><strong>Số tiền thanh toán</strong></span>
          <span class="topup-modal__row-value highlight" id="modalPrice">—</span>
        </div>
        <div class="topup-modal__row">
          <span class="topup-modal__row-label">Số dư sau giao dịch</span>
          <span class="topup-modal__row-value" id="modalBalance" style="color:#0284c7">—</span>
        </div>
      </div>

      <div class="topup-modal__actions" id="modalActions">
        <button class="topup-modal__btn cancel" id="modalCancelBtn" type="button">Hủy</button>
        <button class="topup-modal__btn confirm" id="modalConfirmBtn" type="button">
          <i class="fa-solid fa-bolt"></i> Xác nhận nạp
        </button>
      </div>
    </div>
  </div>

  {{-- TOP BANNER HEADER --}}
  <section class="top-header-banner">
    <a href="{{ route('frontend.home') }}" class="top-header-banner__left">
      <i class="fa-solid fa-house"></i>
      <span>Trang chủ</span>
    </a>

    <div class="top-header-banner__center">
      <div class="top-header-banner__tab">
        <i class="fa-solid fa-mobile-screen-button"></i>
        <span>NẠP TIỀN ĐIỆN THOẠI</span>
      </div>
    </div>

    <div class="top-header-banner__right">
      <div class="topup-balance-chip">
        <i class="fa-solid fa-wallet"></i>
        <span id="headerBalance">{{ number_format((float)$vi->so_du, 0, ',', '.') }}đ</span>
      </div>
      <div class="top-header-agency-info">
        <div>Mã ĐL: <strong>{{ auth()->user()->ten_dang_nhap ?? 'USER' }}</strong></div>
      </div>
      <a href="{{ route('frontend.topup.history') }}" class="top-header-deposit-btn">
        <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử
      </a>
    </div>
  </section>

  {{-- MAIN DASHBOARD CONTENT --}}
  <div class="topup-dashboard-wrap">
    <div class="topup-container">

      {{-- SIDEBAR MENU --}}
      <aside class="topup-sidebar">
        <a href="#" class="topup-menu-item">
          <i class="fa-regular fa-id-badge"></i>
          <span>Thông tin<br>tài khoản</span>
        </a>
        <a href="{{ route('frontend.topup') }}" class="topup-menu-item active">
          <i class="fa-solid fa-mobile-screen-button"></i>
          <span>Nạp tiền<br>điện thoại</span>
        </a>
        <a href="#" class="topup-menu-item">
          <i class="fa-solid fa-tower-broadcast"></i>
          <span>Nạp topup<br>data</span>
        </a>
        <a href="#" class="topup-menu-item">
          <i class="fa-regular fa-credit-card"></i>
          <span>Mua thẻ<br>trả trước</span>
        </a>
        <a href="#" class="topup-menu-item">
          <i class="fa-regular fa-file-lines"></i>
          <span>Thanh toán<br>hóa đơn</span>
        </a>
      </aside>

      {{-- MAIN TOPUP FORM AREA --}}
      <main class="topup-main-content">

        {{-- FORM ROW (Phone & Telco) --}}
        <div class="topup-form-grid">

          {{-- Input Số điện thoại --}}
          <div class="form-group">
            <label class="topup-field-label" for="topupPhoneInput">
              <i class="fa-solid fa-mobile-screen"></i>
              <span>Số điện thoại</span>
              <span class="required">*</span>
            </label>
            <input
              type="tel"
              class="topup-input-box"
              id="topupPhoneInput"
              placeholder="Nhập số điện thoại (VD: 0987654321)"
              maxlength="12"
              autocomplete="off"
              inputmode="tel"
            >
            <div class="topup-field-error" id="phoneError">
              <i class="fa-solid fa-circle-exclamation"></i>
              <span id="phoneErrorMsg">Số điện thoại không hợp lệ</span>
            </div>
          </div>

          {{-- Chọn nhà mạng --}}
          <div class="form-group" style="position: relative;">
            <label class="topup-field-label">
              <i class="fa-regular fa-money-bill-1"></i>
              <span>Chọn nhà mạng</span>
              <span class="required">*</span>
            </label>
            <div class="topup-select-card" id="telcoSelectCard" tabindex="0" role="combobox" aria-expanded="false">
              <span class="topup-select-card-value" id="currentTelcoName">
                {{ $selectedCategory ? $selectedCategory->ten_loai_san_pham : 'Chọn nhà mạng' }}
              </span>
              <div class="topup-select-brand">
                <div id="currentTelcoLogo" class="topup-telco-logo-container">
                  @if (!empty($selectedCategory?->hinh_anh_url))
                    <img src="{{ $selectedCategory->hinh_anh_url }}" alt="{{ $selectedCategory->ten_loai_san_pham }}" style="max-height:28px; max-width:60px; object-fit:contain;">
                  @endif
                </div>
                <i class="fa-solid fa-chevron-down" id="telcoChevron"></i>
              </div>
            </div>

            <div class="topup-select-dropdown" id="telcoDropdown" role="listbox">
              @foreach ($categories as $cat)
                <div
                  class="topup-select-option {{ $selectedCategory?->id == $cat->id ? 'is-active' : '' }}"
                  data-id="{{ $cat->id }}"
                  data-code="{{ $cat->ma_loai_san_pham }}"
                  data-name="{{ $cat->ten_loai_san_pham }}"
                  data-image="{{ $cat->hinh_anh_url ?? '' }}"
                  role="option"
                  tabindex="0"
                >
                  <span>{{ $cat->ten_loai_san_pham }}</span>
                  @if (!empty($cat->hinh_anh_url))
                    <img src="{{ $cat->hinh_anh_url }}" alt="{{ $cat->ten_loai_san_pham }}" style="max-height:28px; max-width:60px; object-fit:contain;">
                  @else
                    <span style="font-size:11px; color:#006b2c; font-weight:700;">{{ $cat->ma_loai_san_pham }}</span>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        </div>

        {{-- MỆNH GIÁ --}}
        <div class="topup-denomination-title">
          <i class="fa-solid fa-wallet"></i>
          <span>Chọn mệnh giá</span>
        </div>

        {{-- Skeleton loading --}}
        <div class="topup-grid-pricing" id="skeletonGrid" style="display:none;">
          @for ($i = 0; $i < 8; $i++)
            <div class="topup-skeleton-card"></div>
          @endfor
        </div>

        {{-- Grid mệnh giá thực --}}
        <div class="topup-grid-pricing" id="denominationGrid">
          @forelse ($products as $product)
            @php
              $menhGia   = (float)$product->menh_gia;
              $giaBan    = $product->gia_ban ? (float)$product->gia_ban : $menhGia;
              $chietKhau = (float)($product->chiet_khau_phan_tram ?? 0);
            @endphp
            <div
              class="topup-price-card"
              data-product-id="{{ $product->id }}"
              data-menh-gia="{{ $menhGia }}"
              data-gia-ban="{{ $giaBan }}"
              data-chiet-khau="{{ $chietKhau }}"
              data-ten="{{ $product->ten_san_pham }}"
              role="radio"
              aria-checked="false"
              tabindex="0"
            >
              <div class="topup-telco-logo" id="logo_{{ $product->id }}">
                @if (!empty($selectedCategory?->hinh_anh_url))
                  <img src="{{ $selectedCategory->hinh_anh_url }}" alt="{{ $selectedCategory->ten_loai_san_pham }}" style="max-height:28px; max-width:60px; object-fit:contain;">
                @endif
              </div>
              <div class="topup-price-value">{{ number_format($menhGia, 0, ',', '.') }}đ</div>
              <div class="topup-price-action">
                @if ($chietKhau > 0)
                  <span style="color:#059669; font-weight:700;">{{ number_format($giaBan, 0, ',', '.') }}đ</span>
                  <span style="background:#dcfce7;color:#15803d;border-radius:4px;padding:1px 5px;font-size:10px;font-weight:700;">-{{ $chietKhau }}%</span>
                @else
                  <span>{{ number_format($giaBan, 0, ',', '.') }}đ</span>
                  <i class="fa-regular fa-eye"></i>
                @endif
              </div>
            </div>
          @empty
            <div style="grid-column: 1/-1; text-align:center; color:#4a6353; padding:30px;">
              <i class="fa-solid fa-circle-info" style="font-size:24px; margin-bottom:8px; display:block;"></i>
              Chưa có mệnh giá nào. Vui lòng chọn nhà mạng khác.
            </div>
          @endforelse
        </div>

        {{-- CONFIRMATION PANEL --}}
        <div class="topup-confirm-panel is-hidden" id="confirmPanel">
          <div class="topup-confirm-title">
            <i class="fa-solid fa-receipt"></i>
            Thông tin giao dịch
          </div>
          <div class="topup-confirm-rows">
            <div class="topup-confirm-row">
              <span class="topup-confirm-row__label">Số điện thoại</span>
              <span class="topup-confirm-row__value" id="confirmPhone">—</span>
            </div>
            <div class="topup-confirm-row">
              <span class="topup-confirm-row__label">Nhà mạng</span>
              <span class="topup-confirm-row__value" id="confirmCarrier">—</span>
            </div>
            <div class="topup-confirm-row">
              <span class="topup-confirm-row__label">Mệnh giá</span>
              <span class="topup-confirm-row__value" id="confirmDenomination">—</span>
            </div>
            <div class="topup-confirm-row" id="confirmDiscountRow" style="display:none">
              <span class="topup-confirm-row__label">Chiết khấu</span>
              <span class="topup-confirm-row__value" style="color:#059669" id="confirmDiscount">—</span>
            </div>
            <div class="topup-confirm-row is-divider">
              <span class="topup-confirm-row__label"><strong>Số tiền thanh toán</strong></span>
              <span class="topup-confirm-row__value is-price" id="confirmPrice">—</span>
            </div>
            <div class="topup-confirm-row">
              <span class="topup-confirm-row__label">Số dư hiện tại</span>
              <span class="topup-confirm-row__value is-balance" id="confirmCurrentBalance">—</span>
            </div>
            <div class="topup-confirm-row">
              <span class="topup-confirm-row__label">Số dư sau giao dịch</span>
              <span class="topup-confirm-row__value" id="confirmAfterBalance">—</span>
            </div>
          </div>
          <button class="topup-submit-btn" id="napTienBtn" type="button" disabled>
            <i class="fa-solid fa-bolt" id="napTienIcon"></i>
            <span id="napTienText">Nạp tiền ngay</span>
          </button>
        </div>

        {{-- LỊCH SỬ 5 GIAO DỊCH GẦN NHẤT --}}
        <div class="topup-history-section" id="recentHistorySection" style="display:none;">
          <div class="topup-history-title">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Giao dịch gần đây</h3>
            <a href="{{ route('frontend.topup.history') }}" class="topup-history-link">Xem tất cả →</a>
          </div>
          <table class="topup-history-table" id="recentHistoryTable">
            <thead>
              <tr>
                <th>Thời gian</th>
                <th>Số điện thoại</th>
                <th>Nhà mạng</th>
                <th>Mệnh giá</th>
                <th>Trạng thái</th>
              </tr>
            </thead>
            <tbody id="recentHistoryBody"></tbody>
          </table>
        </div>

      </main>
    </div>
  </div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
  // ── Cấu hình API dùng route() helper chính xác của Laravel ──
  const API = {
    CARRIERS:      "{{ route('api.topup.carriers') }}",
    DENOMINATIONS: "{{ route('api.topup.denominations') }}",
    PREVIEW:       "{{ route('api.topup.preview') }}",
    ORDERS:        "{{ route('api.topup.orders.create') }}",
    ORDERS_HISTORY: "{{ route('api.topup.orders.history') }}",
  };

  // CSRF token chính xác từ Blade
  const CSRF = "{{ csrf_token() }}";

  // ── State ──
  const state = {
    phone:           "",
    carrierId:       {{ $selectedCategory?->id ?? 'null' }},
    carrierName:     "{{ $selectedCategory?->ten_loai_san_pham ?? '' }}",
    carrierCode:     "{{ $selectedCategory?->ma_loai_san_pham ?? '' }}",
    productId:       null,
    menhGia:         0,
    giaBan:          0,
    chietKhau:       0,
    soduHienTai:     {{ (float)$vi->so_du }},
    isSubmitting:    false,
    idempotencyKey:  null,
  };

  // ── DOM Elements ──
  const phoneInput        = document.getElementById("topupPhoneInput");
  const phoneError        = document.getElementById("phoneError");
  const telcoSelectCard   = document.getElementById("telcoSelectCard");
  const telcoDropdown     = document.getElementById("telcoDropdown");
  const currentTelcoName  = document.getElementById("currentTelcoName");
  const currentTelcoLogo  = document.getElementById("currentTelcoLogo");
  const telcoChevron      = document.getElementById("telcoChevron");
  const denominationGrid  = document.getElementById("denominationGrid");
  const skeletonGrid      = document.getElementById("skeletonGrid");
  const confirmPanel      = document.getElementById("confirmPanel");
  const napTienBtn        = document.getElementById("napTienBtn");
  const napTienIcon       = document.getElementById("napTienIcon");
  const napTienText       = document.getElementById("napTienText");
  const confirmModal      = document.getElementById("confirmModal");
  const toastContainer    = document.getElementById("toastContainer");
  const headerBalance     = document.getElementById("headerBalance");
  const recentHistorySection = document.getElementById("recentHistorySection");
  const recentHistoryBody = document.getElementById("recentHistoryBody");

  // ── Nhận diện nhà mạng từ đầu số ──
  const TELCO_PREFIXES = {
    'VTE': ['032','033','034','035','036','037','038','039','086','096','097','098'],
    'VMS': ['070','076','077','078','079','089','090','093'],
    'VNA': ['081','082','083','084','085','088','091','094'],
    'VNM': ['052','056','058','092'],
    'GMOBILE': ['059','099'],
    'WT': ['055'],
  };

  function detectCarrier(phone) {
    const prefix3 = phone.substring(0, 3);
    for (const [code, prefixes] of Object.entries(TELCO_PREFIXES)) {
      if (prefixes.includes(prefix3)) return code;
    }
    return null;
  }

  // ── Validation số điện thoại ──
  function validatePhone(phone) {
    phone = phone.replace(/[\s\.\-]/g, '');
    if (phone.startsWith('+84')) phone = '0' + phone.slice(3);
    return /^(0)[3-9][0-9]{8}$/.test(phone) ? phone : null;
  }

  function showPhoneError(msg) {
    document.getElementById("phoneErrorMsg").textContent = msg;
    phoneError.classList.add("is-visible");
    phoneInput.classList.add("is-invalid");
  }

  function clearPhoneError() {
    phoneError.classList.remove("is-visible");
    phoneInput.classList.remove("is-invalid");
  }

  // ── Toast notifications ──
  function showToast(type, title, message, duration = 5000) {
    const icons = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
    const toast = document.createElement("div");
    toast.className = `topup-toast is-${type}`;
    toast.innerHTML = `
      <i class="topup-toast__icon fa-solid ${icons[type] || icons.info}"></i>
      <div class="topup-toast__body">
        <div class="topup-toast__title">${title}</div>
        ${message ? `<div class="topup-toast__message">${message}</div>` : ''}
      </div>
      <button class="topup-toast__close" aria-label="Đóng">✕</button>`;
    toast.querySelector(".topup-toast__close").addEventListener("click", () => removeToast(toast));
    toastContainer.appendChild(toast);
    if (duration > 0) setTimeout(() => removeToast(toast), duration);
  }

  function removeToast(toast) {
    toast.style.animation = "fadeOut 0.3s ease forwards";
    setTimeout(() => toast.remove(), 300);
  }

  // ── Format số tiền ──
  function fmt(n) {
    return Number(n).toLocaleString('vi-VN') + 'đ';
  }

  // ── Cập nhật Confirmation Panel ──
  function updateConfirmPanel() {
    const phoneOk  = state.phone.length > 0;
    const carriOk  = state.carrierId !== null;
    const prodOk   = state.productId !== null;
    const allOk    = phoneOk && carriOk && prodOk;

    if (!allOk) {
      confirmPanel.classList.add("is-hidden");
      napTienBtn.disabled = true;
      return;
    }

    const soDuSau = state.soduHienTai - state.giaBan;
    const duSoDu  = soDuSau >= 0;

    document.getElementById("confirmPhone").textContent       = state.phone;
    document.getElementById("confirmCarrier").textContent     = state.carrierName;
    document.getElementById("confirmDenomination").textContent = fmt(state.menhGia);
    document.getElementById("confirmPrice").textContent       = fmt(state.giaBan);
    document.getElementById("confirmCurrentBalance").textContent = fmt(state.soduHienTai);

    const afterEl = document.getElementById("confirmAfterBalance");
    afterEl.textContent = duSoDu ? fmt(soDuSau) : "Không đủ số dư";
    afterEl.className = `topup-confirm-row__value ${duSoDu ? 'is-balance' : 'is-negative'}`;

    const discountRow = document.getElementById("confirmDiscountRow");
    if (state.chietKhau > 0) {
      discountRow.style.display = "flex";
      document.getElementById("confirmDiscount").textContent = `-${state.chietKhau}%`;
    } else {
      discountRow.style.display = "none";
    }

    confirmPanel.classList.remove("is-hidden");
    napTienBtn.disabled = !duSoDu;

    if (!duSoDu) {
      napTienBtn.title = "Số dư không đủ để thực hiện giao dịch này";
    } else {
      napTienBtn.title = "";
    }
  }

  // ── Dropdown Nhà mạng ──
  telcoSelectCard?.addEventListener("click", (e) => {
    e.stopPropagation();
    const isOpen = telcoDropdown.classList.toggle("is-open");
    telcoSelectCard.setAttribute("aria-expanded", String(isOpen));
    telcoChevron.style.transform = isOpen ? "rotate(180deg)" : "";
  });

  document.addEventListener("click", () => {
    telcoDropdown?.classList.remove("is-open");
    telcoChevron.style.transform = "";
  });

  document.querySelectorAll(".topup-select-option").forEach((opt) => {
    const selectOption = (opt) => {
      state.carrierId   = parseInt(opt.dataset.id);
      state.carrierName = opt.dataset.name;
      state.carrierCode = opt.dataset.code;
      state.productId   = null; // Reset mệnh giá khi đổi NM
      state.giaBan      = 0;
      state.menhGia     = 0;

      document.querySelectorAll(".topup-select-option").forEach(o => o.classList.remove("is-active"));
      opt.classList.add("is-active");

      const name = opt.dataset.name;
      const img  = opt.dataset.image;
      if (currentTelcoName) currentTelcoName.textContent = name;

      const logoHtml = img
        ? `<img src="${img}" alt="${name}" style="max-height:28px;max-width:60px;object-fit:contain;">`
        : `<span style="font-size:13px;font-weight:800;color:#006b2c;">${name}</span>`;
      if (currentTelcoLogo) currentTelcoLogo.innerHTML = logoHtml;

      telcoDropdown?.classList.remove("is-open");
      telcoChevron.style.transform = "";

      // Tải mệnh giá mới
      loadDenominations(state.carrierId, img, name);
      updateConfirmPanel();
    };

    opt.addEventListener("click", () => selectOption(opt));
    opt.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") { e.preventDefault(); selectOption(opt); }
    });
  });

  // ── Tải mệnh giá từ API ──
  async function loadDenominations(carrierId, logoImg, carrierName) {
    if (!carrierId) return;

    // Hiện skeleton
    denominationGrid.style.display = "none";
    skeletonGrid.style.display     = "grid";

    try {
      const res  = await fetch(`${API.DENOMINATIONS}?carrier_id=${carrierId}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': CSRF
        }
      });

      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }

      const json = await res.json();

      skeletonGrid.style.display     = "none";
      denominationGrid.style.display = "grid";

      if (!json.success || !json.data?.length) {
        denominationGrid.innerHTML = `
          <div style="grid-column:1/-1;text-align:center;color:#4a6353;padding:30px;">
            <i class="fa-solid fa-circle-info" style="font-size:24px;margin-bottom:8px;display:block;"></i>
            Chưa có mệnh giá khả dụng cho nhà mạng này.
          </div>`;
        return;
      }

      denominationGrid.innerHTML = json.data.map(p => {
        const logoHtml = logoImg
          ? `<img src="${logoImg}" alt="${carrierName}" style="max-height:28px;max-width:60px;object-fit:contain;">`
          : `<span style="font-size:13px;font-weight:800;color:#006b2c;">${carrierName}</span>`;
        const discountBadge = p.chiet_khau > 0
          ? `<span style="background:#dcfce7;color:#15803d;border-radius:4px;padding:1px 5px;font-size:10px;font-weight:700;">-${p.chiet_khau}%</span>`
          : `<i class="fa-regular fa-eye"></i>`;
        const priceLabel = p.chiet_khau > 0
          ? `<span style="color:#059669;font-weight:700;">${p.gia_ban_label}</span>`
          : `<span>${p.gia_ban_label}</span>`;

        return `
          <div class="topup-price-card"
            data-product-id="${p.id}"
            data-menh-gia="${p.menh_gia}"
            data-gia-ban="${p.gia_ban}"
            data-chiet-khau="${p.chiet_khau}"
            role="radio" aria-checked="false" tabindex="0">
            <div class="topup-telco-logo">${logoHtml}</div>
            <div class="topup-price-value">${p.menh_gia_label}</div>
            <div class="topup-price-action">${priceLabel} ${discountBadge}</div>
          </div>`;
      }).join('');

      // Re-attach click events
      attachDenominationEvents();
    } catch (e) {
      skeletonGrid.style.display     = "none";
      denominationGrid.style.display = "grid";
      denominationGrid.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;color:#dc2626;padding:30px;">
          <i class="fa-solid fa-wifi" style="font-size:24px;margin-bottom:8px;display:block;opacity:0.4;"></i>
          Không thể tải mệnh giá. Vui lòng thử lại.
        </div>`;
    }
  }

  // ── Sự kiện chọn mệnh giá ──
  function attachDenominationEvents() {
    document.querySelectorAll(".topup-price-card").forEach((card) => {
      const selectCard = (card) => {
        document.querySelectorAll(".topup-price-card").forEach(c => {
          c.classList.remove("is-selected");
          c.setAttribute("aria-checked", "false");
        });
        card.classList.add("is-selected");
        card.setAttribute("aria-checked", "true");

        state.productId  = parseInt(card.dataset.productId);
        state.menhGia    = parseFloat(card.dataset.menhGia);
        state.giaBan     = parseFloat(card.dataset.giaBan);
        state.chietKhau  = parseFloat(card.dataset.chietKhau);

        updateConfirmPanel();
      };

      card.addEventListener("click", () => selectCard(card));
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); selectCard(card); }
      });
    });
  }

  attachDenominationEvents(); // Initial attach

  // ── Input Số điện thoại ──
  phoneInput?.addEventListener("input", (e) => {
    let val = e.target.value.replace(/[^\d+]/g, '');
    if (val.startsWith('+') && !val.startsWith('+84')) {
      val = val.replace(/[^0-9+]/g, '');
    }
    e.target.value = val;

    if (!val) { clearPhoneError(); state.phone = ""; updateConfirmPanel(); return; }

    // Chuẩn hóa +84 → 0
    let normalized = val.startsWith('+84') ? '0' + val.slice(3) : val;
    const valid = validatePhone(normalized);

    if (val.length >= 10) {
      if (!valid) {
        showPhoneError("Số điện thoại Việt Nam không hợp lệ. Ví dụ: 0987654321");
        state.phone = "";
      } else {
        clearPhoneError();
        state.phone = valid;

        // Tự nhận diện nhà mạng
        const detected = detectCarrier(valid);
        if (detected) {
          const matchedOpt = document.querySelector(`.topup-select-option[data-code="${detected}"]`)
            || document.querySelector(`.topup-select-option[data-code="${detected}_TOPUP"]`)
            || document.querySelector(`.topup-select-option[data-code^="${detected}"]`);
          if (matchedOpt && matchedOpt.dataset.code !== state.carrierCode) {
            matchedOpt.click();
            return; // loadDenominations will call updateConfirmPanel
          }
        }
      }
    } else {
      clearPhoneError();
      state.phone = "";
    }

    updateConfirmPanel();
  });

  // ── Nút Nạp tiền ──
  napTienBtn?.addEventListener("click", async () => {
    if (state.isSubmitting) return;

    const phoneVal = validatePhone(state.phone);
    if (!phoneVal) { showPhoneError("Vui lòng nhập số điện thoại hợp lệ."); return; }
    if (!state.carrierId) { showToast('warning', 'Chưa chọn nhà mạng', 'Vui lòng chọn nhà mạng.'); return; }
    if (!state.productId) { showToast('warning', 'Chưa chọn mệnh giá', 'Vui lòng chọn mệnh giá.'); return; }

    // Gọi preview để lấy số liệu mới nhất và kiểm tra số dư
    try {
      const prevRes = await fetch(API.PREVIEW, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ phone: state.phone, carrier: state.carrierCode, product_id: state.productId }),
      });
      const prevData = await prevRes.json();

      if (!prevRes.ok || !prevData.success) {
        showToast('error', 'Không thể xác nhận', prevData.message || 'Vui lòng thử lại.');
        return;
      }

      const d = prevData.data;
      state.soduHienTai = d.so_du_hien_tai;
      if (!d.du_so_du) {
        napTienBtn.disabled = true;
        document.getElementById("confirmAfterBalance").textContent = "Không đủ số dư";
        document.getElementById("confirmAfterBalance").className = "topup-confirm-row__value is-negative";
        showToast('error', 'Không đủ số dư', `Số dư: ${d.so_du_hien_tai_label}. Cần: ${d.gia_ban_label}`);
        return;
      }

      // Hiện modal xác nhận
      document.getElementById("modalPhone").textContent       = state.phone;
      document.getElementById("modalCarrier").textContent     = state.carrierName;
      document.getElementById("modalDenomination").textContent = d.menh_gia_label;
      document.getElementById("modalPrice").textContent       = d.gia_ban_label;
      document.getElementById("modalBalance").textContent     = d.so_du_sau_label;

      const mDiscountRow = document.getElementById("modalDiscountRow");
      if (d.chiet_khau > 0) {
        mDiscountRow.style.display = "flex";
        document.getElementById("modalDiscount").textContent = d.chiet_khau_label;
      } else {
        mDiscountRow.style.display = "none";
      }

      // Tạo idempotency key mới cho mỗi lần bấm (dựa vào timestamp)
      state.idempotencyKey = `tp_${Date.now()}_${state.productId}_${state.phone.slice(-4)}`;

      openModal("confirm");
    } catch (e) {
      showToast('error', 'Lỗi kết nối', 'Không thể kết nối tới máy chủ. Vui lòng thử lại.');
    }
  });

  // ── Modal ──
  function openModal(type) {
    confirmModal.classList.add("is-open");
    document.body.style.overflow = "hidden";

    if (type === "confirm") {
      document.getElementById("modalIcon").className    = "topup-modal__icon is-confirm";
      document.getElementById("modalIconI").className   = "fa-solid fa-circle-question";
      document.getElementById("modalTitle").textContent = "Xác nhận nạp tiền";
      document.getElementById("modalSubtitle").textContent = "Kiểm tra thông tin trước khi xác nhận";
      document.getElementById("modalActions").style.display = "flex";
      document.getElementById("modalConfirmBtn").disabled = false;
      document.getElementById("modalConfirmBtn").innerHTML = '<i class="fa-solid fa-bolt"></i> Xác nhận nạp';
    }
  }

  function closeModal() {
    if (state.isSubmitting) return;
    confirmModal.classList.remove("is-open");
    document.body.style.overflow = "";
  }

  document.getElementById("modalCancelBtn")?.addEventListener("click", closeModal);
  confirmModal?.addEventListener("click", (e) => { if (e.target === confirmModal) closeModal(); });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeModal(); });

  // ── Xác nhận giao dịch ──
  document.getElementById("modalConfirmBtn")?.addEventListener("click", async () => {
    if (state.isSubmitting) return;
    state.isSubmitting = true;

    const confirmBtn = document.getElementById("modalConfirmBtn");
    const cancelBtn  = document.getElementById("modalCancelBtn");
    confirmBtn.disabled = true;
    cancelBtn.disabled  = true;
    confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';

    // Cũng cập nhật nút chính
    napTienBtn.disabled = true;
    napTienBtn.classList.add("is-loading");
    napTienIcon.className = "fa-solid fa-spinner";
    napTienText.textContent = "Đang xử lý...";

    try {
      const res  = await fetch(API.ORDERS, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          phone:           state.phone,
          carrier:         state.carrierCode,
          product_id:      state.productId,
          idempotency_key: state.idempotencyKey,
        }),
      });
      const json = await res.json();

      if (!res.ok || !json.success) {
        // Lỗi
        showModalResult("failed", "Giao dịch thất bại", json.message || "Vui lòng thử lại sau.");
        state.isSubmitting = false;
        return;
      }

      const order = json.data;
      const status = (order.trang_thai || '').toUpperCase();

      if (order.is_success) {
        showModalResult("success", "Nạp tiền thành công! 🎉", `Mệnh giá ${order.menh_gia_label} đã được nạp vào ${state.phone}`);
        showToast('success', 'Nạp tiền thành công!', `${order.menh_gia_label} → ${state.phone}`);
        // Cập nhật số dư trên header
        const newBalance = state.soduHienTai - state.giaBan;
        state.soduHienTai = newBalance;
        if (headerBalance) headerBalance.textContent = formatBalance(newBalance);
        // Reset form
        resetForm();
        // Tải lịch sử giao dịch
        loadRecentHistory();
      } else if (order.is_failed) {
        showModalResult("failed", "Nạp tiền thất bại", `Giao dịch không thành công. Tiền đã được hoàn lại vào tài khoản của bạn.`);
        // Cập nhật số dư (tiền được hoàn)
        if (headerBalance) headerBalance.textContent = formatBalance(state.soduHienTai);
      } else {
        // Pending / Unknown
        showModalResult("pending", "Đang xử lý", `Giao dịch đang được xử lý. Mã đơn: ${order.ma_don_hang}. Vui lòng chờ kết quả.`);
        showToast('info', 'Đang xử lý', `Mã đơn ${order.ma_don_hang}`);
      }

    } catch (e) {
      showModalResult("failed", "Lỗi kết nối", "Không thể kết nối tới máy chủ. Vui lòng kiểm tra kết nối mạng.");
      state.isSubmitting = false;
    } finally {
      // Reset nút nạp tiền chính
      napTienBtn.classList.remove("is-loading");
      napTienIcon.className = "fa-solid fa-bolt";
      napTienText.textContent = "Nạp tiền ngay";
      cancelBtn.disabled = false;
      state.isSubmitting = false;
    }
  });

  function showModalResult(type, title, message) {
    const iconMap = {
      success: { cls: 'is-success', icon: 'fa-circle-check' },
      failed:  { cls: 'is-failed',  icon: 'fa-circle-xmark' },
      pending: { cls: 'is-pending', icon: 'fa-clock' },
    };
    const info = iconMap[type] || iconMap.pending;

    document.getElementById("modalIcon").className  = `topup-modal__icon ${info.cls}`;
    document.getElementById("modalIconI").className = `fa-solid ${info.icon}`;
    document.getElementById("modalTitle").textContent   = title;
    document.getElementById("modalSubtitle").textContent = message;
    document.getElementById("modalBody").style.display  = "none";
    document.getElementById("modalActions").innerHTML = `
      <button class="topup-modal__btn confirm" onclick="document.getElementById('confirmModal').classList.remove('is-open'); document.body.style.overflow='';" style="flex:1;">
        Đóng
      </button>`;
  }

  function resetForm() {
    phoneInput.value     = "";
    state.phone          = "";
    state.productId      = null;
    state.menhGia        = 0;
    state.giaBan         = 0;
    state.chietKhau      = 0;
    state.idempotencyKey = null;
    document.querySelectorAll(".topup-price-card").forEach(c => {
      c.classList.remove("is-selected");
      c.setAttribute("aria-checked", "false");
    });
    confirmPanel.classList.add("is-hidden");
    napTienBtn.disabled = true;
  }

  function formatBalance(n) {
    return Number(n).toLocaleString('vi-VN') + 'đ';
  }

  // ── Lịch sử gần đây ──
  async function loadRecentHistory() {
    try {
      const res  = await fetch(API.ORDERS_HISTORY + '?page=1', {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const json = await res.json();
      if (!json.success || !json.data?.length) return;

      const STATUS_MAP = {
        'SUCCESS':         { cls: 'success',  label: 'Thành công' },
        'FAILED':          { cls: 'failed',   label: 'Thất bại' },
        'REFUNDED':        { cls: 'refunded', label: 'Đã hoàn tiền' },
        'REFUND_PENDING':  { cls: 'refunded', label: 'Đang hoàn tiền' },
        'MANUAL_REVIEW':   { cls: 'pending',  label: 'Đang đối soát' },
      };

      recentHistoryBody.innerHTML = json.data.slice(0, 5).map(o => {
        const stMap = STATUS_MAP[o.trang_thai] || { cls: 'pending', label: 'Đang xử lý' };
        return `<tr>
          <td>${o.created_at}</td>
          <td>${o.tai_khoan_nhan}</td>
          <td>${o.nha_mang || '—'}</td>
          <td>${o.menh_gia_label}</td>
          <td><span class="topup-status-badge ${stMap.cls}">${stMap.label}</span></td>
        </tr>`;
      }).join('');

      recentHistorySection.style.display = "block";
    } catch (e) {
      // Im lặng nếu không tải được lịch sử
    }
  }

  // Tải lịch sử khi vào trang
  loadRecentHistory();
});
</script>

<style>
  .topup-select-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #d2dbe3;
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    z-index: 100;
    max-height: 280px;
    overflow-y: auto;
    display: none;
  }
  .topup-select-dropdown.is-open { display: block; }
  .topup-select-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    cursor: pointer;
    transition: background .15s;
    border-bottom: 1px solid #f1f5f9;
  }
  .topup-select-option:last-child { border-bottom: none; }
  .topup-select-option:hover { background: #f0fbf4; }
  .topup-select-option.is-active { background: #e6f7ec; font-weight: 700; color: #006b2c; }
  #telcoChevron { transition: transform .2s ease; }
</style>
@endpush
