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

        // Provider GIẢ LẬP cho kiểm thử tích hợp B2B tại local.
        // Class tự từ chối khởi tạo nếu không phải local/testing hoặc thiếu cờ
        // TOPUP_FAKE_ENABLED=true, nên khai báo ở đây không mở đường cho giao dịch thật.
        'fake' => \App\Services\Topup\Fake\FakeTopupProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cấu hình Provider giả lập (chỉ dùng cho kiểm thử local)
    |--------------------------------------------------------------------------
    |
    | enabled: phải bật tường minh; mặc định tắt để không vô tình giả lập giao dịch.
    | default_scenario: kịch bản áp dụng khi không có ghi đè theo mã tham chiếu
    |                   và số điện thoại không khớp tiền tố điều khiển nào.
    | poll_success_after: số lần tra cứu trạng thái trước khi trả kết quả cuối
    |                     cho kịch bản pending_success / pending_fail.
    |
    */
    'fake' => [
        'enabled' => env('TOPUP_FAKE_ENABLED', false),
        'default_scenario' => env('TOPUP_FAKE_DEFAULT_SCENARIO', 'success'),
        'poll_success_after' => env('TOPUP_FAKE_POLL_SUCCESS_AFTER', 2),
        'balance' => env('TOPUP_FAKE_BALANCE', '999999999'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chốt an toàn cho giao dịch thật
    |--------------------------------------------------------------------------
    |
    | Khi false, mọi Provider thật (AppotaPay...) sẽ từ chối gọi ra ngoài.
    | Dùng để bảo đảm phiên kiểm thử local không thể phát sinh giao dịch thật,
    | kể cả khi cấu hình NCC trỏ nhầm sang tài khoản production.
    |
    */
    'allow_real_calls' => env('TOPUP_ALLOW_REAL_CALLS', true),

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
