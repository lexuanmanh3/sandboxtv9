@extends('admin.layout')

@section('title', 'tv9tech - Quản lý Kỳ Đối Soát B2B')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .account-modal__dialog {
      display: flex !important;
      flex-direction: column !important;
      max-height: min(90vh, 850px) !important;
      overflow: hidden !important;
    }
    .account-modal-form {
      display: flex;
      flex-direction: column;
      flex: 1;
      min-height: 0;
      overflow: hidden;
      margin: 0;
    }
    .account-modal__body {
      flex: 1 1 auto !important;
      min-height: 0 !important;
      overflow-y: auto !important;
      -webkit-overflow-scrolling: touch;
      padding: 20px;
    }
    .account-modal__footer {
      flex-shrink: 0 !important;
      border-top: 1px solid var(--admin-slate-200);
      background: #fff;
      padding: 14px 20px;
    }
  </style>
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
        <strong>Kỳ đối soát</strong>
      </nav>
      <h1>Quản lý kỳ đối soát công nợ</h1>
      <p>Tạo kỳ đối soát định kỳ, khóa sổ kế toán và xuất biên bản bảng kê đối soát Excel cho đối tác.</p>
    </div>

    <div class="account-actions">
      <button type="button" class="account-btn account-btn--primary" onclick="openCreateReconModal()">
        <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>
        <span>Tạo kỳ đối soát mới</span>
      </button>
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
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.reconciliations.index') }}">
      <label style="flex: 2; min-width: 250px;">
        <span>Đại lý đối soát</span>
        <select name="dai_ly_api_id">
          <option value="">-- Tất cả đại lý --</option>
          @foreach($partners as $p)
            <option value="{{ $p->id }}" @selected(request('dai_ly_api_id') == $p->id)>{{ $p->ten_dai_ly_api }} ({{ $p->ma_dai_ly_api }})</option>
          @endforeach
        </select>
      </label>

      <label style="width: 200px;">
        <span>Trạng thái khóa sổ</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="DANG_MO" @selected(request('trang_thai') === 'DANG_MO')>Đang mở (DANG_MO)</option>
          <option value="DA_KHOA" @selected(request('trang_thai') === 'DA_KHOA')>Đã khóa sổ (DA_KHOA)</option>
        </select>
      </label>

      <button class="account-btn account-btn--primary" type="submit">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Lọc</span>
      </button>

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.reconciliations.index') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- Bảng kỳ đối soát --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th>Mã kỳ</th>
            <th>Đại lý</th>
            <th>Khoảng thời gian</th>
            <th style="text-align: right;">Dư đầu kỳ</th>
            <th style="text-align: right;">Phát sinh tăng</th>
            <th style="text-align: right;">Đã thanh toán</th>
            <th style="text-align: right;">Dư cuối kỳ</th>
            <th style="text-align: center;">Trạng thái</th>
            <th style="text-align: center; width: 150px;">Hành động</th>
          </tr>
        </thead>
        <tbody>
          @forelse($periods as $recon)
            <tr>
              <td class="account-username">{{ $recon->ma_ky }}</td>
              <td>
                <strong style="color: var(--admin-slate-800);">{{ $recon->daiLyApi?->ten_dai_ly_api }}</strong>
                <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $recon->daiLyApi?->ma_dai_ly_api }}</div>
              </td>
              <td style="font-size: 12px; color: var(--admin-slate-600);">
                {{ \Carbon\Carbon::parse($recon->tu_ngay)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($recon->den_ngay)->format('d/m/Y') }}
              </td>
              <td style="text-align: right; color: var(--admin-slate-600);">{{ number_format($recon->so_du_dau_ky) }} đ</td>
              <td style="text-align: right; font-weight: 700; color: #dc2626;">+{{ number_format($recon->tong_phat_sinh_tang) }} đ</td>
              <td style="text-align: right; font-weight: 700; color: var(--admin-brand-700);">-{{ number_format($recon->tong_thanh_toan) }} đ</td>
              <td style="text-align: right; font-weight: 800; color: var(--admin-slate-900);">{{ number_format($recon->so_du_cuoi_ky) }} đ</td>
              <td style="text-align: center;">
                <span class="account-status-pill {{ $recon->trang_thai === 'DA_KHOA' ? 'is-yes' : 'is-no' }}">
                  {{ $recon->trang_thai === 'DA_KHOA' ? 'Đã khóa sổ' : 'Đang mở' }}
                </span>
              </td>
              <td style="text-align: center;">
                <div style="display: inline-flex; gap: 6px; align-items: center;">
                  <a href="{{ route('admin.b2b.reconciliations.show', $recon->id) }}" class="account-btn account-btn--muted" style="min-height: 28px; padding: 4px 8px; font-size: 12px;">
                    Chi tiết
                  </a>
                  <a href="{{ route('admin.b2b.reconciliations.export', $recon->id) }}" class="account-btn account-btn--primary" style="min-height: 28px; padding: 4px 8px; font-size: 12px;">
                    Excel
                  </a>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="9">Chưa có kỳ đối soát nào được tạo.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($periods->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $periods->firstItem() ?? 0 }} đến {{ $periods->lastItem() ?? 0 }} trong tổng số {{ $periods->total() }} kỳ
        </span>
        <div>
          {{ $periods->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>

{{-- MODAL TẠO KỲ ĐỐI SOÁT --}}
<div class="account-modal" id="createReconModal" hidden>
  <div class="account-modal__backdrop" onclick="closeCreateReconModal()"></div>
  <section class="account-modal__dialog" style="max-width: 550px; width: 100%;">
    <header class="account-modal__header">
      <h2>Tạo kỳ đối soát công nợ</h2>
      <button type="button" class="account-modal__close" onclick="closeCreateReconModal()" aria-label="Đóng">×</button>
    </header>

    <form class="account-modal-form" method="POST" action="{{ route('admin.b2b.reconciliations.store') }}">
      @csrf
      <div class="account-modal__body">
        <div class="account-form-grid account-form-grid--single" style="padding: 0;">
          <label>
            <span>Đại lý đối soát (*)</span>
            <select name="dai_ly_api_id" required>
              @foreach($partners as $p)
                <option value="{{ $p->id }}">{{ $p->ten_dai_ly_api }} ({{ $p->ma_dai_ly_api }})</option>
              @endforeach
            </select>
          </label>

          <label>
            <span>Từ ngày (*)</span>
            <input type="date" name="tu_ngay" required value="{{ date('Y-m-01') }}">
          </label>

          <label>
            <span>Đến ngày (*)</span>
            <input type="date" name="den_ngay" required value="{{ date('Y-m-t') }}">
          </label>
        </div>
      </div>

      <footer class="account-modal__footer">
        <button type="button" class="account-btn account-btn--muted" onclick="closeCreateReconModal()">Hủy</button>
        <button type="submit" class="account-btn account-btn--primary">Tính toán &amp; Mở kỳ</button>
      </footer>
    </form>
  </section>
</div>

@push('scripts')
<script>
function openCreateReconModal() { document.getElementById('createReconModal').hidden = false; }
function closeCreateReconModal() { document.getElementById('createReconModal').hidden = true; }
</script>
@endpush
@endsection
