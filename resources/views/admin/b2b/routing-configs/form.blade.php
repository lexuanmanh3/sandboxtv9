@extends('admin.layout')

@section('title', isset($routing_config) ? 'tv9tech - Cập nhật tuyến dịch vụ' : 'tv9tech - Thêm tuyến dịch vụ mới')

@push('styles')
  <link rel="stylesheet" href="{{ asset('backend/css/admin-accounts.css') }}">
  <style>
    .account-form-grid input[type="checkbox"] {
      width: 18px !important;
      height: 18px !important;
      min-height: 18px !important;
      max-height: 18px !important;
      flex: 0 0 18px !important;
      accent-color: var(--admin-brand-600) !important;
      cursor: pointer !important;
      margin: 0 !important;
      padding: 0 !important;
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
        <a href="{{ route('admin.b2b.routing-configs.index') }}">Cấu hình tuyến dịch vụ</a>
        <span>/</span>
        <strong>{{ isset($routing_config) ? 'Cập nhật' : 'Thêm mới' }}</strong>
      </nav>
      <h1>{{ isset($routing_config) ? 'Cập nhật tuyến dịch vụ #' . $routing_config->id : 'Thêm mới tuyến dịch vụ' }}</h1>
      <p>Thiết lập thứ tự ưu tiên và luồng định tuyến đơn hàng sang Nhà cung cấp.</p>
    </div>

    <div class="account-actions">
      <a href="{{ route('admin.b2b.routing-configs.index') }}" class="account-btn account-btn--muted">
        <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
        <span>Quay lại danh sách</span>
      </a>
    </div>
  </header>

  @if ($errors->any())
    <section class="account-alerts" aria-live="polite">
      <div class="account-alert account-alert--danger">
        Vui lòng kiểm tra lại các trường thông tin: {{ $errors->first() }}
      </div>
    </section>
  @endif

  <section class="account-card" style="max-width: 920px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);">
    <div style="padding: 20px 24px; border-bottom: 1px solid var(--admin-slate-200);">
      <h2 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--admin-slate-900);">
        {{ isset($routing_config) ? 'Thông tin cấu hình tuyến #' . $routing_config->id : 'Thiết lập tuyến dịch vụ mới' }}
      </h2>
      <p style="margin: 4px 0 0; font-size: 13px; color: var(--admin-slate-500);">
        Xác định dịch vụ, sản phẩm và NCC đích khi nhận đơn từ các Đại lý API.
      </p>
    </div>

    <form action="{{ isset($routing_config) ? route('admin.b2b.routing-configs.update', $routing_config->id) : route('admin.b2b.routing-configs.store') }}" method="POST">
      @csrf
      @if(isset($routing_config))
        @method('PUT')
      @endif

      <div class="account-form-grid">
        <label class="account-form-grid__full">
          <span>Tên tuyến / Ghi chú <strong style="color: #ef4444;">*</strong></span>
          <input type="text" name="mo_ta" value="{{ old('mo_ta', $routing_config->ten_cau_hinh ?? '') }}" placeholder="Ví dụ: Tuyến Viettel chính qua AppotaPay" required>
          <span class="account-field-note">Tên hiển thị giúp bạn dễ dàng nhận biết và quản lý luồng chạy.</span>
          @error('mo_ta') <span class="account-field-error">{{ $message }}</span> @enderror
        </label>

        <label>
          <span>Đại lý áp dụng</span>
          <select name="dai_ly_api_id">
            <option value="">-- Áp dụng cho tất cả đại lý (Tuyến chung) --</option>
            @foreach($partners as $partner)
              <option value="{{ $partner->id }}" @selected(old('dai_ly_api_id', $routing_config->dai_ly_ap_dung_id ?? '') == $partner->id)>
                {{ $partner->ten_dai_ly_api }} ({{ $partner->ma_dai_ly_api }})
              </option>
            @endforeach
          </select>
          <span class="account-field-note">Nếu để trống, tuyến sẽ là tuyến mặc định chung cho toàn hệ thống.</span>
          @error('dai_ly_api_id') <span class="account-field-error">{{ $message }}</span> @enderror
        </label>

        <label>
          <span>Chế độ chạy</span>
          <select name="che_do_chay">
            <option value="api" @selected(old('che_do_chay', $routing_config->che_do_chay ?? 'api') === 'api')>Kết nối API Nhà cung cấp</option>
            <option value="kho_the" @selected(old('che_do_chay', $routing_config->che_do_chay ?? '') === 'kho_the')>Kho thẻ nội bộ</option>
            <option value="thu_cong" @selected(old('che_do_chay', $routing_config->che_do_chay ?? '') === 'thu_cong')>Xử lý thủ công</option>
          </select>
          <span class="account-field-note">Hình thức xuất hàng hoặc xử lý đơn khi chạy qua tuyến này.</span>
        </label>

        <label>
          <span>Dịch vụ <strong style="color: #ef4444;">*</strong></span>
          <select name="dich_vu_id" required>
            <option value="">-- Chọn dịch vụ --</option>
            @foreach($services as $service)
              <option value="{{ $service->id }}" @selected(old('dich_vu_id', $routing_config->dich_vu_id ?? '') == $service->id)>
                {{ $service->ten_dich_vu }}
              </option>
            @endforeach
          </select>
          @error('dich_vu_id') <span class="account-field-error">{{ $message }}</span> @enderror
        </label>

        <label>
          <span>Sản phẩm cụ thể</span>
          <select name="san_pham_id">
            <option value="">-- Áp dụng cho toàn bộ dịch vụ --</option>
            @foreach($products as $product)
              <option value="{{ $product->id }}" @selected(old('san_pham_id', $routing_config->san_pham_id ?? '') == $product->id)>
                {{ $product->ten_san_pham }}
              </option>
            @endforeach
          </select>
          <span class="account-field-note">Chọn sản phẩm riêng hoặc để trống để áp dụng chung toàn dịch vụ.</span>
          @error('san_pham_id') <span class="account-field-error">{{ $message }}</span> @enderror
        </label>

        <label>
          <span>Nhà cung cấp (NCC) đích <strong style="color: #ef4444;">*</strong></span>
          <select name="nha_cung_cap_id" required>
            <option value="">-- Chọn Nhà cung cấp --</option>
            @foreach($providers as $provider)
              <option value="{{ $provider->id }}" @selected(old('nha_cung_cap_id', $routing_config->nha_cung_cap_id ?? '') == $provider->id)>
                {{ $provider->ten_nha_cung_cap }}
              </option>
            @endforeach
          </select>
          @error('nha_cung_cap_id') <span class="account-field-error">{{ $message }}</span> @enderror
        </label>

        <label>
          <span>Mức ưu tiên <strong style="color: #ef4444;">*</strong></span>
          <input type="number" name="muc_uu_tien" value="{{ old('muc_uu_tien', $routing_config->muc_uu_tien ?? 1) }}" min="1" required>
          <span class="account-field-note">Số càng nhỏ càng ưu tiên gọi trước (1 là ưu tiên cao nhất).</span>
          @error('muc_uu_tien') <span class="account-field-error">{{ $message }}</span> @enderror
        </label>

        <label>
          <span>Trạng thái tuyến</span>
          <select name="trang_thai">
            <option value="ACTIVE" @selected(old('trang_thai', isset($routing_config) ? ($routing_config->dang_mo ? 'ACTIVE' : 'PAUSED') : 'ACTIVE') === 'ACTIVE')>Hoạt động</option>
            <option value="PAUSED" @selected(old('trang_thai', isset($routing_config) ? (!$routing_config->dang_mo ? 'PAUSED' : 'ACTIVE') : '') === 'PAUSED')>Tạm dừng</option>
          </select>
        </label>

        <div class="account-form-grid__full" style="margin-top: 4px;">
          <label style="display: flex !important; align-items: center; gap: 10px; cursor: pointer; user-select: none; background: #fef2f2; border: 1px solid #fee2e2; padding: 12px 16px; border-radius: 8px;">
            <input type="hidden" name="ket_thuc_cau_hinh" value="0">
            <input type="checkbox" name="ket_thuc_cau_hinh" value="1" @checked(old('ket_thuc_cau_hinh', $routing_config->ket_thuc_cau_hinh ?? false))>
            <span>
              <strong style="color: #991b1b; font-size: 13px; display: block;">Kết thúc luồng nếu tuyến này thất bại chắc chắn.</strong>
              <span style="color: #b91c1c; font-size: 12px; font-weight: normal;">Nếu chọn, hệ thống sẽ dừng luôn và báo thất bại, không tiếp tục thử qua các NCC fallback dự phòng khác.</span>
            </span>
          </label>
        </div>
      </div>

      <footer style="padding: 16px 24px; border-top: 1px solid var(--admin-slate-200); background: var(--admin-slate-50); display: flex; align-items: center; justify-content: flex-end; gap: 12px; border-radius: 0 0 12px 12px;">
        <a href="{{ route('admin.b2b.routing-configs.index') }}" class="account-btn account-btn--muted">Hủy bỏ</a>
        <button type="submit" class="account-btn account-btn--primary">
          <svg class="account-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="width: 16px; height: 16px;">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
          <span>{{ isset($routing_config) ? 'Cập nhật tuyến' : 'Lưu cấu hình' }}</span>
        </button>
      </footer>
    </form>
  </section>
</section>
@endsection
