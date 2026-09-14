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
        $schedule->command('nexus:scan')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('nexus:proactive')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('nexus:github-analyze --batch=25')
            ->everyMinute()
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
