<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('provider:sync-products appotapay')->hourly()->withoutOverlapping();
        $schedule->command('topup:reconcile-pending')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('b2b:recover-outbox')->everyMinute()->withoutOverlapping();
        $schedule->command('b2b:retry-webhooks')->everyMinute()->withoutOverlapping();

        // Lưới an toàn vận hành: phát hiện khoản giữ treo, lệch sổ công nợ, tồn đọng outbox.
        // Lệnh trả mã thoát khác 0 khi có báo động — monitoring bắt mã thoát này để cảnh báo.
        // Lệnh CHỈ BÁO CÁO, tuyệt đối không tự sửa số dư hay tự giải phóng hạn mức.
        $schedule->command('b2b:health-check')->everyFifteenMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
