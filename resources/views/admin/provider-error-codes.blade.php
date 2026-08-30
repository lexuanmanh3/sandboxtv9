@extends('admin.layout')

@section('title', 'VietFin - Quản lý Mã lỗi Nhà Cung Cấp')

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

    /* Thống kê nhanh cards */
    .stat-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
    }
    .stat-card {
      background: #ffffff;
      border: 1px solid var(--admin-slate-200, #e2e8f0);
      border-radius: 12px;
      padding: 16px 20px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    .stat-card__title {
      font-size: 13px;
      font-weight: 500;
      color: var(--admin-slate-500, #64748b);
    }
    .stat-card__val {
      font-size: 24px;
      font-weight: 700;
      color: var(--admin-slate-900, #0f172a);
    }
    .stat-card--success .stat-card__val { color: #059669; }
    .stat-card--pending .stat-card__val { color: #d97706; }
    .stat-card--failure .stat-card__val { color: #dc2626; }

    /* Badges hành động hệ thống */
    .badge-action {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 8px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
    }
    .badge-action-retry {
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
    }
    .badge-action-refund {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }
    .badge-action-manual {
      background: #f3e8ff;
      color: #6b21a8;
      border: 1px solid #e9d5ff;
    }
    .badge-action-none {
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #e2e8f0;
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
          <strong>Mã lỗi Nhà Cung Cấp</strong>
        </nav>
        <h1>Quản lý Mã lỗi Nhà Cung Cấp</h1>
        <p>Quản lý và định nghĩa bảng mã lỗi từ các cổng API Nhà Cung Cấp (AppotaPay, Daily...), quy định hành vi tự động cho hệ thống (Tự động hoàn tiền ví, Tự động kiểm tra lại, hoặc Chờ duyệt thủ công).</p>
      </div>

      <div class="account-actions">
        <form method="POST" action="{{ route('admin.provider-error-codes.seed-defaults') }}" style="display: inline;" onsubmit="return confirm('Bạn có muốn đồng bộ/nạp danh sách mã lỗi chuẩn cho AppotaPay từ hệ thống?');">
          @csrf
          <button class="account-btn account-btn--muted" type="submit" title="Nạp / Khôi phục danh sách mã lỗi chuẩn">
            <x-icon name="sync_alt" />
            <span>Nạp mã lỗi mẫu</span>
          </button>
        </form>

        <button class="account-btn account-btn--primary" type="button" data-modal-open="create">
          <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M12 5v14" />
            <path d="M5 12h14" />
          </svg>
          <span>Thêm mã lỗi</span>
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

    {{-- THỐNG KÊ NHANH --}}
    <div class="stat-cards-grid">
      <div class="stat-card">
        <span class="stat-card__title">Tổng số mã lỗi</span>
        <span class="stat-card__val">{{ number_format($stats['total']) }}</span>
      </div>
      <div class="stat-card stat-card--success">
        <span class="stat-card__title">Mã Thành công (SUCCESS)</span>
        <span class="stat-card__val">{{ number_format($stats['success']) }}</span>
      </div>
      <div class="stat-card stat-card--pending">
        <span class="stat-card__title">Mã Chờ xử lý (PENDING)</span>
        <span class="stat-card__val">{{ number_format($stats['pending']) }}</span>
      </div>
      <div class="stat-card stat-card--failure">
        <span class="stat-card__title">Mã Thất bại / Lỗi (FAILURE)</span>
        <span class="stat-card__val">{{ number_format($stats['failure']) }}</span>
      </div>
    </div>

    {{-- BỘ LỌC TÌM KIẾM NÂNG CAO --}}
    <section class="account-card account-filter-section {{ request()->hasAny(['q', 'nha_cung_cap_id', 'loai_ket_qua', 'hanh_dong_he_thong', 'trang_thai']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['q', 'nha_cung_cap_id', 'loai_ket_qua', 'hanh_dong_he_thong', 'trang_thai']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      <form class="account-filter" method="GET" action="{{ route('admin.provider-error-codes') }}" data-account-filter {{ request()->hasAny(['q', 'nha_cung_cap_id', 'loai_ket_qua', 'hanh_dong_he_thong', 'trang_thai']) ? '' : 'hidden' }}>
        <label>
          <span>Tìm kiếm (Mã lỗi, thông báo, ghi chú)</span>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="0, 34, Giao dịch đang chờ...">
        </label>
        <label>
          <span>Nhà cung cấp</span>
          <select name="nha_cung_cap_id">
            <option value="">Tất cả NCC</option>
            <option value="general" @selected(($filters['nha_cung_cap_id'] ?? '') === 'general')>-- Dùng chung toàn hệ thống --</option>
            @foreach ($providers as $prov)
              <option value="{{ $prov->id }}" @selected(($filters['nha_cung_cap_id'] ?? '') == $prov->id)>{{ $prov->ten_ncc }} ({{ $prov->ma_ncc }})</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Loại kết quả</span>
          <select name="loai_ket_qua">
            <option value="">Tất cả loại kết quả</option>
            @foreach ($resultTypes as $val => $info)
              <option value="{{ $val }}" @selected(($filters['loai_ket_qua'] ?? '') === $val)>{{ $info['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Hành động hệ thống</span>
          <select name="hanh_dong_he_thong">
            <option value="">Tất cả hành động</option>
            @foreach ($actionTypes as $val => $info)
              <option value="{{ $val }}" @selected(($filters['hanh_dong_he_thong'] ?? '') === $val)>{{ $info['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Trạng thái</span>
          <select name="trang_thai">
            <option value="">Tất cả trạng thái</option>
            <option value="ACTIVE" @selected(($filters['trang_thai'] ?? '') === 'ACTIVE')>Đang áp dụng (ACTIVE)</option>
            <option value="INACTIVE" @selected(($filters['trang_thai'] ?? '') === 'INACTIVE')>Tạm dừng (INACTIVE)</option>
          </select>
        </label>
        <button class="account-btn account-btn--primary" type="submit">
          <x-icon name="visibility" />
          Tìm kiếm
        </button>
        <a class="account-btn account-btn--muted" href="{{ route('admin.provider-error-codes') }}">
          Đặt lại
        </a>
      </form>
    </section>

    {{-- BẢNG DANH SÁCH MÃ LỖI --}}
    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th class="th-checkbox">
                <input type="checkbox" class="account-table-checkbox" data-select-all title="Chọn tất cả">
              </th>
              <th>Hành động</th>
              <th>Nhà cung cấp</th>
              <th>Mã lỗi (Code)</th>
              <th>Loại kết quả</th>
              <th>Hành động hệ thống</th>
              <th>Thông báo hiển thị</th>
              <th>Thông báo gốc NCC</th>
              <th>Trạng thái</th>
              <th>Thời gian tạo</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($errorCodes as $item)
              @php
                $resInfo = $resultTypes[$item->loai_ket_qua] ?? ['label' => $item->loai_ket_qua, 'class' => 'is-warn'];
                $actInfo = $actionTypes[$item->hanh_dong_he_thong] ?? ['label' => $item->hanh_dong_he_thong, 'badge' => 'badge-action-none'];
              @endphp
              <tr data-error-code-row="{{ $item->id }}" data-error-code-data="{{ json_encode([
                'id' => $item->id,
                'nha_cung_cap_id' => $item->nha_cung_cap_id,
                'ma_loi' => $item->ma_loi,
                'loai_ket_qua' => $item->loai_ket_qua,
                'thong_bao_goc' => $item->thong_bao_goc,
                'thong_bao_hien_thi' => $item->thong_bao_hien_thi,
                'hanh_dong_he_thong' => $item->hanh_dong_he_thong,
                'mo_ta' => $item->mo_ta,
                'trang_thai' => $item->trang_thai,
                'update_url' => route('admin.provider-error-codes.update', $item),
                'destroy_url' => route('admin.provider-error-codes.destroy', $item),
                'toggle_url' => route('admin.provider-error-codes.toggle-status', $item),
              ]) }}">
                <td>
                  <input type="checkbox" class="account-table-checkbox" data-row-checkbox value="{{ $item->id }}">
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
                      <button type="button" data-action="edit">
                        <x-icon name="edit" /> Sửa thông tin
                      </button>
                      <button type="button" data-action="toggle">
                        <x-icon name="sync_alt" /> {{ $item->trang_thai === 'ACTIVE' ? 'Tạm dừng' : 'Kích hoạt' }}
                      </button>
                      <button type="button" data-action="delete" style="color: #dc2626;">
                        <x-icon name="delete" /> Xóa mã lỗi
                      </button>
                    </div>
                  </div>
                </td>
                <td>
                  @if ($item->nhaCungCap)
                    <span class="account-status-pill is-yes" style="font-size: 11px;">
                      {{ $item->nhaCungCap->ten_ncc }}
                    </span>
                  @else
                    <span class="account-status-pill is-purple" style="font-size: 11px;">
                      Dùng chung
                    </span>
                  @endif
                </td>
                <td>
                  <span style="font-family: monospace; font-weight: 700; font-size: 14px; color: var(--admin-brand-800, #1e40af); background: #eff6ff; padding: 2px 8px; border-radius: 4px; border: 1px solid #dbeafe;">
                    {{ $item->ma_loi }}
                  </span>
                </td>
                <td>
                  <span class="account-status-pill {{ $resInfo['class'] }}">
                    {{ $resInfo['label'] }}
                  </span>
                </td>
                <td>
                  <span class="badge-action {{ $actInfo['badge'] }}">
                    {{ $actInfo['label'] }}
                  </span>
                </td>
                <td style="max-width: 250px;">
                  <strong style="color: #1e293b; font-size: 13px;">{{ $item->thong_bao_hien_thi ?: '-' }}</strong>
                  @if ($item->mo_ta)
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">{{ $item->mo_ta }}</div>
                  @endif
                </td>
                <td style="max-width: 250px; font-size: 12px; color: #475569;">
                  {{ $item->thong_bao_goc ?: '-' }}
                </td>
                <td>
                  <span class="account-status-pill {{ $item->trang_thai === 'ACTIVE' ? 'is-yes' : 'is-no' }}">
                    {{ $item->trang_thai === 'ACTIVE' ? 'Kích hoạt' : 'Tạm dừng' }}
                  </span>
                </td>
                <td style="font-size: 12px; color: #64748b; white-space: nowrap;">
                  {{ optional($item->created_at)->format('d/m/Y H:i') }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" style="text-align: center; padding: 40px; color: #64748b;">
                  <x-icon name="description" style="font-size: 32px; display: block; margin: 0 auto 8px; opacity: 0.4;" />
                  Chưa có mã lỗi nào phù hợp với bộ lọc tìm kiếm.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- PHÂN TRANG --}}
      <footer class="account-pagination">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <span>
            Đang xem {{ $errorCodes->firstItem() ?? 0 }} đến {{ $errorCodes->lastItem() ?? 0 }} trong tổng số {{ $errorCodes->total() }} mục
          </span>
          <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--admin-slate-600);">
            <span>Hiển thị:</span>
            <select onchange="location.href = this.value;" style="border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 4px 8px; font-size: 12px; background: #fff; cursor: pointer;">
              @foreach ([10, 20, 50, 100] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page' => $size]) }}" @selected($errorCodes->perPage() == $size)>{{ $size }}</option>
              @endforeach
            </select>
          </label>
        </div>

        <div>
          {{ $errorCodes->links() }}
        </div>
      </footer>
    </section>

    {{-- THANH TÁC VỤ NỔI (BULK ACTION BAR) --}}
    <aside class="bulk-action-bar" id="bulkActionBar" aria-label="Tác vụ hàng loạt">
      <div class="bulk-action-bar__text">
        <span>Đã chọn</span>
        <span class="bulk-action-bar__count" id="bulkCount">0</span>
        <span>mã lỗi</span>
      </div>
      <button class="bulk-action-bar__btn bulk-action-bar__btn--danger" type="button" id="bulkDeleteBtn">
        <x-icon name="delete" />
        <span>Xóa các mục đã chọn</span>
      </button>
      <button class="bulk-action-bar__btn" type="button" id="bulkCancelBtn">
        <span>Hủy</span>
      </button>
    </aside>

    {{-- MODAL THÊM MỚI / CHỈNH SỬA MÃ LỖI --}}
    <div class="account-modal" id="errorCodeModal" hidden>
      <div class="account-modal__backdrop" data-modal-close></div>
      <section class="account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle" style="max-width: 680px;">
        <header class="account-modal__header">
          <h2 id="modalTitle">Thêm mới mã lỗi Nhà Cung Cấp</h2>
          <button class="account-modal__close" type="button" data-modal-close aria-label="Đóng">&times;</button>
        </header>

        <form class="account-modal__body" method="POST" action="{{ route('admin.provider-error-codes.store') }}" id="errorCodeForm">
          @csrf
          <input type="hidden" name="_method" id="formMethod" value="POST">

          <div class="account-form-grid">
            <label>
              <span>Nhà cung cấp</span>
              <select name="nha_cung_cap_id" id="fNhaCungCapId">
                <option value="">-- Dùng chung toàn hệ thống --</option>
                @foreach ($providers as $prov)
                  <option value="{{ $prov->id }}">{{ $prov->ten_ncc }} ({{ $prov->ma_ncc }})</option>
                @endforeach
              </select>
            </label>

            <label>
              <span>Mã lỗi (errorCode) <span style="color: #dc2626;">*</span></span>
              <input type="text" name="ma_loi" id="fMaLoi" required placeholder="Ví dụ: 0, 34, 35, 99, 1...">
            </label>

            <label>
              <span>Loại kết quả phân loại <span style="color: #dc2626;">*</span></span>
              <select name="loai_ket_qua" id="fLoaiKetQua" required>
                @foreach ($resultTypes as $val => $info)
                  <option value="{{ $val }}">{{ $info['label'] }}</option>
                @endforeach
              </select>
            </label>

            <label>
              <span>Hành động hệ thống khi gặp lỗi <span style="color: #dc2626;">*</span></span>
              <select name="hanh_dong_he_thong" id="fHanhDongHeThong" required>
                @foreach ($actionTypes as $val => $info)
                  <option value="{{ $val }}">{{ $info['label'] }}</option>
                @endforeach
              </select>
            </label>

            <label class="account-form-grid__full">
              <span>Thông báo thân thiện hiển thị cho User/Admin</span>
              <input type="text" name="thong_bao_hien_thi" id="fThongBaoHienThi" placeholder="Ví dụ: Giao dịch đang chờ nhà mạng xử lý, hệ thống đang kiểm tra lại...">
            </label>

            <label class="account-form-grid__full">
              <span>Thông điệp gốc từ phía NCC (Message)</span>
              <input type="text" name="thong_bao_goc" id="fThongBaoGoc" placeholder="Ví dụ: Giao dịch đang chờ xử lý vui lòng kiểm tra lại sau">
            </label>

            <label>
              <span>Ghi chú / Hướng xử lý nội bộ</span>
              <input type="text" name="mo_ta" id="fMoTa" placeholder="Ghi chú thêm...">
            </label>

            <label>
              <span>Trạng thái</span>
              <select name="trang_thai" id="fTrangThai">
                <option value="ACTIVE">Kích hoạt (ACTIVE)</option>
                <option value="INACTIVE">Tạm dừng (INACTIVE)</option>
              </select>
            </label>
          </div>

          <footer class="account-modal__footer">
            <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
            <button class="account-btn account-btn--primary" type="submit">
              <x-icon name="save" />
              <span id="submitBtnText">Lưu mã lỗi</span>
            </button>
          </footer>
        </form>
      </section>
    </div>

    {{-- MODAL XÁC NHẬN XÓA ĐƠN LẺ --}}
    <div class="account-modal" id="deleteConfirmModal" hidden>
      <div class="account-modal__backdrop" data-modal-close></div>
      <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="deleteSingleModalTitle" style="max-width: 480px;">
        <header class="account-modal__header">
          <h2 id="deleteSingleModalTitle">Xác nhận xóa mã lỗi</h2>
          <button class="account-modal__close" type="button" data-modal-close aria-label="Đóng">&times;</button>
        </header>
        <form class="account-modal__body" method="POST" action="" id="deleteSingleForm">
          @csrf
          @method('DELETE')
          <p class="account-confirm-text">
            Bạn có chắc chắn muốn xóa mã lỗi <strong id="deleteTargetCode" style="color: #dc2626;"></strong> khỏi hệ thống? Thao tác này không thể hoàn tác.
          </p>
          <footer class="account-modal__footer">
            <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
            <button class="account-btn account-btn--danger" type="submit" style="background: #dc2626; color: #fff;">
              <x-icon name="delete" /> Xác nhận xóa
            </button>
          </footer>
        </form>
      </section>
    </div>

    {{-- MODAL XÁC NHẬN XÓA HÀNG LOẠT --}}
    <div class="account-modal" id="bulkDeleteConfirmModal" hidden>
      <div class="account-modal__backdrop" data-modal-close></div>
      <section class="account-modal__dialog account-modal__dialog--sm" role="dialog" aria-modal="true" aria-labelledby="bulkDeleteModalTitle" style="max-width: 480px;">
        <header class="account-modal__header">
          <h2 id="bulkDeleteModalTitle">Xác nhận xóa hàng loạt</h2>
          <button class="account-modal__close" type="button" data-modal-close aria-label="Đóng">&times;</button>
        </header>
        <form class="account-modal__body" method="POST" action="{{ route('admin.provider-error-codes.bulk-delete') }}" id="bulkDeleteForm">
          @csrf
          <div id="bulkDeleteHiddenInputs"></div>
          <p class="account-confirm-text">
            Bạn có chắc chắn muốn xóa <strong id="bulkDeleteCountText" style="color: #dc2626;">0</strong> mã lỗi đã chọn khỏi hệ thống? Thao tác này không thể hoàn tác.
          </p>
          <footer class="account-modal__footer">
            <button class="account-btn account-btn--muted" type="button" data-modal-close>Hủy</button>
            <button class="account-btn account-btn--danger" type="submit" style="background: #dc2626; color: #fff;">
              <x-icon name="delete" /> Xác nhận xóa tất cả
            </button>
          </footer>
        </form>
      </section>
    </div>

    {{-- FORM TOGGLE STATUS NHANH --}}
    <form id="quickToggleForm" method="POST" action="" style="display: none;">
      @csrf
      @method('PATCH')
    </form>
  </section>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Toggle Filter
      const filterToggle = document.querySelector('[data-account-filter-toggle]');
      const filterForm = document.querySelector('[data-account-filter]');
      const filterSection = document.querySelector('.account-filter-section');

      if (filterToggle && filterForm) {
        filterToggle.addEventListener('click', () => {
          const isExpanded = filterToggle.getAttribute('aria-expanded') === 'true';
          filterToggle.setAttribute('aria-expanded', !isExpanded);
          filterForm.hidden = isExpanded;
          if (filterSection) {
            filterSection.classList.toggle('is-open', !isExpanded);
          }
        });
      }

      // Row actions dropdown
      document.addEventListener('click', (e) => {
        const actionBtn = e.target.closest('[data-row-action]');
        const allMenus = document.querySelectorAll('.account-row-menu');

        if (actionBtn) {
          e.stopPropagation();
          const currentMenu = actionBtn.nextElementSibling;
          allMenus.forEach(m => {
            if (m !== currentMenu) m.hidden = true;
          });
          if (currentMenu) {
            currentMenu.hidden = !currentMenu.hidden;
          }
        } else {
          allMenus.forEach(m => m.hidden = true);
        }
      });

      // Modals
      const errorCodeModal = document.getElementById('errorCodeModal');
      const deleteConfirmModal = document.getElementById('deleteConfirmModal');
      const bulkDeleteConfirmModal = document.getElementById('bulkDeleteConfirmModal');
      const modalForm = document.getElementById('errorCodeForm');
      const formMethod = document.getElementById('formMethod');
      const modalTitle = document.getElementById('modalTitle');
      const submitBtnText = document.getElementById('submitBtnText');

      function openModal(modal) {
        if (!modal) return;
        closeModals();
        modal.hidden = false;
        document.body.classList.add('account-modal-open');
      }

      function closeModals() {
        document.querySelectorAll('.account-modal').forEach(m => {
          m.hidden = true;
        });
        document.body.classList.remove('account-modal-open');
      }

      // Close modal buttons & backdrop click
      document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', closeModals);
      });

      // ESC key to close modal & dropdown menus
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' || e.key === 'Esc') {
          closeModals();
          document.querySelectorAll('.account-row-menu').forEach(m => m.hidden = true);
        }
      });

      // Open Create Modal
      document.querySelector('[data-modal-open="create"]')?.addEventListener('click', () => {
        modalTitle.textContent = 'Thêm mới mã lỗi Nhà Cung Cấp';
        submitBtnText.textContent = 'Lưu mã lỗi';
        modalForm.action = '{{ route('admin.provider-error-codes.store') }}';
        formMethod.value = 'POST';

        document.getElementById('fNhaCungCapId').value = '';
        document.getElementById('fMaLoi').value = '';
        document.getElementById('fLoaiKetQua').value = 'DEFINITIVE_FAILURE';
        document.getElementById('fHanhDongHeThong').value = 'REFUND_WALLET';
        document.getElementById('fThongBaoHienThi').value = '';
        document.getElementById('fThongBaoGoc').value = '';
        document.getElementById('fMoTa').value = '';
        document.getElementById('fTrangThai').value = 'ACTIVE';

        openModal(errorCodeModal);
      });

      // Open Edit Modal / Actions
      document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const row = btn.closest('[data-error-code-row]');
        if (!row) return;

        const data = JSON.parse(row.getAttribute('data-error-code-data') || '{}');
        const action = btn.getAttribute('data-action');

        if (action === 'edit') {
          modalTitle.textContent = `Chỉnh sửa mã lỗi: ${data.ma_loi}`;
          submitBtnText.textContent = 'Cập nhật mã lỗi';
          modalForm.action = data.update_url;
          formMethod.value = 'PUT';

          document.getElementById('fNhaCungCapId').value = data.nha_cung_cap_id || '';
          document.getElementById('fMaLoi').value = data.ma_loi || '';
          document.getElementById('fLoaiKetQua').value = data.loai_ket_qua || 'DEFINITIVE_FAILURE';
          document.getElementById('fHanhDongHeThong').value = data.hanh_dong_he_thong || 'REFUND_WALLET';
          document.getElementById('fThongBaoHienThi').value = data.thong_bao_hien_thi || '';
          document.getElementById('fThongBaoGoc').value = data.thong_bao_goc || '';
          document.getElementById('fMoTa').value = data.mo_ta || '';
          document.getElementById('fTrangThai').value = data.trang_thai || 'ACTIVE';

          openModal(errorCodeModal);
        } else if (action === 'toggle') {
          const quickForm = document.getElementById('quickToggleForm');
          quickForm.action = data.toggle_url;
          quickForm.submit();
        } else if (action === 'delete') {
          const delForm = document.getElementById('deleteSingleForm');
          delForm.action = data.destroy_url;
          document.getElementById('deleteTargetCode').textContent = data.ma_loi;
          openModal(deleteConfirmModal);
        }
      });

      // Select All & Bulk Actions
      const selectAllCheckbox = document.querySelector('[data-select-all]');
      const rowCheckboxes = document.querySelectorAll('[data-row-checkbox]');
      const bulkBar = document.getElementById('bulkActionBar');
      const bulkCount = document.getElementById('bulkCount');
      const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
      const bulkCancelBtn = document.getElementById('bulkCancelBtn');

      function updateBulkBar() {
        const checked = document.querySelectorAll('[data-row-checkbox]:checked');
        const count = checked.length;

        if (count > 0) {
          bulkCount.textContent = count;
          bulkBar.classList.add('is-visible');
        } else {
          bulkBar.classList.remove('is-visible');
        }

        if (selectAllCheckbox) {
          selectAllCheckbox.checked = count > 0 && count === rowCheckboxes.length;
        }
      }

      if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
          rowCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
          updateBulkBar();
        });
      }

      rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkBar);
      });

      if (bulkCancelBtn) {
        bulkCancelBtn.addEventListener('click', () => {
          rowCheckboxes.forEach(cb => cb.checked = false);
          if (selectAllCheckbox) selectAllCheckbox.checked = false;
          updateBulkBar();
        });
      }

      if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', () => {
          const checked = document.querySelectorAll('[data-row-checkbox]:checked');
          if (checked.length === 0) return;

          const container = document.getElementById('bulkDeleteHiddenInputs');
          container.innerHTML = '';
          checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            container.appendChild(input);
          });

          document.getElementById('bulkDeleteCountText').textContent = checked.length;
          openModal(bulkDeleteConfirmModal);
        });
      }
    });
  </script>
@endpush

