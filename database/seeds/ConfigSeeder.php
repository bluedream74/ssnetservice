<?php

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        DB::table('config')->truncate();

        DB::table('config')->insert([
            [
                'start'             => \Carbon\Carbon::createFromTime('9','0','0')->toDateTimeString(),
                'end'               => \Carbon\Carbon::createFromTime('21','0','0')->toDateTimeString(),
                'mailLimit'         => 55,
                'checkContactForm'  => '0',
                'registerUrl'       => '0',
                'plan'              => 'price_1K3wuGLwiZkAtY2DIUb3WrRN',
            ]
        ]);
    }
}
