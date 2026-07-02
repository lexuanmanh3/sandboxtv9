@extends('layout')

@section('content')
  @php
    $currentUser = auth()->user();
    $canAccessFrontendTopup = $currentUser?->coQuyen('frontend.topup.access');
  @endphp

  <main class="main-content container">
    <section class="dashboard-card">
      <div class="quick-links">
        <a class="quick-link" href="#">
          <x-icon name="description" />
          <span>Thông tin tài khoản</span>
        </a>

        @if ($canAccessFrontendTopup)
          <a class="quick-link" href="{{ route('frontend.topup') }}">
            <x-icon name="payments" />
            <span>Nạp tiền tài khoản</span>
          </a>
        @endif

        <a class="quick-link" href="#">
          <x-icon name="sync_alt" />
          <span>Chuyển tiền đại lý</span>
        </a>

        <a class="quick-link" href="#">
          <x-icon name="history" />
          <span>Lịch sử giao dịch</span>
        </a>
      </div>

      <div class="section-heading">
        <h2>NẠP TIỀN - THANH TOÁN ĐƠN GIẢN CHỈ VỚI VÀI THAO TÁC</h2>
      </div>

      <div class="services-wrap">
        <div class="service-grid">
          @if ($canAccessFrontendTopup)
            <a class="service-item" href="{{ route('frontend.topup') }}">
              <x-icon name="mobile_friendly" />
              <span>NẠP TIỀN ĐIỆN THOẠI</span>
            </a>
          @endif

          <button class="service-item" type="button">
            <x-icon name="phone_iphone" />
            <span>NẠP TIỀN TRẢ SAU</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="cell_wifi" />
            <span>NẠP TOPUP DATA</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="credit_card" />
            <span>MUA THẺ ĐIỆN THOẠI</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="sports_esports" />
            <span>MUA THẺ GAME</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="sim_card" />
            <span>MUA THẺ DATA</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="account_balance_wallet" />
            <span>THANH TOÁN HÓA ĐƠN</span>
          </button>

          <div class="service-placeholder" aria-hidden="true"></div>
          <div class="service-placeholder" aria-hidden="true"></div>
        </div>
      </div>

      <section class="calendar-strip" aria-label="Lịch giao dịch">
        <div class="month-box">
          <span>Tháng 5</span>
          <strong>2026</strong>
        </div>

        <div class="calendar-days">
          <div class="day has-event">
            <span>Thứ 2</span>
            <strong>15</strong>
          </div>
          <div class="day has-event">
            <span>Thứ 3</span>
            <strong>16</strong>
          </div>
          <div class="day">
            <span>Thứ 4</span>
            <strong>17</strong>
          </div>
          <div class="day muted-event">
            <span>Thứ 5</span>
            <strong>18</strong>
          </div>
          <div class="day soft-bg">
            <span>Thứ 6</span>
            <strong>19</strong>
          </div>
          <div class="day soft-bg has-event">
            <span>Thứ 7</span>
            <strong>20</strong>
          </div>
          <div class="day sunday muted-event">
            <span>Chủ nhật</span>
            <strong>21</strong>
          </div>
        </div>
      </section>
    </section>
  </main>
@endsection
