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

        $schedule->command("send:emails11")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails12")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails13")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails14")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails15")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails16")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails17")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails18")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails19")->everyFourMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails20")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails21")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails22")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails23")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails24")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails25")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails26")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails27")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails28")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails29")->everyFourMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails30")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails31")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails32")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails33")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails34")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails35")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails36")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails37")->everyFiveMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails38")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

        $schedule->command("send:emails39")->everyFourMinutes()->runInBackground()->withoutOverlapping();
        $schedule->command("send:emails40")->everyFiveMinutes()->runInBackground()->withoutOverlapping();

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
