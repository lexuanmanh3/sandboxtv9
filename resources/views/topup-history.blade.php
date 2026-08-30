@extends('layout')

@section('title', 'Lịch Sử Giao Dịch Nạp Tiền')

@push('styles')
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="{{ asset('frontend/css/topup.css') }}?v={{ time() }}">
  <style>
    .history-page-wrap {
      background: var(--topup-bg);
      min-height: calc(100vh - 150px);
      padding-bottom: 60px;
    }
    .history-container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 20px;
    }
    .history-card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,77,32,.06);
      border: 1px solid #e3ede6;
      margin-top: 28px;
      overflow: hidden;
    }
    .history-card__header {
      background: linear-gradient(135deg, #f0fbf4 0%, #e8f7ef 100%);
      border-bottom: 1px solid #d0e8d8;
      padding: 18px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .history-card__title {
      font-size: 15px;
      font-weight: 700;
      color: var(--topup-text-main);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .history-card__title i { color: var(--topup-primary); }
    .history-stats {
      display: flex;
      gap: 24px;
    }
    .history-stat {
      text-align: right;
    }
    .history-stat__label { font-size: 11px; color: var(--topup-text-muted); }
    .history-stat__value { font-size: 15px; font-weight: 700; color: var(--topup-primary); }
    .history-card__body { padding: 0; }
    .history-table-wrap { overflow-x: auto; }
    .history-table {
      width: 100%;
      border-collapse: collapse;
    }
    .history-table thead { background: #f8fdf9; }
    .history-table th {
      font-size: 11.5px;
      color: var(--topup-text-muted);
      font-weight: 700;
      text-transform: uppercase;
      padding: 12px 16px;
      border-bottom: 1.5px solid #e2ede5;
      white-space: nowrap;
      text-align: left;
    }
    .history-table td {
      font-size: 13.5px;
      color: var(--topup-text-main);
      padding: 13px 16px;
      border-bottom: 1px solid #f0f5f1;
      vertical-align: middle;
    }
    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:hover td { background: #f8fdf9; }
    .order-code {
      font-family: monospace;
      font-size: 12px;
      color: #64748b;
    }
    .pagination-wrap {
      padding: 20px 24px;
      border-top: 1px solid #e2ede5;
      display: flex;
      justify-content: center;
    }
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--topup-text-muted);
    }
    .empty-state i { font-size: 48px; opacity: 0.3; margin-bottom: 16px; display: block; }
    .empty-state h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
    .empty-state p { font-size: 13px; margin-bottom: 20px; }
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--topup-primary);
      color: #fff;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      transition: all .2s;
    }
    .back-btn:hover { background: var(--topup-primary-light); transform: translateY(-1px); }
  </style>
@endpush

@section('content')
  {{-- TOP BANNER --}}
  <section class="top-header-banner">
    <a href="{{ route('frontend.topup') }}" class="top-header-banner__left">
      <i class="fa-solid fa-arrow-left"></i>
      <span>Nạp tiền điện thoại</span>
    </a>

    <div class="top-header-banner__center">
      <div class="top-header-banner__tab">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>LỊCH SỬ GIAO DỊCH</span>
      </div>
    </div>

    <div class="top-header-banner__right">
      <div class="topup-balance-chip">
        <i class="fa-solid fa-wallet"></i>
        <span>{{ number_format((float)$vi->so_du, 0, ',', '.') }}đ</span>
      </div>
    </div>
  </section>

  <div class="history-page-wrap">
    <div class="history-container">
      <div class="history-card">
        <div class="history-card__header">
          <div class="history-card__title">
            <i class="fa-solid fa-list-check"></i>
            Lịch sử nạp tiền điện thoại
          </div>
          <div class="history-stats">
            <div class="history-stat">
              <div class="history-stat__label">Tổng đơn</div>
              <div class="history-stat__value">{{ $orders->total() }}</div>
            </div>
            <div class="history-stat">
              <div class="history-stat__label">Thành công</div>
              <div class="history-stat__value" style="color:#059669;">
                {{ $orders->getCollection()->where('trang_thai_don_hang', 'SUCCESS')->count() }}
                /{{ $orders->perPage() }}
              </div>
            </div>
          </div>
        </div>

        <div class="history-card__body">
          @if ($orders->isEmpty())
            <div class="empty-state">
              <i class="fa-solid fa-inbox"></i>
              <h3>Chưa có giao dịch nào</h3>
              <p>Bạn chưa thực hiện giao dịch nạp tiền điện thoại nào.</p>
              <a href="{{ route('frontend.topup') }}" class="back-btn">
                <i class="fa-solid fa-bolt"></i> Nạp tiền ngay
              </a>
            </div>
          @else
            <div class="history-table-wrap">
              <table class="history-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Thời gian</th>
                    <th>Mã đơn</th>
                    <th>Số điện thoại</th>
                    <th>Nhà mạng</th>
                    <th>Mệnh giá</th>
                    <th>Giá bán</th>
                    <th>Trạng thái</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($orders as $index => $order)
                    @php
                      $status = strtoupper($order->trang_thai_don_hang ?? '');
                      $badgeMap = [
                        'SUCCESS'        => ['success',  'Thành công', 'fa-circle-check'],
                        'FAILED'         => ['failed',   'Thất bại',   'fa-circle-xmark'],
                        'REFUND_PENDING' => ['refunded', 'Đang hoàn',  'fa-rotate-left'],
                        'REFUNDED'       => ['refunded', 'Đã hoàn',    'fa-rotate-left'],
                        'MANUAL_REVIEW'  => ['pending',  'Đối soát',   'fa-magnifying-glass'],
                        'PROVIDER_PENDING' => ['pending', 'Chờ NCC',   'fa-clock'],
                      ];
                      $badge  = $badgeMap[$status] ?? ['pending', 'Đang xử lý', 'fa-spinner'];
                    @endphp
                    <tr>
                      <td style="color:#94a3b8;font-size:12px;">{{ ($orders->currentPage() - 1) * $orders->perPage() + $loop->iteration }}</td>
                      <td style="white-space:nowrap;font-size:12.5px;color:#64748b;">
                        {{ $order->created_at?->format('d/m/Y') }}<br>
                        <span style="color:#94a3b8;">{{ $order->created_at?->format('H:i:s') }}</span>
                      </td>
                      <td class="order-code">{{ $order->ma_don_hang }}</td>
                      <td>
                        @php
                          $phone = (string)$order->tai_khoan_nhan;
                          $masked = strlen($phone) >= 7 ? substr($phone,0,4).'***'.substr($phone,-3) : $phone;
                        @endphp
                        <strong>{{ $masked }}</strong>
                      </td>
                      <td>{{ $order->nha_mang_yeu_cau ?? '—' }}</td>
                      <td style="font-weight:700;">
                        {{ $order->menh_gia ? number_format((float)$order->menh_gia, 0, ',', '.') . 'đ' : '—' }}
                      </td>
                      <td style="font-weight:700; color:var(--topup-primary);">
                        {{ $order->gia_ban ? number_format((float)$order->gia_ban, 0, ',', '.') . 'đ' : '—' }}
                        @if ((float)$order->chiet_khau > 0)
                          <span style="font-size:10px; background:#dcfce7; color:#15803d; border-radius:3px; padding:1px 4px; font-weight:700;">
                            -{{ number_format((float)$order->chiet_khau, 0) }}%
                          </span>
                        @endif
                      </td>
                      <td>
                        <span class="topup-status-badge {{ $badge[0] }}">
                          <i class="fa-solid {{ $badge[2] }}"></i>
                          {{ $badge[1] }}
                        </span>
                        @if ($status === 'FAILED' || $status === 'REFUNDED')
                          <div style="font-size:10.5px;color:#94a3b8;margin-top:2px;">
                            {{ $status === 'REFUNDED' ? 'Tiền đã hoàn lại' : 'Đang xử lý hoàn tiền' }}
                          </div>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

            <div class="pagination-wrap">
              {{ $orders->links() }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
