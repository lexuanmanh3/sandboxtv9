@extends('admin.layout')

@section('title', 'tv9tech - IP bị từ chối - ' . $partner->ten_dai_ly_api)

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .ip-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 16px;
    }
    .ip-stat-card {
      background: #fff;
      border: 1px solid var(--admin-slate-200, #e5e7eb);
      border-radius: 8px;
      padding: 14px 16px;
    }
    .ip-stat-card .label {
      font-size: 12px;
      color: var(--admin-slate-500, #6b7280);
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .ip-stat-card .value {
      font-size: 24px;
      font-weight: 700;
      color: var(--admin-slate-900, #111827);
      margin-top: 4px;
    }
    .ip-stat-card.is-warning .value { color: #dc2626; }
    .ip-stat-card.is-success .value { color: #16a34a; }
    .ip-address-cell {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-weight: 600;
      color: var(--admin-slate-900, #111827);
    }
    .ip-action-row {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      align-items: center;
    }
    .whitelist-preview {
      background: var(--admin-slate-50, #f9fafb);
      border: 1px dashed var(--admin-slate-300, #d1d5db);
      border-radius: 6px;
      padding: 10px 12px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 13px;
      color: var(--admin-slate-700, #374151);
      white-space: pre-wrap;
      word-break: break-all;
    }
    .account-status-pill.is-no.is-resolved { background: #dcfce7; color: #15803d; }
  </style>
@endpush

@section('content')
<section class="account-page">
  <header class="account-header">
    <div>
      <nav class="account-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Trang chủ</a>
        <span>/</span>
        <a href="{{ route('admin.b2b.partners.index') }}">Quản lý đại lý</a>
        <span>/</span>
        <a href="{{ route('admin.b2b.partners.index') }}">{{ $partner->ten_dai_ly_api }}</a>
        <span>/</span>
        <strong>IP bị từ chối</strong>
      </nav>
      <h1>IP bị từ chối — {{ $partner->ten_dai_ly_api }}</h1>
      <p>Mã đại lý: <code>{{ $partner->ma_dai_ly_api }}</code> &middot; Client ID: <code>{{ $partner->cauHinhApi?->client_id ?? '—' }}</code></p>
    </div>

    <div class="account-actions">
      <a href="{{ route('admin.b2b.partners.index') }}" class="account-btn account-btn--muted">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;">
          <path d="M19 12H5" />
          <polyline points="12 19 5 12 12 5" />
        </svg>
        <span>Quay lại danh sách</span>
      </a>
    </div>
  </header>

  @if (session('success') || session('error'))
    <section class="account-alerts" aria-live="polite">
      @if (session('success'))<div class="account-alert account-alert--success">{{ session('success') }}</div>@endif
      @if (session('error'))<div class="account-alert account-alert--danger">{{ session('error') }}</div>@endif
    </section>
  @endif

  {{-- Stats --}}
  <section class="ip-stats">
    <div class="ip-stat-card">
      <div class="label">Tổng bản ghi</div>
      <div class="value">{{ number_format($thongKe['tong_ban_ghi']) }}</div>
    </div>
    <div class="ip-stat-card {{ $thongKe['chua_xu_ly'] > 0 ? 'is-warning' : '' }}">
      <div class="label">Chưa xử lý</div>
      <div class="value">{{ number_format($thongKe['chua_xu_ly']) }}</div>
    </div>
    <div class="ip-stat-card is-success">
      <div class="label">Đã xử lý</div>
      <div class="value">{{ number_format($thongKe['da_xu_ly']) }}</div>
    </div>
    <div class="ip-stat-card">
      <div class="label">Số IP độc lập</div>
      <div class="value">{{ number_format($thongKe['so_ip_doc_lap']) }}</div>
    </div>
  </section>

  {{-- IP whitelist hiện tại --}}
  <section class="account-card" style="padding: 16px 20px; margin-bottom: 16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:8px;">
      <strong style="font-size:14px;color:var(--admin-slate-800);">📋 IP Whitelist hiện tại của đại lý</strong>
      <a href="{{ route('admin.b2b.partners.index') }}" class="account-btn account-btn--muted" style="font-size:12px;padding:6px 10px;">Sửa trong trang Đại lý</a>
    </div>
    @if (!empty($partner->cauHinhApi?->danh_sach_ip_ket_noi))
      <div class="whitelist-preview">{{ trim($partner->cauHinhApi->danh_sach_ip_ket_noi) }}</div>
    @else
      <div style="color:var(--admin-slate-500);font-size:13px;font-style:italic;">(Đang để trống — trong môi trường production, mọi IP sẽ bị chặn)</div>
    @endif
  </section>

  {{-- Bộ lọc --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.partners.ip-rejections', $partner->id) }}">
      <label style="width: 180px;">
        <span>Trạng thái</span>
        <select name="trang_thai">
          <option value="chua_xu_ly" @selected($filterStatus === 'chua_xu_ly')>Chưa xử lý</option>
          <option value="da_xu_ly" @selected($filterStatus === 'da_xu_ly')>Đã xử lý</option>
          <option value="tat_ca" @selected($filterStatus === 'tat_ca')>Tất cả</option>
        </select>
      </label>
      <label style="width: 160px;">
        <span>Từ ngày</span>
        <input type="date" name="tu_ngay" value="{{ $tuNgay }}">
      </label>
      <label style="width: 160px;">
        <span>Đến ngày</span>
        <input type="date" name="den_ngay" value="{{ $denNgay }}">
      </label>
      <button class="account-btn account-btn--primary" type="submit">Lọc</button>
      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.partners.ip-rejections', $partner->id) }}">Đặt lại</a>
    </form>
  </section>

  {{-- Bảng IP --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th>IP bị chặn</th>
            <th style="text-align:center;width:90px;">Số lần</th>
            <th>Lần cuối</th>
            <th>Endpoint cuối</th>
            <th style="text-align:center;width:120px;">Trạng thái</th>
            <th style="text-align:right;width:300px;">Hành động</th>
          </tr>
        </thead>
        <tbody>
          @forelse($grouped as $row)
            <tr>
              <td class="ip-address-cell">{{ $row['ip_address'] }}</td>
              <td style="text-align:center;font-weight:700;">{{ $row['so_lan'] }}</td>
              <td>{{ $row['lan_cuoi']?->format('d/m/Y H:i:s') ?? '—' }}</td>
              <td style="font-size:12px;color:var(--admin-slate-500);">{{ $row['endpoint_cuoi'] ?? '—' }}</td>
              <td style="text-align:center;">
                @if ($row['da_xu_ly'])
                  <span class="account-status-pill is-no is-resolved">Đã xử lý</span>
                @else
                  <span class="account-status-pill is-no" style="background:#fee2e2;color:#dc2626;">Chưa xử lý</span>
                @endif
              </td>
              <td>
                <div class="ip-action-row" style="justify-content:flex-end;">
                  @if ($row['co_ban_ghi_chua_xu_ly'])
                    <form method="POST" action="{{ route('admin.b2b.partners.ip-rejections.add-to-whitelist', [$partner->id, $row['latest_rejection_id']]) }}" onsubmit="return confirm('Thêm IP {{ $row['ip_address'] }} vào whitelist và đánh dấu đã xử lý?')">
                      @csrf
                      <button type="submit" class="account-btn account-btn--primary" style="background:#16a34a;font-size:12px;padding:6px 10px;">
                        ➕ Thêm vào whitelist
                      </button>
                    </form>
                    <form method="POST" action="{{ route('admin.b2b.partners.ip-rejections.mark-resolved', [$partner->id, $row['latest_rejection_id']]) }}" onsubmit="return confirm('Đánh dấu IP {{ $row['ip_address'] }} đã xử lý (giữ chặn)?')">
                      @csrf
                      <button type="submit" class="account-btn account-btn--muted" style="font-size:12px;padding:6px 10px;">
                        ✓ Đã xử lý (giữ chặn)
                      </button>
                    </form>
                  @else
                    <span style="color:var(--admin-slate-400);font-size:12px;font-style:italic;">Không có hành động</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="6" style="text-align:center;padding:40px 20px;">
                @if ($filterStatus === 'chua_xu_ly')
                  ✅ Không có IP nào đang bị chặn. Mọi request từ IP đã đăng ký đều đi qua được.
                @else
                  Không có bản ghi nào phù hợp với bộ lọc hiện tại.
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <div style="margin-top:12px;font-size:12px;color:var(--admin-slate-500);">
    💡 Mẹo: Khi reseller báo "mất kết nối", hãy vào đây xem IP nào vừa bị chặn → bấm "Thêm vào whitelist" → reseller kết nối lại được ngay.
  </div>
</section>
@endsection
