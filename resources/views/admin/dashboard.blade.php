@extends('admin.layout')

@section('title', 'tv9tech - Tổng quan hệ thống')

@push('styles')
<style>
  /* Cố định kích thước toàn bộ SVG icon trên dashboard, triệt tiêu lỗi tràn kích thước */
  .icon,
  svg.icon {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    display: inline-block !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
  }

  .dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }
  .dash-kpi-card {
    background: #ffffff;
    border: 1px solid var(--admin-slate-200, #e2e8f0);
    border-radius: 12px;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
  }
  .dash-kpi-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--admin-brand-600, #16a34a);
  }
  .dash-kpi-card--blue::before { background: #0284c7; }
  .dash-kpi-card--amber::before { background: #d97706; }
  .dash-kpi-card--purple::before { background: #7c3aed; }
  
  .dash-kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .dash-kpi-label {
    font-size: 13px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .dash-kpi-icon {
    width: 44px !important;
    height: 44px !important;
    min-width: 44px !important;
    min-height: 44px !important;
    max-width: 44px !important;
    max-height: 44px !important;
    border-radius: 10px;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    overflow: hidden;
  }
  .dash-kpi-icon svg,
  .dash-kpi-icon .icon {
    width: 22px !important;
    height: 22px !important;
    max-width: 22px !important;
    max-height: 22px !important;
    display: block !important;
  }

  .dash-kpi-value {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    line-height: 30px;
    margin: 4px 0 2px;
  }
  .dash-kpi-sub {
    font-size: 12.5px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .dash-kpi-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11.5px;
    font-weight: 700;
  }
  .dash-kpi-badge--green { background: #dcfce7; color: #15803d; }
  .dash-kpi-badge--red { background: #fee2e2; color: #b91c1c; }

  /* 2 Columns Layout */
  .dash-main-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
  }
  @media (max-width: 1024px) {
    .dash-main-grid {
      grid-template-columns: 1fr;
    }
  }

  .dash-panel {
    background: #ffffff;
    border: 1px solid var(--admin-slate-200, #e2e8f0);
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  }
  .dash-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
  }
  .dash-panel__title {
    font-size: 15.5px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
  }
  .dash-panel__title svg,
  .dash-panel__title .icon {
    width: 18px !important;
    height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
    display: inline-block !important;
    vertical-align: middle !important;
    flex-shrink: 0 !important;
  }
  .dash-panel__action {
    font-size: 12.5px;
    font-weight: 600;
    color: #0284c7;
    text-decoration: none;
  }
  .dash-panel__action:hover {
    text-decoration: underline;
  }

  /* 7 Days Bar Chart */
  .dash-chart-bars {
    display: flex;
    align-items: flex-end;
    gap: 14px;
    height: 160px;
    padding: 10px 0 0;
    margin-bottom: 12px;
  }
  .dash-chart-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
    gap: 6px;
  }
  .dash-bar-wrap {
    width: 100%;
    max-width: 38px;
    height: 120px;
    display: flex;
    align-items: flex-end;
    background: #f8fafc;
    border-radius: 6px;
    overflow: hidden;
  }
  .dash-bar-fill {
    width: 100%;
    background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
    border-radius: 6px 6px 0 0;
    transition: height 0.3s ease;
    min-height: 4px;
  }
  .dash-bar-fill--zero {
    background: #e2e8f0;
    height: 4px !important;
  }
  .dash-bar-date {
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
  }

  /* Status Badges */
  .badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
  }
  .badge-status--success { background: #dcfce7; color: #15803d; }
  .badge-status--failed { background: #fee2e2; color: #b91c1c; }
  .badge-status--pending { background: #fef3c7; color: #b45309; }
  .badge-status--processing { background: #e0f2fe; color: #0369a1; }

  /* Quick action buttons */
  .dash-quick-links {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  .dash-quick-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    padding: 14px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    color: #1e293b;
    transition: all 0.15s ease;
    gap: 4px;
  }
  .dash-quick-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
  }
  .dash-quick-btn svg,
  .dash-quick-btn .icon {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    display: block !important;
    margin-bottom: 4px;
    color: var(--admin-brand-600, #16a34a);
  }
  .dash-quick-btn strong {
    font-size: 13px;
    font-weight: 700;
  }
  .dash-quick-btn span {
    font-size: 11.5px;
    color: #64748b;
  }

  /* Providers Mini List */
  .dash-provider-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
  }
  .dash-provider-item:last-child {
    border-bottom: none;
  }
  .dash-provider-name {
    font-size: 13.5px;
    font-weight: 700;
    color: #0f172a;
  }
  .dash-provider-code {
    font-size: 11.5px;
    color: #64748b;
    display: block;
  }
</style>
@endpush

@section('content')
  <div class="admin-page-header">
    <div>
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Trang chủ</a>
        <span>/</span>
        <strong>Tổng quan hệ thống</strong>
      </nav>
      <h1>Dashboard Tổng quan</h1>
      <p style="margin: 4px 0 0; font-size: 13.5px; color: #64748b;">
        Hệ thống cổng thanh toán &amp; nạp thẻ viễn thông tv9tech.
      </p>
    </div>

    <div class="admin-actions">
      <a class="btn btn--muted" href="{{ route('admin.orders') }}">
        <x-icon name="payments" size="16" />
        <span>Danh sách đơn hàng</span>
      </a>
      <a class="btn btn--primary" href="{{ route('admin.providers') }}">
        <x-icon name="cell_wifi" size="16" />
        <span>Quản lý Nhà cung cấp</span>
      </a>
    </div>
  </div>

  {{-- CẢNH BÁO VẬN HÀNH (NẾU CÓ ĐƠN MANUAL REVIEW) --}}
  @if ($manualReviewCount > 0)
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 18px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <div style="display: flex; align-items: center; gap: 10px; color: #92400e; font-size: 13.5px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Có <strong>{{ $manualReviewCount }}</strong> đơn hàng đang cần đối soát thủ công hoặc đang xử lý.</span>
      </div>
      <a href="{{ route('admin.orders', ['trang_thai_don_hang' => 'Manual_Review']) }}" style="font-size: 13px; font-weight: 700; color: #b45309; text-decoration: underline;">
        Xem ngay &rarr;
      </a>
    </div>
  @endif

  {{-- 4 THẺ CHỈ SỐ KPI CHÍNH --}}
  <section class="dash-kpi-grid" aria-label="Chỉ số chính">
    {{-- 1. DOANH THU HÔM NAY --}}
    <article class="dash-kpi-card">
      <div class="dash-kpi-header">
        <span class="dash-kpi-label">Doanh thu hôm nay</span>
        <div class="dash-kpi-icon" style="background: #dcfce7; color: #15803d;">
          <x-icon name="payments" size="22" />
        </div>
      </div>
      <div class="dash-kpi-value">{{ number_format($revenueToday, 0, ',', '.') }} đ</div>
      <div class="dash-kpi-sub">
        <span>Tháng này: <strong>{{ number_format($revenueMonth, 0, ',', '.') }} đ</strong></span>
      </div>
    </article>

    {{-- 2. ĐƠN HÀNG HÔM NAY --}}
    <article class="dash-kpi-card dash-kpi-card--blue">
      <div class="dash-kpi-header">
        <span class="dash-kpi-label">Đơn hàng hôm nay</span>
        <div class="dash-kpi-icon" style="background: #e0f2fe; color: #0369a1;">
          <x-icon name="sync_alt" size="22" />
        </div>
      </div>
      <div class="dash-kpi-value">{{ number_format($ordersToday) }} đơn</div>
      <div class="dash-kpi-sub">
        <span class="dash-kpi-badge dash-kpi-badge--green">Thành công: {{ $successOrdersToday }}</span>
        @if ($failedOrdersToday > 0)
          <span class="dash-kpi-badge dash-kpi-badge--red">Lỗi: {{ $failedOrdersToday }}</span>
        @endif
        <span>(Tỷ lệ {{ $successRate }}%)</span>
      </div>
    </article>

    {{-- 3. ĐẠI LÝ B2B --}}
    <article class="dash-kpi-card dash-kpi-card--amber">
      <div class="dash-kpi-header">
        <span class="dash-kpi-label">Đại lý API (B2B)</span>
        <div class="dash-kpi-icon" style="background: #fef3c7; color: #b45309;">
          <x-icon name="badge" size="22" />
        </div>
      </div>
      <div class="dash-kpi-value">{{ $totalPartners }} đối tác</div>
      <div class="dash-kpi-sub">
        <span class="dash-kpi-badge dash-kpi-badge--green">Đang hoạt động: {{ $activePartners }}</span>
      </div>
    </article>

    {{-- 4. NHÀ CUNG CẤP --}}
    <article class="dash-kpi-card dash-kpi-card--purple">
      <div class="dash-kpi-header">
        <span class="dash-kpi-label">Cổng Nhà cung cấp</span>
        <div class="dash-kpi-icon" style="background: #f3e8ff; color: #7c3aed;">
          <x-icon name="cell_wifi" size="22" />
        </div>
      </div>
      <div class="dash-kpi-value">{{ $totalProviders }} cổng</div>
      <div class="dash-kpi-sub">
        <span class="dash-kpi-badge dash-kpi-badge--green">Sẵn sàng: {{ $activeProviders }}</span>
        <span>• {{ $totalProducts }} sản phẩm</span>
      </div>
    </article>
  </section>

  {{-- KHU VỰC CHÍNH: BIỂU ĐỒ & THỐNG KÊ --}}
  <div class="dash-main-grid">
    {{-- CỘT TRÁI: BIỂU ĐỒ 7 NGÀY & DANH SÁCH ĐƠN MỚI --}}
    <div style="display: flex; flex-direction: column; gap: 20px;">
      {{-- Biểu đồ 7 ngày --}}
      <section class="dash-panel">
        <div class="dash-panel__header">
          <h2 class="dash-panel__title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Biểu đồ số lượng đơn hàng 7 ngày gần nhất
          </h2>
          <span style="font-size: 12px; color: #64748b;">Thời gian thực</span>
        </div>

        @php
          $maxOrders = max(array_map(fn($d) => $d['orders'], $dailyTrends));
          if ($maxOrders <= 0) $maxOrders = 1;
        @endphp

        <div class="dash-chart-bars">
          @foreach ($dailyTrends as $trend)
            @php
              $heightPercent = $trend['orders'] > 0 ? max(16, round(($trend['orders'] / $maxOrders) * 100)) : 0;
            @endphp
            <div class="dash-chart-col">
              <span style="font-size: 11px; font-weight: 700; color: #0f172a;">{{ $trend['orders'] > 0 ? $trend['orders'] . ' đơn' : '' }}</span>
              <div class="dash-bar-wrap" title="{{ $trend['date'] }}: {{ $trend['orders'] }} đơn ({{ number_format($trend['revenue']) }} đ)">
                <div class="dash-bar-fill {{ $trend['orders'] > 0 ? '' : 'dash-bar-fill--zero' }}" style="height: {{ $heightPercent }}%;"></div>
              </div>
              <span class="dash-bar-date">{{ $trend['date'] }}</span>
            </div>
          @endforeach
        </div>
      </section>

      {{-- Bảng đơn hàng gần đây --}}
      <section class="dash-panel">
        <div class="dash-panel__header">
          <h2 class="dash-panel__title">
            <x-icon name="history" size="18" />
            Giao dịch đơn hàng mới nhất
          </h2>
          <a class="dash-panel__action" href="{{ route('admin.orders') }}">Xem tất cả &rarr;</a>
        </div>

        <div class="table-wrap" style="overflow-x: auto;">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Mã đơn</th>
                <th>Khách hàng / Đại lý</th>
                <th>Sản phẩm / Mệnh giá</th>
                <th>Nhà cung cấp</th>
                <th class="text-right">Số tiền</th>
                <th>Trạng thái</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($recentOrders as $order)
                <tr>
                  <td>
                    <a href="{{ route('admin.orders.show', $order->id) }}" style="font-weight: 700; color: #0284c7;">
                      #{{ $order->ma_don_hang }}
                    </a>
                  </td>
                  <td>
                    <span style="font-size: 13px; font-weight: 600;">
                      {{ $order->daiLyApi?->ten_dai_ly ?? $order->nguoiDung?->name ?? 'Khách lẻ' }}
                    </span>
                    @if ($order->tai_khoan_nhan)
                      <small style="display: block; color: #64748b; font-size: 11.5px;">{{ $order->tai_khoan_nhan }}</small>
                    @endif
                  </td>
                  <td>
                    <span>{{ $order->ten_san_pham_snapshot ?? 'Nạp điện thoại' }}</span>
                    @if ($order->menh_gia)
                      <small style="display: block; color: #64748b;">{{ number_format($order->menh_gia, 0, ',', '.') }} đ</small>
                    @endif
                  </td>
                  <td>
                    <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 11px;">
                      {{ $order->nhaCungCap?->ten_ncc ?? $order->nha_cung_cap_thanh_cong_id ?? '-' }}
                    </span>
                  </td>
                  <td class="text-right" style="font-weight: 700; color: #0f172a;">
                    {{ number_format($order->gia_ban ?? 0, 0, ',', '.') }} đ
                  </td>
                  <td>
                    @php
                      $status = $order->trang_thai_don_hang;
                      $cls = match($status) {
                        'Success', 'Completed' => 'badge-status--success',
                        'Failed', 'Cancelled' => 'badge-status--failed',
                        'Processing' => 'badge-status--processing',
                        default => 'badge-status--pending',
                      };
                      $label = match($status) {
                        'Success', 'Completed' => 'Thành công',
                        'Failed', 'Cancelled' => 'Thất bại',
                        'Processing' => 'Đang nạp',
                        'Manual_Review' => 'Chờ duyệt',
                        default => $status ?: 'Chờ nạp',
                      };
                    @endphp
                    <span class="badge-status {{ $cls }}">{{ $label }}</span>
                  </td>
                  <td style="font-size: 12px; color: #64748b;">
                    {{ $order->created_at ? $order->created_at->format('H:i d/m') : '-' }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" style="text-align: center; color: #94a3b8; padding: 24px;">
                    Chưa có giao dịch nào trong hệ thống.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>
    </div>

    {{-- CỘT PHẢI: TRẠNG THÁI CỔNG NCC & TÁC VỤ NHANH --}}
    <div style="display: flex; flex-direction: column; gap: 20px;">
      {{-- Trạng thái Nhà cung cấp --}}
      <section class="dash-panel">
        <div class="dash-panel__header">
          <h2 class="dash-panel__title">
            <x-icon name="cell_wifi" size="18" />
            Nhà cung cấp kết nối
          </h2>
          <a class="dash-panel__action" href="{{ route('admin.providers') }}">Cấu hình &rarr;</a>
        </div>

        <div>
          @forelse ($providers as $prov)
            <div class="dash-provider-item">
              <div>
                <span class="dash-provider-name">{{ $prov->ten_ncc }}</span>
                <span class="dash-provider-code">Mã: {{ $prov->ma_ncc }} • {{ $prov->ket_noi_count }} kết nối</span>
              </div>
              <div>
                @if ($prov->trang_thai === 'Active')
                  <span class="badge-status badge-status--success">Hoạt động</span>
                @else
                  <span class="badge-status badge-status--failed">{{ $prov->trang_thai }}</span>
                @endif
              </div>
            </div>
          @empty
            <p style="text-align: center; color: #94a3b8; padding: 16px 0;">Chưa có nhà cung cấp nào được cấu hình.</p>
          @endforelse
        </div>
      </section>

      {{-- Tác vụ nhanh --}}
      <section class="dash-panel">
        <div class="dash-panel__header">
          <h2 class="dash-panel__title">
            <x-icon name="sync_alt" size="18" />
            Truy cập nhanh
          </h2>
        </div>

        <div class="dash-quick-links">
          <a class="dash-quick-btn" href="{{ route('admin.orders') }}">
            <x-icon name="payments" size="20" />
            <strong>Đơn hàng</strong>
            <span>Xem và tra cứu</span>
          </a>
          <a class="dash-quick-btn" href="{{ route('admin.b2b.partners.index') }}">
            <x-icon name="badge" size="20" />
            <strong>Đại lý API</strong>
            <span>Hạn mức &amp; khóa API</span>
          </a>
          <a class="dash-quick-btn" href="{{ route('admin.products') }}">
            <x-icon name="sync_alt" size="20" />
            <strong>Sản phẩm</strong>
            <span>Bảng giá &amp; chiết khấu</span>
          </a>
          <a class="dash-quick-btn" href="{{ route('admin.telegram-settings') }}">
            <x-icon name="notifications" size="20" />
            <strong>Telegram</strong>
            <span>Cảnh báo &amp; bot</span>
          </a>
        </div>
      </section>
    </div>
  </div>
@endsection
