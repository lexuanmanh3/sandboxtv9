<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache store cho trạng thái bảo mật B2B
    |--------------------------------------------------------------------------
    |
    | Chống phát lại nonce (replay protection) và rate limit của B2B API là trạng thái
    | DÙNG CHUNG giữa các tiến trình PHP. Nếu dùng cache cục bộ theo máy (file/array),
    | khi chạy nhiều app server sau load balancer thì một nonce đã dùng ở server A
    | vẫn được chấp nhận ở server B, và rate limit bị nhân lên theo số server.
    |
    | Đặt B2B_SECURITY_CACHE_STORE=redis (hoặc memcached/database) trong production.
    | Chạy `php artisan b2b:health-check` để kiểm tra cấu hình này trước khi go-live.
    | Để trống = dùng cache store mặc định của ứng dụng.
    |
    */
    'security_cache_store' => env('B2B_SECURITY_CACHE_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Giới hạn tần suất theo IP (chưa xác thực)
    |--------------------------------------------------------------------------
    |
    | Lớp chặn flood đầu tiên, áp dụng TRƯỚC khi xác thực HMAC. Nhờ vậy một nguồn
    | gửi request rác với X-Client-Id ngẫu nhiên vẫn bị giới hạn, thay vì được
    | miễn trừ vì mỗi header tạo ra một "đại lý" khác nhau.
    |
    */
    'ip_rate_limit_per_minute' => (int) env('B2B_IP_RATE_LIMIT_PER_MINUTE', 300),

    /*
    |--------------------------------------------------------------------------
    | Kích thước body tối đa (byte)
    |--------------------------------------------------------------------------
    */
    'max_body_bytes' => (int) env('B2B_MAX_BODY_BYTES', 65536),

    /*
    |--------------------------------------------------------------------------
    | Khoảng thời gian tra cứu tối đa (ngày)
    |--------------------------------------------------------------------------
    |
    | Chặn truy vấn quét toàn bộ bảng don_hang bằng from_date=1970-01-01.
    |
    */
    'max_query_range_days' => (int) env('B2B_MAX_QUERY_RANGE_DAYS', 31),

];
