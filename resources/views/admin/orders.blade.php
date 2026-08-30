@extends('admin.layout')

@section('title', 'VietFin - Quản lý Hóa đơn & Giao dịch')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    /* Stats Grid */
    .orders-stat-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 24px;
    }
    @media (max-width: 1024px) {
      .orders-stat-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
      .orders-stat-grid { grid-template-columns: 1fr; }
    }
    .orders-stat-card {
      background: #ffffff;
      border: 1px solid var(--admin-slate-200, #e2e8f0);
      border-radius: 12px;
      padding: 16px 20px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .orders-stat-card__head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }
    .orders-stat-card__label {
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
    }
    .orders-stat-card__icon {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }
    .orders-stat-card__icon.is-blue { background: #eff6ff; color: #2563eb; }
    .orders-stat-card__icon.is-green { background: #f0fdf4; color: #16a34a; }
    .orders-stat-card__icon.is-amber { background: #fffbeb; color: #d97706; }
    .orders-stat-card__icon.is-purple { background: #faf5ff; color: #9333ea; }
    .orders-stat-card__value {
      font-size: 24px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.2;
      margin-bottom: 6px;
    }
    .orders-stat-card__footer {
      font-size: 12px;
      color: #64748b;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Badges */
    .badge-service-topup { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .badge-service-data { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .badge-service-card { background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .badge-service-bill { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }

    .account-status-pill.is-purple {
      background: #f3e8ff;
      color: #7e22ce;
      border: 1px solid #d8b4fe;
    }

    /* Modal Details Grid */
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      margin-bottom: 16px;
    }
    @media (max-width: 640px) {
      .detail-grid { grid-template-columns: 1fr; }
    }
    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: 10px 14px;
      background: #f8fafc;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }
    .detail-item__label {
      font-size: 12px;
      color: #64748b;
      font-weight: 500;
    }
    .detail-item__value {
      font-size: 13px;
      color: #0f172a;
      font-weight: 600;
    }

    /* Timeline */
    .timeline-list {
      list-style: none;
      padding: 0;
      margin: 0;
      position: relative;
    }
    .timeline-item {
      position: relative;
      padding-left: 24px;
      margin-bottom: 14px;
    }
    .timeline-item::before {
      content: "";
      position: absolute;
      left: 4px;
      top: 5px;
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--admin-brand-500, #00875a);
    }
    .timeline-item::after {
      content: "";
      position: absolute;
      left: 8px;
      top: 16px;
      bottom: -10px;
      width: 1px;
      background: #cbd5e1;
    }
    .timeline-item:last-child::after { display: none; }
  </style>
@endpush

@section('content')
  <section class="account-page">
    {{-- HEADER & BREADCRUMB --}}
    <header class="account-header">
      <div>
        <nav class="account-breadcrumb" aria-label="Breadcrumb">
          <a href="{{ route('admin.dashboard') }}">Trang chủ</a>
          <span>/</span>
          <strong>Hóa đơn &amp; Giao dịch</strong>
        </nav>
        <h1>Quản lý Hóa đơn &amp; Giao dịch</h1>
        <p>Theo dõi, tra cứu và đối soát toàn bộ hóa đơn đơn hàng của đại lý và người dùng trong hệ thống.</p>
      </div>

      <div class="account-actions">
        <a class="account-btn account-btn--muted" href="{{ route('admin.orders.export', request()->all()) }}">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M7 3h7l5 5v13H7z" />
            <path d="M14 3v5h5" />
            <path d="m10 11 4 6" />
            <path d="m14 11-4 6" />
          </svg>
          <span>Xuất file Excel/CSV</span>
        </a>
      </div>
    </header>

    {{-- THÔNG BÁO FLASH --}}
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

    {{-- STATS SUMMARY CARDS --}}
    <div class="orders-stat-grid">
      <div class="orders-stat-card">
        <div class="orders-stat-card__head">
          <span class="orders-stat-card__label">Tổng số Hóa đơn</span>
          <div class="orders-stat-card__icon is-blue">
            <x-icon name="description" />
          </div>
        </div>
        <div class="orders-stat-card__value">{{ number_format($stats['total_orders']) }}</div>
        <div class="orders-stat-card__footer">
          <span>Toàn bộ giao dịch</span>
          <span style="font-weight: 600; color: #2563eb;">100% data</span>
        </div>
      </div>

      <div class="orders-stat-card">
        <div class="orders-stat-card__head">
          <span class="orders-stat-card__label">Tổng Doanh thu</span>
          <div class="orders-stat-card__icon is-green">
            <x-icon name="payments" />
          </div>
        </div>
        <div class="orders-stat-card__value" style="color: #16a34a;">
          {{ number_format($stats['total_revenue'], 0, ',', '.') }}đ
        </div>
        <div class="orders-stat-card__footer">
          <span>Lợi nhuận:</span>
          <strong style="color: #059669;">+{{ number_format($stats['total_profit'], 0, ',', '.') }}đ</strong>
        </div>
      </div>

      <div class="orders-stat-card">
        <div class="orders-stat-card__head">
          <span class="orders-stat-card__label">Thành công</span>
          <div class="orders-stat-card__icon is-green">
            <x-icon name="check_circle" />
          </div>
        </div>
        <div class="orders-stat-card__value" style="color: #15803d;">
          {{ number_format($stats['success_count']) }}
        </div>
        <div class="orders-stat-card__footer">
          <span>Đang xử lý:</span>
          <strong style="color: #d97706;">{{ number_format($stats['pending_count']) }} đơn</strong>
        </div>
      </div>

      <div class="orders-stat-card">
        <div class="orders-stat-card__head">
          <span class="orders-stat-card__label">Thất bại / Hoàn tiền</span>
          <div class="orders-stat-card__icon is-purple">
            <x-icon name="history" />
          </div>
        </div>
        <div class="orders-stat-card__value" style="color: #b91c1c;">
          {{ number_format($stats['failed_count']) }}
        </div>
        <div class="orders-stat-card__footer">
          <span>Đã hoàn tiền:</span>
          <strong style="color: #9333ea;">{{ number_format($stats['refunded_count']) }} đơn</strong>
        </div>
      </div>
    </div>

    {{-- BỘ LỌC TÌM KIẾM NÂNG CAO --}}
    <section class="account-card account-filter-section {{ request()->hasAny(['q', 'dich_vu_id', 'loai_san_pham_id', 'nha_cung_cap_id', 'trang_thai_don_hang', 'from_date', 'to_date']) ? 'is-open' : '' }}">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="{{ request()->hasAny(['q', 'dich_vu_id', 'loai_san_pham_id', 'nha_cung_cap_id', 'trang_thai_don_hang', 'from_date', 'to_date']) ? 'true' : 'false' }}">
        <span><x-icon name="menu" /> Hiển thị bộ lọc nâng cao</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      <form class="account-filter" method="GET" action="{{ route('admin.orders') }}" data-account-filter {{ request()->hasAny(['q', 'dich_vu_id', 'loai_san_pham_id', 'nha_cung_cap_id', 'trang_thai_don_hang', 'from_date', 'to_date']) ? '' : 'hidden' }}>
        <label>
          <span>Từ khóa tìm kiếm</span>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã HĐ, SĐT nhận, User...">
        </label>
        <label>
          <span>Dịch vụ</span>
          <select name="dich_vu_id">
            <option value="">Tất cả dịch vụ</option>
            @foreach ($services as $srv)
              <option value="{{ $srv->id }}" @selected(($filters['dich_vu_id'] ?? '') == $srv->id)>{{ $srv->ten_dich_vu }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Nhà mạng / Loại SP</span>
          <select name="loai_san_pham_id">
            <option value="">Tất cả nhà mạng</option>
            @foreach ($categories as $cat)
              <option value="{{ $cat->id }}" @selected(($filters['loai_san_pham_id'] ?? '') == $cat->id)>{{ $cat->ten_loai_san_pham }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Nhà cung cấp</span>
          <select name="nha_cung_cap_id">
            <option value="">Tất cả NCC</option>
            @foreach ($providers as $ncc)
              <option value="{{ $ncc->id }}" @selected(($filters['nha_cung_cap_id'] ?? '') == $ncc->id)>{{ $ncc->ten_ncc }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Trạng thái đơn</span>
          <select name="trang_thai_don_hang">
            <option value="">Tất cả trạng thái</option>
            <option value="SUCCESS" @selected(($filters['trang_thai_don_hang'] ?? '') === 'SUCCESS')>Thành công (SUCCESS)</option>
            <option value="PENDING" @selected(($filters['trang_thai_don_hang'] ?? '') === 'PENDING')>Đang chờ (PENDING)</option>
            <option value="PROCESSING" @selected(($filters['trang_thai_don_hang'] ?? '') === 'PROCESSING')>Đang xử lý (PROCESSING)</option>
            <option value="FAILED" @selected(($filters['trang_thai_don_hang'] ?? '') === 'FAILED')>Thất bại (FAILED)</option>
            <option value="REFUNDED" @selected(($filters['trang_thai_don_hang'] ?? '') === 'REFUNDED')>Đã hoàn tiền (REFUNDED)</option>
          </select>
        </label>
        <label>
          <span>Từ ngày</span>
          <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}">
        </label>
        <label>
          <span>Đến ngày</span>
          <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}">
        </label>
        <button class="account-btn account-btn--primary" type="submit">
          <x-icon name="visibility" />
          Tìm kiếm
        </button>
        <a class="account-btn account-btn--muted" href="{{ route('admin.orders') }}">
          Đặt lại
        </a>
      </form>
    </section>

    {{-- BẢNG DANH SÁCH HÓA ĐƠN --}}
    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table">
          <thead>
            <tr>
              <th>HÀNH ĐỘNG</th>
              <th>MÃ HÓA ĐƠN</th>
              <th>THỜI GIAN TẠO</th>
              <th>KHÁCH HÀNG</th>
              <th>SĐT / TK NHẬN</th>
              <th>DỊCH VỤ &amp; LOẠI SP</th>
              <th>MỆNH GIÁ / GIÁ BÁN</th>
              <th>LỢI NHUẬN</th>
              <th>NHÀ CUNG CẤP</th>
              <th>TRẠNG THÁI</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($orders as $order)
              @php
                $status = strtoupper($order->trang_thai_don_hang ?? 'PENDING');
                $statusClass = match($status) {
                  'SUCCESS', 'THANH_CONG' => 'is-yes',
                  'FAILED', 'THAT_BAI' => 'is-no',
                  'REFUNDED', 'DA_HOAN_TIEN' => 'is-purple',
                  'MANUAL_REVIEW' => 'is-no',
                  'PROVIDER_PENDING' => 'is-warn',
                  'PROCESSING', 'DANG_XU_LY' => 'is-warn',
                  default => 'is-warn',
                };
                $statusLabel = match($status) {
                  'SUCCESS', 'THANH_CONG' => 'Thành công',
                  'FAILED', 'THAT_BAI' => 'Thất bại',
                  'REFUNDED', 'DA_HOAN_TIEN' => 'Đã hoàn tiền',
                  'PROCESSING', 'DANG_XU_LY' => 'Đang nạp tiền',
                  'PROVIDER_PENDING' => 'Chờ NCC xử lý',
                  'MANUAL_REVIEW' => 'Chờ đối soát / duyệt',
                  default => 'Đang chờ',
                };

                $serviceCode = $order->dichVu?->ma_dich_vu ?? '';
                $srvClass = match($serviceCode) {
                  'MOBILE_TOPUP' => 'badge-service-topup',
                  'TOPUP_DATA', 'PIN_DATA' => 'badge-service-data',
                  'PIN_CODE', 'PIN_GAME' => 'badge-service-card',
                  default => 'badge-service-bill',
                };
              @endphp
              <tr>
                <td>
                  <div class="account-row-actions">
                    <button class="account-row-action" type="button" data-row-action>
                      Hành động
                      <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                        <path d="M5 7.5 10 12.5 15 7.5" />
                      </svg>
                    </button>
                    <div class="account-row-menu" hidden>
                      <button type="button" data-order-view="{{ $order->id }}">
                        <x-icon name="visibility" /> Xem chi tiết
                      </button>
                      @if (in_array($status, ['FAILED', 'THAT_BAI', 'PENDING', 'PROCESSING', 'PROVIDER_PENDING', 'MANUAL_REVIEW']) && (float) $order->gia_ban > 0 && $order->trang_thai_thanh_toan !== 'REFUNDED')
                        <form method="POST" action="{{ route('admin.orders.refund', $order->id) }}" onsubmit="return confirm('Bạn có chắc chắn muốn hoàn tiền {{ number_format($order->gia_ban, 0, ',', '.') }}đ cho đơn hàng #{{ $order->ma_don_hang }} vào ví khách hàng?');">
                          @csrf
                          <button type="submit" style="color: #b91c1c;">
                            <x-icon name="history" /> Hoàn tiền ví
                          </button>
                        </form>
                      @endif
                    </div>
                  </div>
                </td>
                <td class="account-username">
                  <code>{{ $order->ma_don_hang }}</code>
                  @if ($order->ma_don_doi_tac)
                    <div style="font-size: 11px; color: #64748b;">Ref: {{ $order->ma_don_doi_tac }}</div>
                  @endif
                </td>
                <td style="font-size: 12px; color: #475569; white-space: nowrap;">
                  {{ optional($order->created_at)->format('d/m/Y H:i:s') }}
                </td>
                <td>
                  <strong>{{ $order->nguoiDung?->ten_dang_nhap ?: ($order->nguoiDung?->name ?: 'Khách vãng lai') }}</strong>
                  @if ($order->nguoiDung && $order->nguoiDung->name && $order->nguoiDung->name !== $order->nguoiDung->ten_dang_nhap)
                    <div style="font-size: 11px; color: #64748b;">{{ $order->nguoiDung->name }}</div>
                  @endif
                  @if ($order->nguon_don)
                    <div style="font-size: 11px; color: #0284c7; font-weight: 600;">Nguồn: {{ strtoupper($order->nguon_don) }}</div>
                  @endif
                </td>
                <td>
                  <span style="font-weight: 700; font-family: monospace; font-size: 13px; color: var(--admin-brand-800);">
                    {{ $order->tai_khoan_nhan ?: '-' }}
                  </span>
                  @if ($order->nha_mang_thuc_te || $order->nha_mang_yeu_cau)
                    <div style="font-size: 11px; color: #64748b;">{{ $order->nha_mang_thuc_te ?: $order->nha_mang_yeu_cau }}</div>
                  @endif
                </td>
                <td>
                  <div style="display: flex; flex-direction: column; gap: 4px;">
                    <span class="{{ $srvClass }}">
                      {{ $order->dichVu?->ten_dich_vu ?: 'Nạp tiền' }}
                    </span>
                    <span style="font-size: 12px; font-weight: 500; color: #334155;">
                      {{ $order->loaiSanPham?->ten_loai_san_pham ?: '-' }}
                    </span>
                  </div>
                </td>
                <td>
                  <div style="font-weight: 700; color: #1e293b;">
                    {{ number_format($order->gia_ban ?: $order->menh_gia, 0, ',', '.') }}đ
                  </div>
                  @if ($order->chiet_khau > 0)
                    <div style="font-size: 11px; color: #059669; font-weight: 600;">CK: -{{ $order->chiet_khau }}%</div>
                  @endif
                </td>
                <td>
                  @if ($order->loi_nhuan > 0)
                    <span style="font-weight: 700; color: #059669;">+{{ number_format($order->loi_nhuan, 0, ',', '.') }}đ</span>
                  @else
                    <span style="color: #94a3b8; font-size: 12px;">0đ</span>
                  @endif
                </td>
                <td>
                  @if ($order->nhaCungCapThanhCong || $order->nhaCungCap)
                    <span class="account-status-pill is-yes" style="font-size: 11px;">
                      {{ ($order->nhaCungCapThanhCong ?? $order->nhaCungCap)->ten_ncc }}
                    </span>
                  @else
                    <span style="color: #94a3b8; font-size: 12px;">-</span>
                  @endif
                </td>
                <td>
                  <span class="account-status-pill {{ $statusClass }}">
                    {{ $statusLabel }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" style="text-align: center; padding: 40px; color: #64748b;">
                  <x-icon name="description" style="font-size: 32px; display: block; margin: 0 auto 8px; opacity: 0.4;" />
                  Chưa có hóa đơn hoặc giao dịch nào phù hợp với bộ lọc tìm kiếm.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- PHÂN TRANG CHUẨN --}}
      <footer class="account-pagination">
        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
          <span>
            Đang xem {{ $orders->firstItem() ?? 0 }} đến {{ $orders->lastItem() ?? 0 }} trong tổng số {{ $orders->total() }} mục
          </span>
          <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--admin-slate-600);">
            <span>Hiển thị:</span>
            <select onchange="location.href = this.value;" style="border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 4px 8px; font-size: 12px; background: #fff; cursor: pointer;">
              @foreach ([10, 20, 50, 100] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" @selected(request('per_page', 20) == $size)>
                  {{ $size }} / trang
                </option>
              @endforeach
            </select>
          </label>
        </div>

        <nav aria-label="Phân trang">
          <a class="{{ $orders->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $orders->url(1) }}">«</a>
          <a class="{{ $orders->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $orders->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($orders->lastPage(), 5); $page++)
            <a class="{{ $orders->currentPage() === $page ? 'is-active' : '' }}" href="{{ $orders->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $orders->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $orders->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $orders->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $orders->url($orders->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- MODAL XEM CHI TIẾT HÓA ĐƠN --}}
  <div class="account-modal" id="orderDetailModal" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--role" role="dialog" aria-modal="true" style="max-width: 750px;">
      <header class="account-modal__header">
        <h2 id="modalOrderCodeTitle">Chi tiết Hóa đơn #<span id="detailOrderCode" style="color: var(--admin-brand-700);"></span></h2>
        <button type="button" class="account-modal__close" data-modal-close aria-label="Đóng">×</button>
      </header>

      <div class="account-modal__body" style="padding: 20px; max-height: 75vh; overflow-y: auto;">
        <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-top: 0; margin-bottom: 10px;">
          1. Thông tin giao dịch
        </h3>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-item__label">Mã hóa đơn / Đơn hàng</span>
            <span class="detail-item__value" id="dMaDon">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Thời gian tạo</span>
            <span class="detail-item__value" id="dCreatedAt">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Khách hàng / Đại lý</span>
            <span class="detail-item__value" id="dNguoiDung">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Số ĐT / Tài khoản nhận</span>
            <span class="detail-item__value" id="dTaiKhoanNhan" style="color: var(--admin-brand-700);">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Dịch vụ &amp; Nhà mạng</span>
            <span class="detail-item__value" id="dDichVu">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Tên sản phẩm</span>
            <span class="detail-item__value" id="dTenSanPham">-</span>
          </div>
        </div>

        <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
          2. Chi tiết tài chính &amp; Chiết khấu
        </h3>
        <div class="detail-grid">
          <div class="detail-item">
            <span class="detail-item__label">Mệnh giá gốc</span>
            <span class="detail-item__value" id="dMenhGia">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Giá bán thanh toán</span>
            <span class="detail-item__value" id="dGiaBan" style="color: #059669;">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Tỷ lệ chiết khấu</span>
            <span class="detail-item__value" id="dChietKhau">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Giá vốn NCC &amp; Lợi nhuận</span>
            <span class="detail-item__value" id="dLoiNhuan">-</span>
          </div>
        </div>

        <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
          3. Trạng thái &amp; Nhà cung cấp định tuyến
        </h3>
        <div class="detail-grid">
          <div class="detail-item" style="grid-column: span 2;">
            <span class="detail-item__label">Trạng thái đơn hàng</span>
            <span class="detail-item__value" id="dTrangThaiDon">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Nhà cung cấp thực hiện</span>
            <span class="detail-item__value" id="dNCC">-</span>
          </div>
          <div class="detail-item">
            <span class="detail-item__label">Mã tham chiếu đối tác</span>
            <span class="detail-item__value" id="dMaDoiTac">-</span>
          </div>
        </div>

        <div id="dErrorBlock" style="display: none; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 700; color: #b91c1c; margin-bottom: 4px;">Thông báo lỗi hệ thống / NCC:</div>
          <div id="dErrorMsg" style="font-size: 13px; color: #991b1b; font-family: monospace;"></div>
        </div>

        <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px; display: flex; justify-content: space-between; align-items: center;">
          <span>4. Lịch sử kết nối &amp; Gọi API Nhà cung cấp</span>
          <span id="dLanGoiBadge" style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #e0e7ff; color: #3730a3; font-weight: 600;">0 lần gọi</span>
        </h3>
        <div id="dLanGoiContainer" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
          <!-- Rendered dynamically -->
        </div>

        <h3 style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px; border-top: 1px solid #e2e8f0; padding-top: 16px;">
          5. Lịch sử thay đổi trạng thái
        </h3>
        <ul class="timeline-list" id="dTimeline">
          <!-- Rendered dynamically -->
        </ul>
      </div>

      <footer class="account-modal__footer">
        <button class="account-btn account-btn--muted" type="button" data-modal-close>Đóng</button>
      </footer>
    </section>
  </div>
@endsection

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Toggle dropdown row action menus
      function closeAllOrderMenus() {
        document.querySelectorAll('.account-row-actions.is-open').forEach(w => {
          w.classList.remove('is-open');
          w.querySelector('.account-row-menu')?.setAttribute('hidden', '');
        });
      }

      document.querySelectorAll('[data-row-action]').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const wrapper = btn.closest('.account-row-actions');
          const menu = wrapper?.querySelector('.account-row-menu');
          if (!wrapper || !menu) return;

          const isOpen = wrapper.classList.contains('is-open');
          closeAllOrderMenus();

          if (!isOpen) {
            wrapper.classList.add('is-open');
            menu.removeAttribute('hidden');

            const rect = btn.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.zIndex = '9999';
            menu.style.left = `${rect.left}px`;

            const menuHeight = menu.offsetHeight || 120;
            const spaceBelow = window.innerHeight - rect.bottom;
            if (spaceBelow < menuHeight + 20) {
              menu.style.top = `${rect.top - menuHeight - 4}px`;
            } else {
              menu.style.top = `${rect.bottom + 4}px`;
            }
          }
        });
      });

      document.addEventListener('click', closeAllOrderMenus);
      window.addEventListener('scroll', closeAllOrderMenus, { passive: true, capture: true });
      document.addEventListener('scroll', closeAllOrderMenus, { passive: true, capture: true });
      window.addEventListener('resize', closeAllOrderMenus, { passive: true });

      // Filter toggle
      const filterToggle = document.querySelector('[data-account-filter-toggle]');
      const filterForm = document.querySelector('[data-account-filter]');
      filterToggle?.addEventListener('click', () => {
        const isHidden = filterForm.hidden;
        filterForm.hidden = !isHidden;
        filterToggle.setAttribute('aria-expanded', String(isHidden));
      });

      // Modal detail handling
      const modal = document.getElementById('orderDetailModal');
      const closeModal = () => { modal.hidden = true; document.body.style.overflow = ''; };
      modal?.querySelectorAll('[data-modal-close]').forEach(b => b.addEventListener('click', closeModal));

      document.querySelectorAll('[data-order-view]').forEach(btn => {
        btn.addEventListener('click', async () => {
          const orderId = btn.dataset.orderView;
          try {
            const res = await fetch(`{{ url('/admin/orders') }}/${orderId}`, {
              headers: { 'Accept': 'application/json' }
            });
            const json = await res.json();
            if (!json.success || !json.data) return;

            const o = json.data;
            document.getElementById('detailOrderCode').textContent = o.ma_don_hang;
            document.getElementById('dMaDon').textContent = o.ma_don_hang;
            document.getElementById('dCreatedAt').textContent = o.created_at ? new Date(o.created_at).toLocaleString('vi-VN') : '-';
            document.getElementById('dNguoiDung').textContent = (o.nguoi_dung ? (o.nguoi_dung.ten_dang_nhap || o.nguoi_dung.name || o.nguoi_dung.username) : 'Khách vãng lai');
            document.getElementById('dTaiKhoanNhan').textContent = o.tai_khoan_nhan || '-';
            document.getElementById('dDichVu').textContent = `${o.dich_vu?.ten_dich_vu || '-'} / ${o.loai_san_pham?.ten_loai_san_pham || '-'}`;
            document.getElementById('dTenSanPham').textContent = o.ten_san_pham_snapshot || o.san_pham?.ten_san_pham || '-';

            document.getElementById('dMenhGia').textContent = Number(o.menh_gia).toLocaleString('vi-VN') + 'đ';
            document.getElementById('dGiaBan').textContent = Number(o.gia_ban).toLocaleString('vi-VN') + 'đ';
            document.getElementById('dChietKhau').textContent = (o.chiet_khau > 0 ? `-${o.chiet_khau}%` : '0%');
            document.getElementById('dLoiNhuan').textContent = `Vốn: ${Number(o.gia_von_thuc_te || o.gia_von || 0).toLocaleString('vi-VN')}đ | Lãi: +${Number(o.loi_nhuan || 0).toLocaleString('vi-VN')}đ`;

            const statusMap = {
              'SUCCESS': { label: 'Thành công', bg: '#dcfce7', color: '#15803d', border: '#bbf7d0' },
              'FAILED': { label: 'Thất bại', bg: '#fee2e2', color: '#b91c1c', border: '#fecaca' },
              'REFUNDED': { label: 'Đã hoàn tiền', bg: '#f3e8ff', color: '#7e22ce', border: '#e9d5ff' },
              'PROCESSING': { label: 'Đang nạp tiền', bg: '#fef3c7', color: '#b45309', border: '#fde68a' },
              'PROVIDER_PENDING': { label: 'Chờ NCC xử lý (PENDING)', bg: '#fef3c7', color: '#b45309', border: '#fde68a' },
              'MANUAL_REVIEW': { label: 'Chờ đối soát thủ công', bg: '#fee2e2', color: '#b91c1c', border: '#fecaca' },
              'PAID': { label: 'Đã thanh toán', bg: '#e0f2fe', color: '#0369a1', border: '#bae6fd' },
              'WAITING_PAYMENT': { label: 'Chờ thanh toán', bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' },
            };
            const st = statusMap[o.trang_thai_don_hang] || { label: o.trang_thai_don_hang, bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' };
            document.getElementById('dTrangThaiDon').innerHTML = `<span style="background:${st.bg}; color:${st.color}; border:1px solid ${st.border}; padding:4px 12px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block;">${st.label}</span>`;
            document.getElementById('dNCC').textContent = o.nha_cung_cap_thanh_cong?.ten_ncc || o.nha_cung_cap?.ten_ncc || '-';
            document.getElementById('dMaDoiTac').textContent = o.ma_don_doi_tac || '-';

            if (o.thong_bao_loi_he_thong || o.ma_loi_he_thong) {
              document.getElementById('dErrorBlock').style.display = 'block';
              document.getElementById('dErrorMsg').textContent = `[${o.ma_loi_he_thong || 'ERROR'}]: ${o.thong_bao_loi_he_thong || ''}`;
            } else {
              document.getElementById('dErrorBlock').style.display = 'none';
            }

            // Render Danh sách lần gọi API NCC
            const lanGoiContainer = document.getElementById('dLanGoiContainer');
            const lanGoiBadge = document.getElementById('dLanGoiBadge');
            const calls = o.lan_goi_nha_cung_cap || [];
            lanGoiBadge.textContent = `${calls.length} lần gọi`;

            if (calls.length > 0) {
              lanGoiContainer.innerHTML = calls.map((c, idx) => {
                const resData = c.response_json || {};
                const reqData = c.request_json || {};
                const resJsonStr = JSON.stringify(resData, null, 2);
                const reqJsonStr = JSON.stringify(reqData, null, 2);
                const resCode = c.ma_loi_ncc !== null && c.ma_loi_ncc !== undefined ? c.ma_loi_ncc : (resData.errorCode !== undefined ? resData.errorCode : '-');
                const resMsg = resData.message || (c.ket_qua_xac_dinh === 'SUCCESS' ? 'Giao dịch thành công' : (c.ket_qua_xac_dinh || 'Đang xử lý'));
                
                const statusBadgeClass = (c.ket_qua_xac_dinh === 'SUCCESS' || c.trang_thai === 'SUCCESS')
                  ? 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;'
                  : ((c.ket_qua_xac_dinh === 'DEFINITIVE_FAILURE' || c.trang_thai === 'FAILED' || c.trang_thai === 'DEFINITIVE_FAILURE')
                    ? 'background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;'
                    : 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;');

                return `
                  <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #f8fafc;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                      <div>
                        <strong style="font-size: 13px; color: #1e293b;">
                          #${c.lan_thu || (idx + 1)}: ${c.nha_cung_cap?.ten_ncc || 'Nhà cung cấp'}
                        </strong>
                        <span style="font-size: 11px; color: #64748b; margin-left: 6px;">(${c.loai_yeu_cau || 'CHARGING'})</span>
                      </div>
                      <span style="font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; ${statusBadgeClass}">
                        ${c.ket_qua_xac_dinh || c.trang_thai || 'PROCESSING'}
                      </span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 8px; font-size: 12px; margin-bottom: 8px;">
                      <div><span style="color: #64748b;">Mã lỗi/phản hồi NCC:</span> <strong style="color: #0f172a; font-family: monospace;">${resCode}</strong></div>
                      <div><span style="color: #64748b;">HTTP Status:</span> <strong style="color: #0f172a;">${c.http_status || '200 OK'}</strong></div>
                      <div><span style="color: #64748b;">Mã GD NCC:</span> <span style="font-family: monospace; color: #475569;">${c.ma_giao_dich_ncc || '-'}</span></div>
                      <div><span style="color: #64748b;">Bắt đầu:</span> <span style="color: #475569;">${c.bat_dau_luc ? new Date(c.bat_dau_luc).toLocaleTimeString('vi-VN') : (c.created_at ? new Date(c.created_at).toLocaleTimeString('vi-VN') : '-')}</span></div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; font-size: 12px; margin-bottom: 8px;">
                      <div style="font-weight: 600; color: #334155; margin-bottom: 2px;">Thông điệp từ NCC:</div>
                      <div style="color: #475569; font-family: monospace;">${resMsg}</div>
                    </div>

                    <details style="font-size: 11px; color: #475569; margin-top: 6px;">
                      <summary style="cursor: pointer; font-weight: 600; color: var(--admin-brand-700); user-select: none;">
                        ▶ Xem chi tiết Request / Response JSON
                      </summary>
                      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
                        <div>
                          <div style="font-weight: 600; margin-bottom: 4px; color: #0284c7;">Request Payload:</div>
                          <pre style="background: #0f172a; color: #38bdf8; padding: 8px; border-radius: 4px; max-height: 150px; overflow: auto; margin: 0; font-size: 10px;">${reqJsonStr}</pre>
                        </div>
                        <div>
                          <div style="font-weight: 600; margin-bottom: 4px; color: #16a34a;">Response Payload:</div>
                          <pre style="background: #0f172a; color: #4ade80; padding: 8px; border-radius: 4px; max-height: 150px; overflow: auto; margin: 0; font-size: 10px;">${resJsonStr}</pre>
                        </div>
                      </div>
                    </details>
                  </div>
                `;
              }).join('');
            } else {
              lanGoiContainer.innerHTML = `
                <div style="padding: 12px; text-align: center; color: #94a3b8; font-size: 12px; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
                  Chưa có lượt gọi API nhà cung cấp nào cho đơn hàng này.
                </div>
              `;
            }

            const timelineUl = document.getElementById('dTimeline');
            if (o.lich_su_trang_thai && o.lich_su_trang_thai.length > 0) {
              timelineUl.innerHTML = o.lich_su_trang_thai.map(h => `
                <li class="timeline-item">
                  <div style="font-size: 13px; font-weight: 700; color: #1e293b;">${h.trang_thai_moi}</div>
                  <div style="font-size: 12px; color: #64748b;">${new Date(h.created_at).toLocaleString('vi-VN')} - ${h.ghi_chu || 'Cập nhật hệ thống'}</div>
                </li>
              `).join('');
            } else {
              timelineUl.innerHTML = `
                <li class="timeline-item">
                  <div style="font-size: 13px; font-weight: 700; color: #1e293b;">${o.trang_thai_don_hang}</div>
                  <div style="font-size: 12px; color: #64748b;">${o.created_at ? new Date(o.created_at).toLocaleString('vi-VN') : ''} - Đơn hàng khởi tạo</div>
                </li>`;
            }

            modal.hidden = false;
            document.body.style.overflow = 'hidden';
          } catch (e) {
            alert('Không thể tải thông tin chi tiết đơn hàng.');
          }
        });
      });
    });
  </script>
@endpush
