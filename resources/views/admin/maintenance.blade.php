@extends('admin.layout')

@section('title', 'tv9tech - Bảo trì hệ thống')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .maint-tabs {
      display: flex;
      gap: 2px;
      border-bottom: 1px solid var(--admin-slate-200);
      margin-bottom: 20px;
    }
    .maint-tab-btn {
      border: 0;
      border-bottom: 2px solid transparent;
      padding: 12px 20px;
      background: transparent;
      font: inherit;
      font-size: 14px;
      font-weight: 600;
      color: var(--admin-slate-600);
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .maint-tab-btn:hover {
      color: var(--admin-brand-700);
    }
    .maint-tab-btn.is-active {
      color: var(--admin-brand-700);
      border-bottom-color: var(--admin-brand-700);
      background: #fff;
    }
    .maint-tab-panel {
      display: none;
    }
    .maint-tab-panel.is-active {
      display: block;
    }
    .maint-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .maint-header-row p {
      margin: 0;
      color: var(--admin-slate-600);
      font-size: 14px;
    }
    .maint-btn-green {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #047857;
      color: #fff;
      border: 0;
      border-radius: 6px;
      padding: 8px 16px;
      font: inherit;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.15s ease;
    }
    .maint-btn-green:hover {
      background: #065f46;
    }
    .maint-btn-green svg {
      width: 16px;
      height: 16px;
    }
    .maint-btn-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #f1f5f9;
      color: var(--admin-slate-700);
      border: 1px solid var(--admin-slate-300);
      border-radius: 6px;
      padding: 8px 16px;
      font: inherit;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    .maint-btn-outline:hover {
      background: #e2e8f0;
    }
    .maint-btn-outline svg {
      width: 16px;
      height: 16px;
    }
    .maint-cache-list {
      display: grid;
      gap: 10px;
    }
    .maint-cache-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 18px;
      background: #fff;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      transition: border-color 0.15s ease;
    }
    .maint-cache-item:hover {
      border-color: var(--admin-slate-300);
    }
    .maint-cache-info strong {
      display: block;
      font-size: 14px;
      color: var(--admin-slate-800);
      font-family: Consolas, Monaco, monospace;
    }
    .maint-cache-info span {
      display: block;
      font-size: 12px;
      color: var(--admin-slate-500);
      margin-top: 2px;
    }
    /* TERMINAL LOG VIEWER STYLE */
    .log-viewer-box {
      background: #ffffff;
      border: 1px solid var(--admin-slate-200);
      border-radius: 8px;
      padding: 16px;
      font-family: Consolas, "Courier New", monospace;
      font-size: 12px;
      line-height: 1.6;
      max-height: 580px;
      overflow: auto;
      white-space: pre-wrap;
      word-break: break-all;
    }
    .log-row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 3px 0;
      border-bottom: 1px solid #f8fafc;
    }
    .log-badge {
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      color: #fff;
      display: inline-block;
      min-width: 50px;
      text-align: center;
      flex-shrink: 0;
    }
    .log-badge.WARN { background: #f59e0b; }
    .log-badge.ERROR { background: #ef4444; }
    .log-badge.INFO { background: #3b82f6; }
    .log-badge.DEBUG { background: #64748b; }
    .log-text {
      color: #334155;
      flex: 1;
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
          <strong>Bảo trì</strong>
        </nav>
        <h1>Bảo trì</h1>
        <p>Quản lý xóa bộ nhớ đệm (Cache) và theo dõi nhật ký máy chủ theo thời gian thực.</p>
      </div>
    </header>

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

    <section class="account-card" style="padding: 20px;">
      {{-- THANH ĐIỀU HƯỚNG TABS --}}
      <div class="maint-tabs" role="tablist">
        <button class="maint-tab-btn is-active" type="button" data-maint-tab="cache">Bộ nhớ cache</button>
        <button class="maint-tab-btn" type="button" data-maint-tab="logs">Nhật ký trang web</button>
      </div>

      {{-- TAB 1: BỘ NHỚ CACHE --}}
      <div class="maint-tab-panel is-active" data-maint-panel="cache">
        <div class="maint-header-row">
          <p>Bạn có thể xóa bộ nhớ cache của ứng dụng trên trang này.</p>
          <form method="POST" action="{{ route('admin.maintenance.cache.clear-all') }}" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ bộ nhớ cache hệ thống?');">
            @csrf
            <button class="maint-btn-green" type="submit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
              </svg>
              <span>Xóa tất cả</span>
            </button>
          </form>
        </div>

        <div class="maint-cache-list">
          @foreach ($caches as $c)
            <div class="maint-cache-item">
              <div class="maint-cache-info">
                <strong>{{ $c['name'] }}</strong>
                <span>{{ $c['desc'] }}</span>
              </div>
              <form method="POST" action="{{ route('admin.maintenance.cache.clear-type', $c['key']) }}">
                @csrf
                <button class="maint-btn-green" type="submit" style="padding: 6px 12px; font-size: 12px;">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <polyline points="1 20 1 14 7 14"></polyline>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                  </svg>
                  <span>Xóa</span>
                </button>
              </form>
            </div>
          @endforeach
        </div>
      </div>

      {{-- TAB 2: NHẬT KÝ TRANG WEB --}}
      <div class="maint-tab-panel" data-maint-panel="logs">
        <div class="maint-header-row">
          <p>Bạn có thể xem nhật ký mới nhất trong trang này hoặc tải xuống tất cả nhật ký trong một tập tin zip. (Dung lượng: {{ $logFileSize }})</p>
          <div style="display: flex; gap: 8px;">
            <a class="maint-btn-outline" href="{{ route('admin.maintenance.logs.download') }}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              <span>Tải xuống tất cả</span>
            </a>
            <button class="maint-btn-green" type="button" id="btnRefreshLogs">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"></polyline>
                <polyline points="1 20 1 14 7 14"></polyline>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
              </svg>
              <span>Làm mới</span>
            </button>
          </div>
        </div>

        <div class="log-viewer-box" id="logViewerContainer">
          @forelse ($logs as $l)
            @if ($l['type'] === 'log')
              <div class="log-row">
                <span class="log-badge {{ $l['level'] }}">{{ $l['level'] }}</span>
                <span class="log-text">{{ $l['time'] }} [ {{ $l['thread'] }} ] {{ $l['message'] }}</span>
              </div>
            @else
              <div class="log-row" style="padding-left: 60px; color: #64748b; font-size: 11px;">
                <span>{{ $l['message'] }}</span>
              </div>
            @endif
          @empty
            <div style="text-align: center; color: var(--admin-slate-400); padding: 30px;">
              Chưa có nhật ký lỗi hoặc sự kiện nào trong tệp logs.
            </div>
          @endforelse
        </div>
      </div>
    </section>
  </section>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
  const tabBtns = document.querySelectorAll("[data-maint-tab]");
  const panels = document.querySelectorAll("[data-maint-panel]");
  const logContainer = document.getElementById("logViewerContainer");
  const btnRefreshLogs = document.getElementById("btnRefreshLogs");

  // Điều khiển chuyển Tab
  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      const tab = btn.dataset.maintTab;
      tabBtns.forEach((b) => b.classList.toggle("is-active", b === btn));
      panels.forEach((p) => p.classList.toggle("is-active", p.dataset.maintPanel === tab));
    });
  });

  // Tải lại nhật ký trực tiếp qua AJAX
  btnRefreshLogs?.addEventListener("click", async () => {
    btnRefreshLogs.disabled = true;
    btnRefreshLogs.style.opacity = "0.7";

    try {
      const res = await fetch("{{ route('admin.maintenance.logs.ajax') }}", {
        headers: { "Accept": "application/json" }
      });
      const json = await res.json();
      if (json.success && json.logs) {
        if (json.logs.length === 0) {
          logContainer.innerHTML = '<div style="text-align: center; color: var(--admin-slate-400); padding: 30px;">Chưa có nhật ký nào.</div>';
        } else {
          logContainer.innerHTML = json.logs.map((l) => {
            if (l.type === 'log') {
              return `
                <div class="log-row">
                  <span class="log-badge ${l.level}">${l.level}</span>
                  <span class="log-text">${l.time} [ ${l.thread} ] ${l.message}</span>
                </div>
              `;
            } else {
              return `
                <div class="log-row" style="padding-left: 60px; color: #64748b; font-size: 11px;">
                  <span>${l.message}</span>
                </div>
              `;
            }
          }).join('');
        }
      }
    } catch (err) {
      console.error("Lỗi làm mới logs:", err);
    } finally {
      btnRefreshLogs.disabled = false;
      btnRefreshLogs.style.opacity = "1";
    }
  });
});
</script>
@endpush
