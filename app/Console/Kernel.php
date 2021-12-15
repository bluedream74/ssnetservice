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
        $schedule->command("send:emailsFirst")->everyTwoMinutes()->runInBackground();
        $schedule->command("send:emailsSecond")->everyThreeMinutes()->runInBackground();
        $schedule->command("send:emailsThird")->everyFourMinutes()->runInBackground();
		$schedule->command("send:emailsFourth")->everyFiveMinutes()->runInBackground();
        $schedule->command("batch:check1")->everyFourMinutes()->runInBackground();
        $schedule->command("batch:check2")->everyFourMinutes()->runInBackground();
        $schedule->command("batch:check3")->everyFourMinutes()->runInBackground();
        $schedule->command("batch:check4")->everyFourMinutes()->runInBackground();
        // $schedule->command("reset:payment")->monthly()->runInBackground();
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
