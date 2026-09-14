@extends('admin.layout')

@section('title', 'tv9tech - Quản lý Sản phẩm Nhà Cung Cấp')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    /* Thanh tác vụ nổi (Floating Bulk Bar) */
    .bulk-action-bar {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(120%);
      background: var(--admin-slate-900, #0f172a);
      color: #ffffff;
      padding: 12px 24px;
      border-radius: 12px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.2);
      display: flex;
      align-items: center;
      gap: 16px;
      z-index: 50;
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .bulk-action-bar.is-visible {
      transform: translateX(-50%) translateY(0);
    }
    .bulk-action-bar__text {
      font-size: 14px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .bulk-action-bar__count {
      background: var(--admin-emerald-500, #10b981);
      color: #ffffff;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }
    .bulk-action-bar__btn {
      background: rgba(255, 255, 255, 0.15);
      color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.2);
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background 0.15s ease;
    }
    .bulk-action-bar__btn:hover {
      background: rgba(255, 255, 255, 0.25);
    }
    .bulk-action-bar__btn--danger {
      background: #ef4444;
      border-color: #dc2626;
    }
    .bulk-action-bar__btn--danger:hover {
      background: #dc2626;
    }
    .badge-service-topup {
      background: #ecfdf5;
      color: #059669;
      border: 1px solid #a7f3d0;
      padding: 2px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .badge-service-data {
      background: #eff6ff;
      color: #2563eb;
      border: 1px solid #bfdbfe;
      padding: 2px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .badge-service-card {
      background: #faf5ff;
      color: #7e22ce;
      border: 1px solid #e9d5ff;
      padding: 2px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .badge-mapped {
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
      padding: 3px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .badge-unmapped {
      background: #fffbeb;
      color: #b45309;
      border: 1px solid #fde68a;
      padding: 3px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      cursor: pointer;
    }
    .badge-unmapped:hover {
      background: #fef3c7;
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
          <a href="{{ route('admin.providers') }}">Nhà cung cấp</a>
          <span>/</span>
          <strong>Sản phẩm Nhà Cung Cấp</strong>
        </nav>
        <h1>Quản lý Sản phẩm Nhà Cung Cấp</h1>
        <p>Quản lý toàn bộ danh mục mã sản phẩm từ API của Nhà Cung Cấp và cấu hình ánh xạ sang Sản phẩm Web.</p>
      </div>

      <div class="account-actions">
        {{-- NÚT TỰ ĐỘNG ÁNH XẠ TOÀN BỘ MÃ CHƯA MAP --}}
        <form method="POST" action="{{ route('admin.provider-products.auto-map') }}" style="display: inline;" onsubmit="return confirm('Bạn có muốn hệ thống tự động tìm và ánh xạ toàn bộ các mã NCC chưa liên kết sang Sản phẩm Web phù hợp?');">
          @csrf
          <button class="account-btn account-btn--muted" type="submit" style="color: #16a34a; border-color: #bbf7d0; background: #f0fdf4;">
            <span>🔗 Tự động ánh xạ</span>
          </button>
        </form>

        {{-- NÚT LẤY SẢN PHẨM TỪ API NHÀ MẠNG/NCC --}}
        <button class="account-btn account-btn--muted" type="button" data-prov-prod-modal-open="sync" style="color: #0d9488; border-color: #99f6e4; background: #f0fdfa;">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2;">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" y1="15" x2="12" y2="3" />
          </svg>
          <span>📥 Lấy SP từ API</span>
        </button>

        {{-- NÚT THÊM MỚI THỦ CÔNG --}}
        <button class="account-btn account-btn--primary" type="button" data-prov-prod-modal-open="create">
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
    <section class="account-card account-filter-section {{ request()->hasAny(['q', 'nha_cung_cap_id', 'loai_dich_vu_ncc', 'ma_nha_mang_ncc', 'trang_thai_map', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['q', 'nha_cung_cap_id', 'loai_dich_vu_ncc', 'ma_nha_mang_ncc', 'trang_thai_map', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      <form class="account-filter" method="GET" action="{{ route('admin.provider-products') }}" data-account-filter {{ request()->hasAny(['q', 'nha_cung_cap_id', 'loai_dich_vu_ncc', 'ma_nha_mang_ncc', 'trang_thai_map', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Từ khóa</span>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã sản phẩm NCC, tên SP Web...">
        </label>
        <label>
          <span>Nhà cung cấp</span>
          <select name="nha_cung_cap_id">
            <option value="">Tất cả NCC</option>
            @foreach ($providers as $p)
              <option value="{{ $p->id }}" @selected(($filters['nha_cung_cap_id'] ?? '') == $p->id)>{{ $p->ten_ncc }} ({{ $p->ma_ncc }})</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Dịch vụ NCC</span>
          <select name="loai_dich_vu_ncc">
            <option value="">Tất cả dịch vụ</option>
            <option value="MOBILE_TOPUP" @selected(($filters['loai_dich_vu_ncc'] ?? '') === 'MOBILE_TOPUP')>Nạp tiền điện thoại</option>
            <option value="TOPUP_DATA" @selected(($filters['loai_dich_vu_ncc'] ?? '') === 'TOPUP_DATA')>Nạp Data 3G/4G</option>
            <option value="PIN_CODE" @selected(($filters['loai_dich_vu_ncc'] ?? '') === 'PIN_CODE')>Mua mã thẻ cào</option>
            <option value="PIN_DATA" @selected(($filters['loai_dich_vu_ncc'] ?? '') === 'PIN_DATA')>Thẻ nạp Data</option>
          </select>
        </label>
        <label>
          <span>Nhà mạng</span>
          <select name="ma_nha_mang_ncc">
            <option value="">Tất cả nhà mạng</option>
            <option value="viettel" @selected(($filters['ma_nha_mang_ncc'] ?? '') === 'viettel')>Viettel</option>
            <option value="mobifone" @selected(($filters['ma_nha_mang_ncc'] ?? '') === 'mobifone')>Mobifone</option>
            <option value="vinaphone" @selected(($filters['ma_nha_mang_ncc'] ?? '') === 'vinaphone')>Vinaphone</option>
            <option value="vietnamobile" @selected(($filters['ma_nha_mang_ncc'] ?? '') === 'vietnamobile')>Vietnamobile</option>
            <option value="gmobile" @selected(($filters['ma_nha_mang_ncc'] ?? '') === 'gmobile')>Gmobile</option>
            <option value="wintel" @selected(($filters['ma_nha_mang_ncc'] ?? '') === 'wintel')>Wintel</option>
          </select>
        </label>
        <label>
          <span>Ánh xạ SP Web</span>
          <select name="trang_thai_map">
            <option value="">Tất cả</option>
            <option value="da_map" @selected(($filters['trang_thai_map'] ?? '') === 'da_map')>Đã ánh xạ</option>
            <option value="chua_map" @selected(($filters['trang_thai_map'] ?? '') === 'chua_map')>Chưa ánh xạ</option>
          </select>
        </label>
        <label>
          <span>Trạng thái</span>
          <select name="trang_thai">
            <option value="">Tất cả</option>
            <option value="hoat_dong" @selected(($filters['trang_thai'] ?? '') === 'hoat_dong' || ($filters['trang_thai'] ?? '') === 'ACTIVE')>Hoạt động</option>
            <option value="tam_dung" @selected(($filters['trang_thai'] ?? '') === 'tam_dung' || ($filters['trang_thai'] ?? '') === 'INACTIVE')>Tạm dừng</option>
          </select>
        </label>

        <button class="account-btn account-btn--primary" type="submit">
          <x-icon name="visibility" />
          Tìm kiếm
        </button>
        <a class="account-btn account-btn--muted" href="{{ route('admin.provider-products') }}">
          Đặt lại
        </a>
      </form>
    </section>

    {{-- BẢNG DỮ LIỆU SẢN PHẨM NHÀ CUNG CẤP --}}
    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th class="th-checkbox">
                <input type="checkbox" class="account-table-checkbox" data-select-all title="Chọn tất cả">
              </th>
              <th>Hành động</th>
              <th>Mã NCC (Product Code)</th>
              <th>Nhà cung cấp</th>
              <th>Dịch vụ</th>
              <th>Nhà mạng</th>
              <th>Mệnh giá NCC</th>
              <th>Giá vốn / CK</th>
              <th>Ưu tiên</th>
              <th>Sản phẩm Web Ánh Xạ</th>
              <th>Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($providerProducts as $item)
              @php
                $payload = [
                  'id' => $item->id,
                  'ma_san_pham_ncc' => $item->ma_san_pham_ncc,
                  'nha_cung_cap_id' => $item->nha_cung_cap_id,
                  'ten_ncc' => $item->nhaCungCap?->ten_ncc ?? 'N/A',
                  'ma_nha_mang_ncc' => $item->ma_nha_mang_ncc,
                  'loai_dich_vu_ncc' => $item->loai_dich_vu_ncc,
                  'loai_thue_bao' => $item->loai_thue_bao,
                  'menh_gia_ncc' => (float) $item->menh_gia_ncc,
                  'gia_nhap' => (float) ($item->gia_nhap ?? $item->menh_gia_ncc),
                  'ty_le_chiet_khau' => (float) ($item->ty_le_chiet_khau ?? 0),
                  'muc_uu_tien' => (int) $item->muc_uu_tien,
                  'san_pham_id' => $item->san_pham_id,
                  'ten_san_pham_web' => $item->sanPham?->ten_san_pham ?? null,
                  'trang_thai' => $item->trang_thai,
                  'urls' => [
                    'update' => route('admin.provider-products.update', $item),
                    'map' => route('admin.provider-products.map', $item),
                    'toggle' => route('admin.provider-products.toggle-status', $item),
                    'destroy' => route('admin.provider-products.destroy', $item),
                  ],
                ];
              @endphp
              <tr>
                <td class="td-checkbox">
                  <input type="checkbox" class="account-table-checkbox bulk-item-checkbox" value="{{ $item->id }}">
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
                      <button type="button" data-prov-prod-modal-open="view" data-item='@json($payload)'>Xem chi tiết</button>
                      <button type="button" data-prov-prod-modal-open="map" data-item='@json($payload)'>Ánh xạ SP Web</button>
                      <button type="button" data-prov-prod-modal-open="edit" data-item='@json($payload)'>Chỉnh sửa</button>
                      <button type="button" data-prov-prod-action-submit="{{ route('admin.provider-products.toggle-status', $item) }}">
                        {{ in_array($item->trang_thai, ['ACTIVE', 'hoat_dong']) ? 'Tạm dừng' : 'Kích hoạt' }}
                      </button>
                      <button type="button" data-prov-prod-modal-open="delete" data-item='@json($payload)'>Xóa bỏ</button>
                    </div>
                  </div>
                </td>
                <td class="account-username">
                  <code style="font-family: monospace; font-size: 13px; font-weight: 600; color: var(--admin-slate-800, #1e293b);">{{ $item->ma_san_pham_ncc }}</code>
                </td>
                <td>
                  <strong>{{ $item->nhaCungCap?->ten_ncc ?? 'N/A' }}</strong>
                  <div style="font-size: 11px; color: var(--admin-slate-500, #64748b);">{{ $item->ketNoi?->ten_ket_noi }}</div>
                </td>
                <td>
                  @if ($item->loai_dich_vu_ncc === 'TOPUP_DATA')
                    <span class="badge-service-data">🌐 Nạp Data</span>
                  @elseif ($item->loai_dich_vu_ncc === 'PIN_CODE')
                    <span class="badge-service-card">🎫 Mã thẻ</span>
                  @elseif ($item->loai_dich_vu_ncc === 'PIN_DATA')
                    <span class="badge-service-card">📶 Thẻ Data</span>
                  @else
                    <span class="badge-service-topup">📱 Nạp tiền</span>
                  @endif
                </td>
                <td>
                  <span style="text-transform: capitalize; font-weight: 500;">{{ $item->ma_nha_mang_ncc ?: '-' }}</span>
                </td>
                <td>
                  <strong style="color: #047857;">{{ number_format($item->menh_gia_ncc, 0, ',', '.') }}đ</strong>
                </td>
                <td>
                  <div>{{ number_format($item->gia_nhap ?? $item->menh_gia_ncc, 0, ',', '.') }}đ</div>
                  @if ($item->ty_le_chiet_khau > 0)
                    <small style="color: #059669; font-weight: 600;">-{{ (float) $item->ty_le_chiet_khau }}%</small>
                  @else
                    <small style="color: #94a3b8;">0%</small>
                  @endif
                </td>
                <td>
                  <span style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                    {{ $item->muc_uu_tien }}
                  </span>
                </td>
                <td>
                  @if ($item->sanPham)
                    <span class="badge-mapped" title="Đang ánh xạ vào SP ID #{{ $item->san_pham_id }}">
                      🔗 {{ $item->sanPham->ten_san_pham }}
                    </span>
                  @else
                    <span class="badge-unmapped" data-prov-prod-modal-open="map" data-item='@json($payload)' title="Bấm để ánh xạ sang sản phẩm Web">
                      ⚠️ Chưa ánh xạ
                    </span>
                  @endif
                </td>
                <td>
                  <span class="account-status-pill {{ in_array($item->trang_thai, ['ACTIVE', 'hoat_dong']) ? 'is-yes' : 'is-no' }}">
                    {{ in_array($item->trang_thai, ['ACTIVE', 'hoat_dong']) ? 'Hoạt động' : 'Tạm dừng' }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="11">Chưa có mã sản phẩm Nhà Cung Cấp nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <span>
            Đang xem {{ $providerProducts->firstItem() ?? 0 }} đến {{ $providerProducts->lastItem() ?? 0 }} trong tổng số {{ $providerProducts->total() }} mục
          </span>
          <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--admin-slate-600);">
            <span>Hiển thị:</span>
            <select onchange="location.href = this.value;" style="border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 4px 8px; font-size: 12px; background: #fff; cursor: pointer;">
              @foreach ([10, 20, 50, 100] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" @selected(request('per_page', 20) == $size)>
                  {{ $size }} / trang
                </option>
              @endforeach
            </select>
          </label>
        </div>

        <nav aria-label="Phân trang">
          <a class="{{ $providerProducts->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $providerProducts->url(1) }}">«</a>
          <a class="{{ $providerProducts->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $providerProducts->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($providerProducts->lastPage(), 5); $page++)
            <a class="{{ $providerProducts->currentPage() === $page ? 'is-active' : '' }}" href="{{ $providerProducts->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $providerProducts->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $providerProducts->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $providerProducts->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $providerProducts->url($providerProducts->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- THANH TÁC VỤ NỔI KHI CHỌN NHIỀU MÃ (BULK ACTION BAR) --}}
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

  {{-- FORM TOGGLE TRẠNG THÁI NHANH --}}
  <form id="provProdToggleForm" method="POST" style="display: none;">
    @csrf
    @method('PATCH')
  </form>

  {{-- MODAL 1: THÊM MỚI MÃ NCC --}}
  <div class="account-modal" data-prov-prod-modal="create" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 620px;">
      <header class="account-modal__header">
        <h3>Thêm mới mã sản phẩm Nhà Cung Cấp</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <form method="POST" action="{{ route('admin.provider-products.store') }}">
        @csrf
        <div class="account-modal__body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <label style="grid-column: 1 / -1;">
            <span style="font-size: 13px; font-weight: 600;">Nhà cung cấp *</span>
            <select name="nha_cung_cap_id" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              @foreach ($providers as $p)
                <option value="{{ $p->id }}">{{ $p->ten_ncc }} ({{ $p->ma_ncc }})</option>
              @endforeach
            </select>
          </label>
          <label style="grid-column: 1 / -1;">
            <span style="font-size: 13px; font-weight: 600;">Mã sản phẩm NCC (Product Code) *</span>
            <input type="text" name="ma_san_pham_ncc" placeholder="VD: VT_TOPUP_50000 hoặc VMS_DATA_1GB" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Dịch vụ NCC</span>
            <select name="loai_dich_vu_ncc" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="MOBILE_TOPUP">📱 Nạp tiền điện thoại</option>
              <option value="TOPUP_DATA">🌐 Nạp Data 3G/4G</option>
              <option value="PIN_CODE">🎫 Mua mã thẻ cào</option>
              <option value="PIN_DATA">📶 Thẻ nạp Data</option>
            </select>
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Nhà mạng NCC</span>
            <select name="ma_nha_mang_ncc" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="viettel">Viettel</option>
              <option value="mobifone">Mobifone</option>
              <option value="vinaphone">Vinaphone</option>
              <option value="vietnamobile">Vietnamobile</option>
              <option value="gmobile">Gmobile</option>
              <option value="wintel">Wintel</option>
            </select>
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Mệnh giá NCC (VNĐ) *</span>
            <input type="number" name="menh_gia_ncc" placeholder="50000" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Chiết khấu NCC (%)</span>
            <input type="number" step="0.01" name="ty_le_chiet_khau" placeholder="5.5" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Giá vốn nhập (VNĐ)</span>
            <input type="number" name="gia_nhap" placeholder="Tự động tính nếu để trống" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Mức ưu tiên</span>
            <input type="number" name="muc_uu_tien" value="100" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label style="grid-column: 1 / -1;">
            <span style="font-size: 13px; font-weight: 600;">Ánh xạ sang Sản phẩm Web (Tùy chọn)</span>
            <select name="san_pham_id" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="">-- Chưa ánh xạ (Sẽ ánh xạ sau) --</option>
              @foreach ($webProducts as $wp)
                <option value="{{ $wp->id }}">
                  [{{ $wp->dichVu?->ten_dich_vu ?? 'Dịch vụ' }}] {{ $wp->ten_san_pham }} ({{ number_format($wp->menh_gia, 0, ',', '.') }}đ)
                </option>
              @endforeach
            </select>
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Trạng thái</span>
            <select name="trang_thai" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="hoat_dong">Hoạt động</option>
              <option value="tam_dung">Tạm dừng</option>
            </select>
          </label>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit">Thêm mới</button>
        </footer>
      </form>
    </div>
  </div>

  {{-- MODAL 2: CHỈNH SỬA MÃ NCC --}}
  <div class="account-modal" data-prov-prod-modal="edit" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 620px;">
      <header class="account-modal__header">
        <h3>Chỉnh sửa mã sản phẩm Nhà Cung Cấp</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <form id="editProvProdForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="account-modal__body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          <label style="grid-column: 1 / -1;">
            <span style="font-size: 13px; font-weight: 600;">Mã sản phẩm NCC (Product Code) *</span>
            <input type="text" name="ma_san_pham_ncc" id="edit_ma_san_pham_ncc" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Dịch vụ NCC</span>
            <select name="loai_dich_vu_ncc" id="edit_loai_dich_vu_ncc" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="MOBILE_TOPUP">📱 Nạp tiền điện thoại</option>
              <option value="TOPUP_DATA">🌐 Nạp Data 3G/4G</option>
              <option value="PIN_CODE">🎫 Mua mã thẻ cào</option>
              <option value="PIN_DATA">📶 Thẻ nạp Data</option>
            </select>
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Nhà mạng NCC</span>
            <select name="ma_nha_mang_ncc" id="edit_ma_nha_mang_ncc" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="viettel">Viettel</option>
              <option value="mobifone">Mobifone</option>
              <option value="vinaphone">Vinaphone</option>
              <option value="vietnamobile">Vietnamobile</option>
              <option value="gmobile">Gmobile</option>
              <option value="wintel">Wintel</option>
            </select>
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Mệnh giá NCC (VNĐ) *</span>
            <input type="number" name="menh_gia_ncc" id="edit_menh_gia_ncc" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Chiết khấu NCC (%)</span>
            <input type="number" step="0.01" name="ty_le_chiet_khau" id="edit_ty_le_chiet_khau" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Giá vốn nhập (VNĐ)</span>
            <input type="number" name="gia_nhap" id="edit_gia_nhap" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Mức ưu tiên</span>
            <input type="number" name="muc_uu_tien" id="edit_muc_uu_tien" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
          </label>
          <label style="grid-column: 1 / -1;">
            <span style="font-size: 13px; font-weight: 600;">Ánh xạ sang Sản phẩm Web</span>
            <select name="san_pham_id" id="edit_san_pham_id" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="">-- Chưa ánh xạ --</option>
              @foreach ($webProducts as $wp)
                <option value="{{ $wp->id }}">
                  [{{ $wp->dichVu?->ten_dich_vu ?? 'Dịch vụ' }}] {{ $wp->ten_san_pham }} ({{ number_format($wp->menh_gia, 0, ',', '.') }}đ)
                </option>
              @endforeach
            </select>
          </label>
          <label>
            <span style="font-size: 13px; font-weight: 600;">Trạng thái</span>
            <select name="trang_thai" id="edit_trang_thai" style="width: 100%; padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="hoat_dong">Hoạt động</option>
              <option value="tam_dung">Tạm dừng</option>
            </select>
          </label>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit">Cập nhật</button>
        </footer>
      </form>
    </div>
  </div>

  {{-- MODAL 3: ÁNH XẠ NHANH SANG SẢN PHẨM WEB --}}
  <div class="account-modal" data-prov-prod-modal="map" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 520px;">
      <header class="account-modal__header">
        <h3>Ánh xạ Sản phẩm Web</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <form id="mapProvProdForm" method="POST" action="">
        @csrf
        <div class="account-modal__body">
          <div style="background: var(--admin-slate-50, #f8fafc); border: 1px solid var(--admin-slate-200, #e2e8f0); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
            <div style="font-size: 13px; margin-bottom: 4px;">Mã NCC: <strong id="map_ma_san_pham_ncc" style="color: var(--admin-slate-900); font-family: monospace;">-</strong></div>
            <div style="font-size: 13px; margin-bottom: 4px;">Nhà cung cấp: <span id="map_ten_ncc">-</span></div>
            <div style="font-size: 13px;">Mệnh giá: <strong id="map_menh_gia_ncc" style="color: #047857;">-</strong> | Nhà mạng: <span id="map_ma_nha_mang_ncc" style="text-transform: uppercase; font-weight: 600;">-</span></div>
          </div>

          <label style="display: block;">
            <span style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Chọn Sản phẩm Web để liên kết:</span>
            <select name="san_pham_id" id="map_san_pham_id" style="width: 100%; padding: 9px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              <option value="">-- Bỏ ánh xạ (Để trống) --</option>
              @foreach ($webProducts as $wp)
                <option value="{{ $wp->id }}">
                  [{{ $wp->dichVu?->ten_dich_vu ?? 'Dịch vụ' }}] {{ $wp->ten_san_pham }} ({{ number_format($wp->menh_gia, 0, ',', '.') }}đ)
                </option>
              @endforeach
            </select>
          </label>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit">Lưu ánh xạ</button>
        </footer>
      </form>
    </div>
  </div>

  {{-- MODAL 4: XEM CHI TIẾT --}}
  <div class="account-modal" data-prov-prod-modal="view" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 540px;">
      <header class="account-modal__header">
        <h3>Chi tiết mã sản phẩm NCC</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <div class="account-modal__body">
        <table class="account-table" style="border: 1px solid var(--admin-slate-200); border-radius: 6px;">
          <tbody>
            <tr>
              <td style="font-weight: 600; width: 160px;">Mã sản phẩm NCC:</td>
              <td id="view_ma_san_pham_ncc" style="font-family: monospace; font-weight: bold;">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Nhà cung cấp:</td>
              <td id="view_ten_ncc">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Dịch vụ NCC:</td>
              <td id="view_loai_dich_vu_ncc">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Nhà mạng:</td>
              <td id="view_ma_nha_mang_ncc" style="text-transform: capitalize;">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Mệnh giá NCC:</td>
              <td id="view_menh_gia_ncc" style="color: #047857; font-weight: bold;">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Giá vốn nhập:</td>
              <td id="view_gia_nhap">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Chiết khấu:</td>
              <td id="view_ty_le_chiet_khau">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Mức ưu tiên:</td>
              <td id="view_muc_uu_tien">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Sản phẩm Web liên kết:</td>
              <td id="view_san_pham_web">-</td>
            </tr>
            <tr>
              <td style="font-weight: 600;">Trạng thái:</td>
              <td id="view_trang_thai">-</td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="account-modal__footer">
        <button class="account-btn account-btn--muted" type="button" data-modal-close>Đóng</button>
      </footer>
    </div>
  </div>

  {{-- MODAL 5: XÁC NHẬN XÓA --}}
  <div class="account-modal" data-prov-prod-modal="delete" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 460px;">
      <header class="account-modal__header">
        <h3>Xác nhận xóa mã NCC</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <form id="deleteProvProdForm" method="POST" action="">
        @csrf
        @method('DELETE')
        <div class="account-modal__body">
          <p>Bạn có chắc chắn muốn xóa mã NCC <strong id="deleteProvProdName">-</strong>?</p>
          <p style="font-size: 12px; color: var(--admin-slate-500); margin-top: 6px;">Hành động này không thể hoàn tác.</p>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xác nhận xóa</button>
        </footer>
      </form>
    </div>
  </div>

  {{-- MODAL 6: XÓA NHIỀU MỤC (BULK DELETE) --}}
  <div class="account-modal" data-prov-prod-modal="bulk-delete" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 480px;">
      <header class="account-modal__header">
        <h3>Xóa nhiều mã sản phẩm NCC</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <form method="POST" action="{{ route('admin.provider-products.bulk-delete') }}">
        @csrf
        <div id="bulkDeleteHiddenInputs"></div>
        <div class="account-modal__body">
          <p>Bạn có chắc chắn muốn xóa <strong id="bulkDeleteConfirmCount">0</strong> mã sản phẩm NCC đã chọn?</p>
          <p style="font-size: 12px; color: var(--admin-slate-500); margin-top: 6px;">Các mã sản phẩm bị xóa sẽ không thể khôi phục lại.</p>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xác nhận xóa tất cả</button>
        </footer>
      </form>
    </div>
  </div>

  {{-- MODAL 7: LẤY SẢN PHẨM TRỰC TIẾP TỪ API NCC --}}
  <div class="account-modal" data-prov-prod-modal="sync" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <div class="account-modal__dialog" style="max-width: 540px;">
      <header class="account-modal__header">
        <h3>📥 Lấy &amp; Đồng bộ sản phẩm từ API Nhà mạng / NCC</h3>
        <button class="account-modal__close" type="button" data-modal-close>&times;</button>
      </header>
      <form method="POST" action="{{ route('admin.provider-products.sync') }}">
        @csrf
        <div class="account-modal__body">
          <p style="margin: 0 0 14px; color: var(--admin-slate-600); font-size: 13px; line-height: 1.5; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px;">
            💡 <strong>Hướng dẫn:</strong> Hệ thống sẽ kết nối trực tiếp đến API của Nhà Cung Cấp, tự động lấy về toàn bộ danh mục mã sản phẩm, phân loại Nạp tiền/Data và lưu vào danh sách CPT này mà không làm ảnh hưởng đến danh mục web.
          </p>
          <label style="display: block; margin-bottom: 12px;">
            <span style="font-size: 13px; font-weight: 600; display: block; margin-bottom: 6px;">Chọn Nhà cung cấp (*):</span>
            <select name="nha_cung_cap_id" required style="width: 100%; padding: 9px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px;">
              @foreach ($providers as $p)
                <option value="{{ $p->id }}">{{ $p->ten_ncc }} (Mã: {{ $p->ma_ncc }})</option>
              @endforeach
            </select>
          </label>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit" style="background: linear-gradient(135deg, #0d9488, #0f766e); border-color: #0d9488;">
            <span>🚀 Bắt đầu lấy sản phẩm từ API</span>
          </button>
        </footer>
      </form>
    </div>
  </div>


@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Helper mở/đóng Modal chuẩn
      function openModal(modal) {
        if (!modal) return;
        modal.removeAttribute('hidden');
        document.body.classList.add('modal-open');
      }

      function closeModal(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        if (!document.querySelector('.account-modal:not([hidden])')) {
          document.body.classList.remove('modal-open');
        }
      }

      document.querySelectorAll('[data-modal-close]').forEach(el => {
        el.addEventListener('click', () => {
          const modal = el.closest('.account-modal');
          closeModal(modal);
        });
      });

      // Mở modal theo attribute
      document.querySelectorAll('[data-prov-prod-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
          const type = btn.getAttribute('data-prov-prod-modal-open');
          const modal = document.querySelector(`[data-prov-prod-modal="${type}"]`);
          const rawItem = btn.getAttribute('data-item');
          const item = rawItem ? JSON.parse(rawItem) : null;

          if (item) {
            // Populate Modal Edit
            if (type === 'edit') {
              const form = document.getElementById('editProvProdForm');
              if (form && item.urls?.update) form.action = item.urls.update;
              document.getElementById('edit_ma_san_pham_ncc').value = item.ma_san_pham_ncc || '';
              document.getElementById('edit_loai_dich_vu_ncc').value = item.loai_dich_vu_ncc || 'MOBILE_TOPUP';
              document.getElementById('edit_ma_nha_mang_ncc').value = item.ma_nha_mang_ncc || 'viettel';
              document.getElementById('edit_menh_gia_ncc').value = item.menh_gia_ncc || '';
              document.getElementById('edit_ty_le_chiet_khau').value = item.ty_le_chiet_khau || 0;
              document.getElementById('edit_gia_nhap').value = item.gia_nhap || '';
              document.getElementById('edit_muc_uu_tien').value = item.muc_uu_tien || 100;
              document.getElementById('edit_san_pham_id').value = item.san_pham_id || '';
              document.getElementById('edit_trang_thai').value = (item.trang_thai === 'INACTIVE' || item.trang_thai === 'tam_dung') ? 'tam_dung' : 'hoat_dong';
            }

            // Populate Modal Map
            if (type === 'map') {
              const form = document.getElementById('mapProvProdForm');
              if (form && item.urls?.map) form.action = item.urls.map;
              document.getElementById('map_ma_san_pham_ncc').textContent = item.ma_san_pham_ncc || '-';
              document.getElementById('map_ten_ncc').textContent = item.ten_ncc || '-';
              document.getElementById('map_menh_gia_ncc').textContent = Number(item.menh_gia_ncc).toLocaleString('vi-VN') + 'đ';
              document.getElementById('map_ma_nha_mang_ncc').textContent = item.ma_nha_mang_ncc || '-';
              document.getElementById('map_san_pham_id').value = item.san_pham_id || '';
            }

            // Populate Modal View
            if (type === 'view') {
              document.getElementById('view_ma_san_pham_ncc').textContent = item.ma_san_pham_ncc || '-';
              document.getElementById('view_ten_ncc').textContent = item.ten_ncc || '-';
              document.getElementById('view_loai_dich_vu_ncc').textContent = item.loai_dich_vu_ncc || '-';
              document.getElementById('view_ma_nha_mang_ncc').textContent = item.ma_nha_mang_ncc || '-';
              document.getElementById('view_menh_gia_ncc').textContent = Number(item.menh_gia_ncc).toLocaleString('vi-VN') + 'đ';
              document.getElementById('view_gia_nhap').textContent = Number(item.gia_nhap).toLocaleString('vi-VN') + 'đ';
              document.getElementById('view_ty_le_chiet_khau').textContent = item.ty_le_chiet_khau + '%';
              document.getElementById('view_muc_uu_tien').textContent = item.muc_uu_tien || 100;
              document.getElementById('view_san_pham_web').textContent = item.ten_san_pham_web ? `🔗 ${item.ten_san_pham_web}` : '⚠️ Chưa ánh xạ';
              document.getElementById('view_trang_thai').textContent = in_array(item.trang_thai, ['ACTIVE', 'hoat_dong']) ? 'Hoạt động' : 'Tạm dừng';
            }

            // Populate Modal Delete
            if (type === 'delete') {
              const form = document.getElementById('deleteProvProdForm');
              if (form && item.urls?.destroy) form.action = item.urls.destroy;
              document.getElementById('deleteProvProdName').textContent = item.ma_san_pham_ncc || '-';
            }
          }

          openModal(modal);
        });
      });

      function in_array(needle, haystack) {
        return haystack.includes(needle);
      }

      // Xử lý nút Hành động Dropdown
      document.querySelectorAll('[data-row-action]').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const wrapper = btn.closest('.account-row-actions');
          const menu = wrapper?.querySelector('.account-row-menu');
          if (!wrapper || !menu) return;

          const isOpen = wrapper.classList.contains('is-open');

          // Đóng toàn bộ menu khác
          closeAllRowMenus();

          if (!isOpen) {
            wrapper.classList.add('is-open');
            menu.removeAttribute('hidden');

            // Định vị thông minh fixed để không bao giờ bị cắt bởi table overflow
            const rect = btn.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.zIndex = '9999';
            menu.style.left = `${rect.left}px`;

            const menuHeight = menu.offsetHeight || 160;
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < menuHeight + 20) {
              menu.style.top = `${rect.top - menuHeight - 4}px`;
            } else {
              menu.style.top = `${rect.bottom + 4}px`;
            }
          }
        });
      });

      function closeAllRowMenus() {
        document.querySelectorAll('.account-row-actions.is-open').forEach(w => {
          w.classList.remove('is-open');
          w.querySelector('.account-row-menu')?.setAttribute('hidden', '');
        });
      }

      // Đóng dropdown khi bấm ra ngoài hoặc cuộn trang
      document.addEventListener('click', closeAllRowMenus);
      window.addEventListener('scroll', closeAllRowMenus, { passive: true, capture: true });
      document.addEventListener('scroll', closeAllRowMenus, { passive: true, capture: true });
      window.addEventListener('resize', closeAllRowMenus, { passive: true });

      // Thao tác đổi trạng thái nhanh từ dropdown
      document.querySelectorAll('[data-prov-prod-action-submit]').forEach(btn => {
        btn.addEventListener('click', () => {
          const url = btn.getAttribute('data-prov-prod-action-submit');
          const form = document.getElementById('provProdToggleForm');
          if (form && url) {
            form.action = url;
            form.submit();
          }
        });
      });

      // Xử lý Checkbox & Thanh tác vụ nổi (Bulk Action Bar)
      const selectAll = document.querySelector('[data-select-all]');
      const checkboxes = document.querySelectorAll('.bulk-item-checkbox');
      const bulkBar = document.getElementById('bulkActionBar');
      const bulkCount = document.getElementById('bulkSelectedCount');
      const bulkDeselect = document.getElementById('bulkDeselectBtn');
      const bulkDelete = document.getElementById('bulkDeleteBtn');
      const bulkDeleteModal = document.querySelector('[data-prov-prod-modal="bulk-delete"]');
      const bulkDeleteInputs = document.getElementById('bulkDeleteHiddenInputs');
      const bulkDeleteConfirmCount = document.getElementById('bulkDeleteConfirmCount');

      function updateBulkBar() {
        const checked = [...checkboxes].filter(cb => cb.checked);
        const count = checked.length;

        if (bulkCount) bulkCount.textContent = count;
        if (bulkDeleteConfirmCount) bulkDeleteConfirmCount.textContent = count;

        if (count > 0) {
          bulkBar?.classList.add('is-visible');
        } else {
          bulkBar?.classList.remove('is-visible');
        }

        if (selectAll) {
          selectAll.checked = count > 0 && count === checkboxes.length;
          selectAll.indeterminate = count > 0 && count < checkboxes.length;
        }
      }

      selectAll?.addEventListener('change', (e) => {
        checkboxes.forEach(cb => cb.checked = e.target.checked);
        updateBulkBar();
      });

      checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
        cb.addEventListener('click', (e) => e.stopPropagation());
      });

      bulkDeselect?.addEventListener('click', () => {
        checkboxes.forEach(cb => cb.checked = false);
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
      });

      bulkDelete?.addEventListener('click', () => {
        const checked = [...checkboxes].filter(cb => cb.checked);
        if (checked.length === 0) return;

        if (bulkDeleteInputs) {
          bulkDeleteInputs.innerHTML = checked.map(cb => `<input type="hidden" name="ids[]" value="${cb.value}">`).join('');
        }
        openModal(bulkDeleteModal);
      });

      // Filter toggle
      const filterToggle = document.querySelector('[data-account-filter-toggle]');
      const filterForm = document.querySelector('[data-account-filter]');
      if (filterToggle && filterForm) {
        filterToggle.addEventListener('click', () => {
          const isHidden = filterForm.hasAttribute('hidden');
          if (isHidden) {
            filterForm.removeAttribute('hidden');
            filterToggle.setAttribute('aria-expanded', 'true');
            filterToggle.closest('.account-filter-section').classList.add('is-open');
          } else {
            filterForm.setAttribute('hidden', '');
            filterToggle.setAttribute('aria-expanded', 'false');
            filterToggle.closest('.account-filter-section').classList.remove('is-open');
          }
        });
      }
    });
  </script>
@endpush
