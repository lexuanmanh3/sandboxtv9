@extends('admin.layout')

@section('title', 'VietFin - Quản lý vai trò')

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
          <strong>Vai trò</strong>
        </nav>
        <h1>Vai trò</h1>
        <p>Gán quyền truy cập module/trang cho từng vai trò trong hệ thống.</p>
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

    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th>Hành động</th>
              <th>Mã vai trò</th>
              <th>Tên vai trò</th>
              <th>Quyền truy cập đang có</th>
              <th>Trạng thái</th>
              <th>Thời gian tạo</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($roles as $role)
              @php
                $permissionIds = $role->quyen->pluck('id')->values();
                $permissionNames = $role->quyen
                    ->filter(fn ($permission) => str_ends_with($permission->ma_quyen, '.access'))
                    ->pluck('ten_quyen')
                    ->filter()
                    ->values();
                $payload = [
                  'id' => $role->id,
                  'ma_vai_tro' => $role->ma_vai_tro,
                  'ten_vai_tro' => $role->ten_vai_tro,
                  'mo_ta' => $role->mo_ta,
                  'trang_thai' => $role->trang_thai,
                  'mac_dinh' => (bool) $role->mac_dinh,
                  'quyen' => $permissionIds,
                  'urls' => [
                    'permissions' => route('admin.roles.permissions', $role),
                  ],
                ];
              @endphp
              <tr data-account-row>
                <td>
                  <div class="account-row-actions">
                    <button class="account-row-action" type="button" data-row-action>
                      Hành động
                      <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                        <path d="M5 7.5 10 12.5 15 7.5" />
                      </svg>
                    </button>
                    <div class="account-row-menu" hidden>
                      <button type="button" data-role-modal-open data-role='@json($payload)'>Sửa quyền truy cập</button>
                    </div>
                  </div>
                </td>
                <td class="account-username">{{ $role->ma_vai_tro }}</td>
                <td>
                  {{ $role->ten_vai_tro }}
                  @if ($role->mac_dinh)
                    <span class="account-status-pill is-yes">Mặc định</span>
                  @endif
                </td>
                <td>
                  <span class="account-role">
                    {{ $permissionNames->isNotEmpty() ? $permissionNames->take(3)->join(', ') : 'Chưa có quyền' }}
                  </span>
                </td>
                <td>
                  <span class="account-status-pill {{ $role->trang_thai === 'hoat_dong' ? 'is-yes' : 'is-no' }}">
                    {{ $role->trang_thai === 'hoat_dong' ? 'Hoạt động' : 'Tạm khóa' }}
                  </span>
                </td>
                <td>{{ optional($role->created_at)->format('d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="6">Chưa có vai trò nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div>
          <span>
            Đang xem {{ $roles->firstItem() ?? 0 }} đến {{ $roles->lastItem() ?? 0 }} trong tổng số {{ $roles->total() }} mục
          </span>
        </div>

        <nav aria-label="Phân trang vai trò">
          <a class="{{ $roles->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $roles->url(1) }}">«</a>
          <a class="{{ $roles->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $roles->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($roles->lastPage(), 5); $page++)
            <a class="{{ $roles->currentPage() === $page ? 'is-active' : '' }}" href="{{ $roles->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $roles->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $roles->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $roles->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $roles->url($roles->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  <div class="account-modal" data-role-modal hidden>
    <div class="account-modal__backdrop" data-role-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--role" role="dialog" aria-modal="true" aria-labelledby="roleModalTitle">
      <header class="account-modal__header">
        <h2 id="roleModalTitle" data-role-modal-title>Chỉnh sửa vai trò</h2>
        <button type="button" class="account-modal__close" data-role-modal-close aria-label="Đóng">×</button>
      </header>

      <form class="account-modal__body" method="POST" data-role-permission-form>
        @csrf
        @method('PUT')

        <div class="account-tabs" role="tablist" aria-label="Vai trò">
          <button type="button" class="is-active" data-account-tab="role-info">Thuộc tính vai trò</button>
          <button type="button" data-account-tab="role-permission">Quyền</button>
        </div>

        <div class="account-tab-panel is-active" data-account-tab-panel="role-info">
          <div class="account-form-grid">
            <label>
              <span>Mã vai trò</span>
              <input type="text" data-role-code readonly>
            </label>
            <label>
              <span>Tên vai trò</span>
              <input type="text" data-role-name readonly>
            </label>
            <label>
              <span>Trạng thái</span>
              <input type="text" data-role-status readonly>
            </label>
            <label>
              <span>Mô tả</span>
              <input type="text" data-role-description readonly>
            </label>
            <label class="account-check">
              <input type="checkbox" name="mac_dinh" value="1" data-role-default>
              <span>Mặc định — dùng làm vai trò gán tự động cho tài khoản mới chưa chọn vai trò.</span>
            </label>
          </div>
        </div>

        <div class="account-tab-panel" data-account-tab-panel="role-permission">
          <label class="account-permission-search">
            <input type="search" placeholder="Tìm kiếm..." data-permission-search>
          </label>

          <div class="account-permission-tree" data-permission-tree>
            @forelse ($permissionTreeRows as $row)
              @php
                $permission = $row['permission'];
                $isFolder = $row['has_children'];
              @endphp
              <label
                class="permission-tree-row {{ $isFolder ? 'is-folder' : 'is-page' }}"
                style="--depth: {{ $row['depth'] }}"
                data-permission-item
                data-permission-id="{{ $permission->id }}"
                data-permission-parent="{{ $row['parent_id'] ?: '' }}"
                data-permission-depth="{{ $row['depth'] }}"
                data-permission-search-text="{{ Str::lower($permission->ten_quyen . ' ' . $permission->ma_quyen . ' ' . $permission->nhom_quyen) }}"
              >
                <span class="permission-tree-row__branch" aria-hidden="true"></span>
                <input
                  type="checkbox"
                  name="quyen[]"
                  value="{{ $permission->id }}"
                  data-permission-checkbox
                  data-permission-parent="{{ $row['parent_id'] ?: '' }}"
                >
                <span class="permission-tree-row__folder" aria-hidden="true"></span>
                <span class="permission-tree-row__name">{{ $permission->ten_quyen }}</span>
                <small>{{ $permission->ma_quyen }}</small>
              </label>
            @empty
              <p class="account-muted">Chưa có quyền truy cập nào trong bảng quyền.</p>
            @endforelse
          </div>

          <div class="permission-self-note">
            Nếu bạn đang thay đổi quyền của riêng bạn, hãy làm mới trang (F5) để quyền mới có hiệu lực trên màn hình hiện tại.
          </div>
        </div>

        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-role-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit">Lưu quyền truy cập</button>
        </footer>
      </form>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('backend/js/admin-roles.js') }}"></script>
@endpush
