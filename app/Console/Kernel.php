<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command("send:emailsFirst")->everyMinute()->runInBackground();
        $schedule->command("send:emailsSecond")->everyMinute()->runInBackground();
        $schedule->command("send:emailsThird")->everyMinute()->runInBackground();
        $schedule->command("batch:check1")->everyMinute()->runInBackground();
        $schedule->command("batch:check2")->everyMinute()->runInBackground();
        $schedule->command("batch:check3")->everyMinute()->runInBackground();
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
