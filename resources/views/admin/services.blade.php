@extends('admin.layout')

@section('title', 'VietFin - Quản lý dịch vụ')

@push('styles')
  {{-- NOTE: Dung chung CSS voi man tai khoan/vai tro vi cung 1 kieu layout card + table + modal. --}}
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
          <strong>Dịch vụ</strong>
        </nav>
        <h1>Quản lý dịch vụ</h1>
        <p>Quản lý danh mục dịch vụ lớn của hệ thống (nạp tiền điện thoại, mua mã thẻ, thanh toán hóa đơn...).</p>
      </div>

      <div class="account-actions">
        <a class="account-btn account-btn--muted" href="{{ route('admin.services.export', request()->only(['ma_dich_vu', 'ten_dich_vu', 'trang_thai'])) }}">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 3h7l5 5v13H7z" />
            <path d="M14 3v5h5" />
            <path d="m10 11 4 6" />
            <path d="m14 11-4 6" />
          </svg>
          <span>Xuất sang excel</span>
        </a>

        {{-- NOTE: Nut nay mo modal tao dich vu, form submit ve route store cua module services. --}}
        <button class="account-btn account-btn--primary" type="button" data-service-modal-open="create">
          <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          <span>Thêm mới</span>
        </button>
      </div>
    </header>

    {{-- NOTE: Khu vuc thong bao server tra ve sau cac thao tac tao/sua/xoa dich vu. --}}
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

    <section class="account-card account-filter-section {{ request()->hasAny(['ma_dich_vu', 'ten_dich_vu', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['ma_dich_vu', 'ten_dich_vu', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      {{-- NOTE: Form filter dung GET de giu query khi phan trang Laravel, giong het pattern man tai khoan. --}}
      <form class="account-filter" method="GET" action="{{ route('admin.services') }}" data-account-filter {{ request()->hasAny(['ma_dich_vu', 'ten_dich_vu', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Mã dịch vụ</span>
          <input type="text" name="ma_dich_vu" value="{{ $filters['ma_dich_vu'] ?? '' }}" placeholder="TOPUP, PIN_CODE...">
        </label>
        <label>
          <span>Tên dịch vụ</span>
          <input type="text" name="ten_dich_vu" value="{{ $filters['ten_dich_vu'] ?? '' }}" placeholder="Nạp tiền điện thoại...">
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
        <a class="account-btn account-btn--muted" href="{{ route('admin.services') }}">
          Đặt lại
        </a>
      </form>
    </section>

    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th>Hành động</th>
              <th>Mã dịch vụ</th>
              <th>Tên dịch vụ</th>
              <th>Thứ tự</th>
              <th>Trạng thái</th>
              <th>Thời gian tạo</th>
            </tr>
          </thead>
          <tbody data-service-table-body>
            @forelse ($services as $service)
              @php
                $payload = [
                  'id' => $service->id,
                  'ma_dich_vu' => $service->ma_dich_vu,
                  'ten_dich_vu' => $service->ten_dich_vu,
                  'trang_thai' => $service->trang_thai,
                  'thu_tu' => $service->thu_tu,
                  'mo_ta' => $service->mo_ta,
                  'urls' => [
                    'update' => route('admin.services.update', $service),
                    'destroy' => route('admin.services.destroy', $service),
                  ],
                ];
              @endphp
              <tr data-service-row>
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
                      <button type="button" data-service-modal-open="view" data-service='@json($payload)'>Xem chi tiết</button>
                      <button type="button" data-service-modal-open="edit" data-service='@json($payload)'>Chỉnh sửa</button>
                      <button type="button" data-service-modal-open="delete" data-service='@json($payload)'>Xóa bỏ</button>
                    </div>
                  </div>
                </td>
                <td class="account-username">{{ $service->ma_dich_vu }}</td>
                <td>{{ $service->ten_dich_vu }}</td>
                <td>{{ $service->thu_tu }}</td>
                <td>
                  <span class="account-status-pill {{ $service->trang_thai === 'hoat_dong' ? 'is-yes' : 'is-no' }}">
                    {{ $statuses[$service->trang_thai] ?? $service->trang_thai }}
                  </span>
                </td>
                <td>{{ optional($service->created_at)->format('d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="6">Chưa có dịch vụ nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div>
          <span>
            Đang xem {{ $services->firstItem() ?? 0 }} đến {{ $services->lastItem() ?? 0 }} trong tổng số {{ $services->total() }} mục
          </span>
        </div>

        <nav aria-label="Phân trang dịch vụ">
          <a class="{{ $services->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $services->url(1) }}">«</a>
          <a class="{{ $services->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $services->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($services->lastPage(), 5); $page++)
            <a class="{{ $services->currentPage() === $page ? 'is-active' : '' }}" href="{{ $services->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $services->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $services->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $services->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $services->url($services->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- NOTE: 1 modal dung chung cho 3 che do: xem/tao/sua, JS chuyen doi bang class + disabled input. --}}
  <div class="account-modal" data-service-modal="form" hidden>
    <div class="account-modal__backdrop" data-service-modal-close></div>
    <section class="account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle">
      <header class="account-modal__header">
        <h2 id="serviceModalTitle" data-service-modal-title>Thêm mới dịch vụ</h2>
        <button type="button" class="account-modal__close" data-service-modal-close aria-label="Đóng">×</button>
      </header>

      <form class="account-modal__body" method="POST" action="{{ route('admin.services.store') }}" data-service-form>
        @csrf
        <input type="hidden" name="_method" value="POST" data-service-form-method>

        <div class="account-form-grid">
          <label>
            <span>Mã dịch vụ (*)</span>
            <input type="text" name="ma_dich_vu" required maxlength="50" placeholder="TOPUP" data-service-code>
          </label>
          <label>
            <span>Tên dịch vụ (*)</span>
            <input type="text" name="ten_dich_vu" required maxlength="150" placeholder="Nạp tiền điện thoại">
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
          <label class="account-form-grid__full">
            <span>Mô tả</span>
            <input type="text" name="mo_ta">
          </label>
        </div>

        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-service-modal-close>Đóng</button>
          <button class="account-btn account-btn--primary" type="submit" data-service-submit>Lưu dịch vụ</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- NOTE: Modal xac nhan xoa, submit DELETE ve controller destroy, giong pattern man tai khoan. --}}
  <div class="account-modal" data-service-modal="delete" hidden>
    <div class="account-modal__backdrop" data-service-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="serviceDeleteModalTitle">
      <header class="account-modal__header">
        <h2 id="serviceDeleteModalTitle">Xóa dịch vụ</h2>
        <button type="button" class="account-modal__close" data-service-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" data-service-delete-form>
        @csrf
        @method('DELETE')
        <p class="account-confirm-text">Bạn có chắc muốn xóa dịch vụ <strong data-service-delete-name></strong>? Thao tác này không thể hoàn tác nếu dịch vụ chưa có dữ liệu liên kết.</p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-service-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xóa dịch vụ</button>
        </footer>
      </form>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('backend/js/admin-services.js') }}"></script>
@endpush
