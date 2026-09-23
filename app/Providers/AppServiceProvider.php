<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('path.public', function () {
            return base_path('public');
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Lưới an toàn B2B: phát hiện sớm schema thiếu để tránh INTERNAL_ERROR mơ hồ.
        // - Không áp dụng khi chạy artisan (tránh đệ quy) hoặc ở môi trường local testing.
        // - Cache kết quả 1 giờ để không query DB mỗi request.
        if (!$this->app->runningInConsole()
            && app()->environment('production')
            && !cache()->has('b2b:schema_check')) {
            $missing = $this->kiemTraSchemaB2b();
            if (!empty($missing)) {
                \Illuminate\Support\Facades\Log::critical(
                    'B2B Schema thiếu các bảng sau: ' . implode(', ', $missing)
                    . ' — Chạy: php artisan migrate --force'
                );
            }
            cache()->put('b2b:schema_check', $missing, now()->addHour());
        }
    }

    /**
     * @return array<int, string> Danh sách bảng B2B còn thiếu.
     */
    protected function kiemTraSchemaB2b(): array
    {
        $required = [
            'b2b_order_outbox',
            'b2b_idempotency_records',
            'webhook_outbox',
            'lich_su_gui_webhook',
            'khoan_giu_han_muc',
            'so_phat_sinh_cong_no',
        ];
        $missing = [];
        foreach ($required as $t) {
            if (!\Illuminate\Support\Facades\Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }
        return $missing;
    }
}
