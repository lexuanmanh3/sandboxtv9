@extends('admin.layout')

@section('title', 'VietFin - Quản lý tài khoản hệ thống')

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
          <strong>Quản lý tài khoản hệ thống</strong>
        </nav>
        <h1>Quản lý tài khoản hệ thống</h1>
        <p>Quản lý tài khoản người dùng và phân quyền truy cập ứng dụng.</p>
      </div>

      <div class="account-actions">
        <div class="account-excel-actions">
          <button class="account-btn account-btn--muted" type="button" data-account-excel-toggle aria-expanded="false">
            <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path d="M7 3h7l5 5v13H7z" />
              <path d="M14 3v5h5" />
              <path d="m10 11 4 6" />
              <path d="m14 11-4 6" />
            </svg>
            <span>Hoạt động Excel</span>
            <svg class="account-btn__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
              <path d="M5 7.5 10 12.5 15 7.5" />
            </svg>
          </button>
          <div class="account-excel-menu" data-account-excel-menu hidden>
            <a href="{{ route('admin.accounts.export', request()->only(['q', 'loai_tai_khoan', 'trang_thai'])) }}">
              <x-icon name="description" />
              <span>Xuất sang excel</span>
            </a>
          </div>
        </div>

        {{-- NOTE: Nut nay mo modal tao tai khoan, form submit ve route store cua module users. --}}
        <button class="account-btn account-btn--primary" type="button" data-account-modal-open="create">
          <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          <span>Tạo tài khoản người dùng mới</span>
        </button>
      </div>
    </header>

    {{-- NOTE: Khu vuc thong bao server tra ve sau cac thao tac tao/sua/xoa/khoa tai khoan. --}}
    @if (session('success') || session('temporary_password') || $errors->any())
      <section class="account-alerts" aria-live="polite">
        @if (session('success'))
          <div class="account-alert account-alert--success">{{ session('success') }}</div>
        @endif
        @if (session('temporary_password'))
          <div class="account-alert account-alert--warning">
            Mật khẩu tạm thời chỉ hiển thị một lần: <strong>{{ session('temporary_password') }}</strong>
          </div>
        @endif
        @if ($errors->any())
          <div class="account-alert account-alert--danger">
            {{ $errors->first() }}
          </div>
        @endif
      </section>
    @endif

    <section class="account-stats" aria-label="Thống kê tài khoản">
      <article class="account-stat">
        <span><x-icon name="account_circle" /></span>
        <div>
          <p>Tổng tài khoản</p>
          <strong>{{ $stats['total'] }}</strong>
        </div>
      </article>
      <article class="account-stat">
        <span><x-icon name="check_circle" /></span>
        <div>
          <p>Đang hoạt động</p>
          <strong>{{ $stats['active'] }}</strong>
        </div>
      </article>
      <article class="account-stat">
        <span><x-icon name="error" /></span>
        <div>
          <p>Bị khóa</p>
          <strong>{{ $stats['locked'] }}</strong>
        </div>
      </article>
      <article class="account-stat">
        <span><x-icon name="history" /></span>
        <div>
          <p>Mới trong tháng</p>
          <strong>{{ $stats['new_this_month'] }}</strong>
        </div>
      </article>
    </section>

    <section class="account-card account-filter-section {{ request()->hasAny(['q', 'loai_tai_khoan', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['q', 'loai_tai_khoan', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      {{-- NOTE: Form filter dung GET de giu query khi phan trang Laravel. --}}
      <form class="account-filter" method="GET" action="{{ route('admin.accounts') }}" data-account-filter {{ request()->hasAny(['q', 'loai_tai_khoan', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Từ khóa</span>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="username, email, số điện thoại...">
        </label>
        <label>
          <span>Loại tài khoản</span>
          <select name="loai_tai_khoan">
            <option value="">Tất cả</option>
            @foreach ($accountTypes as $value => $label)
              <option value="{{ $value }}" @selected(($filters['loai_tai_khoan'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Trạng thái</span>
          <select name="trang_thai">
            <option value="">Tất cả</option>
            <option value="hoat_dong" @selected(($filters['trang_thai'] ?? '') === 'hoat_dong')>Hoạt động</option>
            <option value="bi_khoa" @selected(($filters['trang_thai'] ?? '') === 'bi_khoa')>Bị khóa</option>
            <option value="tam_khoa" @selected(($filters['trang_thai'] ?? '') === 'tam_khoa')>Tạm khóa</option>
            <option value="cho_duyet" @selected(($filters['trang_thai'] ?? '') === 'cho_duyet')>Chờ duyệt</option>
            <option value="huy" @selected(($filters['trang_thai'] ?? '') === 'huy')>Hủy</option>
          </select>
        </label>
        <button class="account-btn account-btn--primary" type="submit">
          <x-icon name="visibility" />
          Tìm kiếm
        </button>
      </form>
    </section>

    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th>Hành động</th>
              <th>Tên truy cập</th>
              <th>Họ tên</th>
              <th>Email</th>
              <th>Số điện thoại</th>
              <th>Loại tài khoản</th>
              <th>Vai trò</th>
              <th>Hoạt động</th>
              <th>Thời gian tạo</th>
            </tr>
          </thead>
          <tbody data-account-table-body>
            @forelse ($users as $user)
              @php
                $roleIds = $user->vaiTro->pluck('id')->values();
                $roleNames = $user->vaiTro->pluck('ten_vai_tro')->filter()->values();
                $roleLabel = $roleNames->isNotEmpty() ? $roleNames->join(', ') : '-';
                $isActive = $user->trang_thai === 'hoat_dong' && ! $user->bi_khoa;
                $isRootAdmin = $user->ten_dang_nhap === 'admin' && $user->loai_tai_khoan === 'admin';
                $isCurrentUser = auth()->id() === $user->id;
                $payload = [
                  'id' => $user->id,
                  'ten_dang_nhap' => $user->ten_dang_nhap,
                  'ho' => $user->ho,
                  'ten' => $user->ten,
                  'name' => $user->name,
                  'email' => $user->email,
                  'so_dien_thoai' => $user->so_dien_thoai,
                  'loai_tai_khoan' => $user->loai_tai_khoan,
                  'trang_thai' => $user->trang_thai,
                  'bi_khoa' => (bool) $user->bi_khoa,
                  'vai_tro' => $roleIds,
                  'is_root_admin' => $isRootAdmin,
                  'is_current_user' => $isCurrentUser,
                  'urls' => [
                    'update' => route('admin.accounts.update', $user),
                    'password' => route('admin.accounts.password', $user),
                    'lock' => route('admin.accounts.lock', $user),
                    'unlock' => route('admin.accounts.unlock', $user),
                    'destroy' => route('admin.accounts.destroy', $user),
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
                      {{-- NOTE: Cac nut dropdown chi mo modal, thao tac that duoc submit ve route co CSRF/method rieng. --}}
                      <button type="button" data-account-modal-open="edit" data-account='@json($payload)'>Sửa tài khoản</button>
                      <button type="button" data-account-modal-open="password" data-account='@json($payload)' @disabled($isRootAdmin)>Đổi mật khẩu</button>
                      @if ($user->bi_khoa)
                        <button type="button" data-account-action-submit="{{ route('admin.accounts.unlock', $user) }}" data-method="PATCH" @disabled($isRootAdmin)>Mở khóa tài khoản</button>
                      @else
                        <button type="button" data-account-action-submit="{{ route('admin.accounts.lock', $user) }}" data-method="PATCH" @disabled($isRootAdmin || $isCurrentUser)>Khóa tài khoản</button>
                      @endif
                      <button type="button" data-account-modal-open="delete" data-account='@json($payload)' @disabled($isRootAdmin || $isCurrentUser)>Xóa tài khoản</button>
                    </div>
                  </div>
                </td>
                <td class="account-username">{{ $user->ten_dang_nhap }}</td>
                <td>{{ $user->ten_hien_thi ?: '-' }}</td>
                <td>{{ $user->email ?: '-' }}</td>
                <td>{{ $user->so_dien_thoai ?: '-' }}</td>
                <td>{{ $accountTypes[$user->loai_tai_khoan] ?? $user->loai_tai_khoan }}</td>
                <td><span class="account-role">{{ $roleLabel }}</span></td>
                <td>
                  <span class="account-status-pill {{ $isActive ? 'is-yes' : 'is-no' }}">
                    {{ $isActive ? 'Đồng ý' : 'Không' }}
                  </span>
                </td>
                <td>{{ optional($user->created_at)->format('d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="9">Chưa có tài khoản nào trong hệ thống.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div>
          <span>
            Đang xem {{ $users->firstItem() ?? 0 }} đến {{ $users->lastItem() ?? 0 }} trong tổng số {{ $users->total() }} mục
          </span>
        </div>

        <nav aria-label="Phân trang tài khoản">
          <a class="{{ $users->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $users->url(1) }}">«</a>
          <a class="{{ $users->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $users->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($users->lastPage(), 5); $page++)
            <a class="{{ $users->currentPage() === $page ? 'is-active' : '' }}" href="{{ $users->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $users->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $users->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $users->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $users->url($users->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- NOTE: Form an dung cho cac action nhanh khoa/mo khoa, giup van gui CSRF va method spoofing dung Laravel. --}}
  <form id="accountQuickActionForm" method="POST" hidden>
    @csrf
    <input type="hidden" name="_method" value="PATCH">
  </form>

  {{-- NOTE: Modal tao/sua tai khoan giu style trang admin, co tab thong tin, vai tro va co cau to chuc. --}}
  <div class="account-modal" data-account-modal="user" hidden>
    <div class="account-modal__backdrop" data-account-modal-close></div>
    <section class="account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="accountUserModalTitle">
      <header class="account-modal__header">
        <h2 id="accountUserModalTitle" data-user-modal-title>Tạo tài khoản người dùng mới</h2>
        <button type="button" class="account-modal__close" data-account-modal-close aria-label="Đóng">×</button>
      </header>

      <form class="account-modal__body" method="POST" action="{{ route('admin.accounts.store') }}" data-user-form>
        @csrf
        <input type="hidden" name="_method" value="POST" data-user-form-method>

        <div class="account-tabs" role="tablist" aria-label="Thông tin tài khoản">
          <button type="button" class="is-active" data-account-tab="info">Thông tin người dùng</button>
          <button type="button" data-account-tab="roles">Vai trò <span>{{ $roles->count() }}</span></button>
          <button type="button" data-account-tab="org">Cơ cấu Tổ chức</button>
        </div>

        <div class="account-tab-panel is-active" data-account-tab-panel="info">
          <div class="account-form-grid">
            <label>
              <span>Tên truy cập (*)</span>
              <input type="text" name="ten_dang_nhap" required>
            </label>
            <label>
              <span>Họ</span>
              <input type="text" name="ho">
            </label>
            <label>
              <span>Tên</span>
              <input type="text" name="ten">
            </label>
            <label>
              <span>Tên hiển thị</span>
              <input type="text" name="name">
            </label>
            <label>
              <span>Địa chỉ email</span>
              <input type="email" name="email">
            </label>
            <label>
              <span>Số điện thoại</span>
              {{-- NOTE: Thuoc tinh HTML nay chi ho tro UX nhap so dien thoai Viet Nam.
                   Backend van validate chinh tai Store/UpdateUserAccountRequest bang regex /^0[0-9]{9}$/. --}}
              <input type="text" name="so_dien_thoai" inputmode="numeric" maxlength="10" pattern="0[0-9]{9}">
              @error('so_dien_thoai')
                <small class="account-field-error">{{ $message }}</small>
              @enderror
            </label>
            <label>
              <span>Loại tài khoản (*)</span>
              <select name="loai_tai_khoan" required>
                @foreach ($accountTypes as $value => $label)
                  <option value="{{ $value }}" @selected($value === 'customer')>{{ $label }}</option>
                @endforeach
              </select>
            </label>
            <label>
              <span>Trạng thái (*)</span>
              <select name="trang_thai" required>
                @foreach ($statuses as $value => $label)
                  <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </label>
            <label class="account-check account-create-only">
              <input type="checkbox" name="tao_mat_khau_ngau_nhien" value="1" data-random-password>
              <span>Đặt mật khẩu ngẫu nhiên.</span>
            </label>
            <label class="account-check account-edit-only">
              <input type="checkbox" name="bi_khoa" value="1">
              <span>Khóa tài khoản này.</span>
            </label>
            <label class="account-create-only">
              <span>Mật khẩu</span>
              <input type="password" name="password" autocomplete="new-password">
            </label>
            <label class="account-create-only">
              <span>Mật khẩu nhắc lại</span>
              <input type="password" name="password_confirmation" autocomplete="new-password">
            </label>
            <label class="account-edit-only">
              <span>M&#7853;t kh&#7849;u m&#7899;i</span>
              {{-- NOTE: Field nay chi dung khi sua tai khoan; de trong thi controller khong cap nhat password.
                   Neu doi chinh sach do dai/confirmed, bao tri rule tai UpdateUserAccountRequest. --}}
              <input type="password" name="password" minlength="8" autocomplete="new-password">
              <small class="account-field-note">&#272;&#7875; tr&#7889;ng n&#7871;u kh&#244;ng mu&#7889;n &#273;&#7893;i m&#7853;t kh&#7849;u</small>
              @error('password')
                <small class="account-field-error">{{ $message }}</small>
              @enderror
            </label>
            <label class="account-edit-only">
              <span>Nh&#7853;p l&#7841;i m&#7853;t kh&#7849;u m&#7899;i</span>
              {{-- NOTE: Field xac nhan password cua form sua, giup backend confirmed khop truoc khi hash va luu. --}}
              <input type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
              @error('password_confirmation')
                <small class="account-field-error">{{ $message }}</small>
              @enderror
            </label>
          </div>
        </div>

        <div class="account-tab-panel" data-account-tab-panel="roles">
          <div class="account-role-grid">
            @forelse ($roles as $role)
              <label class="account-check">
                <input type="checkbox" name="vai_tro[]" value="{{ $role->id }}">
                <span>{{ $role->ten_vai_tro }} <small>{{ $role->ma_vai_tro }}</small></span>
              </label>
            @empty
              <p class="account-muted">Chưa có vai trò nào trong bảng vai_tro.</p>
            @endforelse
          </div>
        </div>

        <div class="account-tab-panel" data-account-tab-panel="org">
          {{-- TODO: Source hien tai chua co bang don vi to chuc/pivot user-to-chuc, bo sung tai day khi co schema. --}}
          <p class="account-muted">Chưa có bảng cơ cấu tổ chức trong source hiện tại. Tab này được giữ sẵn theo demo để gán đơn vị sau.</p>
        </div>

        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-account-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit">Lưu tài khoản</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- NOTE: Modal doi mat khau tach rieng de khong hien password hash va khong anh huong form sua thong tin. --}}
  <div class="account-modal" data-account-modal="password" hidden>
    <div class="account-modal__backdrop" data-account-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="accountPasswordModalTitle">
      <header class="account-modal__header">
        <h2 id="accountPasswordModalTitle">Đổi mật khẩu</h2>
        <button type="button" class="account-modal__close" data-account-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" data-password-form>
        @csrf
        @method('PUT')
        <div class="account-form-grid account-form-grid--single">
          <label>
            <span>Mật khẩu mới (*)</span>
            {{-- NOTE: Modal doi mat khau rieng dong bo minlength 8 voi UpdateUserPasswordRequest. --}}
            <input type="password" name="password" required minlength="8" autocomplete="new-password">
          </label>
          <label>
            <span>Nhập lại mật khẩu mới (*)</span>
            {{-- NOTE: Xac nhan password moi de rule confirmed cua backend co du lieu doi chieu. --}}
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
          </label>
          <label class="account-check">
            <input type="checkbox" name="bat_buoc_doi_mat_khau" value="1">
            <span>Bắt buộc đổi mật khẩu lần đăng nhập sau.</span>
          </label>
        </div>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-account-modal-close>Hủy</button>
          <button class="account-btn account-btn--primary" type="submit">Cập nhật mật khẩu</button>
        </footer>
      </form>
    </section>
  </div>

  {{-- NOTE: Modal xac nhan xoa mem tai khoan, submit DELETE ve controller destroy. --}}
  <div class="account-modal" data-account-modal="delete" hidden>
    <div class="account-modal__backdrop" data-account-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="accountDeleteModalTitle">
      <header class="account-modal__header">
        <h2 id="accountDeleteModalTitle">Xóa tài khoản</h2>
        <button type="button" class="account-modal__close" data-account-modal-close aria-label="Đóng">×</button>
      </header>
      <form class="account-modal__body" method="POST" data-delete-form>
        @csrf
        @method('DELETE')
        <p class="account-confirm-text">Bạn có chắc muốn xóa tài khoản <strong data-delete-account-name></strong>? Tài khoản sẽ được khóa và chuyển sang trạng thái đã xóa.</p>
        <footer class="account-modal__footer">
          <button class="account-btn account-btn--muted" type="button" data-account-modal-close>Hủy</button>
          <button class="account-btn account-btn--danger" type="submit">Xóa tài khoản</button>
        </footer>
      </form>
    </section>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('backend/js/admin-accounts.js') }}"></script>
@endpush
