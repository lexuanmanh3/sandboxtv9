@extends('admin.layout')

@section('title', 'tv9tech - Quản lý sản phẩm')

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
          <a href="#">Quản lý danh mục</a>
          <span>/</span>
          <strong>Sản phẩm</strong>
        </nav>
        <h1>Quản lý sản phẩm</h1>
        <p>Quản lý danh sách sản phẩm, mệnh giá cước và cấu hình ánh xạ mã Nhà cung cấp (Provider Product Mapping).</p>
      </div>

      <div class="account-actions">
        {{-- NÚT THÊM MỚI SẢN PHẨM --}}
        <button class="account-btn account-btn--primary" type="button" data-product-modal-open="create">
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
    <section class="account-card account-filter-section {{ request()->hasAny(['q', 'dich_vu_id', 'loai_san_pham_id', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['q', 'dich_vu_id', 'loai_san_pham_id', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      <form class="account-filter" method="GET" action="{{ route('admin.products') }}" data-account-filter {{ request()->hasAny(['q', 'dich_vu_id', 'loai_san_pham_id', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Từ khóa</span>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã hoặc tên sản phẩm...">
        </label>
        <label>
          <span>Dịch vụ</span>
          <select name="dich_vu_id">
            <option value="">Tất cả dịch vụ</option>
            @foreach ($services as $srv)
              <option value="{{ $srv->id }}" @selected(($filters['dich_vu_id'] ?? '') == $srv->id)>{{ $srv->ten_dich_vu }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Loại sản phẩm</span>
          <select name="loai_san_pham_id">
            <option value="">Tất cả loại SP</option>
            @foreach ($categories as $cat)
              <option value="{{ $cat->id }}" @selected(($filters['loai_san_pham_id'] ?? '') == $cat->id)>{{ $cat->ten_loai_san_pham }}</option>
            @endforeach
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
        <a class="account-btn account-btn--muted" href="{{ route('admin.products') }}">
          Đặt lại
        </a>
      </form>
    </section>

    {{-- BẢNG DANH SÁCH SẢN PHẨM --}}
    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th class="th-checkbox">
                <input type="checkbox" class="account-table-checkbox" data-select-all title="Chọn tất cả">
              </th>
              <th>HÀNH ĐỘNG</th>
              <th>MÃ SẢN PHẨM</th>
              <th>TÊN SẢN PHẨM</th>
              <th>DỊCH VỤ</th>
              <th>LOẠI SẢN PHẨM</th>
              <th>MỆNH GIÁ</th>
              <th>MÃ NCC ĐÃ GÁN</th>
              <th>TRẠNG THÁI</th>
              <th>THỜI GIAN TẠO</th>
            </tr>
          </thead>
          <tbody data-product-table-body>
            @forelse ($products as $product)
              @php
                $mappings = $product->nhaCungCapMappings->map(function ($m) {
                  return [
                    'id' => $m->id,
                    'nha_cung_cap_id' => $m->nha_cung_cap_id,
                    'ten_ncc' => $m->nhaCungCap?->ten_ncc ?? '-',
                    'ma_san_pham_ncc' => $m->ma_san_pham_ncc,
                    'gia_nhap' => $m->gia_nhap,
                    'ty_le_chiet_khau' => $m->ty_le_chiet_khau,
                    'muc_uu_tien' => $m->muc_uu_tien,
                    'trang_thai' => $m->trang_thai,
                    'delete_url' => route('admin.products.mappings.destroy', $m),
                  ];
                })->values();

                $payload = [
                  'id' => $product->id,
                  'ma_san_pham' => $product->ma_san_pham,
                  'ten_san_pham' => $product->ten_san_pham,
                  'dich_vu_id' => $product->dich_vu_id,
                  'loai_san_pham_id' => $product->loai_san_pham_id,
                  'menh_gia' => $product->menh_gia,
                  'don_vi' => $product->don_vi,
                  'thu_tu' => $product->thu_tu,
                  'trang_thai' => $product->trang_thai,
                  'hinh_anh' => $product->hinh_anh,
                  'mo_ta' => $product->mo_ta,
                  'mappings' => $mappings,
                  'urls' => [
                    'update' => route('admin.products.update', $product),
                    'destroy' => route('admin.products.destroy', $product),
                    'toggle' => route('admin.products.toggle-status', $product),
                    'save_mapping' => route('admin.products.mappings.save', $product),
                  ],
                ];
                $isActive = ($product->trang_thai === 'ACTIVE' || $product->trang_thai === 'hoat_dong');
              @endphp
              <tr data-product-row>
                <td class="td-checkbox">
                  <input type="checkbox" class="account-table-checkbox bulk-item-checkbox" value="{{ $product->id }}">
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
                      <button type="button" data-product-modal-open="view" data-product='@json($payload)'>Xem chi tiết</button>
                      <button type="button" data-product-modal-open="edit" data-product='@json($payload)'>Chỉnh sửa</button>
                      <button type="button" data-product-modal-open="mapping" data-product='@json($payload)'>Cấu hình mã NCC</button>
                      <button type="button" data-product-action-submit="{{ route('admin.products.toggle-status', $product) }}" data-method="PATCH">
                        {{ $isActive ? 'Tạm dừng' : 'Kích hoạt' }}
                      </button>
                      <button type="button" data-product-modal-open="delete" data-product='@json($payload)'>Xóa bỏ</button>
                    </div>
                  </div>
                </td>
                <td class="account-username"><code>{{ $product->ma_san_pham }}</code></td>
                <td><strong>{{ $product->ten_san_pham }}</strong></td>
                <td>{{ optional($product->dichVu)->ten_dich_vu ?: '-' }}</td>
                <td>{{ optional($product->loaiSanPham)->ten_loai_san_pham ?: '-' }}</td>
                <td>
                  <span style="font-weight: 700; color: var(--admin-brand-700);">
                    {{ number_format($product->menh_gia, 0, ',', '.') }} {{ $product->don_vi ?: 'đ' }}
                  </span>
                </td>
                <td>
                  <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                    @forelse ($product->nhaCungCapMappings as $m)
                      <span class="account-status-pill is-yes" style="font-size: 11px;" title="{{ $m->nhaCungCap?->ten_ncc }}: {{ $m->ma_san_pham_ncc }}">
                        {{ $m->nhaCungCap?->ma_ncc }}: <strong>{{ $m->ma_san_pham_ncc }}</strong>
                      </span>
                    @empty
                      <span style="color: var(--admin-slate-400); font-size: 12px;">Chưa gán mã</span>
                    @endforelse
                  </div>
                </td>
                <td>
                  <span class="account-status-pill {{ $isActive ? 'is-yes' : 'is-no' }}">
                    {{ $isActive ? 'Hoạt động' : 'Tạm dừng' }}
                  </span>
                </td>
                <td>{{ optional($product->created_at)->format('d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="10">Chưa có sản phẩm nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <span>
            Đang xem {{ $products->firstItem() ?? 0 }} đến {{ $products->lastItem() ?? 0 }} trong tổng số {{ $products->total() }} mục
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

        <nav aria-label="Phân trang sản phẩm">
          <a class="{{ $products->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $products->url(1) }}">«</a>
          <a class="{{ $products->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $products->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($products->lastPage(), 5); $page++)
            <a class="{{ $products->currentPage() === $page ? 'is-active' : '' }}" href="{{ $products->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $products->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $products->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $products->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $products->url($products->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- MODAL THÊM MỚI / CHỈNH SỬA / XEM CHI TIẾT SẢN PHẨM --}}
  <div class="account-modal" data-product-modal="form" hidden>
    <div class="account-modal__backdrop" data-product-modal-close></div>
    <section class="account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
      <header class="account-modal__header">
        <h2 id="productModalTitle" data-product-modal-title>Thêm mới sản phẩm</h2>
        <button type="button" class="account-modal__close" data-product-modal-close aria-label="Đóng">×</button>
      </header>

      <form class="account-modal__body" method="POST" action="{{ route('admin.products.store') }}" data-product-form>
        @csrf
        <input type="hidden" name="_method" value="POST" data-product-form-method>
        <input type="hidden" name="id" data-product-id>

        <div class="account-form-grid">
          <label>
            <span>Mã sản phẩm (*)</span>
            <input type="text" name="ma_san_pham" required maxlength="50" placeholder="TOPUP_VTE_10000">
          </label>
          <label>
            <span>Tên sản phẩm (*)</span>
            <input type="text" name="ten_san_pham" required maxlength="150" placeholder="Viettel 10.000đ">
          </label>
          <label>
            <span>Dịch vụ</span>
            <select name="dich_vu_id">
              <option value="">-- Chọn dịch vụ --</option>
              @foreach ($services as $srv)
                <option value="{{ $srv->id }}">{{ $srv->ten_dich_vu }}</option>
              @endforeach
            </select>
          </label>
          <label>
            <span>Loại sản phẩm / Nhà mạng (*)</span>
            <select name="loai_san_pham_id" required>
              <option value="">-- Chọn loại sản phẩm --</option>
              @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->ten_loai_san_pham }} ({{ $cat->ma_loai_san_pham }})</option>
              @endforeach
            </select>
          </label>
          <label>
            <span>Mệnh giá (VND) (*)</span>
            <input type="number" name="menh_gia" required min="0" step="1000" placeholder="10000">
          </label>
          <label>
            <span>Đơn vị tính</span>
            <input type="text" name="don_vi" value="VND">
          </label>
          <label>
            <span>Thứ tự hiển thị</span>
            <input type="number" name="thu_tu" min="0" value="0">
          </label>
          <label>
            <span>Trạng thái (*)</span>
            <select name="trang_thai" required>
              <option value="hoat_dong">Hoạt động</option>
              <option value="tam_dung">Tạm dừng</option>
            </select>
          </label>
          <label class="account-form-grid__full">
            <span>Mô tả sản phẩm</span>
            <input type="text" name="mo_ta" placeholder="Mô tả chi tiết...">
          </label>
        </div>

        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-product-modal-close>Đóng</button>
          <button class="account-btn account-btn--primary" type="submit" data-product-submit>Lưu sản phẩm</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- MODAL CẤU HÌNH MÃ NHÀ CUNG CẤP CHO SẢN PHẨM (PROVIDER MAPPING) --}}
  <div class="account-modal" data-product-modal="mapping" hidden>
    <div class="account-modal__backdrop" data-product-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--role" role="dialog" aria-modal="true" aria-labelledby="mappingModalTitle">
      <header class="account-modal__header">
        <h2 id="mappingModalTitle">Cấu hình ánh xạ Nhà cung cấp cho: <span id="mappingProductName" style="color: var(--admin-brand-700);"></span></h2>
        <button type="button" class="account-modal__close" data-product-modal-close aria-label="Đóng">×</button>
      </header>

      <div class="account-modal__body" style="padding: 20px;">
        <h3 style="margin-top: 0; font-size: 15px; font-weight: 700; color: var(--admin-slate-800);">1. Danh sách mã NCC đang liên kết</h3>
        <div class="account-table-wrap" style="margin-bottom: 24px;">
          <table class="account-table" style="font-size: 13px;">
            <thead>
              <tr>
                <th>Nhà cung cấp</th>
                <th>Mã sản phẩm NCC (Product Code)</th>
                <th>Mức ưu tiên</th>
                <th>Tỷ lệ chiết khấu (%)</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody id="mappingListBody">
              <!-- Render dynamically by JS -->
            </tbody>
          </table>
        </div>

        <h3 style="font-size: 15px; font-weight: 700; color: var(--admin-slate-800); border-top: 1px solid var(--admin-slate-200); padding-top: 16px;">
          2. Gán thêm / Cập nhật mã Nhà cung cấp
        </h3>
        <form method="POST" action="" id="mappingForm">
          @csrf
          <div class="account-form-grid" style="padding: 0;">
            <label>
              <span>Chọn Nhà cung cấp (*)</span>
              <select name="nha_cung_cap_id" required>
                @foreach ($providers as $prov)
                  <option value="{{ $prov->id }}">{{ $prov->ten_ncc }} ({{ $prov->ma_ncc }})</option>
                @endforeach
              </select>
            </label>
            <label>
              <span>Mã sản phẩm của NCC (*)</span>
              <input type="text" name="ma_san_pham_ncc" required placeholder="VD: viettel_10, VT10...">
            </label>
            <label>
              <span>Mức ưu tiên định tuyến (Routing Priority)</span>
              <input type="number" name="muc_uu_tien" value="100" min="1" max="999" title="Số càng nhỏ càng ưu tiên gọi trước">
            </label>
            <label>
              <span>Tỷ lệ chiết khấu (%)</span>
              <input type="number" name="ty_le_chiet_khau" value="0" min="0" max="100" step="0.1">
            </label>
            <label>
              <span>Trạng thái</span>
              <select name="trang_thai">
                <option value="ACTIVE">Hoạt động</option>
                <option value="tam_dung">Tạm dừng</option>
              </select>
            </label>
          </div>
          <div style="margin-top: 16px; text-align: right;">
            <button class="account-btn account-btn--primary" type="submit">Lưu mã NCC</button>
          </div>
        </form>
      </div>

      <footer class="account-modal__footer">
        <button class="account-btn account-btn--muted" type="button" data-product-modal-close>Đóng</button>
      </footer>
    </section>
  </div>

  {{-- THANH TÁC VỤ NỔI KHI CHỌN NHIỀU SẢN PHẨM --}}
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

  {{-- MODAL XÁC NHẬN XÓA NHIỀU SẢN PHẨM --}}
  <div class="account-modal" data-product-modal="bulk-delete" hidden>
    <div class="account-modal__backdrop" data-product-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="bulkDeleteTitle">
      <header class="account-modal__header">
        <h2 id="bulkDeleteTitle">Xóa nhiều sản phẩm</h2>
        <button type="button" class="account-modal__close" data-product-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" action="{{ route('admin.products.bulk-delete') }}" id="bulkDeleteForm">
        @csrf
        <div id="bulkDeleteHiddenInputs"></div>
        <p class="account-confirm-text" style="padding: 20px; margin: 0; color: var(--admin-slate-700);">
          Bạn có chắc chắn muốn xóa <strong id="bulkDeleteConfirmCount" style="color: #ef4444;">0</strong> sản phẩm đã chọn? Các cấu hình ánh xạ NCC liên quan cũng sẽ bị xóa.
        </p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-product-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xác nhận xóa</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- MODAL XÁC NHẬN XÓA --}}
  <div class="account-modal" data-product-modal="delete" hidden>
    <div class="account-modal__backdrop" data-product-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="productDeleteModalTitle">
      <header class="account-modal__header">
        <h2 id="productDeleteModalTitle">Xóa sản phẩm</h2>
        <button type="button" class="account-modal__close" data-product-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" data-product-delete-form>
        @csrf
        @method('DELETE')
        <p class="account-confirm-text" style="padding: 20px; margin: 0; color: var(--admin-slate-700);">
          Bạn có chắc muốn xóa sản phẩm <strong data-product-delete-name></strong>? Thao tác này sẽ xóa các ánh xạ mã NCC liên quan.
        </p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-product-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xóa sản phẩm</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- FORM ẨN ĐỔI TRẠNG THÁI --}}
  <form method="POST" data-product-toggle-form hidden>
    @csrf
    @method('PATCH')
  </form>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
  const filterToggle = document.querySelector("[data-account-filter-toggle]");
  const filter = document.querySelector("[data-account-filter]");
  const rows = [...document.querySelectorAll("[data-product-row]")];

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
  const formModal = document.querySelector('[data-product-modal="form"]');
  const mappingModal = document.querySelector('[data-product-modal="mapping"]');
  const autoSyncModal = document.querySelector('[data-product-modal="auto-sync"]');
  const syncApiModal = document.querySelector('[data-product-modal="sync-api"]');
  const bulkDeleteModal = document.querySelector('[data-product-modal="bulk-delete"]');
  const deleteModal = document.querySelector('[data-product-modal="delete"]');
  const productForm = document.querySelector("[data-product-form]");
  const productFormMethod = document.querySelector("[data-product-form-method]");
  const productModalTitle = document.querySelector("[data-product-modal-title]");
  const productSubmitBtn = document.querySelector("[data-product-submit]");
  const deleteForm = document.querySelector("[data-product-delete-form]");
  const deleteProductName = document.querySelector("[data-product-delete-name]");
  const toggleForm = document.querySelector("[data-product-toggle-form]");

  const mappingForm = document.getElementById("mappingForm");
  const mappingProductName = document.getElementById("mappingProductName");
  const mappingListBody = document.getElementById("mappingListBody");

  if (productForm) {
    productForm.dataset.storeUrl = productForm.getAttribute("action");
  }

  function parseProduct(button) {
    try {
      return JSON.parse(button.dataset.product || "{}");
    } catch (e) {
      return {};
    }
  }

  function closeModals() {
    document.querySelectorAll("[data-product-modal]").forEach((modal) => {
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
    const field = productForm?.querySelector(`[name="${name}"]`);
    if (field) {
      field.value = value ?? "";
    }
  }

  // Đóng modal qua nút close / backdrop
  document.querySelectorAll("[data-product-modal-close]").forEach((btn) => {
    btn.addEventListener("click", closeModals);
  });

  // Đóng modal khi nhấn phím ESC
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" || event.key === "Esc") {
      closeModals();
      closeAllRowMenus();
    }
  });

  // Mở modal Thêm mới
  document.querySelectorAll('[data-product-modal-open="create"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      productForm?.reset();
      if (productForm && productForm.dataset.storeUrl) {
        productForm.action = productForm.dataset.storeUrl;
      }
      if (productFormMethod) productFormMethod.value = "POST";
      if (productModalTitle) productModalTitle.textContent = "Thêm mới sản phẩm";
      if (productSubmitBtn) {
        productSubmitBtn.hidden = false;
        productSubmitBtn.textContent = "Lưu sản phẩm";
      }

      productForm?.querySelectorAll("input, select").forEach((input) => {
        input.disabled = false;
      });

      openModal(formModal);
    });
  });

  // Mở modal Xem chi tiết / Chỉnh sửa
  document.querySelectorAll('[data-product-modal-open="edit"], [data-product-modal-open="view"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      const mode = btn.dataset.productModalOpen;
      const data = parseProduct(btn);

      productForm?.reset();
      if (productForm && data.urls?.update) {
        productForm.action = data.urls.update;
      }
      if (productFormMethod) productFormMethod.value = "PUT";
      if (productModalTitle) {
        productModalTitle.textContent = mode === "view"
          ? `Chi tiết sản phẩm: ${data.ten_san_pham}`
          : `Chỉnh sửa sản phẩm: ${data.ten_san_pham}`;
      }
      if (productSubmitBtn) {
        productSubmitBtn.hidden = mode === "view";
        productSubmitBtn.textContent = "Cập nhật sản phẩm";
      }

      setField("id", data.id);
      setField("ma_san_pham", data.ma_san_pham);
      setField("ten_san_pham", data.ten_san_pham);
      setField("dich_vu_id", data.dich_vu_id);
      setField("loai_san_pham_id", data.loai_san_pham_id);
      setField("menh_gia", data.menh_gia);
      setField("don_vi", data.don_vi);
      setField("thu_tu", data.thu_tu);
      setField("trang_thai", data.trang_thai);
      setField("mo_ta", data.mo_ta);

      productForm?.querySelectorAll("input, select").forEach((input) => {
        input.disabled = mode === "view";
      });

      openModal(formModal);
    });
  });

  // Mở modal Cấu hình Mã NCC (Mapping)
  document.querySelectorAll('[data-product-modal-open="mapping"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      const data = parseProduct(btn);
      if (mappingProductName) {
        mappingProductName.textContent = `${data.ten_san_pham} (${Number(data.menh_gia).toLocaleString('vi-VN')}đ)`;
      }
      if (mappingForm && data.urls?.save_mapping) {
        mappingForm.action = data.urls.save_mapping;
      }

      // Render danh sách mapping
      if (mappingListBody) {
        const mappings = data.mappings || [];
        if (mappings.length === 0) {
          mappingListBody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--admin-slate-400); padding: 16px;">Sản phẩm này chưa được gán mã NCC nào. Hãy điền form bên dưới để gán.</td></tr>';
        } else {
          mappingListBody.innerHTML = mappings.map((m) => `
            <tr>
              <td><strong>${m.ten_ncc}</strong></td>
              <td><code>${m.ma_san_pham_ncc}</code></td>
              <td>${m.muc_uu_tien}</td>
              <td>${m.ty_le_chiet_khau}%</td>
              <td>
                <span class="account-status-pill ${m.trang_thai === 'ACTIVE' || m.trang_thai === 'hoat_dong' ? 'is-yes' : 'is-no'}" style="font-size: 11px;">
                  ${m.trang_thai}
                </span>
              </td>
              <td>
                <form method="POST" action="${m.delete_url}" style="display:inline;" onsubmit="return confirm('Bạn có chắc muốn xóa mã NCC này?');">
                  <input type="hidden" name="_token" value="{{ csrf_token() }}">
                  <input type="hidden" name="_method" value="DELETE">
                  <button type="submit" class="account-btn account-btn--danger" style="padding: 3px 8px; font-size: 12px;">Xóa</button>
                </form>
              </td>
            </tr>
          `).join('');
        }
      }

      openModal(mappingModal);
    });
  });

  // Mở modal Lấy sản phẩm từ API
  const syncApiProviderSelect = document.getElementById("syncApiProviderSelect");
  const syncApiPresetSelect = document.getElementById("syncApiPresetSelect");
  const syncApiUrlInput = document.getElementById("syncApiUrlInput");

  function updateSyncApiUrl() {
    const selectedOpt = syncApiProviderSelect?.selectedOptions[0];
    const defaultUrl = selectedOpt ? selectedOpt.dataset.url : "";
    const presetVal = syncApiPresetSelect?.value;

    if (presetVal && presetVal !== "custom") {
      if (syncApiUrlInput) syncApiUrlInput.value = presetVal;
    } else if (presetVal === "custom") {
      if (syncApiUrlInput) syncApiUrlInput.focus();
    } else {
      if (syncApiUrlInput) syncApiUrlInput.value = defaultUrl || "";
    }
  }

  syncApiProviderSelect?.addEventListener("change", updateSyncApiUrl);
  syncApiPresetSelect?.addEventListener("change", updateSyncApiUrl);

  document.querySelectorAll('[data-product-modal-open="sync-api"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      updateSyncApiUrl();
      openModal(syncApiModal);
    });
  });

  // Mở modal Tự động sinh mã
  document.querySelectorAll('[data-product-modal-open="auto-sync"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      openModal(autoSyncModal);
    });
  });

  // Mở modal Xóa sản phẩm
  document.querySelectorAll('[data-product-modal-open="delete"]').forEach((btn) => {
    btn.addEventListener("click", () => {
      const data = parseProduct(btn);
      if (deleteForm && data.urls?.destroy) {
        deleteForm.action = data.urls.destroy;
      }
      if (deleteProductName) {
        deleteProductName.textContent = data.ten_san_pham || data.ma_san_pham;
      }
      openModal(deleteModal);
    });
  });

  // Thao tác đổi trạng thái nhanh
  document.querySelectorAll("[data-product-action-submit]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const url = btn.dataset.productActionSubmit;
      if (toggleForm && url) {
        toggleForm.action = url;
        toggleForm.submit();
      }
    });
  });

  // 7. Xử lý Checkbox Chọn nhiều & Thanh tác vụ nổi (Bulk Action Bar)
  const selectAllCheckbox = document.querySelector("[data-select-all]");
  const itemCheckboxes = document.querySelectorAll(".bulk-item-checkbox");
  const bulkActionBar = document.getElementById("bulkActionBar");
  const bulkSelectedCount = document.getElementById("bulkSelectedCount");
  const bulkDeselectBtn = document.getElementById("bulkDeselectBtn");
  const bulkDeleteBtn = document.getElementById("bulkDeleteBtn");
  const bulkDeleteHiddenInputs = document.getElementById("bulkDeleteHiddenInputs");
  const bulkDeleteConfirmCount = document.getElementById("bulkDeleteConfirmCount");

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
