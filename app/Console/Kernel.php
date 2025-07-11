<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send daily attendance summary every day at 6 PM
        $schedule->command('attendance:send-daily-summary')
                 ->dailyAt('18:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Send weekly attendance summary every Monday at 9 AM
        $schedule->command('attendance:send-weekly-summary')
                 ->weeklyOn(1, '09:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Clean up old notifications (older than 30 days)
        $schedule->command('model:prune', ['--model' => 'Illuminate\\Notifications\\DatabaseNotification'])
                 ->daily()
                 ->withoutOverlapping();

        // Database optimization
        $schedule->command('db:optimize')
                 ->weekly()
                 ->sundays()
                 ->at('02:00')
                 ->withoutOverlapping();

        // Cache warm-up
        $schedule->command('cache:warmup')
                 ->hourly()
                 ->withoutOverlapping();

        // Clean up old API logs
        $schedule->command('model:prune', ['--model' => 'App\\Models\\SecurityLog'])
                 ->daily()
                 ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}