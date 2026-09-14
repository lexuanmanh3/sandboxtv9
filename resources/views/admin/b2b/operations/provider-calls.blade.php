@extends('admin.layout')

@section('title', 'tv9tech - Nhật ký gọi Nhà cung cấp')

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
        <a href="#">Vận hành</a>
        <span>/</span>
        <strong>Nhật ký gọi NCC</strong>
      </nav>
      <h1>Nhật ký gọi Nhà cung cấp</h1>
      <p>Lịch sử các lần hệ thống gửi yêu cầu sang API Nhà cung cấp.</p>
    </div>
  </header>

  {{-- BỘ LỌC TÌM KIẾM --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.operations.provider-calls') }}">
      <label style="flex: 2; min-width: 200px;">
        <span>Mã đơn hàng</span>
        <input type="text" name="ma_don_hang" value="{{ request('ma_don_hang') }}" placeholder="Nhập mã đơn hàng...">
      </label>

      <label style="flex: 2; min-width: 200px;">
        <span>Mã GD đối tác (Ref ID)</span>
        <input type="text" name="partner_ref_id" value="{{ request('partner_ref_id') }}" placeholder="Nhập mã đối tác trả về...">
      </label>

      <label style="width: 180px;">
        <span>Trạng thái</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="SUCCESS" @selected(request('trang_thai') === 'SUCCESS')>Thành công (SUCCESS)</option>
          <option value="PENDING" @selected(request('trang_thai') === 'PENDING')>Chờ xử lý (PENDING)</option>
          <option value="FAILED" @selected(request('trang_thai') === 'FAILED')>Thất bại (FAILED)</option>
        </select>
      </label>

      <button class="account-btn account-btn--primary" type="submit">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Lọc</span>
      </button>

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.operations.provider-calls') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- BẢNG DANH SÁCH NHẬT KÝ --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ĐƠN HÀNG</th>
            <th>NHÀ CUNG CẤP</th>
            <th>PARTNER REF ID</th>
            <th>THỜI GIAN GỌI</th>
            <th>THỜI GIAN XỬ LÝ</th>
            <th style="text-align: center;">TRẠNG THÁI</th>
            <th style="text-align: right;">CHI TIẾT</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td>#{{ $log->id }}</td>
              <td>
                @if($log->donHang)
                  <a href="{{ route('admin.b2b.orders.show', $log->donHang->id) }}" style="font-weight: 700; color: var(--admin-brand-700); text-decoration: none;">
                    {{ $log->donHang->ma_don_hang }}
                  </a>
                @else
                  <span style="color: var(--admin-slate-400); font-style: italic;">Đơn #{{ $log->don_hang_id ?? 'N/A' }}</span>
                @endif
              </td>
              <td>
                <span class="account-role" style="background: var(--admin-slate-100); color: var(--admin-slate-800); font-weight: 700;">
                  {{ $log->nhaCungCap?->ten_ncc ?: ($log->nhaCungCap?->ma_ncc ?: 'NCC #' . $log->nha_cung_cap_id) }}
                </span>
              </td>
              <td>
                <span style="font-family: monospace; font-size: 13px;">{{ $log->partner_ref_id ?: '-' }}</span>
              </td>
              <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
              <td>{{ $log->thoi_gian_xu_ly_ms ? $log->thoi_gian_xu_ly_ms . ' ms' : '-' }}</td>
              <td style="text-align: center;">
                @if($log->trang_thai === 'SUCCESS')
                  <span class="account-status-pill is-yes">SUCCESS</span>
                @elseif($log->trang_thai === 'FAILED')
                  <span class="account-status-pill" style="background: #fee2e2; color: #dc2626;">FAILED</span>
                @elseif($log->trang_thai === 'PENDING')
                  <span class="account-status-pill" style="background: #fef3c7; color: #d97706;">PENDING</span>
                @else
                  <span class="account-status-pill is-no">{{ $log->trang_thai }}</span>
                @endif
              </td>
              <td style="text-align: right;">
                <button class="account-btn account-btn--muted account-btn--sm" type="button" style="min-height: 28px; padding: 4px 10px; font-size: 12px;" onclick="viewLogDetail({{ $log->id }})">
                  Xem Log
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="8">Chưa có nhật ký gọi nhà cung cấp nào.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($logs->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $logs->firstItem() ?? 0 }} đến {{ $logs->lastItem() ?? 0 }} trong tổng số {{ $logs->total() }} bản ghi
        </span>
        <div>
          {{ $logs->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>

{{-- MODAL XEM CHI TIẾT LOG --}}
<div class="account-modal" id="logDetailModal" hidden>
  <div class="account-modal__backdrop" onclick="closeLogModal()"></div>
  <section class="account-modal__dialog" style="max-width: 700px; width: 100%;">
    <header class="account-modal__header">
      <h2 id="logModalTitle">Chi tiết nhật ký gọi NCC</h2>
      <button class="account-modal__close" type="button" onclick="closeLogModal()">&times;</button>
    </header>
    <div class="account-modal__body" style="padding: 20px;">
      <div style="display: grid; gap: 16px;">
        <div>
          <label style="font-weight: 700; font-size: 12px; color: var(--admin-slate-500); text-transform: uppercase;">Request Payload (JSON)</label>
          <pre id="logRequestJson" style="background: var(--admin-slate-900); color: #f8fafc; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin-top: 6px;"></pre>
        </div>
        <div>
          <label style="font-weight: 700; font-size: 12px; color: var(--admin-slate-500); text-transform: uppercase;">Response Payload (JSON)</label>
          <pre id="logResponseJson" style="background: var(--admin-slate-900); color: #f8fafc; padding: 12px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin-top: 6px;"></pre>
        </div>
      </div>
    </div>
    <footer class="account-modal__footer">
      <button class="account-btn account-btn--muted" type="button" onclick="closeLogModal()">Đóng</button>
    </footer>
  </section>
</div>
@endsection

@push('scripts')
<script>
const logsData = @json($logs->keyBy('id'));

function viewLogDetail(id) {
  const log = logsData[id];
  if (!log) return;
  document.getElementById('logModalTitle').innerText = 'Chi tiết lần gọi NCC #' + log.id;
  document.getElementById('logRequestJson').innerText = log.request_json ? JSON.stringify(log.request_json, null, 2) : 'Trống';
  document.getElementById('logResponseJson').innerText = log.response_json ? JSON.stringify(log.response_json, null, 2) : 'Trống';
  document.getElementById('logDetailModal').hidden = false;
}

function closeLogModal() {
  document.getElementById('logDetailModal').hidden = true;
}
</script>
@endpush
