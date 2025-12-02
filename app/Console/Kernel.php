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
        // Daily notifications check at 08:00 AM
        $schedule->command('notifications:check-tool-calibration')
            ->dailyAt('08:00')
            ->timezone('Asia/Jakarta');
        
        $schedule->command('notifications:check-overdue-issues')
            ->dailyAt('09:00')
            ->timezone('Asia/Jakarta');
        
        // Weekly notifications check every Monday at 08:00 AM
        $schedule->command('notifications:check-monthly-target')
            ->weeklyOn(1, '08:00')
            ->timezone('Asia/Jakarta');
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
