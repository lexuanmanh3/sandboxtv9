@extends('admin.layout')

@section('title', 'tv9tech - Quản lý Đại lý API (B2B)')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .account-modal__dialog {
      display: flex !important;
      flex-direction: column !important;
      max-height: min(90vh, 880px) !important;
      overflow: hidden !important;
    }
    .account-tabs {
      flex-shrink: 0 !important;
    }
    .account-modal__header {
      flex-shrink: 0 !important;
    }
    .account-modal-form {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-height: 0;
      overflow: hidden;
      margin: 0;
    }
    .account-modal__body {
      flex: 1 1 auto !important;
      min-height: 0 !important;
      overflow-y: auto !important;
      -webkit-overflow-scrolling: touch;
      padding: 20px;
    }
    .account-modal__footer {
      flex-shrink: 0 !important;
      border-top: 1px solid var(--admin-slate-200);
      background: #fff;
      padding: 14px 20px;
    }
    .account-check-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
      gap: 10px 16px;
      padding: 12px 16px;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      background: var(--admin-slate-50);
      max-height: 180px;
      overflow-y: auto;
    }
    .account-form-grid label.account-check-item,
    .account-check-list label,
    label.account-check-item {
      display: inline-flex !important;
      flex-direction: row !important;
      align-items: center !important;
      justify-content: flex-start !important;
      gap: 8px !important;
      cursor: pointer !important;
      user-select: none !important;
      margin: 0 !important;
      padding: 2px 0 !important;
    }
    .account-form-grid label.account-check-item input[type="checkbox"],
    .account-check-list input[type="checkbox"],
    label.account-check-item input[type="checkbox"] {
      width: 16px !important;
      height: 16px !important;
      min-height: 16px !important;
      max-height: 16px !important;
      flex: 0 0 16px !important;
      accent-color: var(--admin-brand-600) !important;
      cursor: pointer !important;
      margin: 0 !important;
      padding: 0 !important;
      display: inline-block !important;
    }
    .account-form-grid label.account-check-item span,
    .account-check-list label span,
    label.account-check-item span {
      display: inline-block !important;
      font-size: 13px !important;
      font-weight: 500 !important;
      color: var(--admin-slate-700) !important;
      white-space: nowrap !important;
      line-height: 1.4 !important;
      margin: 0 !important;
      text-transform: none !important;
      letter-spacing: normal !important;
    }
    .account-form-grid label.account-check-item strong,
    label.account-check-item strong {
      display: inline-block !important;
      font-size: 13px !important;
      font-weight: 600 !important;
      color: var(--admin-slate-800) !important;
      line-height: 1.4 !important;
      margin: 0 !important;
    }
    .account-tabs button {
      transition: all 0.15s ease;
    }
    .account-row-actions {
      position: relative;
      display: inline-block;
    }
    .account-row-menu {
      min-width: 175px;
    }

    /* ============== UI cho cấu hình sản phẩm whitelist / blacklist ============== */
    .product-config-block {
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      background: var(--admin-slate-50);
      padding: 12px 14px;
      margin-bottom: 14px;
    }
    .product-config-block__title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-bottom: 8px;
    }
    .product-config-block__title strong {
      font-size: 13px;
      font-weight: 700;
      color: var(--admin-slate-800);
    }
    .product-config-block__title small {
      font-size: 11px;
      color: var(--admin-slate-500);
      font-weight: 500;
    }
    .product-config-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      margin-bottom: 8px;
    }
    .product-config-toolbar input[type="search"],
    .product-config-toolbar select {
      flex: 1;
      min-width: 140px;
      border: 1px solid var(--admin-slate-300);
      border-radius: 6px;
      padding: 6px 8px;
      font-size: 12px;
      background: #fff;
    }
    .product-config-toolbar button {
      border: 1px solid var(--admin-slate-300);
      background: #fff;
      border-radius: 6px;
      padding: 5px 10px;
      font-size: 12px;
      cursor: pointer;
      color: var(--admin-slate-700);
    }
    .product-config-toolbar button:hover {
      background: var(--admin-slate-100);
    }
    .product-config-list {
      border: 1px solid var(--admin-slate-200);
      border-radius: 6px;
      background: #fff;
      max-height: 260px;
      overflow-y: auto;
      padding: 8px 10px;
    }
    .product-config-group {
      margin-bottom: 10px;
    }
    .product-config-group__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 6px;
      padding: 4px 0;
      border-bottom: 1px dashed var(--admin-slate-200);
      margin-bottom: 6px;
    }
    .product-config-group__header strong {
      font-size: 12px;
      font-weight: 700;
      color: var(--admin-brand-700);
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    .product-config-group__header label {
      display: inline-flex !important;
      flex-direction: row !important;
      align-items: center !important;
      gap: 4px !important;
      font-size: 11px !important;
      color: var(--admin-slate-500) !important;
      margin: 0 !important;
      padding: 0 !important;
      cursor: pointer;
    }
    .product-config-group__items {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 4px 10px;
    }
    .product-config-empty {
      padding: 16px;
      text-align: center;
      font-size: 12px;
      color: var(--admin-slate-400);
    }
    .product-config-summary {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 700;
      background: var(--admin-brand-50);
      color: var(--admin-brand-700);
    }
    .product-config-summary.is-empty {
      background: #fee2e2;
      color: #b91c1c;
    }
  </style>
@endpush

@section('content')
<section class="account-page">
  <header class="account-header">
    <div>
      <nav class="account-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Trang chủ</a>
        <span>/</span>
        <a href="#">Quản lý đại lý</a>
        <span>/</span>
        <strong>Quản lý đại lý API</strong>
      </nav>
      <h1>Quản lý đại lý API</h1>
      <p>Quản lý danh sách đối tác B2B, hạn mức công nợ trả sau, bảng giá chiết khấu và kết nối API.</p>
    </div>

    <div class="account-actions">
      <button type="button" class="account-btn account-btn--primary" onclick="openPartnerModal('create')">
        <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>
        <span>Tạo đại lý API mới</span>
      </button>
    </div>
  </header>

  {{-- Thông báo credential mới tạo --}}
  @if (session('new_credential'))
    <div class="account-card" style="border-left: 4px solid var(--admin-brand-600); background: #f0fdf4; padding: 16px 20px;">
      <div style="display: flex; align-items: center; gap: 8px; color: var(--admin-brand-800); font-weight: 700; margin-bottom: 6px;">
        <svg viewBox="0 0 24 24" style="width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2;">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <polyline points="22 4 12 14.01 9 11.01" />
        </svg>
        <span>THÔNG TIN XÁC THỰC API ĐỐI TÁC (CHỈ HIỂN THỊ 1 LẦN)</span>
      </div>
      <p style="font-size: 13px; color: var(--admin-brand-700); margin: 0 0 12px 0;">
        Vui lòng sao lưu lại <strong>Secret Key</strong> ngay bây giờ. Vì lý do an ninh, hệ thống đã mã hóa và sẽ không hiển thị lại Secret Key này!
      </p>
      <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px; display: grid; gap: 6px; font-family: monospace; font-size: 13px;">
        <div><strong>Client ID:</strong> <span style="color: var(--admin-slate-800);">{{ session('new_credential')['client_id'] }}</span></div>
        <div><strong>Secret Key:</strong> <span style="color: #dc2626; font-weight: bold; word-break: break-all;">{{ session('new_credential')['secret_key'] }}</span></div>
      </div>
    </div>
  @endif

  @if (session('success') || session('error'))
    <section class="account-alerts" aria-live="polite">
      @if (session('success'))
        <div class="account-alert account-alert--success">{{ session('success') }}</div>
      @endif
      @if (session('error'))
        <div class="account-alert account-alert--danger">{{ session('error') }}</div>
      @endif
    </section>
  @endif

  {{-- Bộ lọc tìm kiếm --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.partners.index') }}">
      <label style="flex: 2; min-width: 260px;">
        <span>Từ khóa tìm kiếm</span>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Tìm theo mã, tên, SĐT, email...">
      </label>

      <label style="width: 200px;">
        <span>Trạng thái hoạt động</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="hoat_dong" @selected(request('trang_thai') === 'hoat_dong')>Hoạt động</option>
          <option value="tam_dung" @selected(request('trang_thai') === 'tam_dung')>Tạm dừng</option>
          <option value="khoa" @selected(request('trang_thai') === 'khoa')>Khóa</option>
        </select>
      </label>

      <button class="account-btn account-btn--primary" type="submit">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Lọc</span>
      </button>

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.partners.index') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- Bảng danh sách đại lý --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th style="width: 130px; text-align: center;">Hành động</th>
            <th>Mã đại lý</th>
            <th>Tên đại lý</th>
            <th>Liên hệ</th>
            <th style="text-align: right;">Hạn mức cấp</th>
            <th style="text-align: right;">Công nợ hiện tại</th>
            <th style="text-align: right;">Đang giữ (Hold)</th>
            <th style="text-align: right;">Khả dụng</th>
            <th style="text-align: center;">Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          @forelse($partners as $partner)
            @php $cr = $partner->credit_info; @endphp
            <tr>
              <td style="text-align: center;">
                <div class="account-row-actions">
                  <button class="account-row-action" type="button" onclick="toggleDropdown(this, 'action-{{ $partner->id }}')">
                    <span>Hành động</span>
                    <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                      <path d="M5 7.5 10 12.5 15 7.5" />
                    </svg>
                  </button>
                  <div id="action-{{ $partner->id }}" class="account-row-menu" hidden>
                    <button type="button" onclick="editPartner({{ $partner->id }})">Sửa thông tin</button>
                    <button type="button" onclick="openPricingModal({{ $partner->id }}, '{{ addslashes($partner->ten_dai_ly_api) }}')">Bảng giá đại lý</button>
                    @php
                      $unresolvedIpCount = \App\Models\B2bIpRejection::where('dai_ly_api_id', $partner->id)->where('da_xu_ly', false)->count();
                    @endphp
                    <a href="{{ route('admin.b2b.partners.ip-rejections', $partner->id) }}" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;font-size:13px;text-decoration:none;color:var(--admin-slate-700);text-align:left;">
                      <span>Xem IP bị chặn</span>
                      @if($unresolvedIpCount > 0)
                        <span style="background:#dc2626;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:700;">{{ $unresolvedIpCount }}</span>
                      @endif
                    </a>
                    <form method="POST" action="{{ route('admin.b2b.partners.rotate-key', $partner->id) }}" onsubmit="return confirm('Bạn có chắc muốn xoay API Key cho đại lý này không? Key cũ sẽ duy trì 48h.')">
                      @csrf
                      <button type="submit" style="color: #d97706;">Xoay API Key</button>
                    </form>
                    <form method="POST" action="{{ route('admin.b2b.partners.revoke-key', $partner->id) }}" onsubmit="return confirm('CẢNH BÁO: Thu hồi API Key sẽ lập tức chặn mọi request của đối tác. Tiếp tục?')">
                      @csrf
                      <button type="submit" style="color: #dc2626;">Thu hồi API Key</button>
                    </form>
                    <a href="{{ route('admin.b2b.credit-payments.index', ['dai_ly_api_id' => $partner->id]) }}" style="display: block; padding: 8px 12px; font-size: 13px; text-decoration: none; color: var(--admin-slate-700); text-align: left;">Xem sổ công nợ</a>
                  </div>
                </div>
              </td>
              <td class="account-username">{{ $partner->ma_dai_ly_api }}</td>
              <td>
                <strong style="color: var(--admin-slate-900);">{{ $partner->ten_dai_ly_api }}</strong>
                <div style="font-size: 12px; color: var(--admin-slate-400);">HĐ: {{ $partner->so_hop_dong ?: 'Chưa có' }}</div>
              </td>
              <td>
                <div>{{ $partner->so_dien_thoai }}</div>
                <div style="font-size: 12px; color: var(--admin-slate-500);">{{ $partner->email_ky_thuat }}</div>
              </td>
              <td style="text-align: right; font-weight: 700; color: var(--admin-slate-800);">
                {{ number_format($cr['han_muc_duoc_cap'] ?? 0) }} đ
              </td>
              <td style="text-align: right; font-weight: 700; color: {{ ($cr['cong_no_hien_tai'] ?? 0) > 0 ? '#dc2626' : 'var(--admin-slate-700)' }};">
                {{ number_format($cr['cong_no_hien_tai'] ?? 0) }} đ
              </td>
              <td style="text-align: right; font-weight: 600; color: #d97706;">
                {{ number_format($cr['khoan_dang_giu'] ?? 0) }} đ
              </td>
              <td style="text-align: right; font-weight: 700; color: var(--admin-brand-700);">
                {{ number_format($cr['han_muc_kha_dung'] ?? 0) }} đ
              </td>
              <td style="text-align: center;">
                <span class="account-status-pill {{ $partner->trang_thai === 'hoat_dong' ? 'is-yes' : 'is-no' }}">
                  {{ $partner->trang_thai === 'hoat_dong' ? 'Hoạt động' : ($partner->trang_thai === 'tam_dung' ? 'Tạm dừng' : 'Khóa') }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="9">Chưa có đại lý API nào được tạo trong hệ thống.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($partners->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $partners->firstItem() ?? 0 }} đến {{ $partners->lastItem() ?? 0 }} trong tổng số {{ $partners->total() }} đại lý
        </span>
        <div>
          {{ $partners->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>

{{-- MODAL TẠO / CHỈNH SỬA ĐẠI LÝ (Chuẩn 3 Tab theo thiết kế) --}}
<div class="account-modal" id="partnerModal" hidden>
  <div class="account-modal__backdrop" onclick="closePartnerModal()"></div>
  <section class="account-modal__dialog" style="max-width: 900px; width: 100%;">
    <header class="account-modal__header">
      <h2 id="modalTitle">Tạo đại lý API mới</h2>
      <button type="button" class="account-modal__close" onclick="closePartnerModal()" aria-label="Đóng">×</button>
    </header>

    {{-- Tabs header --}}
    <div class="account-tabs" style="border-bottom: 1px solid var(--admin-slate-200); margin-bottom: 0;">
      <button type="button" id="tabBtnAccount" class="is-active" onclick="switchTab('tab-account')">
        Thông tin Tài khoản
      </button>
      <button type="button" id="tabBtnApi" onclick="switchTab('tab-api')">
        Cấu hình tài khoản API
      </button>
      <button type="button" id="tabBtnContact" onclick="switchTab('tab-contact')">
        Thông tin liên hệ
      </button>
    </div>

    <form id="partnerForm" class="account-modal-form" method="POST" action="">
      @csrf
      <div id="methodContainer"></div>

      <div class="account-modal__body">
        {{-- ==================== TAB A: THÔNG TIN TÀI KHOẢN ==================== --}}
        <div id="tab-account" class="account-tab-panel is-active">
        <div class="account-form-grid" style="padding: 0;">
          <label>
            <span>Mã Đại lý API (*)</span>
            <input type="text" name="ma_dai_ly_api" id="input_ma_dai_ly_api" required placeholder="VD: PARTNER_MOMO">
          </label>
          <label>
            <span>Tên Đại lý API (*)</span>
            <input type="text" name="ten_dai_ly_api" id="input_ten_dai_ly_api" required placeholder="VD: Công ty CP Dịch vụ Di động Trực tuyến">
          </label>

          <label>
            <span>Số điện thoại đại diện (*)</span>
            <input type="text" name="so_dien_thoai" id="input_so_dien_thoai" required placeholder="0901234567">
          </label>
          <label>
            <span>Họ và tên người đại diện</span>
            <input type="text" name="ho" id="input_ho" placeholder="Nguyễn Văn A">
          </label>

          <label>
            <span>Số hợp đồng</span>
            <input type="text" name="so_hop_dong" id="input_so_hop_dong" placeholder="HD-2026/09/B2B-01">
          </label>
          <label>
            <span>Ngày ký hợp đồng</span>
            <input type="date" name="ngay_ky_hop_dong" id="input_ngay_ky_hop_dong">
          </label>

          <label>
            <span>Email kỹ thuật</span>
            <input type="email" name="email_ky_thuat" id="input_email_ky_thuat" placeholder="tech@partner.vn">
          </label>
          <label>
            <span>Email đối soát</span>
            <input type="email" name="email_doi_soat" id="input_email_doi_soat" placeholder="accounting@partner.vn">
          </label>

          <label>
            <span>Kỳ đối soát (ngày)</span>
            <input type="number" name="ky_doi_soat" id="input_ky_doi_soat" value="30" placeholder="30">
          </label>
          <label>
            <span>Trạng thái hoạt động</span>
            <select name="trang_thai" id="input_trang_thai">
              <option value="hoat_dong">Hoạt động</option>
              <option value="tam_dung">Tạm dừng</option>
              <option value="khoa">Khóa</option>
            </select>
          </label>

          <label>
            <span>Thư mục FTP</span>
            <input type="text" name="folder_ftp" id="input_folder_ftp" placeholder="/ftp/partner_momo">
          </label>
          <label>
            <span>Telegram Group ID</span>
            <input type="text" name="telegram_group_id" id="input_telegram_group_id" placeholder="-100123456789">
          </label>

          <div id="passwordFields" class="account-form-grid__full" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
            <label>
              <span>Mật khẩu tài khoản đăng nhập (*)</span>
              <input type="password" name="password" id="input_password" placeholder="Tối thiểu 6 ký tự">
            </label>
            <label>
              <span>Xác nhận mật khẩu (*)</span>
              <input type="password" name="password_confirmation" id="input_password_confirmation" placeholder="Nhập lại mật khẩu">
            </label>
          </div>
        </div>
      </div>

      {{-- ==================== TAB B: CẤU HÌNH TÀI KHOẢN API ==================== --}}
      <div id="tab-api" class="account-tab-panel">
        <div class="account-form-grid" style="padding: 0;">
          <label>
            <span>Client ID</span>
            <input type="text" name="client_id" id="input_client_id" placeholder="Hệ thống tự sinh nếu để trống">
          </label>
          <label>
            <span>Secret Key</span>
            <input type="text" disabled value="••••••••••••••••••••••••••••••••••••••••" style="background: var(--admin-slate-100); color: var(--admin-slate-500);">
            <small style="color: var(--admin-slate-500); font-size: 11px;">Secret sinh tự động an toàn và chỉ hiển thị khi cấp mới hoặc xoay key.</small>
          </label>

          {{-- Dịch vụ cho phép --}}
          <div class="account-form-grid__full">
            <span style="display: block; font-size: 13px; font-weight: 700; color: var(--admin-slate-700); margin-bottom: 6px;">Dịch vụ cho phép</span>
            <div class="account-check-list">
              @foreach($dichVus as $dv)
                <label class="account-check-item">
                  <input type="checkbox" name="dich_vu_ids[]" value="{{ $dv->id }}" class="check-dv">
                  <span>{{ $dv->ten_dich_vu }}</span>
                </label>
              @endforeach
            </div>
          </div>

          {{-- Loại sản phẩm cho phép --}}
          <div class="account-form-grid__full">
            <span style="display: block; font-size: 13px; font-weight: 700; color: var(--admin-slate-700); margin-bottom: 6px;">Loại sản phẩm cho phép</span>
            <div class="account-check-list">
              @foreach($loaiSanPhams as $lsp)
                <label class="account-check-item">
                  <input type="checkbox" name="loai_san_pham_ids[]" value="{{ $lsp->id }}" class="check-lsp">
                  <span>{{ $lsp->ten_loai_san_pham ?: $lsp->ma_loai_san_pham }}</span>
                </label>
              @endforeach
            </div>
          </div>

          {{-- ============ SẢN PHẨM ĐƯỢC PHÉP (whitelist) ============ --}}
          <div class="account-form-grid__full">
            <div class="product-config-block" data-product-config="allow">
              <div class="product-config-block__title">
                <div>
                  <strong>Sản phẩm được phép (Whitelist)</strong>
                  <br>
                  <small>Chọn các sản phẩm cụ thể đại lý được phép giao dịch. Tầng 3 kiểm soát chặt nhất.</small>
                </div>
                <span class="product-config-summary" data-summary="allow">0 sản phẩm</span>
              </div>

              <div class="product-config-toolbar">
                <input type="search" data-filter-search="allow" placeholder="🔍 Tìm theo tên hoặc mã sản phẩm..." />
                <select data-filter-loai="allow">
                  <option value="">-- Tất cả loại sản phẩm --</option>
                  @foreach($loaiSanPhams as $lsp)
                    <option value="{{ $lsp->id }}">{{ $lsp->ten_loai_san_pham ?: $lsp->ma_loai_san_pham }}</option>
                  @endforeach
                </select>
                <button type="button" data-action="select-all-visible" data-target="allow">Chọn tất cả (hiện)</button>
                <button type="button" data-action="clear-all-visible" data-target="allow">Bỏ chọn (hiện)</button>
              </div>

              <div class="product-config-list" data-list="allow">
                @php
                  $sanPhamsTheoLoai = $sanPhams->groupBy(fn($sp) => $sp->loai_san_pham_id ?? 0);
                  $tenLoaiMap = $loaiSanPhams->keyBy('id');
                @endphp
                @forelse($sanPhamsTheoLoai as $loaiId => $dsSp)
                  @php
                    $tenLoai = optional($tenLoaiMap->get($loaiId))->ten_loai_san_pham
                            ?: optional($tenLoaiMap->get($loaiId))->ma_loai_san_pham
                            ?: 'Chưa phân loại';
                  @endphp
                  <div class="product-config-group" data-group-loai="{{ $loaiId }}">
                    <div class="product-config-group__header">
                      <strong>{{ $tenLoai }}</strong>
                      <label>
                        <input type="checkbox" data-group-select="allow" data-group-loai="{{ $loaiId }}">
                        <span>chọn cả nhóm</span>
                      </label>
                    </div>
                    <div class="product-config-group__items">
                      @foreach($dsSp as $sp)
                        <label class="account-check-item" data-product-row data-product-loai="{{ $loaiId }}" data-product-name="{{ strtolower($sp->ten_san_pham . ' ' . $sp->ma_san_pham) }}">
                          <input type="checkbox" name="san_pham_ids[]" value="{{ $sp->id }}" class="check-sp check-sp-allow">
                          <span>
                            {{ $sp->ten_san_pham }}
                            <small style="color: var(--admin-slate-400); font-size: 11px;">
                              ({{ number_format($sp->menh_gia) }}đ)
                            </small>
                          </span>
                        </label>
                      @endforeach
                    </div>
                  </div>
                @empty
                  <div class="product-config-empty">Chưa có sản phẩm hoạt động nào trong hệ thống.</div>
                @endforelse
              </div>
            </div>
          </div>

          {{-- ============ SẢN PHẨM LOẠI TRỪ (blacklist) ============ --}}
          <div class="account-form-grid__full">
            <div class="product-config-block" data-product-config="exclude" style="background: #fef2f2; border-color: #fecaca;">
              <div class="product-config-block__title">
                <div>
                  <strong style="color: #b91c1c;">Sản phẩm loại trừ (Blacklist)</strong>
                  <br>
                  <small style="color: #991b1b;">Đánh dấu các sản phẩm đại lý bị cấm giao dịch, kể cả khi sản phẩm đó nằm trong whitelist ở trên.</small>
                </div>
                <span class="product-config-summary is-empty" data-summary="exclude">0 sản phẩm</span>
              </div>

              <div class="product-config-toolbar">
                <input type="search" data-filter-search="exclude" placeholder="🔍 Tìm theo tên hoặc mã sản phẩm..." />
                <select data-filter-loai="exclude">
                  <option value="">-- Tất cả loại sản phẩm --</option>
                  @foreach($loaiSanPhams as $lsp)
                    <option value="{{ $lsp->id }}">{{ $lsp->ten_loai_san_pham ?: $lsp->ma_loai_san_pham }}</option>
                  @endforeach
                </select>
                <button type="button" data-action="select-all-visible" data-target="exclude">Chọn tất cả (hiện)</button>
                <button type="button" data-action="clear-all-visible" data-target="exclude">Bỏ chọn (hiện)</button>
              </div>

              <div class="product-config-list" data-list="exclude">
                @foreach($sanPhamsTheoLoai as $loaiId => $dsSp)
                  <div class="product-config-group" data-group-loai="{{ $loaiId }}">
                    <div class="product-config-group__header">
                      <strong>{{ optional($tenLoaiMap->get($loaiId))->ten_loai_san_pham ?: (optional($tenLoaiMap->get($loaiId))->ma_loai_san_pham ?: 'Chưa phân loại') }}</strong>
                      <label>
                        <input type="checkbox" data-group-select="exclude" data-group-loai="{{ $loaiId }}">
                        <span>chọn cả nhóm</span>
                      </label>
                    </div>
                    <div class="product-config-group__items">
                      @foreach($dsSp as $sp)
                        <label class="account-check-item" data-product-row data-product-loai="{{ $loaiId }}" data-product-name="{{ strtolower($sp->ten_san_pham . ' ' . $sp->ma_san_pham) }}">
                          <input type="checkbox" name="san_pham_loai_tru[]" value="{{ $sp->id }}" class="check-sp check-sp-exclude">
                          <span>
                            {{ $sp->ten_san_pham }}
                            <small style="color: var(--admin-slate-400); font-size: 11px;">
                              ({{ number_format($sp->menh_gia) }}đ)
                            </small>
                          </span>
                        </label>
                      @endforeach
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

          <label>
            <span>Hạn mức công nợ (VNĐ) (*)</span>
            <input type="number" name="han_muc_cong_no" id="input_han_muc_cong_no" required value="0">
          </label>
          <label>
            <span>Ngưỡng cảnh báo hạn mức (VNĐ)</span>
            <input type="number" name="nguong_canh_bao_han_muc" id="input_nguong_canh_bao_han_muc" value="0">
          </label>

          <label>
            <span>Số lượng kênh song song</span>
            <input type="number" name="so_luong_kenh_toi_da" id="input_so_luong_kenh_toi_da" value="5">
          </label>
          <label>
            <span>Rate limit (Request/Phút)</span>
            <input type="number" name="rate_limit_per_minute" id="input_rate_limit_per_minute" value="60">
          </label>

          <label class="account-form-grid__full">
            <span>Danh sách IP kết nối (Cách nhau dấu ; hoặc xuống dòng)</span>
            <textarea name="danh_sach_ip_ket_noi" id="input_danh_sach_ip_ket_noi" rows="2" placeholder="VD: 127.0.0.1; 14.162.1.20"></textarea>
          </label>

          <label class="account-form-grid__full">
            <span>URL Webhook nhận kết quả giao dịch</span>
            <input type="url" name="webhook_url" id="input_webhook_url" placeholder="https://partner.com/api/webhook">
          </label>

          <div class="account-form-grid__full">
            <label class="account-check-item">
              <input type="checkbox" name="cho_phep_nhan_don" id="input_cho_phep_nhan_don" value="1" checked>
              <strong>Cho phép đại lý gửi đơn nạp tiền (Bật/Tắt tiếp nhận API)</strong>
            </label>
          </div>
        </div>
      </div>

      {{-- ==================== TAB C: THÔNG TIN LIÊN HỆ ==================== --}}
      <div id="tab-contact" class="account-tab-panel">
        <div class="account-form-grid" style="padding: 0;">
          <label>
            <span>Tỉnh / Thành phố</span>
            <input type="text" name="tinh_thanh" id="input_tinh_thanh" placeholder="Hà Nội">
          </label>
          <label>
            <span>Quận / Huyện</span>
            <input type="text" name="quan_huyen" id="input_quan_huyen" placeholder="Cầu Giấy">
          </label>
          <label>
            <span>Phường / Xã</span>
            <input type="text" name="phuong_xa" id="input_phuong_xa" placeholder="Dịch Vọng">
          </label>
          <label>
            <span>Địa chỉ trụ sở chi tiết</span>
            <input type="text" name="dia_chi_chi_tiet" id="input_dia_chi_chi_tiet" placeholder="Số 10, Tòa nhà Công Nghệ">
          </label>

          {{-- Danh bạ liên hệ 4 bộ phận --}}
          <div class="account-form-grid__full" style="margin-top: 8px;">
            <div style="font-size: 14px; font-weight: 700; color: var(--admin-slate-800); border-bottom: 1px solid var(--admin-slate-200); padding-bottom: 6px; margin-bottom: 12px;">
              Danh bạ liên hệ các phòng ban đối tác
            </div>

            {{-- Giám đốc --}}
            <div style="margin-bottom: 12px;">
              <span style="font-weight: 700; font-size: 13px; color: var(--admin-slate-700);">1. Giám đốc / Đại diện pháp luật</span>
              <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 4px;">
                <input type="text" name="lien_he[giam_doc][ho_ten]" id="input_gd_ten" placeholder="Họ và tên">
                <input type="text" name="lien_he[giam_doc][so_dien_thoai]" id="input_gd_sdt" placeholder="Số điện thoại">
                <input type="email" name="lien_he[giam_doc][email]" id="input_gd_email" placeholder="Email">
              </div>
            </div>

            {{-- Kỹ thuật --}}
            <div style="margin-bottom: 12px;">
              <span style="font-weight: 700; font-size: 13px; color: var(--admin-slate-700);">2. Trưởng bộ phận Kỹ thuật / API</span>
              <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 4px;">
                <input type="text" name="lien_he[ky_thuat][ho_ten]" id="input_kt_ten" placeholder="Họ và tên">
                <input type="text" name="lien_he[ky_thuat][so_dien_thoai]" id="input_kt_sdt" placeholder="Số điện thoại">
                <input type="email" name="lien_he[ky_thuat][email]" id="input_kt_email" placeholder="Email">
              </div>
            </div>

            {{-- Đối soát --}}
            <div style="margin-bottom: 12px;">
              <span style="font-weight: 700; font-size: 13px; color: var(--admin-slate-700);">3. Chuyên viên Đối soát công nợ</span>
              <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 4px;">
                <input type="text" name="lien_he[doi_soat][ho_ten]" id="input_ds_ten" placeholder="Họ và tên">
                <input type="text" name="lien_he[doi_soat][so_dien_thoai]" id="input_ds_sdt" placeholder="Số điện thoại">
                <input type="email" name="lien_he[doi_soat][email]" id="input_ds_email" placeholder="Email">
              </div>
            </div>

            {{-- Kế toán --}}
            <div>
              <span style="font-weight: 700; font-size: 13px; color: var(--admin-slate-700);">4. Kế toán trưởng / Thủ quỹ</span>
              <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 4px;">
                <input type="text" name="lien_he[ke_toan][ho_ten]" id="input_ketoan_ten" placeholder="Họ và tên">
                <input type="text" name="lien_he[ke_toan][so_dien_thoai]" id="input_ketoan_sdt" placeholder="Số điện thoại">
                <input type="email" name="lien_he[ke_toan][email]" id="input_ketoan_email" placeholder="Email">
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>

      {{-- Footer modal --}}
      <footer class="account-modal__footer">
        <button type="button" class="account-btn account-btn--muted" onclick="closePartnerModal()">Hủy bỏ</button>
        <button type="submit" class="account-btn account-btn--primary">Lưu thông tin đại lý</button>
      </footer>
    </form>
  </section>
</div>

{{-- MODAL BẢNG GIÁ ĐẠI LÝ --}}
<div class="account-modal" id="pricingModal" hidden>
  <div class="account-modal__backdrop" onclick="closePricingModal()"></div>
  <section class="account-modal__dialog" style="max-width: 850px; width: 100%;">
    <header class="account-modal__header">
      <h2 id="pricingTitle">Bảng giá riêng cho Đại lý</h2>
      <button type="button" class="account-modal__close" onclick="closePricingModal()" aria-label="Đóng">×</button>
    </header>

    <form id="pricingForm" class="account-modal-form" method="POST" action="">
      @csrf
      <div class="account-modal__body">
        <p style="font-size: 13px; color: var(--admin-slate-500); margin: 0 0 12px 0;">
          Cấu hình tỷ lệ chiết khấu (%) hoặc đơn giá bán cố định cho đại lý này. Nếu không tích chọn, hệ thống sẽ áp dụng theo bảng giá mặc định của từng sản phẩm.
        </p>

        <div style="border: 1px solid var(--admin-slate-200); border-radius: 6px;">
          <table class="account-table">
            <thead>
              <tr>
                <th style="width: 50px; text-align: center;">Áp dụng</th>
                <th>Sản phẩm</th>
                <th style="text-align: right;">Mệnh giá</th>
                <th style="width: 150px;">Kiểu chiết khấu</th>
                <th style="width: 150px;">Giá trị (% / VNĐ)</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sanPhams as $sp)
                <tr>
                  <td style="text-align: center;">
                    <input type="checkbox" name="pricing[{{ $sp->id }}][enabled]" value="1" style="width: 16px; height: 16px; accent-color: var(--admin-brand-600);">
                  </td>
                  <td>
                    <strong style="color: var(--admin-slate-800);">{{ $sp->ten_san_pham }}</strong>
                    <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $sp->ma_san_pham }}</div>
                  </td>
                  <td style="text-align: right; font-weight: 600;">
                    {{ number_format($sp->menh_gia) }} đ
                  </td>
                  <td>
                    <select name="pricing[{{ $sp->id }}][type]" style="width: 100%; border: 1px solid var(--admin-slate-300); border-radius: 4px; padding: 4px 6px; font-size: 12px;">
                      <option value="PERCENT">Chiết khấu (%)</option>
                      <option value="FIXED_PRICE">Đơn giá bán (VNĐ)</option>
                    </select>
                  </td>
                  <td>
                    <input type="number" step="0.01" name="pricing[{{ $sp->id }}][value]" placeholder="VD: 3.5 hoặc 96500" style="width: 100%; border: 1px solid var(--admin-slate-300); border-radius: 4px; padding: 4px 6px; font-size: 12px;">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <footer class="account-modal__footer">
        <button type="button" class="account-btn account-btn--muted" onclick="closePricingModal()">Đóng</button>
        <button type="submit" class="account-btn account-btn--primary">Lưu bảng giá đại lý</button>
      </footer>
    </form>
  </section>
</div>

@push('scripts')
<script>
const partnersData = @json($partners->keyBy('id'));

function toggleDropdown(button, menuId) {
  event.stopPropagation();
  const wrapper = button.closest('.account-row-actions');
  const menu = document.getElementById(menuId);
  if (!menu) return;

  const willOpen = menu.hidden;
  closeAllDropdowns();

  if (willOpen) {
    wrapper.classList.add('is-open');
    menu.hidden = false;
    const rect = button.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.left = rect.left + 'px';
    menu.style.top = (rect.bottom + 4) + 'px';
  }
}

function closeAllDropdowns() {
  document.querySelectorAll('.account-row-actions.is-open').forEach(w => w.classList.remove('is-open'));
  document.querySelectorAll('.account-row-menu').forEach(m => m.hidden = true);
}

document.addEventListener('click', closeAllDropdowns);
window.addEventListener('scroll', closeAllDropdowns, { passive: true, capture: true });
window.addEventListener('resize', closeAllDropdowns, { passive: true });

function switchTab(tabId) {
  document.querySelectorAll('.account-tab-panel').forEach(p => p.classList.remove('is-active'));
  document.querySelectorAll('.account-tabs button').forEach(b => b.classList.remove('is-active'));

  const target = document.getElementById(tabId);
  if (target) target.classList.add('is-active');

  if (tabId === 'tab-account') document.getElementById('tabBtnAccount').classList.add('is-active');
  if (tabId === 'tab-api') document.getElementById('tabBtnApi').classList.add('is-active');
  if (tabId === 'tab-contact') document.getElementById('tabBtnContact').classList.add('is-active');

  const modalBody = document.querySelector('#partnerModal .account-modal__body');
  if (modalBody) modalBody.scrollTop = 0;
}

function openPartnerModal(mode) {
  document.getElementById('partnerModal').hidden = false;
  document.getElementById('partnerForm').reset();
  document.querySelectorAll('.check-dv, .check-lsp, .check-sp').forEach(c => c.checked = false);
  document.querySelectorAll('[data-filter-search], [data-filter-loai]').forEach(el => el.value = '');
  applyProductFilters();
  updateProductSummary();
  switchTab('tab-account');

  if (mode === 'create') {
    document.getElementById('modalTitle').innerText = 'Tạo đại lý API mới';
    document.getElementById('partnerForm').action = "{{ route('admin.b2b.partners.store') }}";
    document.getElementById('methodContainer').innerHTML = '';
    document.getElementById('input_ma_dai_ly_api').readOnly = false;
    document.getElementById('passwordFields').style.display = 'grid';
    document.getElementById('input_password').required = true;
    document.getElementById('input_password_confirmation').required = true;
  }
}

function closePartnerModal() {
  document.getElementById('partnerModal').hidden = true;
}

function editPartner(partnerOrId) {
  const partner = (typeof partnerOrId === 'object' && partnerOrId !== null) ? partnerOrId : (partnersData[partnerOrId] || {});
  openPartnerModal('edit');

  document.getElementById('modalTitle').innerText = 'Chỉnh sửa Đại lý API: ' + (partner.ten_dai_ly_api || '');
  document.getElementById('partnerForm').action = "/admin/b2b/partners/" + partner.id;
  document.getElementById('methodContainer').innerHTML = '<input type="hidden" name="_method" value="PUT">';
  document.getElementById('input_ma_dai_ly_api').readOnly = true;
  document.getElementById('passwordFields').style.display = 'none';
  document.getElementById('input_password').required = false;
  document.getElementById('input_password_confirmation').required = false;

  document.getElementById('input_ma_dai_ly_api').value = partner.ma_dai_ly_api || '';
  document.getElementById('input_ten_dai_ly_api').value = partner.ten_dai_ly_api || '';
  document.getElementById('input_so_dien_thoai').value = partner.so_dien_thoai || '';
  document.getElementById('input_ho').value = partner.ho || '';
  document.getElementById('input_so_hop_dong').value = partner.so_hop_dong || '';
  document.getElementById('input_ngay_ky_hop_dong').value = partner.ngay_ky_hop_dong ? partner.ngay_ky_hop_dong.substring(0, 10) : '';
  document.getElementById('input_email_ky_thuat').value = partner.email_ky_thuat || '';
  document.getElementById('input_email_doi_soat').value = partner.email_doi_soat || '';
  document.getElementById('input_ky_doi_soat').value = partner.ky_doi_soat || 30;
  document.getElementById('input_folder_ftp').value = partner.folder_ftp || '';
  document.getElementById('input_telegram_group_id').value = partner.telegram_group_id || '';
  document.getElementById('input_trang_thai').value = partner.trang_thai || 'hoat_dong';

  // Address
  document.getElementById('input_tinh_thanh').value = partner.tinh_thanh || '';
  document.getElementById('input_quan_huyen').value = partner.quan_huyen || '';
  document.getElementById('input_phuong_xa').value = partner.phuong_xa || '';
  document.getElementById('input_dia_chi_chi_tiet').value = partner.dia_chi_chi_tiet || '';

  // Contacts
  const contacts = partner.thong_tin_lien_he || {};
  if (contacts.giam_doc) {
    document.getElementById('input_gd_ten').value = contacts.giam_doc.ho_ten || '';
    document.getElementById('input_gd_sdt').value = contacts.giam_doc.so_dien_thoai || '';
    document.getElementById('input_gd_email').value = contacts.giam_doc.email || '';
  }
  if (contacts.ky_thuat) {
    document.getElementById('input_kt_ten').value = contacts.ky_thuat.ho_ten || '';
    document.getElementById('input_kt_sdt').value = contacts.ky_thuat.so_dien_thoai || '';
    document.getElementById('input_kt_email').value = contacts.ky_thuat.email || '';
  }
  if (contacts.doi_soat) {
    document.getElementById('input_ds_ten').value = contacts.doi_soat.ho_ten || '';
    document.getElementById('input_ds_sdt').value = contacts.doi_soat.so_dien_thoai || '';
    document.getElementById('input_ds_email').value = contacts.doi_soat.email || '';
  }
  if (contacts.ke_toan) {
    document.getElementById('input_ketoan_ten').value = contacts.ke_toan.ho_ten || '';
    document.getElementById('input_ketoan_sdt').value = contacts.ke_toan.so_dien_thoai || '';
    document.getElementById('input_ketoan_email').value = contacts.ke_toan.email || '';
  }

  // API Config
  if (partner.cau_hinh_api) {
    document.getElementById('input_client_id').value = partner.cau_hinh_api.client_id || '';
    document.getElementById('input_client_id').readOnly = true;
    document.getElementById('input_han_muc_cong_no').value = partner.cau_hinh_api.han_muc_cong_no || 0;
    document.getElementById('input_nguong_canh_bao_han_muc').value = partner.cau_hinh_api.nguong_canh_bao_han_muc || 0;
    document.getElementById('input_so_luong_kenh_toi_da').value = partner.cau_hinh_api.so_luong_kenh_toi_da || 5;
    document.getElementById('input_rate_limit_per_minute').value = partner.cau_hinh_api.rate_limit_per_minute || 60;
    document.getElementById('input_danh_sach_ip_ket_noi').value = partner.cau_hinh_api.danh_sach_ip_ket_noi || '';
    document.getElementById('input_webhook_url').value = partner.cau_hinh_api.webhook_url || '';
    document.getElementById('input_cho_phep_nhan_don').checked = !!partner.cau_hinh_api.cho_phep_nhan_don;
  }

  // Permissions
  const dvIds = (partner.dich_vu || []).map(d => d.id);
  document.querySelectorAll('.check-dv').forEach(c => c.checked = dvIds.includes(parseInt(c.value)));

  const lspIds = (partner.loai_san_pham || []).map(l => l.id);
  document.querySelectorAll('.check-lsp').forEach(c => c.checked = lspIds.includes(parseInt(c.value)));

  // Whitelist sản phẩm (tầng 3)
  const spIds = (partner.san_pham || []).map(s => s.id);
  document.querySelectorAll('.check-sp-allow').forEach(c => c.checked = spIds.includes(parseInt(c.value)));

  // Blacklist sản phẩm loại trừ (lưu trong cau_hinh_api.san_pham_loai_tru - chấp nhận cả ID dạng string)
  let excludedIds = [];
  if (partner.cau_hinh_api && Array.isArray(partner.cau_hinh_api.san_pham_loai_tru)) {
    excludedIds = partner.cau_hinh_api.san_pham_loai_tru
      .map(v => parseInt(v))
      .filter(v => !isNaN(v));
  }
  document.querySelectorAll('.check-sp-exclude').forEach(c => {
    c.checked = excludedIds.includes(parseInt(c.value));
  });

  applyProductFilters();
  updateProductSummary();
}

function openPricingModal(partnerId, partnerName) {
  document.getElementById('pricingModal').hidden = false;
  document.getElementById('pricingTitle').innerText = 'Bảng giá riêng cho Đại lý: ' + partnerName;
  document.getElementById('pricingForm').action = '/admin/b2b/partners/' + partnerId + '/pricing';
}

function closePricingModal() {
  document.getElementById('pricingModal').hidden = true;
}

// ========================================================================
// LOGIC CẤU HÌNH SẢN PHẨM (WHITELIST / BLACKLIST)
// ========================================================================

/**
 * Áp dụng filter theo từ khóa tìm kiếm + loại sản phẩm cho cả 2 block (allow / exclude).
 * Ẩn các dòng sản phẩm không khớp, ẩn cả nhóm nếu không còn dòng nào hiển thị.
 */
function applyProductFilters() {
  ['allow', 'exclude'].forEach(function (mode) {
    const searchInput = document.querySelector('[data-filter-search="' + mode + '"]');
    const loaiSelect  = document.querySelector('[data-filter-loai="' + mode + '"]');
    const listEl      = document.querySelector('[data-list="' + mode + '"]');
    if (!listEl) return;

    const keyword = (searchInput?.value || '').trim().toLowerCase();
    const loaiId  = loaiSelect?.value || '';

    listEl.querySelectorAll('[data-product-row]').forEach(function (row) {
      const rowLoai = row.getAttribute('data-product-loai') || '';
      const rowName = row.getAttribute('data-product-name') || '';
      const matchLoai  = !loaiId || rowLoai === loaiId;
      const matchName  = !keyword || rowName.indexOf(keyword) !== -1;
      row.style.display = (matchLoai && matchName) ? '' : 'none';
    });

    // Ẩn cả nhóm nếu không còn sản phẩm nào hiển thị
    listEl.querySelectorAll('[data-group-loai]').forEach(function (group) {
      const visibleRows = group.querySelectorAll('[data-product-row]:not([style*="display: none"])').length;
      group.style.display = visibleRows > 0 ? '' : 'none';
    });
  });
}

/**
 * Cập nhật badge đếm số sản phẩm đã chọn cho mỗi block (allow / exclude).
 */
function updateProductSummary() {
  ['allow', 'exclude'].forEach(function (mode) {
    const summaryEl = document.querySelector('[data-summary="' + mode + '"]');
    if (!summaryEl) return;
    const checked = document.querySelectorAll('.check-sp-' + mode + ':checked').length;
    summaryEl.textContent = checked + ' sản phẩm';
    summaryEl.classList.toggle('is-empty', checked === 0);
  });
}

/**
 * Bắt sự kiện filter / search / select-all / clear-all cho cả 2 block.
 */
document.addEventListener('input', function (e) {
  if (e.target.matches('[data-filter-search], [data-filter-loai]')) {
    applyProductFilters();
  }
  if (e.target.matches('.check-sp')) {
    updateProductSummary();
  }
});

document.addEventListener('change', function (e) {
  // Chọn cả nhóm: tick tất cả checkbox sản phẩm thuộc loại đó (chỉ trong nhóm đang hiển thị)
  if (e.target.matches('[data-group-select]')) {
    const mode   = e.target.getAttribute('data-group-select');
    const loaiId = e.target.getAttribute('data-group-loai');
    const checked = e.target.checked;
    document.querySelectorAll('[data-list="' + mode + '"] [data-product-row][data-product-loai="' + loaiId + '"]').forEach(function (row) {
      if (row.style.display !== 'none') {
        const cb = row.querySelector('.check-sp-' + mode);
        if (cb) cb.checked = checked;
      }
    });
    updateProductSummary();
  }
});

document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  const action = btn.getAttribute('data-action');
  const target = btn.getAttribute('data-target');

  if (action === 'select-all-visible' || action === 'clear-all-visible') {
    const wantChecked = action === 'select-all-visible';
    document.querySelectorAll('[data-list="' + target + '"] [data-product-row]').forEach(function (row) {
      if (row.style.display !== 'none') {
        const cb = row.querySelector('.check-sp-' + target);
        if (cb) cb.checked = wantChecked;
      }
    });
    updateProductSummary();
  }
});
</script>
@endpush
@endsection
