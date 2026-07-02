@extends('layout')

@section('content')
  <main class="main-content container">
    <section class="dashboard-card">
      <div class="section-heading">
        <h2>NẠP TIỀN ĐIỆN THOẠI</h2>
      </div>

      <div class="services-wrap">
        <div class="service-grid">
          <button class="service-item" type="button">
            <x-icon name="mobile_friendly" />
            <span>NẠP TIỀN ĐIỆN THOẠI</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="phone_iphone" />
            <span>NẠP TIỀN TRẢ SAU</span>
          </button>

          <button class="service-item" type="button">
            <x-icon name="cell_wifi" />
            <span>NẠP TOPUP DATA</span>
          </button>
        </div>
      </div>
    </section>
  </main>
@endsection
