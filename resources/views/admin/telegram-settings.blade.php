@extends('admin.layout')

@section('title', 'VietFin - Cấu hình Thông báo Telegram')

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
    .tele-input-row {
      display: flex;
      gap: 10px;
      align-items: center;
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
    .tele-input::placeholder {
      color: #94a3b8;
    }
    .tele-btn-test {
      height: 42px;
      padding: 0 16px;
      font-size: 13px;
      font-weight: 600;
      border-radius: 8px;
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

    /* Grid for Notification Toggles */
    .tele-toggle-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
      gap: 16px;
    }
    .tele-toggle-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px 18px;
      border: 1px solid var(--admin-slate-200);
      border-radius: 10px;
      background: #f8fafc;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .tele-toggle-item:hover {
      background: #f1f5f9;
      border-color: #cbd5e1;
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
    .tele-toggle-info {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .tele-toggle-info strong {
      font-size: 14px;
      font-weight: 600;
      color: var(--admin-slate-800);
    }
    .tele-toggle-info span {
      font-size: 12.5px;
      color: var(--admin-slate-500);
      line-height: 18px;
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
        <p>Quản lý kết nối Bot Telegram, phân nhóm nhận tin và bật/tắt từng loại thông báo tự động.</p>
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

    <form method="POST" action="{{ route('admin.telegram-settings.update') }}">
      @csrf
      @method('PUT')

      {{-- CARD 1: CẤU HÌNH BOT & KÊNH NHẬN TIN --}}
      <section class="tele-card">
        <div class="tele-card__header">
          <div class="tele-card__title-group">
            <div class="tele-card__title-row">
              <h2>Kết nối Bot Telegram &amp; Kênh nhận tin</h2>
              <span class="tele-badge tele-badge--blue">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Bảo mật
              </span>
            </div>
            <p class="tele-card__desc">Nhập mã Token của Bot và các Chat ID của nhóm nhận thông báo theo từng mục đích.</p>
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
            placeholder="Ví dụ: 7123456789:AAFn_abcXYZ1234567890abcdef"
            autocomplete="off"
            @cannot('quyen', 'telegram_setting.update') disabled @endcannot
          >
          <span class="tele-hint">Lấy từ <code>@BotFather</code> trên Telegram khi tạo bot mới.</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 20px; margin-top: 22px;">
          {{-- Kênh 1: Kỹ thuật & Lỗi --}}
          <div class="tele-form-group">
            <label class="tele-label" for="chat_id_alert">
              <span>Nhóm Cảnh báo Lỗi &amp; Kỹ thuật</span>
              <span class="tele-badge tele-badge--amber">Kỹ thuật</span>
            </label>
            <div class="tele-input-row">
              <input
                class="tele-input"
                type="text"
                id="chat_id_alert"
                name="chat_id_alert"
                value="{{ old('chat_id_alert', $config->chat_id_alert) }}"
                placeholder="Ví dụ: -1001234567890"
                @cannot('quyen', 'telegram_setting.update') disabled @endcannot
              >
              <button class="tele-btn-test" type="button" onclick="testChatId('chat_id_alert', 'Nhóm Lỗi &amp; Kỹ thuật')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                <span>Test</span>
              </button>
            </div>
            <span class="tele-hint">Nhận tin: Lỗi NCC, Circuit Breaker, Xử lý chậm, Số dư NCC thấp.</span>
          </div>

          {{-- Kênh 2: Đơn hàng thành công --}}
          <div class="tele-form-group">
            <label class="tele-label" for="chat_id_order">
              <span>Nhóm Đơn hàng Thành công</span>
              <span class="tele-badge tele-badge--green">Kinh doanh</span>
            </label>
            <div class="tele-input-row">
              <input
                class="tele-input"
                type="text"
                id="chat_id_order"
                name="chat_id_order"
                value="{{ old('chat_id_order', $config->chat_id_order) }}"
                placeholder="Ví dụ: -1001234567891"
                @cannot('quyen', 'telegram_setting.update') disabled @endcannot
              >
              <button class="tele-btn-test" type="button" onclick="testChatId('chat_id_order', 'Nhóm Đơn hàng Thành công')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                <span>Test</span>
              </button>
            </div>
            <span class="tele-hint">Nhận tin: Mỗi khi có khách nạp tiền thành công.</span>
          </div>

          {{-- Kênh 3: Quản trị & Hoàn tiền --}}
          <div class="tele-form-group">
            <label class="tele-label" for="chat_id_admin">
              <span>Nhóm Quản trị &amp; Hoàn tiền</span>
              <span class="tele-badge tele-badge--purple">Kế toán/Admin</span>
            </label>
            <div class="tele-input-row">
              <input
                class="tele-input"
                type="text"
                id="chat_id_admin"
                name="chat_id_admin"
                value="{{ old('chat_id_admin', $config->chat_id_admin) }}"
                placeholder="Ví dụ: -1001234567892"
                @cannot('quyen', 'telegram_setting.update') disabled @endcannot
              >
              <button class="tele-btn-test" type="button" onclick="testChatId('chat_id_admin', 'Nhóm Quản trị &amp; Hoàn tiền')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                <span>Test</span>
              </button>
            </div>
            <span class="tele-hint">Nhận tin: Hoàn tiền đơn hàng, Đơn cần đối soát thủ công.</span>
          </div>
        </div>
      </section>

      {{-- CARD 2: BẬT / TẮT TỪNG LOẠI THÔNG BÁO --}}
      <section class="tele-card">
        <div class="tele-card__header">
          <div class="tele-card__title-group">
            <h2>Bật / Tắt Từng Loại Thông Báo</h2>
            <p class="tele-card__desc">Bật hoặc tắt các sự kiện bạn muốn bot Telegram tự động gửi cảnh báo.</p>
          </div>
        </div>

        <div class="tele-toggle-grid">
          {{-- 1. Đơn hàng thành công --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_thong_bao_don_hang" value="1" {{ old('bat_thong_bao_don_hang', $config->bat_thong_bao_don_hang) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>🎉 Đơn hàng thành công</strong>
              <span>Gửi thông báo khi đơn nạp tiền điện thoại hoàn tất thành công.</span>
            </div>
          </label>

          {{-- 2. Cảnh báo lỗi NCC --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_canh_bao_loi" value="1" {{ old('bat_canh_bao_loi', $config->bat_canh_bao_loi) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>⚠️ Lỗi giao dịch nhà cung cấp</strong>
              <span>Gửi cảnh báo khi NCC trả về mã lỗi thất bại dứt khoát.</span>
            </div>
          </label>

          {{-- 3. Xử lý chậm --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_canh_bao_xu_ly_cham" value="1" {{ old('bat_canh_bao_xu_ly_cham', $config->bat_canh_bao_xu_ly_cham) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>🐢 Giao dịch xử lý chậm</strong>
              <span>Gửi cảnh báo khi thời gian kết nối NCC vượt quá ngưỡng cài đặt.</span>
            </div>
          </label>

          {{-- 4. Circuit Breaker --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_canh_bao_circuit_breaker" value="1" {{ old('bat_canh_bao_circuit_breaker', $config->bat_canh_bao_circuit_breaker) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>🚨 Tự ngắt kết nối (Circuit Breaker)</strong>
              <span>Gửi cảnh báo khi hệ thống tạm dừng kết nối NCC do lỗi liên tiếp.</span>
            </div>
          </label>

          {{-- 5. Số dư NCC thấp --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_canh_bao_so_du_thap" value="1" {{ old('bat_canh_bao_so_du_thap', $config->bat_canh_bao_so_du_thap) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>💰 Số dư nhà cung cấp thấp</strong>
              <span>Gửi cảnh báo khi số dư ví NCC xuống dưới ngưỡng tối thiểu.</span>
            </div>
          </label>

          {{-- 6. Manual Review --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_canh_bao_manual_review" value="1" {{ old('bat_canh_bao_manual_review', $config->bat_canh_bao_manual_review) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>🟡 Đơn cần đối soát thủ công</strong>
              <span>Gửi nhắc nhở khi có đơn quá hạn chờ NCC cần Admin vào xử lý.</span>
            </div>
          </label>

          {{-- 7. Hoàn tiền --}}
          <label class="tele-toggle-item">
            <span class="tele-toggle-switch">
              <input type="checkbox" name="bat_thong_bao_hoan_tien" value="1" {{ old('bat_thong_bao_hoan_tien', $config->bat_thong_bao_hoan_tien) ? 'checked' : '' }} @cannot('quyen', 'telegram_setting.update') disabled @endcannot>
              <span class="tele-slider"></span>
            </span>
            <div class="tele-toggle-info">
              <strong>🔄 Hoàn tiền đơn hàng</strong>
              <span>Gửi thông báo khi Admin thực hiện hoàn tiền ví cho khách.</span>
            </div>
          </label>
        </div>
      </section>

      {{-- CARD 3: HƯỚNG DẪN LẤY TOKEN & CHAT ID --}}
      <section class="tele-help-callout" style="margin-bottom: 24px;">
        <h3>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
          Hướng dẫn lấy Bot Token và Chat ID:
        </h3>
        <ol>
          <li><strong>Lấy Bot Token:</strong> Mở Telegram tìm <code>@BotFather</code> &gt; Gõ lệnh <code>/newbot</code> &gt; Đặt tên bot &gt; Copy chuỗi <strong>HTTP API Token</strong> dán vào ô <em>Telegram Bot Token</em> ở trên.</li>
          <li><strong>Lấy Chat ID cá nhân:</strong> Tìm bot bạn vừa tạo &gt; Bấm <code>/start</code>. Sau đó tìm bot <code>@userinfobot</code> &gt; Bấm <code>/start</code> để lấy <strong>Id</strong> của bạn.</li>
          <li><strong>Lấy Chat ID Nhóm (Group):</strong> Tạo nhóm Telegram mới &gt; Thêm bot của bạn vào nhóm &gt; Cấp quyền Admin cho bot. Sau đó thêm bot <code>@RawDataBot</code> vào nhóm để xem Chat ID của nhóm (thường bắt đầu bằng <code>-100...</code>).</li>
        </ol>
      </section>

      {{-- ACTION BUTTONS --}}
      @can('quyen', 'telegram_setting.update')
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
      @endcan
    </form>
  </section>

  {{-- TOAST NOTIFICATION CONTAINER --}}
  <div id="teleToast" class="tele-toast" role="alert">
    <span id="teleToastIcon" style="font-size: 16px;"></span>
    <span id="teleToastMsg"></span>
  </div>
@endsection

@push('scripts')
  <script>
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

    async function testChatId(inputId, channelName) {
      const input = document.getElementById(inputId);
      const chatId = (input.value || '').trim();

      if (!chatId) {
        showToast('Vui lòng nhập Chat ID vào ô trước khi bấm gửi thử nghiệm.', 'error');
        input.focus();
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
            chat_id: chatId,
            channel_name: channelName
          })
        });

        const data = await response.json();

        if (response.ok && data.success) {
          showToast(data.message || 'Gửi thử nghiệm thành công!', 'success');
        } else {
          showToast(data.message || 'Gửi thất bại. Vui lòng kiểm tra lại Token hoặc Chat ID.', 'error');
        }
      } catch (err) {
        showToast('Lỗi kết nối mạng: ' + err.message, 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    }
  </script>
@endpush

