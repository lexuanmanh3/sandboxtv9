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
