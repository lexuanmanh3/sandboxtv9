@extends('admin.layout')

@section('title', 'tv9tech - Chi tiết kỳ đối soát ' . $period->ma_ky)

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .recon-kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    .recon-kpi-box {
      background: #fff;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      padding: 16px;
    }
    .recon-kpi-box .lbl {
      font-size: 12px;
      color: var(--admin-slate-500);
      margin-bottom: 6px;
      font-weight: 500;
    }
    .recon-kpi-box .val {
      font-size: 18px;
      font-weight: 700;
      color: var(--admin-slate-800);
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
        <a href="{{ route('admin.b2b.reconciliations.index') }}">Kỳ đối soát</a> / 
        <span>{{ $period->ma_ky }}</span>
      </nav>
      <h1>Kỳ đối soát: {{ $period->ma_ky }}</h1>
      <p>
        Đại lý: <strong>{{ $period->daiLyApi?->ten_dai_ly_api }}</strong> ({{ $period->daiLyApi?->ma_dai_ly_api }}) | 
        Thời gian: {{ $period->tu_ngay->format('d/m/Y') }} &rarr; {{ $period->den_ngay->format('d/m/Y') }}
      </p>
    </div>

    <div style="display: flex; gap: 8px; align-items: center;">
      <a href="{{ route('admin.b2b.reconciliations.export', $period->id) }}" class="account-btn" style="background: #0284c7; border-color: #0284c7; color: #fff;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
        <span>Xuất Excel</span>
      </a>

      @if($period->trang_thai === 'DANG_MO')
        <form method="POST" action="{{ route('admin.b2b.reconciliations.recalculate', $period->id) }}" style="display: inline;">
          @csrf
          <button type="submit" class="account-btn account-btn--muted">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
            <span>Tính lại</span>
          </button>
        </form>

        <form method="POST" action="{{ route('admin.b2b.reconciliations.lock', $period->id) }}" onsubmit="return confirm('CẢNH BÁO: Khi đã khóa sổ, kỳ đối soát này sẽ không thể tự động sửa số liệu. Bạn có chắc chắn?')" style="display: inline;">
          @csrf
          <button type="submit" class="account-btn" style="background: #dc2626; border-color: #dc2626; color: #fff;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span>Khóa & Chốt sổ kỳ</span>
          </button>
        </form>
      @else
        <span class="account-status-pill is-no" style="font-size: 13px; padding: 6px 12px;">
          Đã khóa bởi {{ $period->nguoiKhoa?->name ?: 'Admin' }} lúc {{ $period->ngay_khoa?->format('d/m/Y H:i') }}
        </span>
      @endif

      <a href="{{ route('admin.b2b.reconciliations.index') }}" class="account-btn account-btn--muted">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Quay lại
      </a>
    </div>
  </header>

  {{-- Alert thông báo thành công nếu có --}}
  @if (session('success'))
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 500;">
      {{ session('success') }}
    </div>
  @endif

  {{-- Thẻ số liệu tài chính của kỳ --}}
  <div class="recon-kpi-grid">
    <div class="recon-kpi-box">
      <div class="lbl">(1) Số dư đầu kỳ</div>
      <div class="val">{{ number_format($period->so_du_dau_ky) }} đ</div>
    </div>
    <div class="recon-kpi-box">
      <div class="lbl">(2) Phát sinh tăng (Đơn hàng)</div>
      <div class="val" style="color: #dc2626;">+{{ number_format($period->tong_phat_sinh_tang) }} đ</div>
    </div>
    <div class="recon-kpi-box">
      <div class="lbl">(3) Thanh toán thu nợ</div>
      <div class="val" style="color: var(--admin-brand-700);">-{{ number_format($period->tong_thanh_toan) }} đ</div>
    </div>
    <div class="recon-kpi-box">
      <div class="lbl">(4) Bút toán điều chỉnh</div>
      <div class="val" style="color: #0284c7;">{{ number_format($period->tong_dieu_chinh) }} đ</div>
    </div>
    <div class="recon-kpi-box" style="background: #f0fdf4; border-color: #bbf7d0;">
      <div class="lbl" style="color: #166534; font-weight: 600;">(5) Dư nợ cuối kỳ (1+2-3+4)</div>
      <div class="val" style="color: var(--admin-brand-700); font-weight: 800;">{{ number_format($period->so_du_cuoi_ky) }} đ</div>
    </div>
  </div>

  {{-- Danh sách đơn hàng đối soát trong kỳ --}}
  <section class="account-card account-table-card">
    <div style="padding: 16px 20px; border-bottom: 1px solid var(--admin-slate-200); display: flex; justify-content: space-between; align-items: center;">
      <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--admin-slate-800);">
        Danh sách đơn hàng thành công trong kỳ ({{ $period->chiTiet->count() }} đơn)
      </h3>
    </div>

    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th>Mã đơn HT</th>
            <th>Mã đơn đối tác</th>
            <th>Tài khoản nhận</th>
            <th style="text-align: right;">Mệnh giá</th>
            <th style="text-align: right;">Giá bán đại lý</th>
            <th style="text-align: center;">Trạng thái</th>
            <th>Thời gian tạo</th>
          </tr>
        </thead>
        <tbody>
          @forelse($period->chiTiet as $item)
            @php $don = $item->donHang; @endphp
            <tr>
              <td class="account-username">
                <a href="{{ $don ? route('admin.b2b.orders.show', $don->id) : '#' }}" style="color: inherit; text-decoration: none; font-weight: 700;">
                  {{ $don?->ma_don_hang ?: '-' }}
                </a>
              </td>
              <td style="font-family: monospace; font-size: 12px; color: #0284c7;">{{ $don?->ma_don_doi_tac ?: '-' }}</td>
              <td><strong style="color: var(--admin-slate-800);">{{ $don?->tai_khoan_nhan ?: '-' }}</strong></td>
              <td style="text-align: right;">{{ number_format((float) ($don?->menh_gia ?? 0)) }} đ</td>
              <td style="text-align: right; font-weight: 700; color: var(--admin-brand-700);">{{ number_format($item->so_tien) }} đ</td>
              <td style="text-align: center;">
                <span class="account-status-pill is-yes">
                  {{ $item->trang_thai_don_hang }}
                </span>
              </td>
              <td style="font-size: 12px; color: var(--admin-slate-500);">{{ $item->ngay_tao_don ? $item->ngay_tao_don->format('d/m/Y H:i') : '-' }}</td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="7">Không có đơn hàng phát sinh trong kỳ này.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</section>
@endsection
