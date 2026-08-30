@extends('admin.layout')

@section('title', 'VietFin - Cấu hình Thông báo Telegram')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .tele-card {
      background: #fff;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .tele-card__header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding-bottom: 16px;
      margin-bottom: 20px;
      border-bottom: 1px solid var(--admin-slate-100);
    }
    .tele-card__header h3 {
      margin: 0;
      font-size: 16px;
      font-weight: 700;
      color: var(--admin-slate-800);
    }
    .tele-card__header p {
      margin: 4px 0 0;
      font-size: 13px;
      color: var(--admin-slate-500);
    }
    .tele-badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 8px;
      border-radius: 9999px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .tele-badge--primary { background: #e0f2fe; color: #0284c7; }
    .tele-badge--success { background: #dcfce7; color: #15803d; }
    .tele-badge--warning { background: #fef3c7; color: #b45309; }

    .tele-form-group {
      margin-bottom: 20px;
    }
    .tele-form-group:last-child {
      margin-bottom: 0;
    }
    .tele-label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--admin-slate-700);
      margin-bottom: 6px;
    }
    .tele-input-row {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    .tele-input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid var(--admin-slate-300);
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      color: var(--admin-slate-900);
      background: #f8fafc;
      transition: all 0.15s ease;
    }
    .tele-input:focus {
      background: #fff;
      border-color: var(--admin-brand-500);
      outline: none;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }
    .tele-btn-test {
      white-space: nowrap;
      padding: 9px 16px;
      font-size: 13px;
      font-weight: 600;
      border-radius: 6px;
      border: 1px solid var(--admin-slate-300);
      background: #fff;
      color: var(--admin-slate-700);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.15s;
    }
    .tele-btn-test:hover:not(:disabled) {
      background: #f1f5f9;
      border-color: var(--admin-slate-400);
      color: var(--admin-slate-900);
    }
    .tele-btn-test:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .tele-hint {
      display: block;
      font-size: 12px;
      color: var(--admin-slate-500);
      margin-top: 5px;
    }

    /* Grid for Notification Toggles */
    .tele-toggle-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 16px;
    }
    .tele-toggle-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      background: #f8fafc;
      transition: background 0.15s;
    }
    .tele-toggle-item:hover {
      background: #f1f5f9;
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
    }
    .tele-slider {
      position: absolute;
      cursor: pointer;
      top: 0; left: 0; right: 0; bottom: 0;
      background-color: #cbd5e1;
      transition: .2s;
      border-radius: 24px;
    }
    .tele-slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .2s;
      border-radius: 50%;
    }
    input:checked + .tele-slider {
      background-color: #0284c7;
    }
    input:checked + .tele-slider:before {
      transform: translateX(20px);
    }
    .tele-toggle-info strong {
      display: block;
      font-size: 14px;
      color: var(--admin-slate-800);
      margin-bottom: 3px;
    }
    .tele-toggle-info span {
      display: block;
      font-size: 12px;
      color: var(--admin-slate-500);
      line-height: 1.4;
    }

    /* Help Box */
    .tele-help-box {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 8px;
      padding: 18px 20px;
      color: #166534;
      font-size: 13px;
      line-height: 1.6;
    }
    .tele-help-box h4 {
      margin: 0 0 10px;
      font-size: 14px;
      font-weight: 700;
      color: #14532d;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .tele-help-box ol {
      margin: 0;
      padding-left: 20px;
    }
    .tele-help-box code {
      background: rgba(22, 101, 52, 0.1);
      padding: 2px 6px;
      border-radius: 4px;
      font-weight: 600;
    }

    /* Floating Feedback Toast */
    .tele-toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 12px 20px;
      border-radius: 8px;
      background: #1e293b;
      color: #fff;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 9999;
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
    }
    .tele-toast.is-show {
      opacity: 1;
      transform: translateY(0);
    }
    .tele-toast--success { background: #065f46; border-left: 4px solid #34d399; }
    .tele-toast--error { background: #991b1b; border-left: 4px solid #f87171; }
  </style>
@endpush

@section('content')
  <div class="admin-page">
    <div class="admin-page__header">
      <div>
        <h1 class="admin-page__title">Cấu hình Thông báo Telegram</h1>
        <p class="admin-page__subtitle">Quản lý kết nối Bot Telegram, phân nhóm nhận tin và bật/tắt từng loại thông báo hệ thống.</p>
      </div>

      <div class="admin-page__actions">
        <a class="admin-btn admin-btn--secondary" href="{{ route('admin.dashboard') }}">
          <x-icon name="arrow_back" /> Quay lại
        </a>
      </div>
    </div>

    @if (session('success'))
      <div class="admin-alert admin-alert--success" style="margin-bottom: 20px; padding: 14px 18px; border-radius: 6px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: 14px; display: flex; align-items: center; gap: 10px;">
        <x-icon name="check_circle" />
        <span>{{ session('success') }}</span>
      </div>
    @endif

    @if ($errors->any())
      <div class="admin-alert admin-alert--danger" style="margin-bottom: 20px; padding: 14px 18px; border-radius: 6px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 14px;">
        <ul style="margin: 0; padding-left: 20px;">
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.telegram-settings.update') }}">
      @csrf
      @method('PUT')

      {{-- CARD 1: CẤU HÌNH BOT & KÊNH NHẬN TIN --}}
      <div class="tele-card">
        <div class="tele-card__header">
          <div style="flex: 1;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <h3>Kết nối Bot Telegram &amp; Kênh nhận tin</h3>
              <span class="tele-badge tele-badge--primary">Bảo mật</span>
            </div>
            <p>Nhập mã Token của Bot và các Chat ID của nhóm nhận thông báo theo từng mục đích.</p>
          </div>
        </div>

        <div class="tele-form-group">
          <label class="tele-label" for="bot_token">
            Telegram Bot Token <span style="color: #ef4444;">*</span>
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
          <span class="tele-hint">Lấy từ <code>@BotFather</code> trên Telegram khi tạo bot.</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
          {{-- Kênh 1: Kỹ thuật & Lỗi --}}
          <div class="tele-form-group">
            <label class="tele-label" for="chat_id_alert">
              Nhóm Cảnh báo Lỗi &amp; Kỹ thuật
              <span class="tele-badge tele-badge--warning" style="font-size: 10px; margin-left: 4px;">Kỹ thuật</span>
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
                <x-icon name="send" /> Test
              </button>
            </div>
            <span class="tele-hint">Nhận tin: Lỗi NCC, Circuit Breaker, Xử lý chậm, Số dư NCC thấp.</span>
          </div>

          {{-- Kênh 2: Đơn hàng thành công --}}
          <div class="tele-form-group">
            <label class="tele-label" for="chat_id_order">
              Nhóm Đơn hàng Thành công
              <span class="tele-badge tele-badge--success" style="font-size: 10px; margin-left: 4px;">Kinh doanh</span>
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
                <x-icon name="send" /> Test
              </button>
            </div>
            <span class="tele-hint">Nhận tin: Mỗi khi có khách nạp tiền thành công.</span>
          </div>

          {{-- Kênh 3: Quản trị & Hoàn tiền --}}
          <div class="tele-form-group">
            <label class="tele-label" for="chat_id_admin">
              Nhóm Quản trị &amp; Hoàn tiền
              <span class="tele-badge tele-badge--primary" style="font-size: 10px; margin-left: 4px;">Kế toán/Admin</span>
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
                <x-icon name="send" /> Test
              </button>
            </div>
            <span class="tele-hint">Nhận tin: Hoàn tiền đơn hàng, Đơn cần đối soát thủ công.</span>
          </div>
        </div>
      </div>

      {{-- CARD 2: BẬT / TẮT TỪNG LOẠI THÔNG BÁO --}}
      <div class="tele-card">
        <div class="tele-card__header">
          <div style="flex: 1;">
            <h3>Bật / Tắt Từng Loại Thông Báo</h3>
            <p>Bật hoặc tắt các sự kiện bạn muốn bot Telegram gửi cảnh báo.</p>
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
      </div>

      {{-- CARD 3: HƯỚNG DẪN LẤY TOKEN & CHAT ID --}}
      <div class="tele-card" style="background: transparent; border: 0; padding: 0; box-shadow: none;">
        <div class="tele-help-box">
          <h4><x-icon name="help" /> Hướng dẫn lấy Bot Token và Chat ID:</h4>
          <ol>
            <li><strong>Lấy Bot Token:</strong> Mở Telegram tìm <code>@BotFather</code> &gt; Gõ lệnh <code>/newbot</code> &gt; Đặt tên bot &gt; Copy chuỗi <strong>HTTP API Token</strong> dán vào ô <em>Telegram Bot Token</em> ở trên.</li>
            <li><strong>Lấy Chat ID cá nhân:</strong> Tìm bot bạn vừa tạo &gt; Bấm <code>/start</code>. Sau đó tìm bot <code>@userinfobot</code> &gt; Bấm <code>/start</code> để lấy <strong>Id</strong> của bạn.</li>
            <li><strong>Lấy Chat ID Nhóm (Group):</strong> Tạo nhóm Telegram mới &gt; Thêm bot của bạn vào nhóm &gt; Cấp quyền Admin cho bot. Sau đó thêm bot <code>@RawDataBot</code> vào nhóm để xem Chat ID của nhóm (thường bắt đầu bằng <code>-100...</code>).</li>
          </ol>
        </div>
      </div>

      {{-- ACTION BUTTONS --}}
      @can('quyen', 'telegram_setting.update')
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
          <button class="admin-btn admin-btn--primary" type="submit" style="padding: 12px 24px; font-size: 15px; font-weight: 600;">
            <x-icon name="save" /> Lưu cấu hình thông báo
          </button>
        </div>
      @else
        <div class="admin-alert" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #64748b; padding: 12px 18px; border-radius: 6px; font-size: 13px;">
          <x-icon name="lock" /> Bạn đang ở chế độ xem. Chỉ tài khoản Quản trị viên (Admin) mới có quyền chỉnh sửa cấu hình này.
        </div>
      @endcan
    </form>
  </div>

  {{-- TOAST NOTIFICATION CONTAINER --}}
  <div id="teleToast" class="tele-toast" role="alert">
    <span id="teleToastIcon"></span>
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
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '⏳ Đang gửi...';

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
        btn.innerHTML = originalText;
      }
    }
  </script>
@endpush
