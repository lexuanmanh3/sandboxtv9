<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Danh sách Provider Drivers
    |--------------------------------------------------------------------------
    |
    | Khai báo mapping giữa mã driver/mã NCC và Provider Class tương ứng.
    | Khi tích hợp thêm NCC mới (ví dụ VNPay, MoMo, Gate...), chỉ cần tạo Class
    | implements NhaCungCapTopupInterface và khai báo thêm vào mảng này.
    |
    */
    'drivers' => [
        'appotapay' => \App\Services\Topup\AppotaPay\AppotaPayTopupProvider::class,
        // 'vnpay'  => \App\Services\Topup\VNPay\VNPayTopupProvider::class,
        // 'momo'   => \App\Services\Topup\MoMo\MoMoTopupProvider::class,
        // 'gate'   => \App\Services\Topup\Gate\GateTopupProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cấu hình Timeout mặc định
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'connect_timeout' => 10,
        'request_timeout' => 25,
    ],
];
