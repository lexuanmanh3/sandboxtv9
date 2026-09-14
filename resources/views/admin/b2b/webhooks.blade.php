@extends('admin.layout')

@section('title', 'tv9tech - Lịch sử Webhook B2B')

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
        <a href="#">Quản lý đại lý</a>
        <span>/</span>
        <strong>Lịch sử Webhook</strong>
      </nav>
      <h1>Lịch sử Webhook Outbox</h1>
      <p>Theo dõi danh sách sự kiện thông báo gửi tới đại lý, nhật ký HTTP và kích hoạt gửi lại khi gặp sự cố mạng.</p>
    </div>
  </header>

  @if (session('success') || session('error'))
    <section class="account-alerts" aria-live="polite">
      @if (session('success'))
        <div class="account-alert account-alert--success">{{ session('success') }}</div>
      @endif
      @if (session('error'))
        <div class="account-alert account-alert--danger">{{ session('error') }}</div>
      @endif
    </section>
  @endif

  {{-- Bộ lọc --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.webhooks.index') }}">
      <label style="flex: 2; min-width: 250px;">
        <span>Event ID</span>
        <input type="text" name="event_id" value="{{ request('event_id') }}" placeholder="Tìm theo Event ID...">
      </label>

      <label style="width: 220px;">
        <span>Đại lý</span>
        <select name="dai_ly_api_id">
          <option value="">-- Tất cả đại lý --</option>
          @foreach($partners as $p)
            <option value="{{ $p->id }}" @selected(request('dai_ly_api_id') == $p->id)>{{ $p->ten_dai_ly_api }}</option>
          @endforeach
        </select>
      </label>

      <label style="width: 170px;">
        <span>Trạng thái gửi</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="SUCCESS" @selected(request('trang_thai') === 'SUCCESS')>Thành công (SUCCESS)</option>
          <option value="PENDING" @selected(request('trang_thai') === 'PENDING')>Chờ gửi (PENDING)</option>
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

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.webhooks.index') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- Bảng webhook outbox --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th>Event ID</th>
            <th>Đại lý</th>
            <th>Mã đơn hàng</th>
            <th>Loại sự kiện</th>
            <th style="text-align: center;">Số lần thử</th>
            <th style="text-align: center;">Trạng thái</th>
            <th>Thời gian tạo</th>
            <th style="text-align: center; width: 120px;">Hành động</th>
          </tr>
        </thead>
        <tbody>
          @forelse($webhooks as $outbox)
            <tr>
              <td class="account-username" style="font-size: 11px;">{{ $outbox->event_id }}</td>
              <td>
                <strong style="color: var(--admin-slate-800);">{{ $outbox->daiLyApi?->ten_dai_ly_api }}</strong>
                <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $outbox->daiLyApi?->ma_dai_ly_api }}</div>
              </td>
              <td>
                <span style="font-family: monospace; font-weight: 700; color: #0284c7;">#{{ $outbox->don_hang_id }}</span>
                <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $outbox->donHang?->ma_don_doi_tac }}</div>
              </td>
              <td>
                <span style="font-size: 12px; font-weight: 600;">{{ $outbox->event_type }}</span>
              </td>
              <td style="text-align: center; font-weight: 700;">
                {{ $outbox->so_lan_thu }} / 5
              </td>
              <td style="text-align: center;">
                @php
                  $stClass = match($outbox->trang_thai) {
                    'SUCCESS' => 'is-yes',
                    'FAILED' => 'is-no',
                    default => 'is-pending',
                  };
                @endphp
                <span class="account-status-pill {{ $stClass }}">
                  {{ $outbox->trang_thai }}
                </span>
              </td>
              <td style="font-size: 12px; color: var(--admin-slate-500);">
                {{ $outbox->created_at ? $outbox->created_at->format('d/m/Y H:i:s') : '-' }}
              </td>
              <td style="text-align: center;">
                <form method="POST" action="{{ route('admin.b2b.webhooks.retry', $outbox->id) }}" onsubmit="return confirm('Bạn có chắc muốn gửi lại webhook này ngay bây giờ?')">
                  @csrf
                  <button type="submit" class="account-btn account-btn--primary" style="min-height: 28px; padding: 4px 10px; font-size: 12px;">
                    Gửi lại
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="8">Chưa có bản ghi webhook outbox nào.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($webhooks->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $webhooks->firstItem() ?? 0 }} đến {{ $webhooks->lastItem() ?? 0 }} trong tổng số {{ $webhooks->total() }} webhook
        </span>
        <div>
          {{ $webhooks->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>
@endsection
