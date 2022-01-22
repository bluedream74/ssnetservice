<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Config;

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
        $schedule->command("send:emails1")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails2")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails3")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails4")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails5")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails6")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails7")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails8")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails9")->everyFourMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails10")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("batch:check1")->everyFourMinutes()->runInBackground()->withoutOverlapping()->when(function (){
            return Config::get()->first()->checkContactForm;
          });
        $schedule->command("batch:check2")->everyFourMinutes()->runInBackground()->withoutOverlapping()->when(function (){
            return Config::get()->first()->checkContactForm;
          });
        // $schedule->command("batch:check3")->everyFourMinutes()->runInBackground()->withoutOverlapping()->when(function (){
        //     return Config::get()->first()->checkContactForm;
        //   });
        // $schedule->command("batch:check4")->everyFourMinutes()->runInBackground()->withoutOverlapping()->when(function (){
        //     return Config::get()->first()->checkContactForm;
        //   });
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
