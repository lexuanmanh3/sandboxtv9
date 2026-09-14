@extends('admin.layout')

@section('title', 'tv9tech - Chi tiết đơn hàng B2B #' . $order->id)

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .order-meta-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px 20px;
      font-size: 13px;
    }
    .order-meta-item {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }
    .order-meta-item .lbl {
      font-size: 12px;
      color: var(--admin-slate-500);
      font-weight: 500;
    }
    .order-meta-item .val {
      font-size: 13px;
      color: var(--admin-slate-800);
      font-weight: 600;
    }
    .order-layout-grid {
      display: grid;
      grid-template-columns: 1.8fr 1.2fr;
      gap: 20px;
    }
    @media (max-width: 900px) {
      .order-layout-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
@endpush

@section('content')
<section class="account-page">
  {{-- Header --}}
  <header class="account-header">
    <div>
      <nav class="account-breadcrumb">
        <a href="{{ route('admin.dashboard') }}">Trang chủ</a> / 
        <a href="{{ route('admin.b2b.orders.index') }}">Đơn hàng B2B</a> / 
        <span>Chi tiết #{{ $order->id }}</span>
      </nav>
      <h1>Đơn hàng B2B: {{ $order->ma_don_hang }}</h1>
      <p>Đối tác: <strong>{{ $order->daiLyApi?->ten_dai_ly_api }}</strong> ({{ $order->daiLyApi?->ma_dai_ly_api }})</p>
    </div>
    <div>
      <a href="{{ route('admin.b2b.orders.index') }}" class="account-btn account-btn--muted">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Quay lại danh sách
      </a>
    </div>
  </header>

  <div class="order-layout-grid">
    {{-- Cột trái: Thông tin đơn & Lịch sử NCC --}}
    <div style="display: flex; flex-direction: column; gap: 20px;">
      {{-- Card Thông tin giao dịch --}}
      <div class="account-card" style="padding: 20px;">
        <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 15px; font-weight: 700; color: var(--admin-slate-800); border-bottom: 1px solid var(--admin-slate-200); padding-bottom: 10px;">
          Thông tin giao dịch
        </h3>
        <div class="order-meta-grid">
          <div class="order-meta-item">
            <span class="lbl">Mã đơn hệ thống</span>
            <span class="val" style="font-family: monospace; color: var(--admin-brand-700);">{{ $order->ma_don_hang }}</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Mã đơn đối tác (Partner Ref)</span>
            <span class="val" style="font-family: monospace; color: #0284c7;">{{ $order->ma_don_doi_tac }}</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Tài khoản nhận</span>
            <span class="val" style="font-weight: 700;">{{ $order->tai_khoan_nhan }}</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Sản phẩm</span>
            <span class="val">{{ $order->ten_san_pham_snapshot ?: $order->sanPham?->ten_san_pham }}</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Mệnh giá</span>
            <span class="val">{{ number_format($order->menh_gia) }} đ</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Giá thanh toán đại lý</span>
            <span class="val" style="color: var(--admin-brand-700); font-weight: 700;">{{ number_format($order->gia_ban) }} đ</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Chiết khấu</span>
            <span class="val">{{ number_format($order->chiet_khau) }} đ</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Trạng thái đơn</span>
            <span class="val">
              @php
                $statusMap = [
                  'SUCCESS' => ['label' => 'Thành công', 'cls' => 'is-yes'],
                  'PROCESSING' => ['label' => 'Đang xử lý', 'cls' => ''],
                  'PENDING' => ['label' => 'Chờ xử lý', 'cls' => ''],
                  'FAILED' => ['label' => 'Thất bại', 'cls' => 'is-no'],
                  'CANCELLED' => ['label' => 'Đã hủy', 'cls' => 'is-no'],
                ];
                $st = $statusMap[$order->trang_thai_don_hang] ?? ['label' => $order->trang_thai_don_hang, 'cls' => ''];
              @endphp
              <span class="account-status-pill {{ $st['cls'] }}">{{ $st['label'] }}</span>
            </span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Thời gian tạo</span>
            <span class="val" style="font-weight: normal; color: var(--admin-slate-500);">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i:s') : '-' }}</span>
          </div>
          <div class="order-meta-item">
            <span class="lbl">Thời gian hoàn thành</span>
            <span class="val" style="font-weight: normal; color: var(--admin-slate-500);">{{ $order->hoan_thanh_luc ? $order->hoan_thanh_luc->format('d/m/Y H:i:s') : 'Chưa hoàn thành' }}</span>
          </div>
          <div class="order-meta-item" style="grid-column: span 2;">
            <span class="lbl">Idempotency-Key</span>
            <span class="val" style="font-family: monospace; font-size: 11px; color: var(--admin-slate-500); word-break: break-all;">{{ $order->idempotency_key ?: '-' }}</span>
          </div>
        </div>
      </div>

      {{-- Lịch sử kết nối NCC --}}
      <div class="account-card account-table-card">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--admin-slate-200);">
          <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--admin-slate-800);">Lịch sử kết nối Nhà cung cấp</h3>
        </div>
        <div class="account-table-wrap">
          <table class="account-table">
            <thead>
              <tr>
                <th style="width: 50px;">Lần</th>
                <th>NCC</th>
                <th>Partner Ref</th>
                <th>Mã GD NCC</th>
                <th>Mã lỗi</th>
                <th>Kết quả</th>
                <th>Thời gian</th>
              </tr>
            </thead>
            <tbody>
              @forelse($order->lanGoiNhaCungCap as $call)
                <tr>
                  <td class="account-username">#{{ $call->lan_thu }}</td>
                  <td><strong style="color: var(--admin-slate-800);">{{ $call->nhaCungCap?->ten_ncc }}</strong></td>
                  <td style="font-family: monospace; font-size: 12px; color: #0284c7;">{{ $call->partner_ref_id }}</td>
                  <td style="font-family: monospace; font-size: 12px;">{{ $call->ma_giao_dich_ncc ?: '-' }}</td>
                  <td><span style="font-family: monospace; font-size: 11px; color: var(--admin-slate-500);">{{ $call->ma_loi_ncc ?: '-' }}</span></td>
                  <td>
                    <span class="account-status-pill {{ $call->ket_qua_xac_dinh === 'SUCCESS' ? 'is-yes' : ($call->ket_qua_xac_dinh === 'FAILED' ? 'is-no' : '') }}">
                      {{ $call->ket_qua_xac_dinh }}
                    </span>
                  </td>
                  <td style="font-size: 12px; color: var(--admin-slate-500);">{{ $call->created_at ? $call->created_at->format('H:i:s d/m') : '-' }}</td>
                </tr>
              @empty
                <tr>
                  <td class="account-empty" colspan="7">Chưa có lần gọi NCC nào.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Cột phải: Hạn mức & Webhook outbox --}}
    <div style="display: flex; flex-direction: column; gap: 20px;">
      {{-- Card Khoản giữ hạn mức --}}
      <div class="account-card" style="padding: 20px;">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 15px; font-weight: 700; color: var(--admin-slate-800); border-bottom: 1px solid var(--admin-slate-200); padding-bottom: 10px;">
          Khoản giữ hạn mức
        </h3>
        @if($order->khoanGiuHanMuc)
          <div class="order-meta-grid" style="grid-template-columns: 1fr;">
            <div class="order-meta-item">
              <span class="lbl">Số tiền tạm giữ</span>
              <span class="val" style="color: #d97706; font-size: 16px;">{{ number_format($order->khoanGiuHanMuc->so_tien_giu) }} đ</span>
            </div>
            <div class="order-meta-item">
              <span class="lbl">Trạng thái giữ</span>
              <span class="val">
                @php
                  $holdMap = [
                    'COMMITTED' => ['label' => 'Đã chốt nợ (COMMITTED)', 'cls' => 'is-yes'],
                    'HOLDING' => ['label' => 'Đang tạm giữ (HOLDING)', 'cls' => ''],
                    'RELEASED' => ['label' => 'Đã giải phóng (RELEASED)', 'cls' => 'is-no'],
                  ];
                  $hm = $holdMap[$order->khoanGiuHanMuc->trang_thai] ?? ['label' => $order->khoanGiuHanMuc->trang_thai, 'cls' => ''];
                @endphp
                <span class="account-status-pill {{ $hm['cls'] }}">{{ $hm['label'] }}</span>
              </span>
            </div>
            @if($order->khoanGiuHanMuc->chot_cong_no_luc)
              <div class="order-meta-item">
                <span class="lbl">Thời gian chốt nợ</span>
                <span class="val" style="font-weight: normal; color: var(--admin-slate-500);">{{ $order->khoanGiuHanMuc->chot_cong_no_luc->format('d/m/Y H:i:s') }}</span>
              </div>
            @endif
            @if($order->khoanGiuHanMuc->giai_phong_luc)
              <div class="order-meta-item">
                <span class="lbl">Thời gian giải phóng</span>
                <span class="val" style="font-weight: normal; color: var(--admin-slate-500);">{{ $order->khoanGiuHanMuc->giai_phong_luc->format('d/m/Y H:i:s') }}</span>
              </div>
              <div class="order-meta-item">
                <span class="lbl">Lý do giải phóng</span>
                <span class="val" style="font-weight: normal; color: var(--admin-slate-600);">{{ $order->khoanGiuHanMuc->ly_do_giai_phong }}</span>
              </div>
            @endif
          </div>
        @else
          <p style="font-size: 13px; color: var(--admin-slate-400); margin: 0;">Không có bản ghi giữ hạn mức.</p>
        @endif
      </div>

      {{-- Card Webhook Outbox --}}
      <div class="account-card" style="padding: 20px;">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 15px; font-weight: 700; color: var(--admin-slate-800); border-bottom: 1px solid var(--admin-slate-200); padding-bottom: 10px;">
          Webhook Outbox
        </h3>
        @forelse($order->webhookOutbox as $wb)
          <div style="border: 1px solid var(--admin-slate-200); border-radius: 6px; padding: 12px; margin-bottom: 10px; font-size: 12px; background: var(--admin-slate-50);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <strong style="color: var(--admin-slate-800); font-size: 13px;">{{ $wb->event_type }}</strong>
              <span class="account-status-pill {{ $wb->trang_thai === 'SUCCESS' ? 'is-yes' : ($wb->trang_thai === 'FAILED' ? 'is-no' : '') }}" style="font-size: 11px;">
                {{ $wb->trang_thai }}
              </span>
            </div>
            <div style="font-family: monospace; color: var(--admin-slate-500); margin-bottom: 4px; word-break: break-all;">
              {{ $wb->event_id }}
            </div>
            <div style="color: var(--admin-slate-500); font-size: 11px;">
              Đã thử {{ $wb->so_lan_thu }} lần @if($wb->gui_thanh_cong_luc) • Gửi lúc {{ $wb->gui_thanh_cong_luc->format('H:i d/m') }} @endif
            </div>
            @if($wb->trang_thai !== 'SUCCESS')
              <form method="POST" action="{{ route('admin.b2b.webhooks.retry', $wb->id) }}" style="margin-top: 8px;">
                @csrf
                <button type="submit" class="account-btn account-btn--primary" style="padding: 4px 10px; font-size: 11px;">
                  Gửi lại webhook ngay
                </button>
              </form>
            @endif
          </div>
        @empty
          <p style="font-size: 13px; color: var(--admin-slate-400); margin: 0;">Chưa có webhook nào được tạo cho đơn này.</p>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection
