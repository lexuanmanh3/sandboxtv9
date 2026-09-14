@extends('admin.layout')

@section('title', 'tv9tech - Cấu hình tuyến dịch vụ (Routing)')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .account-row-actions {
      position: relative;
      display: inline-block;
    }
    .account-row-menu {
      min-width: 160px;
    }
    .modal-section-title {
      font-size: 13px;
      font-weight: 800;
      color: var(--admin-slate-800);
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin: 16px 0 10px;
      padding-bottom: 6px;
      border-bottom: 1px dashed var(--admin-slate-200);
      grid-column: 1 / -1;
    }
    .account-modal__dialog {
      display: flex !important;
      flex-direction: column !important;
      max-height: min(90vh, 760px) !important;
      overflow: hidden !important;
    }
    .account-modal-form {
      display: flex !important;
      flex-direction: column !important;
      flex: 1 1 auto !important;
      min-height: 0 !important;
      overflow: hidden !important;
      margin: 0 !important;
    }
    .account-modal__body {
      flex: 1 1 auto !important;
      min-height: 0 !important;
      overflow-y: auto !important;
      padding: 20px 24px !important;
    }
    .account-modal__footer {
      flex-shrink: 0 !important;
      background: var(--admin-slate-50) !important;
      border-top: 1px solid var(--admin-slate-200) !important;
      padding: 14px 24px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: flex-end !important;
      gap: 12px !important;
      z-index: 10 !important;
    }
    .account-form-grid input[type="checkbox"] {
      width: 18px !important;
      height: 18px !important;
      min-height: 18px !important;
      max-height: 18px !important;
      flex: 0 0 18px !important;
      accent-color: var(--admin-brand-600) !important;
      cursor: pointer !important;
      margin: 0 !important;
      display: inline-block !important;
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
        <a href="#">B2B</a>
        <span>/</span>
        <strong>Cấu hình tuyến dịch vụ</strong>
      </nav>
      <h1>Cấu hình tuyến dịch vụ</h1>
      <p>Quản lý luồng định tuyến ưu tiên từ Đại lý đến Nhà cung cấp.</p>
    </div>

    <div class="account-actions">
      <button type="button" class="account-btn account-btn--primary" onclick="openRoutingModal('create')">
        <svg class="account-action-icon account-action-icon--plus" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>
        <span>Thêm cấu hình tuyến</span>
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

  {{-- BỘ LỌC TÌM KIẾM --}}
  <section class="account-card account-filter-section is-open">
    <form class="account-filter" method="GET" action="{{ route('admin.b2b.routing-configs.index') }}">
      <label style="flex: 2; min-width: 240px;">
        <span>Đại lý áp dụng</span>
        <select name="dai_ly_api_id">
          <option value="">-- Tất cả đại lý (Tuyến chung) --</option>
          @foreach($partners as $partner)
            <option value="{{ $partner->id }}" @selected(request('dai_ly_api_id') == $partner->id)>{{ $partner->ten_dai_ly_api }} ({{ $partner->ma_dai_ly_api }})</option>
          @endforeach
        </select>
      </label>

      <label style="flex: 1.5; min-width: 200px;">
        <span>Nhà cung cấp</span>
        <select name="nha_cung_cap_id">
          <option value="">-- Tất cả NCC --</option>
          @foreach($providers as $provider)
            <option value="{{ $provider->id }}" @selected(request('nha_cung_cap_id') == $provider->id)>{{ $provider->ten_ncc ?: ($provider->ma_ncc ?: 'NCC #' . $provider->id) }}</option>
          @endforeach
        </select>
      </label>

      <label style="flex: 1; min-width: 160px;">
        <span>Trạng thái</span>
        <select name="trang_thai">
          <option value="">-- Tất cả trạng thái --</option>
          <option value="ACTIVE" @selected(request('trang_thai') === 'ACTIVE')>Hoạt động</option>
          <option value="PAUSED" @selected(request('trang_thai') === 'PAUSED')>Tạm dừng</option>
        </select>
      </label>

      <button class="account-btn account-btn--primary" type="submit">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <circle cx="11" cy="11" r="8" />
          <line x1="21" y1="21" x2="16.65" y2="16.65" />
        </svg>
        <span>Lọc</span>
      </button>

      <a class="account-btn account-btn--muted" href="{{ route('admin.b2b.routing-configs.index') }}">
        Đặt lại
      </a>
    </form>
  </section>

  {{-- BẢNG DANH SÁCH CẤU HÌNH --}}
  <section class="account-card account-table-card">
    <div class="account-table-wrap">
      <table class="account-table">
        <thead>
          <tr>
            <th style="width: 130px; text-align: center;">HÀNH ĐỘNG</th>
            <th>TÊN TUYẾN</th>
            <th>ĐẠI LÝ ÁP DỤNG</th>
            <th>PHẠM VI DỊCH VỤ</th>
            <th>NHÀ CUNG CẤP</th>
            <th>CHẾ ĐỘ</th>
            <th style="text-align: center;">ƯU TIÊN</th>
            <th style="text-align: center;">TRẠNG THÁI</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($configs as $config)
            <tr>
              <td style="text-align: center;">
                <div class="account-row-actions">
                  <button class="account-row-action" type="button" onclick="toggleDropdown(this, 'action-{{ $config->id }}')">
                    <span>Hành động</span>
                    <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
                      <path d="M5 7.5 10 12.5 15 7.5" />
                    </svg>
                  </button>
                  <div id="action-{{ $config->id }}" class="account-row-menu" hidden>
                    <button type="button" onclick="openRoutingModal('edit', {{ $config->id }})" style="width: 100%; text-align: left;">
                      Sửa cấu hình
                    </button>
                    <form action="{{ route('admin.b2b.routing-configs.destroy', $config->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa cấu hình này không?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" style="color: #dc2626; width: 100%; text-align: left;">
                        Xóa cấu hình
                      </button>
                    </form>
                  </div>
                </div>
              </td>
              <td>
                <strong style="color: var(--admin-slate-900);">{{ $config->ten_cau_hinh }}</strong>
                @if($config->ket_thuc_cau_hinh)
                  <div style="font-size: 11px; color: #dc2626; font-weight: 600;">(Chặn fallback khi lỗi)</div>
                @endif
              </td>
              <td>
                @if($config->dai_ly_ap_dung_id)
                  <strong style="color: var(--admin-brand-700);">{{ $config->daiLyApi?->ten_dai_ly_api }}</strong>
                  <div style="font-size: 12px; color: var(--admin-slate-400);">Mã: {{ $config->daiLyApi?->ma_dai_ly_api }}</div>
                @else
                  <span style="color: var(--admin-slate-500); font-style: italic;">Tất cả (Tuyến chung)</span>
                @endif
              </td>
              <td>
                <div style="font-weight: 600; color: var(--admin-slate-800);">DV: {{ $config->dichVu?->ten_dich_vu ?? 'Dịch vụ #' . $config->dich_vu_id }}</div>
                @if($config->san_pham_id)
                  <div style="font-size: 12px; color: var(--admin-slate-500);">SP: {{ $config->sanPham?->ten_san_pham ?? 'SP #' . $config->san_pham_id }}</div>
                @elseif($config->loai_san_pham_id)
                  <div style="font-size: 12px; color: var(--admin-slate-500);">Loại: {{ $config->loaiSanPham?->ten_loai_san_pham ?? 'Loại #' . $config->loai_san_pham_id }}</div>
                @endif
              </td>
              <td>
                <span class="account-role" style="background: var(--admin-slate-100); color: var(--admin-slate-800); font-weight: 700;">
                  {{ $config->nhaCungCap?->ten_ncc ?: ($config->nhaCungCap?->ma_ncc ?: 'NCC #' . $config->nha_cung_cap_id) }}
                </span>
              </td>
              <td>
                <span style="font-weight: 600; font-size: 12px; color: var(--admin-slate-600);">
                  {{ strtoupper($config->che_do_chay ?? 'API') }}
                </span>
              </td>
              <td style="text-align: center; font-weight: 800; color: var(--admin-brand-800);">
                {{ $config->muc_uu_tien }}
              </td>
              <td style="text-align: center;">
                <span class="account-status-pill {{ $config->dang_mo ? 'is-yes' : 'is-no' }}">
                  {{ $config->dang_mo ? 'Hoạt động' : 'Tạm dừng' }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td class="account-empty" colspan="8">
                Chưa có cấu hình định tuyến nào được thiết lập.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($configs->hasPages())
      <footer class="account-pagination">
        <span>
          Đang xem {{ $configs->firstItem() ?? 0 }} đến {{ $configs->lastItem() ?? 0 }} trong tổng số {{ $configs->total() }} cấu hình
        </span>
        <div>
          {{ $configs->links() }}
        </div>
      </footer>
    @endif
  </section>
</section>

{{-- MODAL POPUP CẤU HÌNH TUYẾN DỊCH VỤ (Chuẩn style hệ thống) --}}
<div class="account-modal" id="routingModal" hidden>
  <div class="account-modal__backdrop" onclick="closeRoutingModal()"></div>
  <section class="account-modal__dialog" style="max-width: 920px; width: 100%;">
    <header class="account-modal__header">
      <h2 id="routingModalTitle">Thêm mới cấu hình tuyến dịch vụ</h2>
      <button type="button" class="account-modal__close" onclick="closeRoutingModal()" aria-label="Đóng">×</button>
    </header>

    <form id="routingForm" class="account-modal-form" method="POST" action="{{ route('admin.b2b.routing-configs.store') }}">
      @csrf
      <div id="routingMethodContainer"></div>

      <div class="account-modal__body" style="padding: 20px;">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 16px;">
          {{-- HÀNG 1: NCC, DỊCH VỤ, LOẠI SẢN PHẨM --}}
          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Nhà cung cấp (*)</span>
            <select name="nha_cung_cap_id" id="modal_nha_cung_cap_id" required style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
              <option value="">-- Chọn Nhà cung cấp --</option>
              @foreach($providers as $provider)
                <option value="{{ $provider->id }}">{{ $provider->ten_ncc ?: ($provider->ma_ncc ?: 'NCC #' . $provider->id) }}</option>
              @endforeach
            </select>
          </label>

          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Dịch vụ (*)</span>
            <select name="dich_vu_id" id="modal_dich_vu_id" required onchange="onDichVuChange(this.value)" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
              <option value="">-- Chọn dịch vụ --</option>
              @foreach($services as $service)
                <option value="{{ $service->id }}">{{ $service->ten_dich_vu }}</option>
              @endforeach
            </select>
          </label>

          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Loại sản phẩm</span>
            <select name="loai_san_pham_id" id="modal_loai_san_pham_id" onchange="onLoaiSanPhamChange(this.value)" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
              <option value="">-- Tất cả loại sản phẩm --</option>
              @foreach($categories as $category)
                <option value="{{ $category->id }}" data-dich-vu="{{ $category->dich_vu_id }}">{{ $category->ten_loai_san_pham }}</option>
              @endforeach
            </select>
          </label>
        </div>

        {{-- HÀNG 2: SẢN PHẨM & ĐẠI LÝ ÁP DỤNG --}}
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; margin-top: 14px;">
          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Sản phẩm cụ thể</span>
            <select name="san_pham_id" id="modal_san_pham_id" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
              <option value="">-- Áp dụng cho toàn bộ dịch vụ/loại SP --</option>
              @foreach($products as $product)
                <option value="{{ $product->id }}" data-dich-vu="{{ $product->dich_vu_id }}" data-loai-sp="{{ $product->loai_san_pham_id }}">{{ $product->ten_san_pham }}</option>
              @endforeach
            </select>
          </label>

          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Cấu hình cho Đại lý</span>
            <select name="dai_ly_api_id" id="modal_dai_ly_api_id" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
              <option value="">-- Tuyến chung (Áp dụng cho mọi đại lý) --</option>
              @foreach($partners as $partner)
                <option value="{{ $partner->id }}">{{ $partner->ten_dai_ly_api }} ({{ $partner->ma_dai_ly_api }})</option>
              @endforeach
            </select>
          </label>
        </div>

        {{-- HÀNG 3: TÊN CẤU HÌNH, CHECKBOX MỞ, MỨC ƯU TIÊN --}}
        <div style="display: grid; grid-template-columns: 2fr auto 1fr; gap: 14px 16px; align-items: end; margin-top: 14px;">
          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Tên cấu hình dịch vụ (*)</span>
            <input type="text" name="ten_cau_hinh" id="modal_ten_cau_hinh" required placeholder="Ví dụ: TOPUP VTE QUA NCC TEST" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
          </label>

          <div style="padding-bottom: 8px;">
            <label class="account-check" style="cursor: pointer; user-select: none; display: flex; align-items: center; gap: 8px; margin: 0;">
              <input type="hidden" name="dang_mo" value="0">
              <input type="checkbox" name="dang_mo" id="modal_dang_mo" value="1" checked style="width: 18px; height: 18px; accent-color: var(--admin-brand-600);">
              <span style="font-weight: 700; color: var(--admin-slate-800);">Mở</span>
            </label>
          </div>

          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Mức ưu tiên (*)</span>
            <input type="number" name="muc_uu_tien" id="modal_muc_uu_tien" value="1" min="0" required style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
          </label>
        </div>

        {{-- HÀNG 4: MÔ TẢ --}}
        <div style="margin-top: 14px;">
          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Mô tả / Ghi chú</span>
            <textarea name="mo_ta" id="modal_mo_ta" rows="2" placeholder="Ghi chú chi tiết cho tuyến cấu hình này..." style="width: 100%; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 8px 10px; font-family: inherit; font-size: 13px;"></textarea>
          </label>
        </div>

        {{-- HÀNG 5: KHAI BÁO NẠP CHẬM & TIMEOUT --}}
        <div class="modal-section-title">Khai báo nạp chậm & Timeout</div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 16px;">
          <label style="display: grid; gap: 6px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--admin-slate-600);">Timeout Hệ thống chờ tối đa (giây)</span>
            <input type="number" name="timeout_he_thong_giay" id="modal_timeout_he_thong_giay" value="30" min="1" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
          </label>

          <label style="display: grid; gap: 6px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--admin-slate-600);">Timeout truyền sang NCC (giây)</span>
            <input type="number" name="timeout_gui_ncc_giay" id="modal_timeout_gui_ncc_giay" value="25" min="1" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
          </label>

          <label style="display: grid; gap: 6px;">
            <span style="font-size: 11px; font-weight: 700; color: var(--admin-slate-600);">Thời gian trả kết quả (giây)</span>
            <input type="number" name="thoi_gian_tra_ket_qua_giay" id="modal_thoi_gian_tra_ket_qua_giay" value="60" min="1" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
          </label>
        </div>

        {{-- HÀNG 6: TÙY CHỌN LUỒNG & CHẾ ĐỘ CHẠY --}}
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; margin-top: 14px; align-items: center;">
          <label style="display: grid; gap: 6px;">
            <span style="font-size: 12px; font-weight: 800; color: var(--admin-slate-600); text-transform: uppercase;">Chế độ chạy</span>
            <select name="che_do_chay" id="modal_che_do_chay" style="min-height: 38px; border: 1px solid var(--admin-slate-300); border-radius: 6px; padding: 6px 10px;">
              <option value="api">Kết nối API NCC</option>
              <option value="kho_the">Kho thẻ nội bộ</option>
              <option value="thu_cong">Xử lý thủ công</option>
            </select>
          </label>

          <div style="padding-top: 20px;">
            <label class="account-check" style="cursor: pointer; user-select: none; display: flex; align-items: center; gap: 8px;">
              <input type="hidden" name="ket_thuc_cau_hinh" value="0">
              <input type="checkbox" name="ket_thuc_cau_hinh" id="modal_ket_thuc_cau_hinh" value="1" style="width: 18px; height: 18px; accent-color: var(--admin-brand-600);">
              <span style="font-weight: 600; color: #991b1b; font-size: 13px;">Kết thúc ở cấu hình này (Không thử NCC khác khi lỗi)</span>
            </label>
          </div>
        </div>
      </div>

      <footer class="account-modal__footer">
        <button type="button" class="account-btn account-btn--muted" onclick="closeRoutingModal()">Hủy bỏ</button>
        <button type="submit" class="account-btn account-btn--primary">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span id="routingSubmitBtnText">Lưu cấu hình</span>
        </button>
      </footer>
    </form>
  </section>
</div>
@endsection

@push('scripts')
<script>
const configsData = @json($configs->keyBy('id'));
const categoriesData = @json($categories);
const productsData = @json($products);

function toggleDropdown(button, menuId) {
  event.stopPropagation();
  const wrapper = button.closest('.account-row-actions');
  const menu = document.getElementById(menuId);
  if (!menu) return;

  const willOpen = menu.hidden;
  closeAllDropdowns();

  if (willOpen) {
    wrapper.classList.add('is-open');
    menu.hidden = false;
    const rect = button.getBoundingClientRect();
    menu.style.position = 'fixed';
    menu.style.left = rect.left + 'px';
    menu.style.top = (rect.bottom + 4) + 'px';
  }
}

function closeAllDropdowns() {
  document.querySelectorAll('.account-row-actions.is-open').forEach(w => w.classList.remove('is-open'));
  document.querySelectorAll('.account-row-menu').forEach(m => m.hidden = true);
}

document.addEventListener('click', closeAllDropdowns);
window.addEventListener('scroll', closeAllDropdowns, { passive: true, capture: true });
window.addEventListener('resize', closeAllDropdowns, { passive: true });

function updateLoaiSanPhamOptions(dichVuId, selectedId = '') {
  const lspSelect = document.getElementById('modal_loai_san_pham_id');
  const dvStr = dichVuId ? String(dichVuId) : '';
  const currentVal = (selectedId !== null && selectedId !== undefined && selectedId !== '') ? String(selectedId) : '';

  lspSelect.innerHTML = '<option value="">-- Tất cả loại sản phẩm --</option>';

  const filtered = categoriesData.filter(cat => {
    if (!dvStr) return true;
    return String(cat.dich_vu_id) === dvStr;
  });

  filtered.forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat.id;
    opt.textContent = cat.ten_loai_san_pham;
    if (String(cat.id) === currentVal) {
      opt.selected = true;
    }
    lspSelect.appendChild(opt);
  });

  updateSanPhamOptions(dvStr, lspSelect.value);
}

function updateSanPhamOptions(dichVuId, loaiSpId, selectedId = '') {
  const spSelect = document.getElementById('modal_san_pham_id');
  const dvStr = dichVuId ? String(dichVuId) : document.getElementById('modal_dich_vu_id').value;
  const lspStr = loaiSpId ? String(loaiSpId) : '';
  const currentVal = (selectedId !== null && selectedId !== undefined && selectedId !== '') ? String(selectedId) : '';

  spSelect.innerHTML = '<option value="">-- Áp dụng cho toàn bộ dịch vụ/loại SP --</option>';

  const filtered = productsData.filter(p => {
    if (lspStr && String(p.loai_san_pham_id) !== lspStr) return false;
    if (dvStr && String(p.dich_vu_id) !== dvStr) return false;
    return true;
  });

  filtered.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.id;
    opt.textContent = p.ten_san_pham;
    if (String(p.id) === currentVal) {
      opt.selected = true;
    }
    spSelect.appendChild(opt);
  });
}

function onDichVuChange(dichVuId) {
  updateLoaiSanPhamOptions(dichVuId, '');
}

function onLoaiSanPhamChange(loaiSpId) {
  const dvId = document.getElementById('modal_dich_vu_id').value;
  updateSanPhamOptions(dvId, loaiSpId, '');
}

function openRoutingModal(action, id = null) {
  const modal = document.getElementById('routingModal');
  const form = document.getElementById('routingForm');
  const methodContainer = document.getElementById('routingMethodContainer');
  const title = document.getElementById('routingModalTitle');
  const submitText = document.getElementById('routingSubmitBtnText');

  if (action === 'create') {
    title.innerText = 'Thêm mới cấu hình tuyến dịch vụ';
    submitText.innerText = 'Lưu cấu hình';
    form.action = '{{ route("admin.b2b.routing-configs.store") }}';
    methodContainer.innerHTML = '';
    
    // Reset form fields
    document.getElementById('modal_nha_cung_cap_id').value = '';
    document.getElementById('modal_dich_vu_id').value = '';
    updateLoaiSanPhamOptions('', '');
    updateSanPhamOptions('', '', '');
    document.getElementById('modal_dai_ly_api_id').value = '';
    document.getElementById('modal_ten_cau_hinh').value = '';
    document.getElementById('modal_dang_mo').checked = true;
    document.getElementById('modal_muc_uu_tien').value = '1';
    document.getElementById('modal_mo_ta').value = '';
    document.getElementById('modal_timeout_he_thong_giay').value = '30';
    document.getElementById('modal_timeout_gui_ncc_giay').value = '25';
    document.getElementById('modal_thoi_gian_tra_ket_qua_giay').value = '60';
    document.getElementById('modal_che_do_chay').value = 'api';
    document.getElementById('modal_ket_thuc_cau_hinh').checked = false;
  } else if (action === 'edit' && id) {
    const config = configsData[id];
    if (!config) return;

    title.innerText = 'Cập nhật cấu hình dịch vụ #' + config.id;
    submitText.innerText = 'Cập nhật tuyến';
    form.action = '/admin/b2b/routing-configs/' + config.id;
    methodContainer.innerHTML = '<input type="hidden" name="_method" value="PUT">';

    // Populate data
    document.getElementById('modal_nha_cung_cap_id').value = config.nha_cung_cap_id || '';
    document.getElementById('modal_dich_vu_id').value = config.dich_vu_id || '';
    
    updateLoaiSanPhamOptions(config.dich_vu_id || '', config.loai_san_pham_id || '');
    updateSanPhamOptions(config.dich_vu_id || '', config.loai_san_pham_id || '', config.san_pham_id || '');
    
    document.getElementById('modal_dai_ly_api_id').value = config.dai_ly_ap_dung_id || '';
    document.getElementById('modal_ten_cau_hinh').value = config.ten_cau_hinh || '';
    document.getElementById('modal_dang_mo').checked = !!config.dang_mo;
    document.getElementById('modal_muc_uu_tien').value = config.muc_uu_tien !== null ? config.muc_uu_tien : 1;
    document.getElementById('modal_mo_ta').value = config.ten_cau_hinh || '';
    document.getElementById('modal_timeout_he_thong_giay').value = config.timeout_he_thong_giay || 30;
    document.getElementById('modal_timeout_gui_ncc_giay').value = config.timeout_gui_ncc_giay || 25;
    document.getElementById('modal_thoi_gian_tra_ket_qua_giay').value = config.thoi_gian_tra_ket_qua_giay || 60;
    document.getElementById('modal_che_do_chay').value = config.che_do_chay || 'api';
    document.getElementById('modal_ket_thuc_cau_hinh').checked = !!config.ket_thuc_cau_hinh;
  }

  modal.hidden = false;
}

function closeRoutingModal() {
  document.getElementById('routingModal').hidden = true;
}
</script>
@endpush
