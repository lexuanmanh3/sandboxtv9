@extends('admin.layout')

@section('title', 'tv9tech - Quản lý khoản giữ hạn mức (Hold)')

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
        <strong>Khoản giữ hạn mức</strong>
      </nav>
      <h1>Quản lý khoản giữ hạn mức (Credit Hold)</h1>
      <p>Theo dõi các khoản hạn mức bị tạm giữ khi đơn hàng B2B đang trong trạng thái xử lý.</p>
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

  {{-- BỘ LỌC TÌM KIẾM --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.operations.credit-holds') }}">
      <label style="flex: 2; min-width: 200px;">
        <span>Mã hoặc ID Đại lý</span>
        <input type="text" name="dai_ly_api_id" value="{{ request('dai_ly_api_id') }}" placeholder="Nhập ID Đại lý...">
      </label>

      <label style="width: 220px;">
        <span>Trạng thái khoản giữ</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="HOLDING" @selected(request('trang_thai') === 'HOLDING' || request('trang_thai') === 'DANG_GIU')>Đang giữ (HOLDING)</option>
          <option value="COMMITTED" @selected(request('trang_thai') === 'COMMITTED' || request('trang_thai') === 'DA_CHOT')>Đã chốt (COMMITTED)</option>
          <option value="RELEASED" @selected(request('trang_thai') === 'RELEASED' || request('trang_thai') === 'DA_GIAI_PHONG')>Đã giải phóng (RELEASED)</option>
        </select>
      </label>

      <button class="account-btn account-btn--primary" type="submit">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Lọc</span>
      </button>

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.operations.credit-holds') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- BẢNG DANH SÁCH KHOẢN GIỮ --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th style="width: 110px; text-align: center;">HÀNH ĐỘNG</th>
            <th>ID</th>
            <th>ĐẠI LÝ</th>
            <th>ĐƠN HÀNG</th>
            <th style="text-align: right;">SỐ TIỀN GIỮ</th>
            <th>THỜI GIAN TẠO</th>
            <th style="text-align: center;">TRẠNG THÁI</th>
            <th>GHI CHÚ / LÝ DO</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($holds as $hold)
            <tr>
              <td style="text-align: center;">
                @if(in_array($hold->trang_thai, ['HOLDING', 'DANG_GIU'], true))
                  <form action="{{ route('admin.b2b.operations.credit-holds.release', $hold->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn GIẢI PHÓNG khoản tiền này cho đại lý? Chỉ thực hiện khi đơn hàng đã hủy hoặc thất bại ở NCC.');">
                    @csrf
                    <button type="submit" class="account-btn account-btn--muted account-btn--sm" style="min-height: 28px; padding: 4px 10px; font-size: 12px; color: #dc2626; border-color: #fecaca;" title="Nhả lại hạn mức">
                      Nhả giữ
                    </button>
                  </form>
                @else
                  <span style="color: var(--admin-slate-400); font-size: 12px;">Đã xử lý</span>
                @endif
              </td>
              <td>#{{ $hold->id }}</td>
              <td>
                <a href="{{ route('admin.b2b.partners.index', ['search' => $hold->daiLyApi?->ma_dai_ly_api]) }}" style="font-weight: 700; color: var(--admin-brand-700); text-decoration: none;">
                  {{ $hold->daiLyApi?->ten_dai_ly_api ?? 'Đại lý #' . $hold->dai_ly_api_id }}
                </a>
                <div style="font-size: 12px; color: var(--admin-slate-400);">Mã: {{ $hold->daiLyApi?->ma_dai_ly_api }}</div>
              </td>
              <td>
                @if($hold->donHang)
                  <a href="{{ route('admin.b2b.orders.show', $hold->donHang->id) }}" style="font-weight: 600; color: var(--admin-slate-800); text-decoration: none;">
                    {{ $hold->donHang->ma_don_hang }}
                  </a>
                @else
                  <span style="color: var(--admin-slate-400); font-style: italic;">Đơn #{{ $hold->don_hang_id ?? 'N/A' }}</span>
                @endif
              </td>
              <td style="text-align: right; font-weight: 700; color: #ea580c;">
                {{ number_format($hold->so_tien_giu) }} đ
              </td>
              <td>
                <div>{{ $hold->created_at->format('d/m/Y H:i') }}</div>
                @if(in_array($hold->trang_thai, ['HOLDING', 'DANG_GIU'], true))
                  <div style="font-size: 11px; color: #dc2626; font-weight: 600;">Treo {{ $hold->created_at->diffForHumans() }}</div>
                @endif
              </td>
              <td style="text-align: center;">
                @if(in_array($hold->trang_thai, ['HOLDING', 'DANG_GIU'], true))
                  <span class="account-status-pill" style="background: #fef3c7; color: #d97706;">Đang giữ</span>
                @elseif(in_array($hold->trang_thai, ['COMMITTED', 'DA_CHOT'], true))
                  <span class="account-status-pill is-yes">Đã chốt</span>
                @elseif(in_array($hold->trang_thai, ['RELEASED', 'DA_GIAI_PHONG'], true))
                  <span class="account-status-pill is-no">Đã giải phóng</span>
                @endif
              </td>
              <td style="font-size: 13px; color: var(--admin-slate-500); max-width: 200px; white-space: normal;">
                {{ $hold->ly_do_giai_phong ?: '-' }}
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="8">Không có khoản giữ hạn mức nào.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($holds->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $holds->firstItem() ?? 0 }} đến {{ $holds->lastItem() ?? 0 }} trong tổng số {{ $holds->total() }} bản ghi
        </span>
        <div>
          {{ $holds->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>
@endsection
