@extends('admin.layout')

@section('title', 'VietFin - Quản lý loại sản phẩm')

@push('styles')
  {{-- NOTE: Dung chung CSS voi man tai khoan/vai tro/dich vu vi cung 1 kieu layout card + table + modal. --}}
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
          <strong>Loại sản phẩm</strong>
        </nav>
        <h1>Quản lý loại sản phẩm</h1>
        <p>Quản lý nhóm sản phẩm thuộc từng dịch vụ (ví dụ Viettel, Mobifone thuộc dịch vụ nạp tiền điện thoại).</p>
      </div>

      <div class="account-actions">
        <a class="account-btn account-btn--muted" href="{{ route('admin.categories.export', request()->only(['ma_loai_san_pham', 'ten_loai_san_pham', 'dich_vu_id', 'trang_thai'])) }}">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 3h7l5 5v13H7z" />
            <path d="M14 3v5h5" />
            <path d="m10 11 4 6" />
            <path d="m14 11-4 6" />
          </svg>
          <span>Xuất sang excel</span>
        </a>

        {{-- NOTE: Nut nay mo modal tao loai san pham, form submit ve route store cua module categories. --}}
        <button class="account-btn account-btn--primary" type="button" data-category-modal-open="create">
          <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          <span>Thêm mới</span>
        </button>
      </div>
    </header>

    {{-- NOTE: Khu vuc thong bao server tra ve sau cac thao tac tao/sua/xoa loai san pham. --}}
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

    <section class="account-card account-filter-section {{ request()->hasAny(['ma_loai_san_pham', 'ten_loai_san_pham', 'dich_vu_id', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['ma_loai_san_pham', 'ten_loai_san_pham', 'dich_vu_id', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      {{-- NOTE: Form filter dung GET de giu query khi phan trang Laravel, giong het pattern man dich vu. --}}
      <form class="account-filter" method="GET" action="{{ route('admin.categories') }}" data-account-filter {{ request()->hasAny(['ma_loai_san_pham', 'ten_loai_san_pham', 'dich_vu_id', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Mã loại sản phẩm</span>
          <input type="text" name="ma_loai_san_pham" value="{{ $filters['ma_loai_san_pham'] ?? '' }}" placeholder="VIETTEL, MOBIFONE...">
        </label>
        <label>
          <span>Tên loại sản phẩm</span>
          <input type="text" name="ten_loai_san_pham" value="{{ $filters['ten_loai_san_pham'] ?? '' }}" placeholder="Viettel, Mobifone...">
        </label>
        <label>
          <span>Dịch vụ</span>
          <select name="dich_vu_id">
            <option value="">Tất cả</option>
            @foreach ($services as $service)
              <option value="{{ $service->id }}" @selected(($filters['dich_vu_id'] ?? '') == $service->id)>{{ $service->ten_dich_vu }}</option>
            @endforeach
          </select>
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
        <a class="account-btn account-btn--muted" href="{{ route('admin.categories') }}">
          Đặt lại
        </a>
      </form>
    </section>

    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th class="th-checkbox">
                <input type="checkbox" class="account-table-checkbox" data-select-all title="Chọn tất cả">
              </th>
              <th>Hành động</th>
              <th style="width: 70px; text-align: center;">Hình ảnh</th>
              <th>Mã loại sản phẩm</th>
              <th>Tên loại sản phẩm</th>
              <th>Dịch vụ</th>
              <th>Loại cha</th>
              <th>Thứ tự</th>
              <th>Trạng thái</th>
              <th>Thời gian tạo</th>
            </tr>
          </thead>
          <tbody data-category-table-body>
            @forelse ($categories as $category)
              @php
                $imgUrl = !empty($category->hinh_anh) 
                  ? (Str::startsWith($category->hinh_anh, ['http://', 'https://', 'data:']) ? $category->hinh_anh : asset(ltrim($category->hinh_anh, '/')))
                  : null;

                $payload = [
                  'id' => $category->id,
                  'ma_loai_san_pham' => $category->ma_loai_san_pham,
                  'ten_loai_san_pham' => $category->ten_loai_san_pham,
                  'dich_vu_id' => $category->dich_vu_id,
                  'loai_san_pham_cha_id' => $category->loai_san_pham_cha_id,
                  'trang_thai' => $category->trang_thai,
                  'thu_tu' => $category->thu_tu,
                  'hinh_anh' => $category->hinh_anh,
                  'mo_ta' => $category->mo_ta,
                  'urls' => [
                    'update' => route('admin.categories.update', $category),
                    'destroy' => route('admin.categories.destroy', $category),
                  ],
                ];
              @endphp
              <tr data-category-row>
                <td class="td-checkbox">
                  <input type="checkbox" class="account-table-checkbox bulk-item-checkbox" value="{{ $category->id }}">
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
                      {{-- NOTE: Cac nut dropdown chi mo modal, thao tac luu/xoa that duoc submit ve route co CSRF/method rieng. --}}
                      <button type="button" data-category-modal-open="view" data-category='@json($payload)'>Xem chi tiết</button>
                      <button type="button" data-category-modal-open="edit" data-category='@json($payload)'>Chỉnh sửa</button>
                      <button type="button" data-category-modal-open="delete" data-category='@json($payload)'>Xóa bỏ</button>
                    </div>
                  </div>
                </td>
                <td style="text-align: center;">
                  @if ($imgUrl)
                    <img src="{{ $imgUrl }}" 
                         alt="{{ $category->ten_loai_san_pham }}" 
                         style="width: 38px; height: 38px; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; background: #ffffff; padding: 2px; vertical-align: middle; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
                         onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-flex';">
                    <span style="display: none; width: 38px; height: 38px; border-radius: 6px; background: #f1f5f9; color: #94a3b8; font-size: 11px; align-items: center; justify-content: center; border: 1px dashed #cbd5e1;">-</span>
                  @else
                    <span style="display: inline-flex; width: 38px; height: 38px; border-radius: 6px; background: #f8fafc; border: 1px dashed #cbd5e1; color: #94a3b8; font-size: 10px; align-items: center; justify-content: center; font-weight: 600;">
                      Trống
                    </span>
                  @endif
                </td>
                <td class="account-username">{{ $category->ma_loai_san_pham }}</td>
                <td>{{ $category->ten_loai_san_pham }}</td>
                <td>{{ optional($category->dichVu)->ten_dich_vu ?: '-' }}</td>
                <td>{{ optional($category->loaiCha)->ten_loai_san_pham ?: '-' }}</td>
                <td>{{ $category->thu_tu }}</td>
                <td>
                  <span class="account-status-pill {{ $category->trang_thai === 'hoat_dong' ? 'is-yes' : 'is-no' }}">
                    {{ $statuses[$category->trang_thai] ?? $category->trang_thai }}
                  </span>
                </td>
                <td>{{ optional($category->created_at)->format('d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="10">Chưa có loại sản phẩm nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <span>
            Đang xem {{ $categories->firstItem() ?? 0 }} đến {{ $categories->lastItem() ?? 0 }} trong tổng số {{ $categories->total() }} mục
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

        <nav aria-label="Phân trang loại sản phẩm">
          <a class="{{ $categories->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $categories->url(1) }}">«</a>
          <a class="{{ $categories->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $categories->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($categories->lastPage(), 5); $page++)
            <a class="{{ $categories->currentPage() === $page ? 'is-active' : '' }}" href="{{ $categories->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $categories->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $categories->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $categories->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $categories->url($categories->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- NOTE: 1 modal dung chung cho 3 che do: xem/tao/sua, JS chuyen doi bang class + disabled input. --}}
  <div class="account-modal" data-category-modal="form" hidden>
    <div class="account-modal__backdrop" data-category-modal-close></div>
    <section class="account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
      <header class="account-modal__header">
        <h2 id="categoryModalTitle" data-category-modal-title>Thêm mới loại sản phẩm</h2>
        <button type="button" class="account-modal__close" data-category-modal-close aria-label="Đóng">×</button>
      </header>

      <form class="account-modal__body" method="POST" action="{{ route('admin.categories.store') }}" enctype="multipart/form-data" data-category-form>
        @csrf
        <input type="hidden" name="_method" value="POST" data-category-form-method>

        <div class="account-form-grid">
          <label>
            <span>Mã loại sản phẩm (*)</span>
            <input type="text" name="ma_loai_san_pham" required maxlength="50" placeholder="VIETTEL">
          </label>
          <label>
            <span>Tên loại sản phẩm (*)</span>
            <input type="text" name="ten_loai_san_pham" required maxlength="150" placeholder="Viettel">
          </label>
          <label>
            {{-- NOTE: Dich vu khong con bat buoc vi nhom goc theo nha mang (Viettel, Vinaphone...)
                 dung chung cho nhieu dich vu, khong thuoc rieng 1 dich vu nao. --}}
            <span>Dịch vụ</span>
            <select name="dich_vu_id">
              <option value="">-- Không thuộc dịch vụ nào (nhóm gốc) --</option>
              @foreach ($services as $service)
                <option value="{{ $service->id }}">{{ $service->ten_dich_vu }}</option>
              @endforeach
            </select>
          </label>
          <label>
            <span>Loại cha</span>
            <select name="loai_san_pham_cha_id">
              <option value="">-- Không có (nhóm gốc) --</option>
              @foreach ($parentCategories as $parent)
                <option value="{{ $parent->id }}">{{ $parent->ten_loai_san_pham }}</option>
              @endforeach
            </select>
          </label>
          <label>
            <span>Trạng thái (*)</span>
            <select name="trang_thai" required>
              @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected($value === 'hoat_dong')>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>
            <span>Thứ tự hiển thị</span>
            <input type="number" name="thu_tu" min="0" value="0">
          </label>

          <div class="account-form-grid__full" style="display: flex; gap: 16px; align-items: center; background: #f8fafc; padding: 12px 14px; border-radius: 6px; border: 1px dashed #cbd5e1;">
            <div style="flex: 1;">
              <label style="display: block; font-size: 12.5px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
                <span>Tải ảnh lên từ máy tính (PNG, JPG, SVG, WEBP)</span>
              </label>
              <input type="file" name="hinh_anh_file" accept="image/*" data-category-file-input style="width: 100%; font-size: 13px;">
              <div style="font-size: 11.5px; color: #64748b; margin-top: 6px;">Hoặc dán URL/đường dẫn ảnh:</div>
              <input type="text" name="hinh_anh" data-category-image-input placeholder="/frontend/img/logo-viettel.png hoặc https://..." style="margin-top: 4px;">
            </div>
            <div data-category-image-preview-wrapper style="width: 84px; height: 84px; border: 1px solid #e2e8f0; border-radius: 6px; background: #ffffff; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
              <img src="" alt="Preview" data-category-image-preview style="max-width: 100%; max-height: 100%; object-fit: contain; display: none;">
              <span data-category-image-placeholder style="font-size: 11px; color: #94a3b8; text-align: center;">Chưa có ảnh</span>
            </div>
          </div>

          <label class="account-form-grid__full">
            <span>Mô tả</span>
            <input type="text" name="mo_ta">
          </label>
        </div>

        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-category-modal-close>Đóng</button>
          <button class="account-btn account-btn--primary" type="submit" data-category-submit>Lưu loại sản phẩm</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- THANH TÁC VỤ NỔI KHI CHỌN NHIỀU LOẠI SẢN PHẨM --}}
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

  {{-- MODAL XÁC NHẬN XÓA NHIỀU LOẠI SẢN PHẨM --}}
  <div class="account-modal" data-category-modal="bulk-delete" hidden>
    <div class="account-modal__backdrop" data-category-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="bulkCategoryDeleteTitle">
      <header class="account-modal__header">
        <h2 id="bulkCategoryDeleteTitle">Xóa nhiều loại sản phẩm</h2>
        <button type="button" class="account-modal__close" data-category-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" action="{{ route('admin.categories.bulk-delete') }}" id="bulkCategoryDeleteForm">
        @csrf
        <div id="bulkCategoryDeleteHiddenInputs"></div>
        <p class="account-confirm-text" style="padding: 20px; margin: 0; color: var(--admin-slate-700);">
          Bạn có chắc muốn xóa <strong id="bulkCategoryDeleteConfirmCount" style="color: #ef4444;">0</strong> loại sản phẩm đã chọn? Thao tác này không thể hoàn tác nếu không có ràng buộc.
        </p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-category-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xóa các loại sản phẩm</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- NOTE: Modal xac nhan xoa, submit DELETE ve controller destroy, giong pattern man dich vu. --}}
  <div class="account-modal" data-category-modal="delete" hidden>
    <div class="account-modal__backdrop" data-category-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="categoryDeleteModalTitle">
      <header class="account-modal__header">
        <h2 id="categoryDeleteModalTitle">Xóa loại sản phẩm</h2>
        <button type="button" class="account-modal__close" data-category-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" data-category-delete-form>
        @csrf
        @method('DELETE')
        <p class="account-confirm-text">Bạn có chắc muốn xóa loại sản phẩm <strong data-category-delete-name></strong>? Thao tác này không thể hoàn tác nếu chưa có dữ liệu liên kết.</p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-category-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xóa loại sản phẩm</button>
        </footer>
      </form>
    </section>
  </div>
@endsection

@push('scripts')
  <script>
    window.APP_ASSET_BASE_URL = "{{ asset('') }}";
  </script>
  <script src="{{ asset('backend/js/admin-categories.js') }}?v={{ time() }}"></script>
@endpush
