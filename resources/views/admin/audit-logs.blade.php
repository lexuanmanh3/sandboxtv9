@extends('admin.layout')

@section('title', 'VietFin - Nhật ký hoạt động')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .audit-inspect-btn {
      width: 32px;
      height: 32px;
      border: 1px solid #38bdf8;
      border-radius: 6px;
      background: #f0f9ff;
      color: #0284c7;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .audit-inspect-btn:hover {
      background: #0284c7;
      color: #fff;
    }
    .audit-inspect-btn svg {
      width: 16px;
      height: 16px;
    }
    .audit-status-icon {
      width: 20px;
      height: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }
    .audit-status-icon.is-success {
      background: #dcfce7;
      color: #16a34a;
    }
    .audit-status-icon.is-error {
      background: #fee2e2;
      color: #dc2626;
    }
    .audit-status-icon svg {
      width: 14px;
      height: 14px;
    }
    .audit-table th {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--admin-slate-500);
      font-weight: 700;
    }
    .audit-detail-grid {
      display: grid;
      grid-template-columns: 140px 1fr;
      gap: 10px;
      font-size: 13px;
      padding: 16px;
      background: var(--admin-slate-50);
      border-radius: 8px;
      margin-bottom: 16px;
    }
    .audit-detail-label {
      font-weight: 700;
      color: var(--admin-slate-600);
    }
    .audit-code-box {
      background: #0f172a;
      color: #e2e8f0;
      border-radius: 8px;
      padding: 14px;
      font-family: Consolas, Monaco, monospace;
      font-size: 12px;
      line-height: 1.5;
      max-height: 280px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .audit-code-box.is-error {
      border-left: 4px solid #ef4444;
      background: #1e1014;
      color: #fca5a5;
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
          <a href="#">Quản trị</a>
          <span>/</span>
          <strong>Nhật ký hoạt động</strong>
        </nav>
        <h1>Nhật ký hoạt động (Audit Logs)</h1>
        <p>Theo dõi toàn bộ lịch sử truy cập, thao tác nghiệp vụ, thời gian phản hồi và nhật ký lỗi hệ thống.</p>
      </div>

      <div class="account-actions">
        <a class="account-btn account-btn--muted" href="{{ route('admin.audit-logs.export', request()->query()) }}">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
            <polyline points="7 10 12 15 17 10" />
            <line x1="12" y1="15" x2="12" y2="3" />
          </svg>
          <span>Xuất Excel</span>
        </a>
      </div>
    </header>

    {{-- BỘ LỌC TÌM KIẾM NÂNG CAO --}}
    <section class="account-card account-filter-section is-open">
      <button class="account-filter-toggle" type="button" data-account-filter-toggle aria-expanded="true">
        <span><x-icon name="menu" /> Bộ lọc tìm kiếm nhật ký</span>
        <svg class="account-filter-toggle__chevron" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 7.5 10 12.5 15 7.5" />
        </svg>
      </button>

      <form class="account-filter" method="GET" action="{{ route('admin.audit-logs') }}" data-account-filter>
        <label>
          <span>Từ khóa</span>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="IP, hoạt động, thông tin...">
        </label>
        <label>
          <span>Dịch vụ / Controller</span>
          <select name="dich_vu">
            <option value="">Tất cả dịch vụ</option>
            @foreach ($services as $srv)
              <option value="{{ $srv }}" @selected(($filters['dich_vu'] ?? '') === $srv)>{{ $srv }}</option>
            @endforeach
          </select>
        </label>
        <label>
          <span>Trạng thái</span>
          <select name="trang_thai">
            <option value="">Tất cả</option>
            <option value="thanh_cong" @selected(($filters['trang_thai'] ?? '') === 'thanh_cong')>Thành công</option>
            <option value="loi" @selected(($filters['trang_thai'] ?? '') === 'loi')>Lỗi / Thất bại</option>
          </select>
        </label>
        <label>
          <span>Từ ngày</span>
          <input type="date" name="tu_ngay" value="{{ $filters['tu_ngay'] ?? '' }}">
        </label>
        <label>
          <span>Đến ngày</span>
          <input type="date" name="den_ngay" value="{{ $filters['den_ngay'] ?? '' }}">
        </label>
        <button class="account-btn account-btn--primary" type="submit">
          <x-icon name="visibility" />
          Tìm kiếm
        </button>
        <a class="account-btn account-btn--muted" href="{{ route('admin.audit-logs') }}">
          Đặt lại
        </a>
      </form>
    </section>

    {{-- BẢNG NHẬT KÝ HOẠT ĐỘNG --}}
    <section class="account-card account-table-card">
      <div class="account-table-wrap">
        <table class="account-table audit-table">
          <thead>
            <tr>
              <th style="width: 50px; text-align: center;">CHI TIẾT</th>
              <th style="width: 50px; text-align: center;">TRẠNG THÁI</th>
              <th>TÊN TRUY CẬP</th>
              <th>DỊCH VỤ</th>
              <th>HOẠT ĐỘNG</th>
              <th>KHOẢNG THỜI GIAN</th>
              <th>ĐỊA CHỈ IP</th>
              <th>KHÁCH HÀNG</th>
              <th>TRÌNH DUYỆT</th>
              <th>THỜI GIAN</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($logs as $log)
              @php
                $isSuccess = ($log->trang_thai === 'thanh_cong');
                $username = $log->nguoiThucHien?->name ?? $log->khach_hang ?? 'backend';
              @endphp
              <tr>
                <td style="text-align: center;">
                  <button class="audit-inspect-btn" type="button" title="Xem chi tiết hoạt động" data-log-id="{{ $log->id }}" data-detail-url="{{ route('admin.audit-logs.detail', $log) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="11" cy="11" r="8"></circle>
                      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                  </button>
                </td>
                <td style="text-align: center;">
                  @if ($isSuccess)
                    <span class="audit-status-icon is-success" title="Thành công">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                      </svg>
                    </span>
                  @else
                    <span class="audit-status-icon is-error" title="Lỗi / Thất bại">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                      </svg>
                    </span>
                  @endif
                </td>
                <td><strong>{{ $username }}</strong></td>
                <td><code>{{ $log->dich_vu ?: 'UnknownController' }}</code></td>
                <td><span style="font-weight: 600; color: var(--admin-slate-700);">{{ $log->hoat_dong ?: 'Index' }}</span></td>
                <td>
                  <span style="color: {{ ($log->thoi_gian_thuc_thi_ms ?? 0) > 1000 ? '#ea580c' : '#16a34a' }}; font-weight: 700;">
                    {{ $log->thoi_gian_thuc_thi_ms ?? 0 }} mili giây
                  </span>
                </td>
                <td><code>{{ $log->ip ?: '127.0.0.1' }}</code></td>
                <td>{{ $log->khach_hang ?: '-' }}</td>
                <td title="{{ $log->user_agent }}">
                  <span style="font-size: 12px; color: var(--admin-slate-500);">
                    {{ Str::limit($log->user_agent ?: '-', 35) }}
                  </span>
                </td>
                <td>{{ optional($log->created_at)->format('H:i:s d/m/Y') ?: '-' }}</td>
              </tr>
            @empty
              <tr>
                <td class="account-empty" colspan="10">Chưa có nhật ký hoạt động nào được ghi nhận.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <footer class="account-pagination">
        <div>
          <span>
            Đang xem {{ $logs->firstItem() ?? 0 }} đến {{ $logs->lastItem() ?? 0 }} trong tổng số {{ $logs->total() }} mục
          </span>
        </div>

        <nav aria-label="Phân trang nhật ký">
          <a class="{{ $logs->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $logs->url(1) }}">«</a>
          <a class="{{ $logs->onFirstPage() ? 'is-disabled' : '' }}" href="{{ $logs->previousPageUrl() ?: '#' }}">‹</a>
          @for ($page = 1; $page <= min($logs->lastPage(), 5); $page++)
            <a class="{{ $logs->currentPage() === $page ? 'is-active' : '' }}" href="{{ $logs->url($page) }}">{{ $page }}</a>
          @endfor
          <a class="{{ $logs->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $logs->nextPageUrl() ?: '#' }}">›</a>
          <a class="{{ $logs->hasMorePages() ? '' : 'is-disabled' }}" href="{{ $logs->url($logs->lastPage()) }}">»</a>
        </nav>
      </footer>
    </section>
  </section>

  {{-- MODAL XEM CHI TIẾT NHẬT KÝ HOẠT ĐỘNG (🔍) --}}
  <div class="account-modal" id="auditDetailModal" hidden>
    <div class="account-modal__backdrop" data-modal-close></div>
    <section class="account-modal__dialog account-modal__dialog--role" role="dialog" aria-modal="true" aria-labelledby="auditDetailTitle" style="max-width: 800px;">
      <header class="account-modal__header">
        <h2 id="auditDetailTitle">Chi tiết nhật ký hoạt động</h2>
        <button type="button" class="account-modal__close" data-modal-close aria-label="Đóng">×</button>
      </header>

      <div class="account-modal__body" style="padding: 20px;">
        <div class="audit-detail-grid">
          <div class="audit-detail-label">Tên truy cập:</div>
          <div id="modalUser">-</div>

          <div class="audit-detail-label">Dịch vụ (Service):</div>
          <div><code id="modalService">-</code></div>

          <div class="audit-detail-label">Hoạt động (Action):</div>
          <div id="modalAction" style="font-weight: 700;">-</div>

          <div class="audit-detail-label">Thời gian thực thi:</div>
          <div id="modalDuration" style="font-weight: 700; color: #16a34a;">-</div>

          <div class="audit-detail-label">Địa chỉ IP:</div>
          <div><code id="modalIp">-</code></div>

          <div class="audit-detail-label">Trình duyệt:</div>
          <div id="modalBrowser" style="font-size: 12px; color: var(--admin-slate-600);">-</div>

          <div class="audit-detail-label">Thời gian:</div>
          <div id="modalTime">-</div>

          <div class="audit-detail-label">Trạng thái:</div>
          <div id="modalStatus">-</div>
        </div>

        <h4 style="margin: 16px 0 8px; font-size: 14px; font-weight: 700; color: var(--admin-slate-800);">
          Tham số yêu cầu (Request Parameters):
        </h4>
        <pre class="audit-code-box" id="modalParams">{}</pre>

        <div id="modalErrorSection" hidden>
          <h4 style="margin: 16px 0 8px; font-size: 14px; font-weight: 700; color: #dc2626;">
            Chi tiết lỗi / Ngoại lệ (Exception Trace):
          </h4>
          <pre class="audit-code-box is-error" id="modalErrorText"></pre>
        </div>
      </div>

      <footer class="account-modal__footer">
        <button class="account-btn account-btn--muted" type="button" data-modal-close>Đóng</button>
      </footer>
    </section>
  </div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
  const modal = document.getElementById("auditDetailModal");
  const modalUser = document.getElementById("modalUser");
  const modalService = document.getElementById("modalService");
  const modalAction = document.getElementById("modalAction");
  const modalDuration = document.getElementById("modalDuration");
  const modalIp = document.getElementById("modalIp");
  const modalBrowser = document.getElementById("modalBrowser");
  const modalTime = document.getElementById("modalTime");
  const modalStatus = document.getElementById("modalStatus");
  const modalParams = document.getElementById("modalParams");
  const modalErrorSection = document.getElementById("modalErrorSection");
  const modalErrorText = document.getElementById("modalErrorText");

  function openModal() {
    modal.hidden = false;
    document.body.classList.add("account-modal-open");
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove("account-modal-open");
  }

  document.querySelectorAll("[data-modal-close]").forEach((btn) => {
    btn.addEventListener("click", closeModal);
  });

  // Bấm nút soi chi tiết 🔍
  document.querySelectorAll("[data-log-id]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const url = btn.dataset.detailUrl;
      if (!url) return;

      try {
        const res = await fetch(url, { headers: { "Accept": "application/json" } });
        const json = await res.json();
        if (!json.success || !json.data) return;

        const data = json.data;
        modalUser.textContent = data.ten_truy_cap;
        modalService.textContent = data.dich_vu;
        modalAction.textContent = data.hoat_dong;
        modalDuration.textContent = data.thoi_gian_thuc_thi;
        modalIp.textContent = data.ip;
        modalBrowser.textContent = data.user_agent;
        modalTime.textContent = data.thoi_gian;
        modalStatus.innerHTML = data.trang_thai === 'thanh_cong'
          ? '<span class="account-status-pill is-yes">Thành công</span>'
          : '<span class="account-status-pill is-no">Lỗi / Thất bại</span>';

        modalParams.textContent = data.tham_so ? JSON.stringify(data.tham_so, null, 2) : 'Không có tham số';

        if (data.loi_ngoai_le) {
          modalErrorSection.hidden = false;
          modalErrorText.textContent = data.loi_ngoai_le;
        } else {
          modalErrorSection.hidden = true;
        }

        openModal();
      } catch (err) {
        console.error("Lỗi tải chi tiết log:", err);
      }
    });
  });
});
</script>
@endpush
