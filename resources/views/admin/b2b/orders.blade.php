@extends('admin.layout')

@section('title', 'tv9tech - Đơn hàng B2B')

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
        <strong>Đơn hàng B2B</strong>
      </nav>
      <h1>Quản lý đơn hàng B2B</h1>
      <p>Theo dõi các đơn hàng được đẩy từ API của đại lý, tiến trình gọi nhà cung cấp và trạng thái webhook.</p>
    </div>
  </header>

  {{-- Bộ lọc --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.orders.index') }}">
      <label style="flex: 2; min-width: 220px;">
        <span>Từ khóa tìm kiếm</span>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Mã đơn, mã đối tác, SĐT...">
      </label>

      <label style="width: 200px;">
        <span>Đại lý</span>
        <select name="dai_ly_api_id">
          <option value="">-- Tất cả đại lý --</option>
          @foreach($partners as $p)
            <option value="{{ $p->id }}" @selected(request('dai_ly_api_id') == $p->id)>{{ $p->ten_dai_ly_api }} ({{ $p->ma_dai_ly_api }})</option>
          @endforeach
        </select>
      </label>

      <label style="width: 170px;">
        <span>Trạng thái đơn</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="QUEUED" @selected(request('trang_thai') === 'QUEUED')>Chờ xử lý (QUEUED)</option>
          <option value="PROCESSING" @selected(request('trang_thai') === 'PROCESSING')>Đang xử lý (PROCESSING)</option>
          <option value="PROVIDER_PENDING" @selected(request('trang_thai') === 'PROVIDER_PENDING')>Chờ NCC (PENDING)</option>
          <option value="SUCCESS" @selected(request('trang_thai') === 'SUCCESS')>Thành công (SUCCESS)</option>
          <option value="FAILED" @selected(request('trang_thai') === 'FAILED')>Thất bại (FAILED)</option>
          <option value="MANUAL_REVIEW" @selected(request('trang_thai') === 'MANUAL_REVIEW')>Đối soát (MANUAL_REVIEW)</option>
        </select>
      </label>

      <label style="width: 140px;">
        <span>Từ ngày</span>
        <input type="date" name="tu_ngay" value="{{ request('tu_ngay') }}">
      </label>

      <label style="width: 140px;">
        <span>Đến ngày</span>
        <input type="date" name="den_ngay" value="{{ request('den_ngay') }}">
      </label>

      <button class="account-btn account-btn--primary" type="submit">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Lọc đơn</span>
      </button>

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.orders.index') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- Bảng đơn hàng --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th style="width: 70px;">ID</th>
            <th>Đại lý</th>
            <th>Mã đơn đối tác / Mã HT</th>
            <th>Tài khoản nhận</th>
            <th>Sản phẩm</th>
            <th style="text-align: right;">Mệnh giá</th>
            <th style="text-align: right;">Giá bán đại lý</th>
            <th style="text-align: center;">Trạng thái</th>
            <th>Thời gian tạo</th>
            <th style="text-align: center; width: 80px;">Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orders as $order)
            <tr>
              <td class="account-username">#{{ $order->id }}</td>
              <td>
                <strong style="color: var(--admin-slate-800);">{{ $order->daiLyApi?->ten_dai_ly_api }}</strong>
                <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $order->daiLyApi?->ma_dai_ly_api }}</div>
              </td>
              <td>
                <span style="font-family: monospace; font-weight: 700; color: #0284c7;">{{ $order->ma_don_doi_tac }}</span>
                <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $order->ma_don_hang }}</div>
              </td>
              <td style="font-weight: 600;">{{ $order->tai_khoan_nhan }}</td>
              <td>
                <div>{{ $order->ten_san_pham_snapshot ?: $order->sanPham?->ten_san_pham }}</div>
                <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $order->ma_san_pham_snapshot }}</div>
              </td>
              <td style="text-align: right;">{{ number_format($order->menh_gia) }} đ</td>
              <td style="text-align: right; font-weight: 700; color: var(--admin-brand-700);">
                {{ number_format($order->gia_ban) }} đ
              </td>
              <td style="text-align: center;">
                @php
                  $statusClass = match($order->trang_thai_don_hang) {
                    'SUCCESS' => 'is-yes',
                    'FAILED' => 'is-no',
                    default => 'is-pending',
                  };
                @endphp
                <span class="account-status-pill {{ $statusClass }}">
                  {{ $order->trang_thai_don_hang }}
                </span>
              </td>
              <td style="font-size: 12px; color: var(--admin-slate-500);">
                {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '-' }}
              </td>
              <td style="text-align: center;">
                <a href="{{ route('admin.b2b.orders.show', $order->id) }}" class="account-btn account-btn--muted" style="min-height: 28px; padding: 4px 10px; font-size: 12px;">
                  Xem
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="10">Không có đơn hàng B2B nào phù hợp tiêu chí lọc.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($orders->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $orders->firstItem() ?? 0 }} đến {{ $orders->lastItem() ?? 0 }} trong tổng số {{ $orders->total() }} đơn
        </span>
        <div>
          {{ $orders->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>
@endsection
