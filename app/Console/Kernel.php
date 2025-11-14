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
        // Submit IndexNow sitemap daily at configured time (default: 3:00 AM)
        // Change the time in .env: INDEXNOW_SCHEDULE_TIME=03:00
        $scheduleTime = env('INDEXNOW_SCHEDULE_TIME', '03:00');
        $schedule->command('indexnow:submit')
            ->dailyAt($scheduleTime)
            ->withoutOverlapping()
            ->onFailure(function () {
                \Log::error('IndexNow scheduled submission failed');
            })
            ->onSuccess(function () {
                \Log::info('IndexNow scheduled submission completed successfully');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
