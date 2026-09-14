@extends('admin.layout')

@section('title', 'tv9tech - Cấu hình Thông báo Telegram')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .tele-card {
      background: #ffffff;
      border: 1px solid var(--admin-slate-200);
      border-radius: 12px;
      padding: 24px 28px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
    }
    .tele-card__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 16px;
      margin-bottom: 22px;
      border-bottom: 1px solid var(--admin-slate-100);
      flex-wrap: wrap;
      gap: 12px;
    }
    .tele-card__title-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .tele-card__title-row {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .tele-card__title-row h2 {
      margin: 0;
      font-size: 17px;
      font-weight: 700;
      color: var(--admin-slate-900);
      line-height: 24px;
    }
    .tele-card__desc {
      margin: 0;
      font-size: 13.5px;
      color: var(--admin-slate-500);
      line-height: 20px;
    }
    .tele-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 9px;
      border-radius: 6px;
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: 0.3px;
    }
    .tele-badge--blue { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .tele-badge--green { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .tele-badge--amber { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .tele-badge--purple { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }

    .tele-form-group {
      margin-bottom: 20px;
    }
    .tele-form-group:last-child {
      margin-bottom: 0;
    }
    .tele-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 13.5px;
      font-weight: 600;
      color: var(--admin-slate-700);
      margin-bottom: 8px;
    }
    .tele-label span.req {
      color: #ef4444;
      margin-left: 2px;
    }
    .tele-input {
      width: 100%;
      height: 42px;
      padding: 0 14px;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      color: var(--admin-slate-900);
      background: #f8fafc;
      transition: all 0.2s ease;
    }
    .tele-input:focus {
      background: #ffffff;
      border-color: #0284c7;
      outline: none;
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .tele-select {
      width: 100%;
      height: 38px;
      padding: 0 12px;
      border: 1px solid #cbd5e1;
      border-radius: 6px;
      font-size: 13.5px;
      color: var(--admin-slate-800);
      background: #ffffff;
      transition: all 0.2s ease;
    }
    .tele-select:focus {
      border-color: #0284c7;
      outline: none;
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }
    .tele-btn-test {
      height: 36px;
      padding: 0 14px;
      font-size: 12.5px;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid #cbd5e1;
      background: #ffffff;
      color: var(--admin-slate-700);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      white-space: nowrap;
      transition: all 0.15s ease;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }
    .tele-btn-test:hover:not(:disabled) {
      background: #f1f5f9;
      border-color: #94a3b8;
      color: var(--admin-slate-900);
    }
    .tele-btn-test:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .tele-hint {
      display: block;
      font-size: 12px;
      color: var(--admin-slate-500);
      margin-top: 6px;
      line-height: 16px;
    }

    /* Channel Table */
    .tele-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      overflow: hidden;
    }
    .tele-table th {
      background: #f8fafc;
      padding: 12px 16px;
      font-size: 13px;
      font-weight: 700;
      color: var(--admin-slate-700);
      text-align: left;
      border-bottom: 1px solid var(--admin-slate-200);
    }
    .tele-table td {
      padding: 12px 16px;
      font-size: 13.5px;
      color: var(--admin-slate-800);
      border-bottom: 1px solid var(--admin-slate-100);
      vertical-align: middle;
    }
    .tele-table tr:last-child td {
      border-bottom: none;
    }
    .tele-table tr:hover td {
      background: #f8fafc;
    }

    /* Event Notification Cards */
    .tele-event-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
      gap: 16px;
    }
    .tele-event-card {
      border: 1px solid var(--admin-slate-200);
      border-radius: 10px;
      padding: 18px 20px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      gap: 14px;
      transition: all 0.15s ease;
    }
    .tele-event-card:hover {
      background: #ffffff;
      border-color: #cbd5e1;
      box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    }
    .tele-event-card__top {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    .tele-toggle-switch {
      position: relative;
      display: inline-block;
      width: 44px;
      height: 24px;
      flex-shrink: 0;
      margin-top: 2px;
    }
    .tele-toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
      position: absolute;
    }
    .tele-slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: #cbd5e1;
      transition: 0.2s ease;
      border-radius: 24px;
    }
    .tele-slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: #ffffff;
      transition: 0.2s ease;
      border-radius: 50%;
      box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    input:checked + .tele-slider {
      background-color: #0284c7;
    }
    input:checked + .tele-slider:before {
      transform: translateX(20px);
    }
    .tele-event-info {
      flex: 1;
    }
    .tele-event-info strong {
      display: block;
      font-size: 14px;
      font-weight: 700;
      color: var(--admin-slate-900);
      margin-bottom: 2px;
    }
    .tele-event-info span {
      display: block;
      font-size: 12.5px;
      color: var(--admin-slate-500);
      line-height: 18px;
    }
    .tele-event-channel-picker {
      padding-top: 10px;
      border-top: 1px dashed var(--admin-slate-200);
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .tele-event-channel-picker label {
      font-size: 12px;
      font-weight: 600;
      color: var(--admin-slate-600);
    }

    /* Help Callout */
    .tele-help-callout {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 10px;
      padding: 20px 24px;
      color: #166534;
      font-size: 13.5px;
      line-height: 1.6;
    }
    .tele-help-callout h3 {
      margin: 0 0 12px;
      font-size: 15px;
      font-weight: 700;
      color: #14532d;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .tele-help-callout ol {
      margin: 0;
      padding-left: 20px;
    }
    .tele-help-callout li {
      margin-bottom: 8px;
    }
    .tele-help-callout li:last-child {
      margin-bottom: 0;
    }
    .tele-help-callout code {
      background: rgba(22, 101, 52, 0.12);
      color: #14532d;
      padding: 2px 7px;
      border-radius: 4px;
      font-weight: 700;
      font-size: 12.5px;
    }

    /* Toast Notification */
    .tele-toast {
      position: fixed;
      bottom: 28px;
      right: 28px;
      padding: 14px 22px;
      border-radius: 10px;
      background: #0f172a;
      color: #ffffff;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.25);
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 99999;
      opacity: 0;
      transform: translateY(16px);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
      max-width: 460px;
    }
    .tele-toast.is-show {
      opacity: 1;
      transform: translateY(0);
    }
    .tele-toast--success {
      background: #064e3b;
      border-left: 4px solid #10b981;
    }
    .tele-toast--error {
      background: #7f1d1d;
      border-left: 4px solid #ef4444;
    }
  </style>
@endpush

@section('content')
  @php
    $currentUser = auth()->user();
    $canEdit = $currentUser && ($currentUser->isAdmin() || $currentUser->coQuyen('telegram_setting.update'));
    $channelsList = $channels ?? [];
    $eventMap = $eventChannels ?? [];
  @endphp

  <section class="account-page">
    <header class="account-header">
      <div>
        <nav class="account-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ route('admin.dashboard') }}">Trang chủ</a>
          <span>/</span>
          <a href="#">Quản trị</a>
          <span>/</span>
          <strong>Thông báo Telegram</strong>
        </nav>
        <h1>Cấu hình Thông báo Telegram</h1>
        <p>Quản lý đa kênh nhận tin Telegram, phân luồng sự kiện cảnh báo và phân công người phụ trách.</p>
      </div>

      <div class="account-actions">
        <a class="account-btn account-btn--secondary" href="{{ route('admin.dashboard') }}">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M19 12H5" />
            <path d="m12 19-7-7 7-7" />
          </svg>
          <span>Quay lại</span>
        </a>
      </div>
    </header>

    @if (session('success') || $errors->any())
      <section class="account-alerts" aria-live="polite">
        @if (session('success'))
          <div class="account-alert account-alert--success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
          <div class="account-alert account-alert--danger">{{ $errors->first() }}</div>
        @endif
      </section>
    @endif

    <form method="POST" action="{{ route('admin.telegram-settings.update') }}" id="telegramSettingsForm">
      @csrf
      @method('PUT')

      {{-- Hidden input containing dynamic JSON channels list --}}
      <input type="hidden" name="danh_sach_kenh" id="danhSachKenhJson" value="{{ json_encode($channelsList) }}">

      {{-- CARD 1: BOT TOKEN --}}
      <section class="tele-card">
        <div class="tele-card__header">
          <div class="tele-card__title-group">
            <div class="tele-card__title-row">
              <h2>1. Kết nối Telegram Bot Token</h2>
              <span class="tele-badge tele-badge--blue">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Bảo mật
              </span>
            </div>
            <p class="tele-card__desc">Nhập mã Token chung của Bot Telegram hệ thống dùng để gửi tất cả các thông báo.</p>
          </div>
        </div>

        <div class="tele-form-group">
          <label class="tele-label" for="bot_token">
            <span>Telegram Bot Token <span class="req">*</span></span>
          </label>
          <input
            class="tele-input"
            type="password"
            id="bot_token"
            name="bot_token"
            value="{{ old('bot_token', $config->bot_token) }}"
            placeholder="Ví dụ: 1234567890:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw"
            autocomplete="off"
            {{ $canEdit ? '' : 'disabled' }}
          >
          <span class="tele-hint">Lấy từ <code>@BotFather</code> trên Telegram khi tạo bot mới.</span>
        </div>
      </section>

      {{-- CARD 2: QUẢN LÝ DANH SÁCH NHÓM & KÊNH TELEGRAM --}}
      <section class="tele-card">
        <div class="tele-card__header">
          <div class="tele-card__title-group">
            <div class="tele-card__title-row">
              <h2>2. Quản lý Danh sách Nhóm &amp; Kênh Telegram</h2>
              <span class="tele-badge tele-badge--green">Đa Kênh</span>
            </div>
            <p class="tele-card__desc">Thêm không giới hạn các nhóm/kênh nhận tin để phân chia cho từng bộ phận, ca trực hoặc nhân viên.</p>
          </div>

          @if ($canEdit)
            <button class="account-btn account-btn--secondary" type="button" onclick="openAddChannelModal()" style="font-size: 13px; height: 38px;">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
              <span>Thêm nhóm nhận tin</span>
            </button>
          @endif
        </div>

        <table class="tele-table" id="channelsTable">
          <thead>
            <tr>
              <th style="width: 28%;">Tên nhóm / Kênh</th>
              <th style="width: 25%;">Chat ID</th>
              <th style="width: 25%;">Ghi chú / Bộ phận</th>
              <th style="width: 22%; text-align: right;">Hành động</th>
            </tr>
          </thead>
          <tbody id="channelsTableBody">
            {{-- Rendered dynamically by Javascript --}}
          </tbody>
        </table>
      </section>

      {{-- CARD 3: BẬT / TẮT & PHÂN LUỒNG CẢNH BÁO CHO TỪNG LOẠI SỰ KIỆN --}}
      <section class="tele-card">
        <div class="tele-card__header">
          <div class="tele-card__title-group">
            <div class="tele-card__title-row">
              <h2>3. Phân luồng Cảnh báo theo Từng Loại Sự Kiện</h2>
              <span class="tele-badge tele-badge--purple">Định tuyến</span>
            </div>
            <p class="tele-card__desc">Bật/tắt và chọn chính xác nhóm Telegram sẽ nhận thông báo khi sự kiện đó xảy ra.</p>
          </div>
        </div>

        <div class="tele-event-grid">
          {{-- 1. Đóng cầu dao (Circuit Breaker) --}}
          <div class="tele-event-card">
            <div class="tele-event-card__top">
              <label class="tele-toggle-switch">
                <input type="checkbox" name="bat_canh_bao_circuit_breaker" value="1" {{ old('bat_canh_bao_circuit_breaker', $config->bat_canh_bao_circuit_breaker) ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                <span class="tele-slider"></span>
              </label>
              <div class="tele-event-info">
                <strong>🚨 Tự ngắt kết nối (Circuit Breaker)</strong>
                <span>Cảnh báo khi hệ thống tạm đóng cổng NCC do lỗi liên tiếp chạm ngưỡng.</span>
              </div>
            </div>
            <div class="tele-event-channel-picker">
              <label for="event_circuit_breaker">Nhóm nhận cảnh báo cầu dao:</label>
              <select class="tele-select event-channel-select" id="event_circuit_breaker" name="cau_hinh_kenh_su_kien[circuit_breaker]" {{ $canEdit ? '' : 'disabled' }} data-selected="{{ $eventMap['circuit_breaker'] ?? ($config->chat_id_alert ?? '') }}">
                {{-- Populated by JS --}}
              </select>
            </div>
          </div>

          {{-- 3. Xử lý chậm --}}
          <div class="tele-event-card">
            <div class="tele-event-card__top">
              <label class="tele-toggle-switch">
                <input type="checkbox" name="bat_canh_bao_xu_ly_cham" value="1" {{ old('bat_canh_bao_xu_ly_cham', $config->bat_canh_bao_xu_ly_cham) ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                <span class="tele-slider"></span>
              </label>
              <div class="tele-event-info">
                <strong>🐢 Giao dịch xử lý chậm</strong>
                <span>Cảnh báo khi thời gian kết nối NCC vượt quá số giây cài đặt (ví dụ > 3s).</span>
              </div>
            </div>
            <div class="tele-event-channel-picker">
              <label for="event_slow_transaction">Nhóm nhận cảnh báo xử lý chậm:</label>
              <select class="tele-select event-channel-select" id="event_slow_transaction" name="cau_hinh_kenh_su_kien[slow_transaction]" {{ $canEdit ? '' : 'disabled' }} data-selected="{{ $eventMap['slow_transaction'] ?? ($config->chat_id_alert ?? '') }}">
                {{-- Populated by JS --}}
              </select>
            </div>
          </div>

          {{-- 4. Số dư NCC thấp --}}
          <div class="tele-event-card">
            <div class="tele-event-card__top">
              <label class="tele-toggle-switch">
                <input type="checkbox" name="bat_canh_bao_so_du_thap" value="1" {{ old('bat_canh_bao_so_du_thap', $config->bat_canh_bao_so_du_thap) ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                <span class="tele-slider"></span>
              </label>
              <div class="tele-event-info">
                <strong>💰 Số dư nhà cung cấp thấp</strong>
                <span>Cảnh báo khi số dư ví NCC xuống dưới ngưỡng tối thiểu để kịp nạp thêm.</span>
              </div>
            </div>
            <div class="tele-event-channel-picker">
              <label for="event_low_balance">Nhóm nhận cảnh báo số dư thấp:</label>
              <select class="tele-select event-channel-select" id="event_low_balance" name="cau_hinh_kenh_su_kien[low_balance]" {{ $canEdit ? '' : 'disabled' }} data-selected="{{ $eventMap['low_balance'] ?? ($config->chat_id_alert ?? '') }}">
                {{-- Populated by JS --}}
              </select>
            </div>
          </div>

          {{-- 5. Đơn hàng thành công --}}
          <div class="tele-event-card">
            <div class="tele-event-card__top">
              <label class="tele-toggle-switch">
                <input type="checkbox" name="bat_thong_bao_don_hang" value="1" {{ old('bat_thong_bao_don_hang', $config->bat_thong_bao_don_hang) ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                <span class="tele-slider"></span>
              </label>
              <div class="tele-event-info">
                <strong>🎉 Đơn hàng nạp thành công</strong>
                <span>Gửi thông báo khi đơn nạp tiền điện thoại hoàn tất thành công.</span>
              </div>
            </div>
            <div class="tele-event-channel-picker">
              <label for="event_order_success">Nhóm nhận thông báo đơn thành công:</label>
              <select class="tele-select event-channel-select" id="event_order_success" name="cau_hinh_kenh_su_kien[order_success]" {{ $canEdit ? '' : 'disabled' }} data-selected="{{ $eventMap['order_success'] ?? ($config->chat_id_order ?? '') }}">
                {{-- Populated by JS --}}
              </select>
            </div>
          </div>

          {{-- 6. Manual Review --}}
          <div class="tele-event-card">
            <div class="tele-event-card__top">
              <label class="tele-toggle-switch">
                <input type="checkbox" name="bat_canh_bao_manual_review" value="1" {{ old('bat_canh_bao_manual_review', $config->bat_canh_bao_manual_review) ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                <span class="tele-slider"></span>
              </label>
              <div class="tele-event-info">
                <strong>🟡 Đơn cần đối soát thủ công</strong>
                <span>Gửi nhắc nhở khi có đơn quá hạn chờ NCC cần Admin vào xử lý.</span>
              </div>
            </div>
            <div class="tele-event-channel-picker">
              <label for="event_manual_review">Nhóm nhận thông báo đối soát:</label>
              <select class="tele-select event-channel-select" id="event_manual_review" name="cau_hinh_kenh_su_kien[manual_review]" {{ $canEdit ? '' : 'disabled' }} data-selected="{{ $eventMap['manual_review'] ?? ($config->chat_id_admin ?? '') }}">
                {{-- Populated by JS --}}
              </select>
            </div>
          </div>

          {{-- 7. Hoàn tiền đơn hàng --}}
          <div class="tele-event-card">
            <div class="tele-event-card__top">
              <label class="tele-toggle-switch">
                <input type="checkbox" name="bat_thong_bao_hoan_tien" value="1" {{ old('bat_thong_bao_hoan_tien', $config->bat_thong_bao_hoan_tien) ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}>
                <span class="tele-slider"></span>
              </label>
              <div class="tele-event-info">
                <strong>🔄 Hoàn tiền đơn hàng</strong>
                <span>Gửi thông báo khi Admin thực hiện hoàn tiền ví cho khách.</span>
              </div>
            </div>
            <div class="tele-event-channel-picker">
              <label for="event_order_refunded">Nhóm nhận thông báo hoàn tiền:</label>
              <select class="tele-select event-channel-select" id="event_order_refunded" name="cau_hinh_kenh_su_kien[order_refunded]" {{ $canEdit ? '' : 'disabled' }} data-selected="{{ $eventMap['order_refunded'] ?? ($config->chat_id_admin ?? '') }}">
                {{-- Populated by JS --}}
              </select>
            </div>
          </div>
        </div>
      </section>

      {{-- CARD 4: HƯỚNG DẪN LẤY TOKEN & CHAT ID --}}
      <section class="tele-help-callout" style="margin-bottom: 24px;">
        <h3>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
          Hướng dẫn lấy Bot Token và Chat ID:
        </h3>
        <ol>
          <li><strong>Lấy Bot Token:</strong> Mở Telegram tìm <code>@BotFather</code> &gt; Gõ <code>/newbot</code> &gt; Đặt tên &gt; Copy chuỗi <strong>HTTP API Token</strong> dán vào ô <em>Telegram Bot Token</em> ở trên.</li>
          <li><strong>Lấy Chat ID Nhóm (Group):</strong> Tạo nhóm Telegram mới &gt; Thêm con Bot của bạn vào nhóm &gt; Cấp quyền <strong>Admin</strong> cho bot trong nhóm &gt; Dùng bot <code>@RawDataBot</code> hoặc <code>@MissRose_bot</code> gõ <code>/id</code> để lấy ID nhóm (ví dụ: <code>-1005428010028</code> hoặc <code>-5428010028</code>).</li>
          <li><strong>Lấy Chat ID Cá nhân:</strong> Mở chat với bot của bạn bấm <code>/start</code>. Sau đó tìm bot <code>@userinfobot</code> bấm <code>/start</code> để lấy <strong>Id</strong> của bạn.</li>
        </ol>
      </section>

      {{-- ACTION BUTTONS --}}
      @if ($canEdit)
        <div style="display: flex; justify-content: flex-end; gap: 12px;">
          <button class="account-btn account-btn--primary" type="submit" style="padding: 10px 24px; font-size: 14.5px;">
            <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/>
              <polyline points="7 3 7 8 15 8"/>
            </svg>
            <span>Lưu cấu hình thông báo</span>
          </button>
        </div>
      @else
        <div class="account-alert account-alert--warning" style="display: flex; align-items: center; gap: 8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Bạn đang ở chế độ xem. Chỉ tài khoản Quản trị viên (Admin) mới có quyền chỉnh sửa cấu hình này.</span>
        </div>
      @endif
    </form>
  </section>

  {{-- MODAL THÊM / SỬA KÊNH TELEGRAM --}}
  <div class="account-modal" id="channelModal" hidden>
    <div class="account-modal__backdrop" onclick="closeChannelModal()"></div>
    <section class="account-modal__dialog" style="max-width: 480px;" role="dialog" aria-modal="true">
      <header class="account-modal__header">
        <h2 id="channelModalTitle" style="font-size: 16px; font-weight: 700;">Thêm nhóm nhận tin Telegram</h2>
        <button type="button" class="account-modal__close" onclick="closeChannelModal()">×</button>
      </header>
      <div class="account-modal__body" style="padding: 20px 24px; display: flex; flex-direction: column; gap: 16px;">
        <input type="hidden" id="modalChannelId">

        <div>
          <label class="tele-label" for="modalChannelName">Tên nhóm / Tên kênh <span class="req">*</span></label>
          <input class="tele-input" type="text" id="modalChannelName" placeholder="Ví dụ: Nhóm Cầu dao Khẩn cấp, Nhóm CSKH...">
        </div>

        <div>
          <label class="tele-label" for="modalChannelChatId">Chat ID Telegram <span class="req">*</span></label>
          <input class="tele-input" type="text" id="modalChannelChatId" placeholder="Ví dụ: -1001234567890 hoặc 123456789">
          <span class="tele-hint">Đảm bảo bạn đã thêm Bot vào nhóm này trước khi lưu.</span>
        </div>

        <div>
          <label class="tele-label" for="modalChannelNote">Ghi chú / Người phụ trách</label>
          <input class="tele-input" type="text" id="modalChannelNote" placeholder="Ví dụ: Đội trực ca đêm, Kế toán đối soát...">
        </div>
      </div>
      <footer class="account-modal__footer" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button class="account-btn account-btn--secondary" type="button" onclick="closeChannelModal()">Đóng</button>
        <button class="account-btn account-btn--primary" type="button" onclick="saveChannelFromModal()">Xác nhận lưu</button>
      </footer>
    </section>
  </div>

  {{-- TOAST NOTIFICATION CONTAINER --}}
  <div id="teleToast" class="tele-toast" role="alert">
    <span id="teleToastIcon" style="font-size: 16px;"></span>
    <span id="teleToastMsg"></span>
  </div>
@endsection

@push('scripts')
  <script>
    // State of channels
    let channelsData = @json($channelsList);

    function showToast(message, type = 'success') {
      const toast = document.getElementById('teleToast');
      const msg = document.getElementById('teleToastMsg');
      const icon = document.getElementById('teleToastIcon');

      toast.className = 'tele-toast tele-toast--' + type + ' is-show';
      msg.textContent = message;
      icon.innerHTML = type === 'success' ? '✅' : '❌';

      setTimeout(() => {
        toast.classList.remove('is-show');
      }, 4500);
    }

    function renderChannelsTable() {
      const tbody = document.getElementById('channelsTableBody');
      const jsonInput = document.getElementById('danhSachKenhJson');
      if (!tbody) return;

      jsonInput.value = JSON.stringify(channelsData);
      tbody.innerHTML = '';

      if (channelsData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 24px;">Chưa có nhóm Telegram nào. Bấm "Thêm nhóm nhận tin" để tạo nhóm mới.</td></tr>`;
        updateEventSelectors();
        return;
      }

      channelsData.forEach((chan, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <strong style="color: #0f172a; font-size: 14px;">${escapeHtml(chan.ten_kenh)}</strong>
          </td>
          <td>
            <code style="background: #f1f5f9; padding: 3px 7px; border-radius: 4px; font-weight: 600; color: #0369a1;">${escapeHtml(chan.chat_id)}</code>
          </td>
          <td>
            <span style="color: #64748b; font-size: 13px;">${escapeHtml(chan.ghi_chu || '-')}</span>
          </td>
          <td style="text-align: right;">
            <div style="display: inline-flex; gap: 6px; align-items: center;">
              <button class="tele-btn-test" type="button" onclick="testCustomChannel('${escapeHtml(chan.chat_id)}', '${escapeHtml(chan.ten_kenh)}')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                <span>Test</span>
              </button>
              <button type="button" class="tele-btn-test" onclick="editChannel(${idx})" style="padding: 0 10px;" title="Chỉnh sửa">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              </button>
              <button type="button" class="tele-btn-test" onclick="deleteChannel(${idx})" style="padding: 0 10px; color: #ef4444; border-color: #fecaca;" title="Xóa">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      });

      updateEventSelectors();
    }

    function updateEventSelectors() {
      document.querySelectorAll('.event-channel-select').forEach((sel) => {
        const currentVal = sel.value || sel.dataset.selected || '';
        sel.innerHTML = '<option value="">-- Mặc định (Nhóm đầu tiên / .env) --</option>';

        channelsData.forEach((chan) => {
          const opt = document.createElement('option');
          opt.value = chan.chat_id;
          opt.textContent = `${chan.ten_kenh} (${chan.chat_id})`;
          if (chan.chat_id === currentVal) {
            opt.selected = true;
          }
          sel.appendChild(opt);
        });
      });
    }

    function openAddChannelModal() {
      document.getElementById('channelModalTitle').textContent = 'Thêm nhóm nhận tin Telegram';
      document.getElementById('modalChannelId').value = '';
      document.getElementById('modalChannelName').value = '';
      document.getElementById('modalChannelChatId').value = '';
      document.getElementById('modalChannelNote').value = '';

      const modal = document.getElementById('channelModal');
      modal.hidden = false;
      document.body.classList.add('account-modal-open');
    }

    function editChannel(idx) {
      const chan = channelsData[idx];
      if (!chan) return;

      document.getElementById('channelModalTitle').textContent = 'Chỉnh sửa nhóm Telegram';
      document.getElementById('modalChannelId').value = idx;
      document.getElementById('modalChannelName').value = chan.ten_kenh;
      document.getElementById('modalChannelChatId').value = chan.chat_id;
      document.getElementById('modalChannelNote').value = chan.ghi_chu || '';

      const modal = document.getElementById('channelModal');
      modal.hidden = false;
      document.body.classList.add('account-modal-open');
    }

    function closeChannelModal() {
      const modal = document.getElementById('channelModal');
      modal.hidden = true;
      document.body.classList.remove('account-modal-open');
    }

    function saveChannelFromModal() {
      const name = (document.getElementById('modalChannelName').value || '').trim();
      const chatId = (document.getElementById('modalChannelChatId').value || '').trim();
      const note = (document.getElementById('modalChannelNote').value || '').trim();
      const idxStr = document.getElementById('modalChannelId').value;

      if (!name) {
        showToast('Vui lòng nhập Tên nhóm nhận tin!', 'error');
        document.getElementById('modalChannelName').focus();
        return;
      }
      if (!chatId) {
        showToast('Vui lòng nhập Chat ID Telegram!', 'error');
        document.getElementById('modalChannelChatId').focus();
        return;
      }

      if (idxStr !== '') {
        const idx = parseInt(idxStr, 10);
        channelsData[idx] = {
          ...channelsData[idx],
          ten_kenh: name,
          chat_id: chatId,
          ghi_chu: note
        };
        showToast('Đã cập nhật nhóm Telegram thành công!', 'success');
      } else {
        channelsData.push({
          id: 'chan_' + Date.now(),
          ten_kenh: name,
          chat_id: chatId,
          ghi_chu: note
        });
        showToast('Đã thêm nhóm Telegram mới!', 'success');
      }

      closeChannelModal();
      renderChannelsTable();
    }

    function deleteChannel(idx) {
      const chan = channelsData[idx];
      if (!chan) return;
      if (confirm(`Bạn có chắc muốn xóa nhóm "${chan.ten_kenh}"?`)) {
        channelsData.splice(idx, 1);
        renderChannelsTable();
        showToast('Đã xóa nhóm Telegram.', 'success');
      }
    }

    async function testCustomChannel(chatId, channelName) {
      const botTokenInput = document.getElementById('bot_token');
      const botToken = (botTokenInput?.value || '').trim();

      if (!botToken) {
        showToast('Vui lòng nhập Telegram Bot Token ở mục 1 trước khi bấm Test!', 'error');
        botTokenInput?.focus();
        return;
      }

      const btn = event.currentTarget;
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span>Đang gửi...</span>';

      try {
        const response = await fetch('{{ route("admin.telegram-settings.test") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            bot_token: botToken,
            chat_id: chatId,
            channel_name: channelName
          })
        });

        const data = await response.json();

        if (response.ok && data.success) {
          showToast(data.message || 'Gửi thử nghiệm thành công!', 'success');
        } else {
          showToast(data.message || 'Không thể gửi tin nhắn. Vui lòng kiểm tra lại Bot Token và Chat ID.', 'error');
        }
      } catch (err) {
        showToast('Lỗi kết nối mạng: ' + err.message, 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    // Initialize table on DOM load
    document.addEventListener('DOMContentLoaded', () => {
      renderChannelsTable();
    });
  </script>
@endpush


