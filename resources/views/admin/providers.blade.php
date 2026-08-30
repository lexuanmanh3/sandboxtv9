@extends('admin.layout')

@section('title', 'VietFin - Quản lý nhà cung cấp & Kết nối')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
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
          <strong>Nhà cung cấp</strong>
        </nav>
        <h1>Quản lý nhà cung cấp</h1>
        <p>Quản lý danh sách nhà cung cấp, cấu hình kết nối API, kiểm tra kết nối trực tiếp, cảnh báo số dư, đóng tự động (Circuit Breaker) và cảnh báo Telegram.</p>
      </div>

      <div class="account-actions">
        <button class="account-btn account-btn--primary" type="button" data-provider-modal-open="create">
          <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          <span>Thêm mới</span>
        </button>
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

    {{-- BỘ LỌC TÌM KIẾM NÂNG CAO --}}
    <section class="account-card account-filter-section {{ request()->hasAny(['ma_ncc', 'ten_ncc', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['ma_ncc', 'ten_ncc', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      <form class="account-filter" method="GET" action="{{ route('admin.providers') }}" data-account-filter {{ request()->hasAny(['ma_ncc', 'ten_ncc', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Mã NCC</span>
          <input type="text" name="ma_ncc" value="{{ $filters['ma_ncc'] ?? '' }}" placeholder="HPZ, APPOTAPAY...">
        </label>
        <label>
          <span>Tên nhà cung cấp</span>
          <input type="text" name="ten_ncc" value="{{ $filters['ten_ncc'] ?? '' }}" placeholder="Tên nhà cung cấp...">
        </label>
        <label>
          <span>Trạng thái</span>
          <select name="trang_thai">
            <option value="">Tất cả</option>
            @foreach ($statuses as $value => $label)
              <option value="{{ $value }}" @selected(($filters['trang_thai'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <button class="account-btn account-btn--primary" type="submit">
          <x-icon name="visibility" />
          Tìm kiếm
        </button>
        <a class="account-btn account-btn--muted" href="{{ route('admin.providers') }}">
          Đặt lại
        </a>
      </form>
    </section>

    {{-- BẢNG DANH SÁCH NHÀ CUNG CẤP --}}
    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th class="th-checkbox">
                <input type="checkbox" class="account-table-checkbox" data-select-all title="Chọn tất cả">
              </th>
              <th>Hành động</th>
              <th>Mã NCC</th>
              <th>Tên nhà cung cấp</th>
              <th>NCC Cha</th>
              <th>URL Kết nối API</th>
              <th>Cảnh báo & Circuit Breaker</th>
              <th>Trạng thái</th>
              <th>Thời gian tạo</th>
            </tr>
          </thead>
          <tbody data-provider-table-body>
            @forelse ($providers as $provider)
              @php
                $conn = $provider->ketNoi->first();
                $circuit = $conn?->cau_hinh_dong_tu_dong ?? [];
                $alert = $conn?->cau_hinh_canh_bao_loi ?? [];
                $balance = $conn?->cau_hinh_canh_bao_so_du ?? [];
                $payload = [
                  'id' => $provider->id,
                  'ma_ncc' => $provider->ma_ncc,
                  'ten_ncc' => $provider->ten_ncc,
                  'nha_cung_cap_cha_id' => $provider->nha_cung_cap_cha_id,
                  'so_dien_thoai' => $provider->so_dien_thoai,
                  'email' => $provider->email,
                  'trang_thai' => $provider->trang_thai,
                  'username' => $conn?->username ?? '',
                  'has_password' => (bool) $conn?->hasPassword(),
                  'api_user' => $conn?->api_user ?? '',
                  'has_api_password' => (bool) $conn?->hasApiPassword(),
                  'api_url' => $conn?->api_url ?? $conn?->base_url ?? '',
                  'cau_hinh_ma_gd' => $conn?->cau_hinh_ma_gd ?? '',
                  'public_key' => $conn?->public_key ?? '',
                  'public_key_file' => $conn?->public_key_file ?? '',
                  'has_private_key_file' => (bool) $conn?->hasPrivateKey(),
                  'timeout_he_thong' => $conn?->timeout_he_thong ?? 60,
                  'timeout_ncc' => $conn?->timeout_ncc ?? 60,
                  'so_du_canh_bao' => $balance['so_du_canh_bao'] ?? 0,
                  'so_du_toi_thieu_nap' => $balance['so_du_toi_thieu_nap'] ?? 0,
                  'so_tien_nap_moi_lan' => $balance['so_tien_nap_moi_lan'] ?? 0,
                  'chay_nhieu_tk_con' => (bool) ($balance['chay_nhieu_tk_con'] ?? false),
                  'tu_dong_nap_tien' => (bool) ($balance['tu_dong_nap_tien'] ?? false),
                  'kich_hoat_nap_cham' => (bool) ($balance['kich_hoat_nap_cham'] ?? false),
                  'so_gd_that_bai_lien_tiep' => $circuit['so_gd_that_bai_lien_tiep'] ?? 0,
                  'thoi_gian_dong' => $circuit['thoi_gian_dong'] ?? 0,
                  'ma_loi_bo_qua' => $circuit['ma_loi_bo_qua'] ?? '',
                  'tinh_gd_loi' => (bool) ($circuit['tinh_gd_loi'] ?? false),
                  'so_gd_nghi_ngo' => $circuit['so_gd_nghi_ngo'] ?? 0,
                  'thoi_gian_quet' => $circuit['thoi_gian_quet'] ?? 0,
                  'tong_so_gd_quet' => $circuit['tong_so_gd_quet'] ?? 0,
                  'so_gd_loi_toi_da' => $circuit['so_gd_loi_toi_da'] ?? 0,
                  'so_lan_kiem_tra_lai' => $circuit['so_lan_kiem_tra_lai'] ?? 6,
                  'hanh_dong_khi_het_gio' => $circuit['hanh_dong_khi_het_gio'] ?? 'TU_DONG_HOAN_TIEN',
                  'bat_canh_bao' => (bool) ($alert['bat_canh_bao'] ?? false),
                  'canh_bao_xu_ly_cham_giay' => $alert['canh_bao_xu_ly_cham_giay'] ?? 0,
                  'kenh_canh_bao' => $alert['kenh_canh_bao'] ?? 'Telegram',
                  'nhom_canh_bao_chat_id' => $alert['nhom_canh_bao_chat_id'] ?? '',
                  'bo_qua_ma_loi_ncc' => $alert['bo_qua_ma_loi_ncc'] ?? '',
                  'bo_qua_message_ncc' => $alert['bo_qua_message_ncc'] ?? '',
                  'urls' => [
                    'update' => route('admin.providers.update', $provider),
                    'destroy' => route('admin.providers.destroy', $provider),
                    'toggle' => route('admin.providers.toggle-status', $provider),
                    'test_connection' => route('admin.providers.test-connection', $provider),
                  ],
                ];
              @endphp
              <tr data-provider-row>
                <td class="td-checkbox">
                  <input type="checkbox" class="account-table-checkbox bulk-item-checkbox" value="{{ $provider->id }}">
                </td>
                <td>
                  <div class="account-row-actions">
                    <button class="account-row-action" type="button" data-row-action>
                      Hành động
                      <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                        <path d="M5 7.5 10 12.5 15 7.5" />
                      </svg>
                    </button>
                    <div class="account-row-menu" hidden>
                      <button type="button" data-provider-modal-open="view" data-provider='@json($payload)'>Xem chi tiết</button>
                      <button type="button" data-provider-modal-open="edit" data-provider='@json($payload)'>Chỉnh sửa</button>
                      <button type="button" data-provider-test-btn data-url="{{ route('admin.providers.test-connection', $provider) }}" data-name="{{ $provider->ten_ncc }}">
                        Kiểm tra kết nối
                      </button>
                      <button type="button" data-provider-action-submit="{{ route('admin.providers.toggle-status', $provider) }}" data-method="PATCH">
                        {{ $provider->trang_thai === 'hoat_dong' ? 'Tạm dừng' : 'Kích hoạt' }}
                      </button>
                      @if(($circuitStats[$provider->id] ?? 0) > 0)
                        <button type="button" data-provider-action-submit="{{ route('admin.providers.reset-circuit', $provider) }}" data-method="POST" style="color: #d97706;">
                          Reset lỗi Circuit ({{ $circuitStats[$provider->id] }})
                        </button>
                      @endif
                      <button type="button" data-provider-modal-open="delete" data-provider='@json($payload)'>Xóa bỏ</button>
                    </div>
                  </div>
                </td>
                <td class="account-username">{{ $provider->ma_ncc }}</td>
                <td><strong>{{ $provider->ten_ncc }}</strong></td>
                <td>{{ optional($provider->nhaCungCapCha)->ten_ncc ?: '-' }}</td>
                <td>
                  <span style="font-size: 13px; font-family: monospace; color: var(--admin-slate-600);" title="{{ $conn?->api_url }}">
                    {{ Str::limit($conn?->api_url ?: '-', 30) }}
                  </span>
                </td>
                <td>
                  <div style="display: flex; flex-direction: column; gap: 4px;">
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                      @if(!empty($alert['bat_canh_bao']))
                        <span class="account-status-pill is-yes" style="font-size: 11px;">Telegram: Bật</span>
                      @endif
                      @if(!empty($circuit['so_gd_that_bai_lien_tiep']))
                        @php
                          $currentFails = $circuitStats[$provider->id] ?? 0;
                          $maxFails = (int) $circuit['so_gd_that_bai_lien_tiep'];
                          $isTripped = $currentFails >= $maxFails || ($conn && $conn->trang_thai === 'tam_dung');
                        @endphp
                        <span class="account-status-pill {{ $isTripped ? 'is-no' : ($currentFails > 0 ? 'is-warn' : 'is-muted') }}" style="font-size: 11px; {{ !$isTripped && $currentFails === 0 ? 'background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;' : '' }}" title="Ngưỡng ngắt mạch: {{ $maxFails }} lỗi liên tiếp (Hiện tại: {{ $currentFails }} lỗi)">
                          Circuit: {{ $currentFails }}/{{ $maxFails }} lỗi {{ $isTripped ? '🚨 (Đã ngắt)' : '' }}
                        </span>
                      @endif
                    </div>
                    @if(empty($alert['bat_canh_bao']) && empty($circuit['so_gd_that_bai_lien_tiep']))
                      <span style="color: var(--admin-slate-400);">-</span>
                    @endif
                  </div>
                </td>
                <td>
                  <span class="account-status-pill {{ $provider->trang_thai === 'hoat_dong' ? 'is-yes' : 'is-no' }}">
                    {{ $statuses[$provider->trang_thai] ?? $provider->trang_thai }}
                  </span>
                  @if($conn && $conn->trang_thai !== $provider->trang_thai)
                    <div style="font-size: 10px; color: #dc2626; margin-top: 2px; font-weight: 600;">
                      Kết nối: {{ $statuses[$conn->trang_thai] ?? $conn->trang_thai }}
                    </div>
                  @endif
                </td>
                <td>{{ optional($provider->created_at)->format('d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="9">Chưa có nhà cung cấp nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <span>
            Đang xem {{ $providers->firstItem() ?? 0 }} đến {{ $providers->lastItem() ?? 0 }} trong tổng số {{ $providers->total() }} mục
          </span>
          <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--admin-slate-600);">
            <span>Hiển thị:</span>
            <select onchange="location.href = this.value;" style="border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 4px 8px; font-size: 12px; background: #fff; cursor: pointer;">
              @foreach ([10, 20, 50, 100] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" @selected(request('per_page', 10) == $size)>
                  {{ $size }} / trang
                </option>
              @endforeach
            </select>
          </label>
        </div>

        <nav aria-label="Phân trang nhà cung cấp">
          <a class="{{ $providers->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $providers->url(1) }}">«</a>
          <a class="{{ $providers->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $providers->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($providers->lastPage(), 5); $page++)
            <a class="{{ $providers->currentPage() === $page ? 'is-active' : '' }}" href="{{ $providers->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $providers->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $providers->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $providers->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $providers->url($providers->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- MODAL THÊM MỚI / CHỈNH SỬA / XEM CHI TIẾT --}}
  <div class="account-modal" data-provider-modal="form" hidden>
    <div class="account-modal__backdrop" data-provider-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--role" role="dialog" aria-modal="true" aria-labelledby="providerModalTitle">
      <header class="account-modal__header">
        <h2 id="providerModalTitle" data-provider-modal-title>Thêm mới nhà cung cấp</h2>
        <button type="button" class="account-modal__close" data-provider-modal-close aria-label="Đóng">×</button>
      </header>

      <form class="account-modal__body" method="POST" action="{{ route('admin.providers.store') }}" data-provider-form>
        @csrf
        <input type="hidden" name="_method" value="POST" data-provider-form-method>
        <input type="hidden" name="id" data-provider-id>

        {{-- TAB NAVIGATION --}}
        <div class="account-tabs" role="tablist">
          <button type="button" class="is-active" data-provider-tab="general" role="tab" aria-selected="true">Thông tin chung &amp; Kết nối</button>
          <button type="button" data-provider-tab="balance" role="tab" aria-selected="false">Cảnh báo số dư</button>
          <button type="button" data-provider-tab="circuit" role="tab" aria-selected="false">Cầu dao &amp; SLA Đơn treo</button>
          <button type="button" data-provider-tab="telegram" role="tab" aria-selected="false">Cảnh báo Telegram</button>
        </div>

        {{-- TABS CONTENT --}}
        <div class="account-tab-panel is-active" data-provider-panel="general" role="tabpanel">
           <div class="account-form-grid">
            <label><span>Mã (*)</span><input type="text" name="ma_ncc" required placeholder="HPZ, APPOTAPAY..."></label>
            <label><span>Tên (*)</span><input type="text" name="ten_ncc" required placeholder="NCC HPZ..."></label>
            <label><span>NCC cha</span>
              <select name="nha_cung_cap_cha_id">
                <option value="">-- Chọn nhà cung cấp cha --</option>
                @foreach ($parentProviders as $p)<option value="{{ $p->id }}">{{ $p->ten_ncc }} ({{ $p->ma_ncc }})</option>@endforeach
              </select>
            </label>
            <label><span>Trạng thái (*)</span>
              <select name="trang_thai" required>
                @foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected($value === 'hoat_dong')>{{ $label }}</option>@endforeach
              </select>
            </label>
            <label><span>Số điện thoại</span><input type="text" name="so_dien_thoai" placeholder="0987654321"></label>
            <label><span>Địa chỉ email</span><input type="email" name="email" placeholder="ncc@example.com"></label>
            <label><span>UserName (Tài khoản Portal)</span><input type="text" name="username" placeholder="Tài khoản đăng nhập portal"></label>
            <label>
              <span style="display: flex; justify-content: space-between; align-items: center;">
                <span>Secret Key (Mật khẩu ký HMAC / Password)</span>
                <span id="passwordIndicator" style="font-size: 11px; color: #10b981; font-weight: 600; display: none;">🔒 Đã có Secret Key</span>
              </span>
              <input type="password" name="password" id="providerPasswordInput" placeholder="Nhập Secret Key" autocomplete="new-password">
            </label>
            <label><span>Partner Code / ApiUser (*)</span><input type="text" name="api_user" placeholder="Ví dụ: quyducphan96"></label>
            <label>
              <span style="display: flex; justify-content: space-between; align-items: center;">
                <span>API Key (ApiPassword) (*)</span>
                <span id="apiPasswordIndicator" style="font-size: 11px; color: #10b981; font-weight: 600; display: none;">🔒 Đã có API Key</span>
              </span>
              <input type="password" name="api_password" id="providerApiPasswordInput" placeholder="Nhập API Key do AppotaPay cấp" autocomplete="new-password">
            </label>
            <div class="account-form-grid__full"><label><span>ApiUrl (*)</span><input type="text" name="api_url" id="providerApiUrlInput" placeholder="https://gateway.dev.appotapay.com"></label></div>
            <label><span>Cấu hình Mã GD</span><input type="text" name="cau_hinh_ma_gd"></label>
            <label><span>PublicKey</span><input type="text" name="public_key"></label>
            <label><span>PublicKeyFile</span><input type="text" name="public_key_file"></label>
            <label>
              <span style="display: flex; justify-content: space-between; align-items: center;">
                <span>PrivateKeyFile</span>
                <span id="privateKeyIndicator" style="font-size: 11px; color: #10b981; font-weight: 600; display: none;">🔒 Đã có Private Key</span>
              </span>
              <input type="password" name="private_key_file" id="providerPrivateKeyInput" placeholder="Nội dung Private Key" autocomplete="new-password">
            </label>
            <label>
              <span>TimeOut (s)</span>
              <input type="number" name="timeout_he_thong" value="30">
              <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Thời gian tối đa để kết nối server NCC (TCP/SSL handshake). Khuyên dùng: 30s.</small>
            </label>
            <label>
              <span>TimeOut Provider (s)</span>
              <input type="number" name="timeout_ncc" value="25">
              <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Thời gian tối đa chờ NCC xử lý nạp tiền trước khi ngắt và đưa về đơn Chờ. Khuyên dùng: 25s.</small>
            </label>
          </div>
        </div>

        <div class="account-tab-panel" data-provider-panel="balance" role="tabpanel">
            <div class="account-form-grid">
                <label><span>Số dư cảnh báo (*)</span><input type="number" name="so_du_canh_bao" value="0"></label>
                <label><span>Số dư tối thiểu nạp (*)</span><input type="number" name="so_du_toi_thieu_nap" value="0"></label>
                <label><span>Số tiền nạp/lần (*)</span><input type="number" name="so_tien_nap_moi_lan" value="0"></label>
                <label class="account-check"><input type="checkbox" name="chay_nhieu_tk_con" value="1"> <span>Chạy nhiều tài khoản</span></label>
                <label class="account-check"><input type="checkbox" name="tu_dong_nap_tien" value="1"> <span>Tự động nạp tiền</span></label>
                <label class="account-check"><input type="checkbox" name="kich_hoat_nap_cham" value="1"> <span>Kích hoạt nạp chậm</span></label>
            </div>
        </div>

        <div class="account-tab-panel" data-provider-panel="circuit" role="tabpanel">
           <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
             <strong style="color: #0f172a; font-size: 13px; display: block; margin-bottom: 4px;">⚡ Cầu dao tự động (Circuit Breaker) &amp; SLA Xử lý đơn treo</strong>
             <p style="font-size: 12px; color: #475569; margin: 0; line-height: 1.5;">
               Tự động tạm dừng cổng NCC khi bị lỗi liên tiếp để chống nghẽn hàng loạt, đồng thời quy định số lần kiểm tra lại và cơ chế tự động hoàn tiền ví bảo vệ khách hàng.
             </p>
           </div>

           <div class="account-form-grid">
               <label>
                 <span>Số lần kiểm tra lại tối đa (SLA Retries)</span>
                 <input type="number" name="so_lan_kiem_tra_lai" value="6" min="1" max="20">
                 <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Worker tự kiểm tra giãn cách (10s, 30s, 1p, 3p, 5p, 15p). Mặc định: 6 lần (~25 phút).</small>
               </label>
               <label>
                 <span>Hành động khi hết thời gian chờ SLA</span>
                 <select name="hanh_dong_khi_het_gio">
                   <option value="TU_DONG_HOAN_TIEN">Tự động Hủy &amp; Hoàn 100% tiền ví cho khách (Khuyên dùng)</option>
                   <option value="MANUAL_REVIEW">Chuyển sang Chờ duyệt thủ công (MANUAL_REVIEW)</option>
                 </select>
                 <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Tự động hoàn tiền giúp khách hàng không phải chờ đợi lâu khi NCC bị treo.</small>
               </label>

               <label>
                 <span>GD lỗi liên tiếp để ngắt cầu dao</span>
                 <input type="number" name="so_gd_that_bai_lien_tiep" value="0">
                 <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Số đơn lỗi liên tiếp sẽ tự động tạm dừng cổng này (0 = Tắt).</small>
               </label>
               <label>
                 <span>Thời gian tạm đóng (giây)</span>
                 <input type="number" name="thoi_gian_dong" value="0">
                 <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Thời gian tạm dừng trước khi mở lại (0 = Chờ Admin kích hoạt lại).</small>
               </label>
               <label class="account-form-grid__full">
                 <span>Mã lỗi bỏ qua không tính vào cầu dao (cách nhau dấu phẩy)</span>
                 <input type="text" name="ma_loi_bo_qua" placeholder="Ví dụ: 35, 99">
                 <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Các lỗi phía khách (sai số, sai nhà mạng...) không tính vào lỗi sập cổng NCC.</small>
               </label>

               <label class="account-check account-form-grid__full"><input type="checkbox" name="tinh_gd_loi" value="1"> <span>Bật quét lỗi nâng cao theo tỉ lệ &amp; cửa sổ thời gian</span></label>
               <label><span>Số GD nghi ngờ</span><input type="number" name="so_gd_nghi_ngo" value="0"><small style="color: #64748b; font-size: 11px;">Ngưỡng GD pending để theo dõi.</small></label>
               <label><span>Thời gian quét (giây)</span><input type="number" name="thoi_gian_quet" value="0"><small style="color: #64748b; font-size: 11px;">Cửa sổ thời gian thống kê.</small></label>
               <label><span>Tổng số GD quét</span><input type="number" name="tong_so_gd_quet" value="0"><small style="color: #64748b; font-size: 11px;">Số GD tối thiểu để xét tỉ lệ.</small></label>
               <label><span>Số GD lỗi tối đa</span><input type="number" name="so_gd_loi_toi_da" value="0"><small style="color: #64748b; font-size: 11px;">Ngưỡng lỗi tối đa trong cửa sổ quét.</small></label>
           </div>
        </div>

        <div class="account-tab-panel" data-provider-panel="telegram" role="tabpanel">
            <div class="account-form-grid">
                <label class="account-check account-form-grid__full"><input type="checkbox" name="bat_canh_bao" value="1"> <span>Bật cảnh báo Telegram</span></label>
                <label>
                  <span>Cảnh báo xử lý chậm (giây)</span>
                  <input type="number" name="canh_bao_xu_ly_cham_giay" value="0">
                  <small style="color: #64748b; font-size: 11px; margin-top: 2px;">Nếu gọi NCC mất quá số giây này (ví dụ: 3s), hệ thống sẽ gửi cảnh báo Telegram (0 = Tắt).</small>
                </label>
                <label><span>Kênh cảnh báo</span><select name="kenh_canh_bao"><option value="Telegram">Telegram</option><option value="Email">Email</option></select></label>
                <label>
                  <span>Chọn nhóm nhận thông báo</span>
                  <select name="nhom_canh_bao_chat_id" id="providerTelegramChatIdSelect">
                    <option value="">-- Mặc định (Theo cấu hình hệ thống) --</option>
                    @if (isset($telegramConfig))
                      @foreach ($telegramConfig->layDanhSachKenhHopLe() as $chan)
                        <option value="{{ $chan['chat_id'] }}">{{ $chan['ten_kenh'] }} ({{ $chan['chat_id'] }})</option>
                      @endforeach
                    @endif
                    <option value="__custom__">-- Tùy chỉnh Chat ID khác... --</option>
                  </select>
                </label>
                <label id="providerCustomChatIdWrapper" style="display: none;">
                  <span>ChatID tùy chỉnh</span>
                  <input type="text" id="providerCustomChatIdInput" placeholder="Ví dụ: -1001234567890">
                </label>
                <label class="account-form-grid__full"><span>Bỏ qua mã lỗi</span><input type="text" name="bo_qua_ma_loi_ncc"></label>
                <label class="account-form-grid__full"><span>Bỏ qua nội dung lỗi</span><input type="text" name="bo_qua_message_ncc"></label>
            </div>
        </div>


        <footer class="account-modal__footer" style="display: flex; align-items: center; justify-content: space-between;">
          <div><button class="account-btn account-btn--muted" type="button" id="btnTestConnectionModal" hidden>⚡ Kiểm tra kết nối</button></div>
          <div style="display: flex; gap: 8px;">
            <button class="account-btn account-btn--muted" type="button" data-provider-modal-close>Đóng</button>
            <button class="account-btn account-btn--primary" type="submit" data-provider-submit>Lưu nhà cung cấp</button>
          </div>
        </footer>
      </form>
    </section>
  </div>

  {{-- THANH TÁC VỤ NỔI KHI CHỌN NHIỀU NHÀ CUNG CẤP --}}
  <div class="account-bulk-bar" id="bulkActionBar" hidden>
    <div class="account-bulk-bar__info">
      <span>Đã chọn:</span>
      <span class="account-bulk-bar__badge" id="bulkSelectedCount">0</span>
    </div>
    <div class="account-bulk-bar__actions">
      <button type="button" class="account-bulk-btn account-bulk-btn--ghost" id="bulkDeselectBtn">Bỏ chọn</button>
      <button type="button" class="account-bulk-btn account-bulk-btn--danger" id="bulkDeleteBtn">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2;">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
        <span>Xóa các mục đã chọn</span>
      </button>
    </div>
  </div>

  {{-- MODAL XÁC NHẬN XÓA NHIỀU --}}
  <div class="account-modal" data-provider-modal="bulk-delete" hidden>
    <div class="account-modal__backdrop" data-provider-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="bulkProviderDeleteTitle">
      <header class="account-modal__header">
        <h2 id="bulkProviderDeleteTitle">Xóa nhiều nhà cung cấp</h2>
        <button type="button" class="account-modal__close" data-provider-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" action="{{ route('admin.providers.bulk-delete') }}" id="bulkProviderDeleteForm">
        @csrf
        <div id="bulkProviderDeleteHiddenInputs"></div>
        <p class="account-confirm-text" style="padding: 20px; margin: 0; color: var(--admin-slate-700);">
          Bạn có chắc muốn xóa <strong id="bulkProviderDeleteConfirmCount" style="color: #ef4444;">0</strong> nhà cung cấp đã chọn? Thao tác này không thể hoàn tác.
        </p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-provider-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xác nhận xóa</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- MODAL KẾT QUẢ KIỂM TRA KẾT NỐI --}}
  <div class="account-modal" data-provider-modal="test-result" hidden>
    <div class="account-modal__backdrop" data-provider-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="testResultTitle">
      <header class="account-modal__header">
        <h2 id="testResultTitle">Kết quả kiểm tra kết nối</h2>
        <button type="button" class="account-modal__close" data-provider-modal-close aria-label="Đóng">×</button>
      </header>
      <div class="account-modal__body" style="padding: 24px;">
        <div id="testResultIcon" style="font-size: 46px; text-align: center; margin-bottom: 12px;"></div>
        <div id="testResultMessage" style="font-size: 16px; font-weight: 800; text-align: center; margin-bottom: 16px;"></div>
        <div id="testResultDetails" style="background: var(--admin-slate-50, #f8fafc); border: 1px solid var(--admin-slate-200, #e2e8f0); border-radius: 8px; padding: 14px; font-size: 13px; display: grid; gap: 8px;">
        </div>
      </div>
      <footer class="account-modal__footer">
        <button class="account-btn account-btn--primary" type="button" data-provider-modal-close style="width: 100%;">Đã hiểu</button>
      </footer>
    </section>
  </div>

  {{-- THANH TÁC VỤ NỔI KHI CHỌN NHIỀU NHÀ CUNG CẤP --}}
  <div class="account-bulk-bar" id="bulkActionBar">
    <div class="account-bulk-bar__info">
      <span>Đã chọn:</span>
      <span class="account-bulk-bar__badge" id="bulkSelectedCount">0</span>
    </div>
    <div class="account-bulk-bar__actions">
      <button type="button" class="account-bulk-btn account-bulk-btn--ghost" id="bulkDeselectBtn">Bỏ chọn</button>
      <button type="button" class="account-bulk-btn account-bulk-btn--danger" id="bulkDeleteBtn">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2;">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
        <span>Xóa các mục đã chọn</span>
      </button>
    </div>
  </div>

  {{-- MODAL XÁC NHẬN XÓA NHIỀU NHÀ CUNG CẤP --}}
  <div class="account-modal" data-provider-modal="bulk-delete" hidden>
    <div class="account-modal__backdrop" data-provider-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="bulkProviderDeleteTitle">
      <header class="account-modal__header">
        <h2 id="bulkProviderDeleteTitle">Xóa nhiều nhà cung cấp</h2>
        <button type="button" class="account-modal__close" data-provider-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" action="{{ route('admin.providers.bulk-delete') }}" id="bulkProviderDeleteForm">
        @csrf
        <div id="bulkProviderDeleteHiddenInputs"></div>
        <p class="account-confirm-text" style="padding: 20px; margin: 0; color: var(--admin-slate-700);">
          Bạn có chắc muốn xóa <strong id="bulkProviderDeleteConfirmCount" style="color: #ef4444;">0</strong> nhà cung cấp đã chọn? Cấu hình kết nối API đi kèm cũng sẽ bị xóa. Thao tác này không thể hoàn tác.
        </p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-provider-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xác nhận xóa</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- MODAL XÁC NHẬN XÓA (ĐỒNG NHẤT VỚI HỆ THỐNG) --}}
  <div class="account-modal" data-provider-modal="delete" hidden>
    <div class="account-modal__backdrop" data-provider-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="providerDeleteModalTitle">
      <header class="account-modal__header">
        <h2 id="providerDeleteModalTitle">Xóa nhà cung cấp</h2>
        <button type="button" class="account-modal__close" data-provider-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" data-provider-delete-form>
        @csrf
        @method('DELETE')
        <p class="account-confirm-text" style="padding: 20px; margin: 0; color: var(--admin-slate-700);">
          Bạn có chắc muốn xóa nhà cung cấp <strong data-provider-delete-name></strong>? Thao tác này không thể hoàn tác nếu không có ràng buộc dữ liệu.
        </p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-provider-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xóa nhà cung cấp</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- FORM ẨN ĐỔI TRẠNG THÁI --}}
  <form method="POST" data-provider-toggle-form hidden>
    @csrf
    @method('PATCH')
  </form>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
  const filterToggle = document.querySelector("[data-account-filter-toggle]");
  const filter = document.querySelector("[data-account-filter]");
  const rows = [...document.querySelectorAll("[data-provider-row]")];

  // 1. Điều khiển đóng/mở bộ lọc
  filterToggle?.addEventListener("click", () => {
    const isOpen = filterToggle.getAttribute("aria-expanded") === "true";
    const section = filterToggle.closest(".account-filter-section");
    filterToggle.setAttribute("aria-expanded", String(!isOpen));
    section?.classList.toggle("is-open", !isOpen);
    if (filter) {
      filter.hidden = isOpen;
    }
  });

  // 2. Chọn dòng trên table khi click
  rows.forEach((row) => {
    row.addEventListener("click", (event) => {
      if (event.target.closest(".account-row-actions")) return;
      rows.forEach((item) => item.classList.remove("is-selected"));
      row.classList.add("is-selected");
    });
  });

  // 3. Dropdown hành động (position fixed theo chuẩn)
  document.querySelectorAll("[data-row-action]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.stopPropagation();
      const wrapper = button.closest(".account-row-actions");
      const menu = wrapper?.querySelector(".account-row-menu");
      if (!wrapper || !menu) return;

      document.querySelectorAll(".account-row-actions.is-open").forEach((openWrapper) => {
        if (openWrapper === wrapper) return;
        openWrapper.classList.remove("is-open");
        openWrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
      });

      const isOpen = wrapper.classList.toggle("is-open");
      menu.hidden = !isOpen;

      if (isOpen) {
        const rect = button.getBoundingClientRect();
        menu.style.left = `${rect.left}px`;
        menu.style.top = `${rect.bottom + 6}px`;
      }
    });
  });

  function closeAllRowMenus() {
    document.querySelectorAll(".account-row-actions.is-open").forEach((wrapper) => {
      wrapper.classList.remove("is-open");
      wrapper.querySelector(".account-row-menu")?.setAttribute("hidden", "");
    });
  }

  document.addEventListener("click", (event) => {
    if (!event.target.closest(".account-row-actions")) {
      closeAllRowMenus();
    }
  });

  window.addEventListener("scroll", closeAllRowMenus, { passive: true, capture: true });
  document.addEventListener("scroll", closeAllRowMenus, { passive: true, capture: true });
  window.addEventListener("resize", closeAllRowMenus, { passive: true });

  // 4. Modal elements
  const formModal = document.querySelector('[data-provider-modal="form"]');
  const deleteModal = document.querySelector('[data-provider-modal="delete"]');
  const testResultModal = document.querySelector('[data-provider-modal="test-result"]');
  const providerForm = document.querySelector("[data-provider-form]");
  const providerFormMethod = document.querySelector("[data-provider-form-method]");
  const providerModalTitle = document.querySelector("[data-provider-modal-title]");
  const providerSubmitBtn = document.querySelector("[data-provider-submit]");
  const btnTestConnectionModal = document.getElementById("btnTestConnectionModal");
  const deleteForm = document.querySelector("[data-provider-delete-form]");
  const deleteProviderName = document.querySelector("[data-provider-delete-name]");
  const toggleForm = document.querySelector("[data-provider-toggle-form]");

  let currentTestingUrl = null;
  let currentTestingName = null;

  if (providerForm) {
    providerForm.dataset.storeUrl = providerForm.getAttribute("action");
  }

  function parseProvider(button) {
    try {
      return JSON.parse(button.dataset.provider || "{}");
    } catch (e) {
      return {};
    }
  }

  function closeModals() {
    document.querySelectorAll("[data-provider-modal]").forEach((modal) => {
      modal.hidden = true;
    });
    document.body.classList.remove("account-modal-open");
  }

  function openModal(modal) {
    if (!modal) return;
    modal.hidden = false;
    document.body.classList.add("account-modal-open");
  }

  function setField(name, value) {
    const field = providerForm?.querySelector(`[name="${name}"]`);
    if (field) {
      if (field.type === "checkbox") {
        field.checked = Boolean(value);
      } else {
        field.value = value ?? "";
      }
    }
  }

  // 5. Tab Switching trong Modal Form
  document.querySelectorAll("[data-provider-tab]").forEach((tabBtn) => {
    tabBtn.addEventListener("click", () => {
      const tabName = tabBtn.dataset.providerTab;
      document.querySelectorAll("[data-provider-tab]").forEach((btn) => {
        btn.classList.toggle("is-active", btn === tabBtn);
        btn.setAttribute("aria-selected", btn === tabBtn ? "true" : "false");
      });
      document.querySelectorAll("[data-provider-panel]").forEach((panel) => {
        panel.classList.toggle("is-active", panel.dataset.providerPanel === tabName);
      });
    });
  });

  // Đóng modal qua nút close / backdrop
  document.querySelectorAll("[data-provider-modal-close]").forEach((btn) => {
    btn.addEventListener("click", closeModals);
  });

  // Đóng modal khi nhấn phím ESC
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" || event.key === "Esc") {
      closeModals();
      closeAllRowMenus();
    }
  });

  const pwdInput = document.getElementById("providerPasswordInput");
  const apiPwdInput = document.getElementById("providerApiPasswordInput");
  const privKeyInput = document.getElementById("providerPrivateKeyInput");
  const pwdInd = document.getElementById("passwordIndicator");
  const apiPwdInd = document.getElementById("apiPasswordIndicator");
  const privKeyInd = document.getElementById("privateKeyIndicator");

  // Tự động xóa placeholder khi user bấm vào để gõ mật khẩu mới
  [pwdInput, apiPwdInput, privKeyInput].forEach((inp) => {
    if (!inp) return;
    inp.addEventListener("focus", () => {
      if (inp.value === "••••••••••••") {
        inp.value = "";
      }
    });
    inp.addEventListener("blur", () => {
      if (inp.value === "" && inp.dataset.hasSaved === "true") {
        inp.value = "••••••••••••";
      }
    });
  });

  // Mở modal Thêm mới
  document.querySelectorAll('[data-provider-modal-open="create"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      providerForm?.reset();
      if (providerForm && providerForm.dataset.storeUrl) {
        providerForm.action = providerForm.dataset.storeUrl;
      }
      if (providerFormMethod) providerFormMethod.value = "POST";
      if (providerModalTitle) providerModalTitle.textContent = "Thêm mới nhà cung cấp";
      if (providerSubmitBtn) {
        providerSubmitBtn.hidden = false;
        providerSubmitBtn.textContent = "Lưu nhà cung cấp";
      }
      if (btnTestConnectionModal) {
        btnTestConnectionModal.hidden = true;
      }

      // Reset indicators & masked passwords
      if (pwdInput) { pwdInput.value = ""; delete pwdInput.dataset.hasSaved; }
      if (apiPwdInput) { apiPwdInput.value = ""; delete apiPwdInput.dataset.hasSaved; }
      if (privKeyInput) { privKeyInput.value = ""; delete privKeyInput.dataset.hasSaved; }
      if (pwdInd) pwdInd.style.display = "none";
      if (apiPwdInd) apiPwdInd.style.display = "none";
      if (privKeyInd) privKeyInd.style.display = "none";

      const teleSelect = document.getElementById("providerTelegramChatIdSelect");
      const customWrapper = document.getElementById("providerCustomChatIdWrapper");
      const customInput = document.getElementById("providerCustomChatIdInput");
      if (teleSelect) teleSelect.selectedIndex = 0;
      if (customWrapper) customWrapper.style.display = "none";
      if (customInput) customInput.value = "";

      // Kích hoạt lại toàn bộ inputs
      providerForm?.querySelectorAll("input, select").forEach((input) => {
        input.disabled = false;
      });

      // Mở lại tab đầu tiên
      document.querySelector('[data-provider-tab="general"]')?.click();
      openModal(formModal);
    });
  });

  // Lắng nghe thay đổi dropdown chọn nhóm Telegram
  const providerTeleSelect = document.getElementById("providerTelegramChatIdSelect");
  const providerCustomWrapper = document.getElementById("providerCustomChatIdWrapper");
  const providerCustomInput = document.getElementById("providerCustomChatIdInput");

  providerTeleSelect?.addEventListener("change", () => {
    if (providerTeleSelect.value === "__custom__") {
      if (providerCustomWrapper) providerCustomWrapper.style.display = "block";
      providerCustomInput?.focus();
    } else {
      if (providerCustomWrapper) providerCustomWrapper.style.display = "none";
    }
  });

  providerForm?.addEventListener("submit", () => {
    if (providerTeleSelect && providerTeleSelect.value === "__custom__") {
      const customVal = (providerCustomInput?.value || "").trim();
      let opt = providerTeleSelect.querySelector('option[value="__custom__"]');
      if (opt) {
        opt.value = customVal;
      }
    }
  });

  // Mở modal Xem chi tiết / Chỉnh sửa
  document.querySelectorAll('[data-provider-modal-open="edit"], [data-provider-modal-open="view"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      const mode = btn.dataset.providerModalOpen;
      const data = parseProvider(btn);

      providerForm?.reset();
      if (providerForm && data.urls?.update) {
        providerForm.action = data.urls.update;
      }
      if (providerFormMethod) providerFormMethod.value = "PUT";
      if (providerModalTitle) {
        providerModalTitle.textContent = mode === "view"
          ? `Chi tiết nhà cung cấp: ${data.ten_ncc}`
          : `Chỉnh sửa nhà cung cấp: ${data.ten_ncc}`;
      }
      if (providerSubmitBtn) {
        providerSubmitBtn.hidden = mode === "view";
        providerSubmitBtn.textContent = "Cập nhật nhà cung cấp";
      }

      // Hiển thị nút test kết nối khi sửa/xem
      if (btnTestConnectionModal && data.urls?.test_connection) {
        btnTestConnectionModal.hidden = false;
        currentTestingUrl = data.urls.test_connection;
        currentTestingName = data.ten_ncc;
      }

      // Điền dữ liệu các trường
      setField("id", data.id);
      setField("ma_ncc", data.ma_ncc);
      setField("ten_ncc", data.ten_ncc);
      setField("nha_cung_cap_cha_id", data.nha_cung_cap_cha_id);
      setField("trang_thai", data.trang_thai);
      setField("so_dien_thoai", data.so_dien_thoai);
      setField("email", data.email);

      setField("username", data.username);
      setField("api_user", data.api_user);
      setField("api_url", data.api_url);
      setField("cau_hinh_ma_gd", data.cau_hinh_ma_gd);
      setField("public_key", data.public_key);
      setField("public_key_file", data.public_key_file);
      setField("timeout_he_thong", data.timeout_he_thong);
      setField("timeout_ncc", data.timeout_ncc);

      // Xử lý hiển thị dấu chấm mật khẩu đã lưu
      if (data.has_password) {
        if (pwdInput) { pwdInput.value = "••••••••••••"; pwdInput.dataset.hasSaved = "true"; }
        if (pwdInd) pwdInd.style.display = "inline";
      } else {
        if (pwdInput) { pwdInput.value = ""; delete pwdInput.dataset.hasSaved; }
        if (pwdInd) pwdInd.style.display = "none";
      }

      if (data.has_api_password) {
        if (apiPwdInput) { apiPwdInput.value = "••••••••••••"; apiPwdInput.dataset.hasSaved = "true"; }
        if (apiPwdInd) apiPwdInd.style.display = "inline";
      } else {
        if (apiPwdInput) { apiPwdInput.value = ""; delete apiPwdInput.dataset.hasSaved; }
        if (apiPwdInd) apiPwdInd.style.display = "none";
      }

      if (data.has_private_key_file) {
        if (privKeyInput) { privKeyInput.value = "••••••••••••"; privKeyInput.dataset.hasSaved = "true"; }
        if (privKeyInd) privKeyInd.style.display = "inline";
      } else {
        if (privKeyInput) { privKeyInput.value = ""; delete privKeyInput.dataset.hasSaved; }
        if (privKeyInd) privKeyInd.style.display = "none";
      }

      setField("so_du_canh_bao", data.so_du_canh_bao);
      setField("so_du_toi_thieu_nap", data.so_du_toi_thieu_nap);
      setField("so_tien_nap_moi_lan", data.so_tien_nap_moi_lan);
      setField("chay_nhieu_tk_con", data.chay_nhieu_tk_con);
      setField("tu_dong_nap_tien", data.tu_dong_nap_tien);
      setField("kich_hoat_nap_cham", data.kich_hoat_nap_cham);

      setField("so_gd_that_bai_lien_tiep", data.so_gd_that_bai_lien_tiep);
      setField("thoi_gian_dong", data.thoi_gian_dong);
      setField("ma_loi_bo_qua", data.ma_loi_bo_qua);
      setField("tinh_gd_loi", data.tinh_gd_loi);
      setField("so_gd_nghi_ngo", data.so_gd_nghi_ngo);
      setField("thoi_gian_quet", data.thoi_gian_quet);
      setField("tong_so_gd_quet", data.tong_so_gd_quet);
      setField("so_gd_loi_toi_da", data.so_gd_loi_toi_da);
      setField("so_lan_kiem_tra_lai", data.so_lan_kiem_tra_lai ?? 6);
      setField("hanh_dong_khi_het_gio", data.hanh_dong_khi_het_gio ?? "TU_DONG_HOAN_TIEN");

      setField("bat_canh_bao", data.bat_canh_bao);
      setField("canh_bao_xu_ly_cham_giay", data.canh_bao_xu_ly_cham_giay);
      setField("kenh_canh_bao", data.kenh_canh_bao);
      setField("bo_qua_ma_loi_ncc", data.bo_qua_ma_loi_ncc);
      setField("bo_qua_message_ncc", data.bo_qua_message_ncc);

      // Xử lý chọn nhóm Telegram cảnh báo
      const teleSelect = document.getElementById("providerTelegramChatIdSelect");
      const customWrapper = document.getElementById("providerCustomChatIdWrapper");
      const customInput = document.getElementById("providerCustomChatIdInput");
      if (teleSelect && customWrapper && customInput) {
        const currentChatId = String(data.nhom_canh_bao_chat_id || "").trim();
        let optionFound = false;
        for (let i = 0; i < teleSelect.options.length; i++) {
          if (teleSelect.options[i].value !== "__custom__" && teleSelect.options[i].value === currentChatId) {
            teleSelect.selectedIndex = i;
            optionFound = true;
            break;
          }
        }
        if (!optionFound && currentChatId !== "") {
          teleSelect.value = "__custom__";
          customInput.value = currentChatId;
          customWrapper.style.display = "block";
        } else {
          if (!optionFound) teleSelect.selectedIndex = 0;
          customInput.value = "";
          customWrapper.style.display = "none";
        }
      }

      // Disable inputs nếu chế độ View
      providerForm?.querySelectorAll("input, select").forEach((input) => {
        input.disabled = mode === "view";
      });

      // Xử lý chọn link mẫu nhanh
      const providerPresetApiUrlSelect = document.getElementById("providerPresetApiUrlSelect");
      const providerApiUrlInput = document.getElementById("providerApiUrlInput");
      providerPresetApiUrlSelect?.addEventListener("change", (e) => {
        if (e.target.value && providerApiUrlInput) {
          providerApiUrlInput.value = e.target.value;
        }
      });

      // Mở lại tab đầu tiên
      document.querySelector('[data-provider-tab="general"]')?.click();
      openModal(formModal);
    });
  });

  // 6. Xử lý chức năng KIỂM TRA KẾT NỐI API
  async function runTestConnection(url, providerName) {
    const icon = document.getElementById("testResultIcon");
    const msg = document.getElementById("testResultMessage");
    const details = document.getElementById("testResultDetails");

    icon.textContent = "⏳";
    msg.style.color = "var(--admin-slate-800, #0f172a)";
    msg.textContent = `Đang kết nối tới ${providerName}...`;
    details.innerHTML = '<div>Đang gửi yêu cầu xác thực JWT & kiểm tra endpoint API...</div>';
    openModal(testResultModal);

    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": "{{ csrf_token() }}",
          "Accept": "application/json",
        },
      });
      const data = await res.json();
      if (data.success) {
        icon.textContent = "✅";
        msg.style.color = "#16a34a";
        msg.textContent = data.message || "Kết nối thành công!";
        details.innerHTML = `
          <div><strong>🏢 Nhà cung cấp:</strong> ${providerName}</div>
          <div><strong>⏱️ Độ trễ phản hồi (Latency):</strong> <span style="color:#0284c7; font-weight:800;">${data.latency_ms} ms</span></div>
          <div><strong>🌐 Endpoint:</strong> <code>${data.endpoint || '-'}</code></div>
          <div><strong>📦 Số lượng sản phẩm NCC hỗ trợ:</strong> <span style="font-weight:700;">${data.product_count ?? 'N/A'}</span></div>
        `;
      } else {
        icon.textContent = "❌";
        msg.style.color = "#dc2626";
        msg.textContent = data.message || "Kết nối thất bại!";
        details.innerHTML = `
          <div><strong>🏢 Nhà cung cấp:</strong> ${providerName}</div>
          <div><strong>⏱️ Thời gian phản hồi:</strong> ${data.latency_ms ?? 0} ms</div>
          <div><strong>⚠️ Chi tiết lỗi:</strong> <span style="color:#dc2626; font-weight:600;">${data.error || data.message}</span></div>
        `;
      }
    } catch (err) {
      icon.textContent = "❌";
      msg.style.color = "#dc2626";
      msg.textContent = "Lỗi khi gửi yêu cầu kiểm tra kết nối";
      details.innerHTML = `<div>${err.message}</div>`;
    }
  }

  // Click test từ Menu Hành động
  document.querySelectorAll("[data-provider-test-btn]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const url = btn.dataset.url;
      const name = btn.dataset.name;
      if (url) runTestConnection(url, name);
    });
  });

  // Click test từ trong Modal Form
  btnTestConnectionModal?.addEventListener("click", () => {
    if (currentTestingUrl) {
      runTestConnection(currentTestingUrl, currentTestingName);
    }
  });

  // Mở modal Xóa
  document.querySelectorAll('[data-provider-modal-open="delete"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      const data = parseProvider(btn);
      if (deleteForm && data.urls?.destroy) {
        deleteForm.action = data.urls.destroy;
      }
      if (deleteProviderName) {
        deleteProviderName.textContent = data.ten_ncc || data.ma_ncc;
      }
      openModal(deleteModal);
    });
  });

  // Thao tác đổi trạng thái nhanh / reset circuit
  document.querySelectorAll("[data-provider-action-submit]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const url = btn.dataset.providerActionSubmit;
      const method = btn.dataset.method || "POST";
      if (toggleForm && url) {
        let methodInput = toggleForm.querySelector('input[name="_method"]');
        if (methodInput) {
          methodInput.value = method;
        }
        toggleForm.action = url;
        toggleForm.submit();
      }
    });
  });

  // Xử lý Checkbox Chọn nhiều & Thanh tác vụ nổi (Bulk Action Bar)
  const selectAllCheckbox = document.querySelector("[data-select-all]");
  const itemCheckboxes = document.querySelectorAll(".bulk-item-checkbox");
  const bulkActionBar = document.getElementById("bulkActionBar");
  const bulkSelectedCount = document.getElementById("bulkSelectedCount");
  const bulkDeselectBtn = document.getElementById("bulkDeselectBtn");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const bulkDeleteHiddenInputs = document.getElementById("bulkProviderDeleteHiddenInputs");
  const bulkDeleteConfirmCount = document.getElementById("bulkProviderDeleteConfirmCount");
  const bulkDeleteModal = document.querySelector('[data-provider-modal="bulk-delete"]');

  function updateBulkBar() {
    const checked = [...itemCheckboxes].filter(cb => cb.checked);
    const count = checked.length;

    if (bulkSelectedCount) bulkSelectedCount.textContent = count;
    if (bulkDeleteConfirmCount) bulkDeleteConfirmCount.textContent = count;

    if (count > 0) {
      bulkActionBar?.classList.add("is-visible");
    } else {
      bulkActionBar?.classList.remove("is-visible");
    }

    if (selectAllCheckbox) {
      selectAllCheckbox.checked = count > 0 && count === itemCheckboxes.length;
      selectAllCheckbox.indeterminate = count > 0 && count < itemCheckboxes.length;
    }
  }

  selectAllCheckbox?.addEventListener("change", (e) => {
    itemCheckboxes.forEach(cb => cb.checked = e.target.checked);
    updateBulkBar();
  });

  itemCheckboxes.forEach(cb => {
    cb.addEventListener("change", updateBulkBar);
    cb.addEventListener("click", (e) => e.stopPropagation());
  });

  bulkDeselectBtn?.addEventListener("click", () => {
    itemCheckboxes.forEach(cb => cb.checked = false);
    if (selectAllCheckbox) selectAllCheckbox.checked = false;
    updateBulkBar();
  });

  bulkDeleteBtn?.addEventListener("click", () => {
    const checked = [...itemCheckboxes].filter(cb => cb.checked);
    if (checked.length === 0) return;

    if (bulkDeleteHiddenInputs) {
      bulkDeleteHiddenInputs.innerHTML = checked.map(cb => `<input type="hidden" name="ids[]" value="${cb.value}">`).join('');
    }
    openModal(bulkDeleteModal);
  });
});
</script>
@endpush
