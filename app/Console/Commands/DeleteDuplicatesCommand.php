<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeleteDuplicatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $companies = \App\Models\Company::select('url', 'id')->distinct()->pluck('url', 'id')->toArray();
        // foreach ($companies as $id => $company) {
        //     $parse = parse_url($company);
        //     $host = $parse['host'];

        //     \App\Models\Company::where('id', '!=', $id)->where('url', 'like', "%{$host}%")->delete();
        //     $this->info($host);
        // }
        return 0;
    }
}
