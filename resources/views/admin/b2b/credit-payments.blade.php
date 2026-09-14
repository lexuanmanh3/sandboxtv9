@extends('admin.layout')

@section('title', 'tv9tech - Sổ Công Nợ & Thanh Toán B2B')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .credit-summary-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
      margin-top: 16px;
      padding-top: 16px;
      border-top: 1px solid var(--admin-slate-200);
    }
    @media (max-width: 768px) {
      .credit-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    .credit-summary-box {
      background: #fff;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      padding: 12px 16px;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .credit-summary-box .lbl {
      font-size: 12px;
      color: var(--admin-slate-500);
      font-weight: 500;
    }
    .credit-summary-box .val {
      font-size: 18px;
      font-weight: 700;
      color: var(--admin-slate-900);
    }
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
        <strong>Công nợ &amp; Thanh toán</strong>
      </nav>
      <h1>Sổ công nợ &amp; Thanh toán đại lý</h1>
      <p>Theo dõi phát sinh công nợ, quản lý các khoản thanh toán chuyển khoản và lập bút toán điều chỉnh.</p>
    </div>

    <div class="account-actions">
      <button type="button" class="account-btn account-btn--primary" onclick="openPaymentModal()">
        <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>
        <span>Ghi nhận thanh toán</span>
      </button>

      <button type="button" class="account-btn account-btn--muted" onclick="openAdjustmentModal()">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
        </svg>
        <span>Bút toán điều chỉnh</span>
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

  {{-- Chọn đại lý để xem tóm tắt hạn mức --}}
  <section class="account-card" style="padding: 20px;">
    <form method="GET" action="{{ route('admin.b2b.credit-payments.index') }}" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
      <label style="font-weight: 700; font-size: 14px; color: var(--admin-slate-800);">Đại lý API:</label>
      <select name="dai_ly_api_id" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid var(--admin-slate-300); border-radius: 6px; min-width: 280px; font-size: 13px; font-weight: 500;">
        <option value="">-- Tất cả đại lý --</option>
        @foreach($partners as $p)
          <option value="{{ $p->id }}" @selected($selectedPartnerId == $p->id)>
            {{ $p->ten_dai_ly_api }} ({{ $p->ma_dai_ly_api }})
          </option>
        @endforeach
      </select>
      <input type="hidden" name="tab" value="{{ $tab }}">
    </form>

    @if($creditInfo)
      <div class="credit-summary-grid">
        <div class="credit-summary-box">
          <div class="lbl">Hạn mức được cấp</div>
          <div class="val" style="color: var(--admin-slate-800);">{{ number_format($creditInfo['han_muc_duoc_cap']) }} đ</div>
        </div>
        <div class="credit-summary-box">
          <div class="lbl">Công nợ hiện tại</div>
          <div class="val" style="color: {{ $creditInfo['cong_no_hien_tai'] > 0 ? '#dc2626' : 'var(--admin-slate-700)' }};">
            {{ number_format($creditInfo['cong_no_hien_tai']) }} đ
          </div>
        </div>
        <div class="credit-summary-box">
          <div class="lbl">Khoản đang giữ (Hold)</div>
          <div class="val" style="color: #d97706;">{{ number_format($creditInfo['khoan_dang_giu']) }} đ</div>
        </div>
        <div class="credit-summary-box">
          <div class="lbl">Hạn mức khả dụng</div>
          <div class="val" style="color: var(--admin-brand-700);">{{ number_format($creditInfo['han_muc_kha_dung']) }} đ</div>
        </div>
      </div>
    @endif
  </section>

  {{-- Tabs nghiệp vụ --}}
  <div class="account-tabs" style="border-radius: 8px 8px 0 0; border: 1px solid var(--admin-slate-200); border-bottom: none; margin-bottom: -1px;">
    <button type="button" class="{{ $tab === 'ledger' ? 'is-active' : '' }}" onclick="location.href='{{ route('admin.b2b.credit-payments.index', ['dai_ly_api_id' => $selectedPartnerId, 'tab' => 'ledger']) }}'">
      Sổ phát sinh công nợ
    </button>
    <button type="button" class="{{ $tab === 'payments' ? 'is-active' : '' }}" onclick="location.href='{{ route('admin.b2b.credit-payments.index', ['dai_ly_api_id' => $selectedPartnerId, 'tab' => 'payments']) }}'">
      Lịch sử thanh toán thu tiền
    </button>
    <button type="button" class="{{ $tab === 'adjustments' ? 'is-active' : '' }}" onclick="location.href='{{ route('admin.b2b.credit-payments.index', ['dai_ly_api_id' => $selectedPartnerId, 'tab' => 'adjustments']) }}'">
      Bút toán điều chỉnh
    </button>
  </div>

  {{-- Nội dung Tab --}}
  <section class="account-card account-table-card" style="border-top-left-radius: 0;">
    <div class="account-table-wrap">
      @if($tab === 'ledger')
        {{-- ==================== TAB SỔ CÁI PHÁT SINH ==================== --}}
        <table class="account-table">
          <thead>
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Thời gian</th>
              <th>Đại lý</th>
              <th>Loại phát sinh</th>
              <th>Mã tham chiếu</th>
              <th style="text-align: right;">Số tiền</th>
              <th style="text-align: right;">Dư nợ trước</th>
              <th style="text-align: right;">Dư nợ sau</th>
              <th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            @forelse($ledgers as $rec)
              <tr>
                <td class="account-username">#{{ $rec->id }}</td>
                <td style="font-size: 12px; color: var(--admin-slate-500);">{{ $rec->created_at->format('d/m/Y H:i:s') }}</td>
                <td>
                  <strong style="color: var(--admin-slate-800);">{{ $rec->daiLyApi?->ten_dai_ly_api }}</strong>
                  <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $rec->daiLyApi?->ma_dai_ly_api }}</div>
                </td>
                <td>
                  @php
                    $isTang = in_array($rec->loai_phat_sinh, ['TANG_CONG_NO_DON_HANG', 'DIEU_CHINH_TANG']);
                  @endphp
                  <span class="account-status-pill {{ $isTang ? 'is-no' : 'is-yes' }}">
                    {{ $rec->loai_phat_sinh }}
                  </span>
                </td>
                <td style="font-family: monospace; font-size: 12px; color: #0284c7; font-weight: 600;">
                  {{ $rec->ma_tham_chieu }}
                </td>
                <td style="text-align: right; font-weight: 700; color: {{ $isTang ? '#dc2626' : 'var(--admin-brand-700)' }};">
                  {{ $isTang ? '+' : '-' }}{{ number_format($rec->so_tien) }} đ
                </td>
                <td style="text-align: right; color: var(--admin-slate-600);">{{ number_format($rec->so_du_truoc) }} đ</td>
                <td style="text-align: right; font-weight: 700; color: var(--admin-slate-800);">{{ number_format($rec->so_du_sau) }} đ</td>
                <td style="font-size: 12px; color: var(--admin-slate-500); max-width: 250px;">{{ $rec->ghi_chu }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="9">Chưa có bản ghi phát sinh công nợ nào.</td>
              </tr>
            @endforelse
          </tbody>
        </table>

      @elseif($tab === 'payments')
        {{-- ==================== TAB LỊCH SỬ THANH TOÁN ==================== --}}
        <table class="account-table">
          <thead>
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Mã thanh toán</th>
              <th>Thời gian</th>
              <th>Đại lý</th>
              <th style="text-align: right;">Số tiền thu</th>
              <th>Phương thức</th>
              <th>Mã GD Ngân hàng</th>
              <th>Ghi chú</th>
              <th style="text-align: center;">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            @forelse($payments as $pay)
              <tr>
                <td class="account-username">#{{ $pay->id }}</td>
                <td style="font-family: monospace; font-weight: 700; color: #0284c7;">{{ $pay->ma_thanh_toan }}</td>
                <td style="font-size: 12px; color: var(--admin-slate-500);">{{ $pay->ngay_thanh_toan ? $pay->ngay_thanh_toan->format('d/m/Y H:i') : '' }}</td>
                <td>
                  <strong style="color: var(--admin-slate-800);">{{ $pay->daiLyApi?->ten_dai_ly_api }}</strong>
                  <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $pay->daiLyApi?->ma_dai_ly_api }}</div>
                </td>
                <td style="text-align: right; font-weight: 700; color: var(--admin-brand-700);">
                  {{ number_format($pay->so_tien) }} đ
                </td>
                <td>{{ $pay->phuong_thuc }}</td>
                <td style="font-family: monospace; font-size: 12px;">{{ $pay->ma_giao_dich_ngan_hang ?: '-' }}</td>
                <td style="font-size: 12px; color: var(--admin-slate-500);">{{ $pay->ghi_chu ?: '-' }}</td>
                <td style="text-align: center;">
                  <span class="account-status-pill is-yes">{{ $pay->trang_thai }}</span>
                </td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="9">Chưa có giao dịch thanh toán công nợ nào.</td>
              </tr>
            @endforelse
          </tbody>
        </table>

      @elseif($tab === 'adjustments')
        {{-- ==================== TAB ĐIỀU CHỈNH CÔNG NỢ ==================== --}}
        <table class="account-table">
          <thead>
            <tr>
              <th style="width: 70px;">ID</th>
              <th>Mã điều chỉnh</th>
              <th>Thời gian</th>
              <th>Đại lý</th>
              <th>Loại điều chỉnh</th>
              <th style="text-align: right;">Số tiền</th>
              <th>Mã tham chiếu gốc</th>
              <th>Lý do điều chỉnh</th>
            </tr>
          </thead>
          <tbody>
            @forelse($adjustments as $adj)
              <tr>
                <td class="account-username">#{{ $adj->id }}</td>
                <td style="font-family: monospace; font-weight: 700; color: #0284c7;">{{ $adj->ma_dieu_chinh }}</td>
                <td style="font-size: 12px; color: var(--admin-slate-500);">{{ $adj->created_at->format('d/m/Y H:i') }}</td>
                <td>
                  <strong style="color: var(--admin-slate-800);">{{ $adj->daiLyApi?->ten_dai_ly_api }}</strong>
                  <div style="font-size: 11px; color: var(--admin-slate-400);">{{ $adj->daiLyApi?->ma_dai_ly_api }}</div>
                </td>
                <td>
                  <span class="account-status-pill {{ $adj->loai_dieu_chinh === 'TANG_NO' ? 'is-no' : 'is-yes' }}">
                    {{ $adj->loai_dieu_chinh === 'TANG_NO' ? 'TĂNG NỢ (+)' : 'GIẢM NỢ (-)' }}
                  </span>
                </td>
                <td style="text-align: right; font-weight: 700; color: {{ $adj->loai_dieu_chinh === 'TANG_NO' ? '#dc2626' : 'var(--admin-brand-700)' }};">
                  {{ $adj->loai_dieu_chinh === 'TANG_NO' ? '+' : '-' }}{{ number_format($adj->so_tien) }} đ
                </td>
                <td style="font-family: monospace; font-size: 12px;">{{ $adj->ma_tham_chieu_goc ?: '-' }}</td>
                <td style="font-size: 12px; color: var(--admin-slate-600);">{{ $adj->ly_do }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="8">Chưa có bút toán điều chỉnh công nợ nào.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      @endif
    </div>

    @php
      $currentPaginator = match($tab) {
        'payments' => $payments,
        'adjustments' => $adjustments,
        default => $ledgers,
      };
    @endphp
    @if ($currentPaginator->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $currentPaginator->firstItem() ?? 0 }} đến {{ $currentPaginator->lastItem() ?? 0 }} trong tổng số {{ $currentPaginator->total() }} bản ghi
        </span>
        <div>
          {{ $currentPaginator->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>

{{-- MODAL THU TIỀN THANH TOÁN --}}
<div class="account-modal" id="paymentModal" hidden>
  <div class="account-modal__backdrop" onclick="closePaymentModal()"></div>
  <section class="account-modal__dialog" style="max-width: 550px; width: 100%;">
    <header class="account-modal__header">
      <h2>Ghi nhận thanh toán công nợ</h2>
      <button type="button" class="account-modal__close" onclick="closePaymentModal()" aria-label="Đóng">×</button>
    </header>

    <form class="account-modal-form" method="POST" action="{{ route('admin.b2b.credit-payments.payment') }}">
      @csrf
      <div class="account-modal__body">
        <div class="account-form-grid account-form-grid--single" style="padding: 0;">
          <label>
            <span>Đại lý thanh toán (*)</span>
            <select name="dai_ly_api_id" required>
              @foreach($partners as $p)
                <option value="{{ $p->id }}" @selected($selectedPartnerId == $p->id)>
                  {{ $p->ten_dai_ly_api }} ({{ $p->ma_dai_ly_api }})
                </option>
              @endforeach
            </select>
          </label>

          <label>
            <span>Số tiền thanh toán (VNĐ) (*)</span>
            <input type="number" name="so_tien" required min="1" placeholder="VD: 50000000">
          </label>

          <label>
            <span>Phương thức thanh toán</span>
            <select name="phuong_thuc">
              <option value="CHUYEN_KHOAN">Chuyển khoản ngân hàng</option>
              <option value="TIEN_MAT">Tiền mặt</option>
              <option value="KHAC">Khác</option>
            </select>
          </label>

          <label>
            <span>Mã GD Ngân hàng / Uỷ nhiệm chi</span>
            <input type="text" name="ma_giao_dich_ngan_hang" placeholder="VD: FT24090123456">
          </label>

          <label>
            <span>Ghi chú chứng từ</span>
            <textarea name="ghi_chu" rows="2" placeholder="Ghi chú thêm..."></textarea>
          </label>
        </div>
      </div>

      <footer class="account-modal__footer">
        <button type="button" class="account-btn account-btn--muted" onclick="closePaymentModal()">Hủy</button>
        <button type="submit" class="account-btn account-btn--primary">Xác nhận thu nợ</button>
      </footer>
    </form>
  </section>
</div>

{{-- MODAL BÚT TOÁN ĐIỀU CHỈNH --}}
<div class="account-modal" id="adjustmentModal" hidden>
  <div class="account-modal__backdrop" onclick="closeAdjustmentModal()"></div>
  <section class="account-modal__dialog" style="max-width: 550px; width: 100%;">
    <header class="account-modal__header">
      <h2>Tạo bút toán điều chỉnh công nợ</h2>
      <button type="button" class="account-modal__close" onclick="closeAdjustmentModal()" aria-label="Đóng">×</button>
    </header>

    <form class="account-modal-form" method="POST" action="{{ route('admin.b2b.credit-payments.adjustment') }}">
      @csrf
      <div class="account-modal__body">
        <div class="account-form-grid account-form-grid--single" style="padding: 0;">
          <label>
            <span>Đại lý áp dụng (*)</span>
            <select name="dai_ly_api_id" required>
              @foreach($partners as $p)
                <option value="{{ $p->id }}" @selected($selectedPartnerId == $p->id)>
                  {{ $p->ten_dai_ly_api }}
                </option>
              @endforeach
            </select>
          </label>

          <label>
            <span>Loại điều chỉnh (*)</span>
            <select name="loai_dieu_chinh" required>
              <option value="GIAM_NO">Giảm công nợ đại lý (-)</option>
              <option value="TANG_NO">Tăng công nợ đại lý (+)</option>
            </select>
          </label>

          <label>
            <span>Số tiền điều chỉnh (VNĐ) (*)</span>
            <input type="number" name="so_tien" required min="1" placeholder="VD: 500000">
          </label>

          <label>
            <span>Mã tham chiếu gốc (nếu có)</span>
            <input type="text" name="ma_tham_chieu_goc" placeholder="VD: B2B20260906...">
          </label>

          <label>
            <span>Lý do điều chỉnh (*)</span>
            <textarea name="ly_do" required rows="2" placeholder="Bù trừ sai lệch kỳ trước..."></textarea>
          </label>
        </div>
      </div>

      <footer class="account-modal__footer">
        <button type="button" class="account-btn account-btn--muted" onclick="closeAdjustmentModal()">Hủy</button>
        <button type="submit" class="account-btn account-btn--primary">Lưu bút toán</button>
      </footer>
    </form>
  </section>
</div>

@push('scripts')
<script>
function openPaymentModal() { document.getElementById('paymentModal').hidden = false; }
function closePaymentModal() { document.getElementById('paymentModal').hidden = true; }
function openAdjustmentModal() { document.getElementById('adjustmentModal').hidden = false; }
function closeAdjustmentModal() { document.getElementById('adjustmentModal').hidden = true; }
</script>
@endpush
@endsection
