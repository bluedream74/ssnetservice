<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Goutte\Client;
use Illuminate\Support\Facades\Artisan;
// use Symfony\Component\Console\Output\ConsoleOutput;


class BatchCheckCommand1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:check1';

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
        $check_contact_form = config('values.check_contact_form');
        // $output = new ConsoleOutput();
        if($check_contact_form=="1"){
            //$limit = intval(config('values.mail_limit'));
			$limit = 40;
            $date=Carbon::now()->timezone('Asia/Tokyo');
          
            $companies = Company::where('check_contact_form',0)->get();
            
            $sent = 0;
            if(sizeof($companies)>0){
                foreach($companies as $company) {
                    try {
                        $sent++;
                        // $output->writeln("<info>sent count</info>".$sent);
                        Company::where('id',$company->id)->update(['check_contact_form'=>1]);
                        if(isset($company->contact_form_url)&&(!empty($company->contact_form_url))){
                            if ($sent >= $limit) {
                                return 0;
                            }else {
                                continue;
                            }
                        }else {
                            $topPageUrl = $this->getTopUrl($company->url);
                            $check_url = $this->checkTopContactForm($topPageUrl);
                            // $output->writeln("<info>env</info>".$check_url);
                            if(isset($check_url)&&($check_url)){
                                Company::where('id',$company->id)->update(['contact_form_url'=>$check_url]);
                            }else {
                                
                                if($this->checkSubContactForm($topPageUrl.'/contact')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/contact']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/contact.php')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/contact.php']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/contact.html')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/contact.html']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/inquiry')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/inquiry']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/inquiry.php')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/inquiry.php']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/inquiry.html')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/inquiry.html']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/form')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/form']);
                                    continue;
                                }
                                if($this->checkSubContactForm($topPageUrl.'/toiawase')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/toiawase']);
                                    continue;
                                }
                            }
                        }
                        
                    }catch (\Throwable $e) {
                        continue;
                    }
        
                    if ($sent >= $limit) return 0;
                }
            }else {
                $key = 'CHECK_CONTACT_FORM';
                $this->upsert($key, 0);
                Artisan::call('config:cache');
                Artisan::call('queue:restart');
                usleep(500);
            }
            return 0;
        }
        
        return 0;
    }

    private function upsert($key, $value)
    {
        $envFilePath = app()->environmentFilePath();
        $contents = file_get_contents($envFilePath);
        if (preg_match("/ /", $value)) {
            $value = '"'.$value.'"';
        }
        if (preg_match("/^{$key}=[^\n\r]*/m", $contents)) {
            file_put_contents($envFilePath, preg_replace("/^{$key}=[^\n\r]*/m", $key.'='.$value, $contents));
        } else {
            file_put_contents($envFilePath, $contents . "\n{$key}={$value}");
        }
    }

    
    private function getTopUrl($companyurl) {
        $topurl='';
        $url=explode('://',$companyurl);
        if(isset($url)){
            $topurl = explode('/',$url[1])[0];
            $topurl = $url[0].'://'.$topurl;
        }
        return $topurl;
    }
    private function checkTopContactForm($url) {
        $client = new Client();
        $crawler = $client->request('GET', $url);
        try {
            if($crawler->selectLink('お問い合わせ')->link()){
                return $crawler->selectLink('お問い合わせ')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try {
            if($crawler->selectLink('お問合せ')->link()){
                return $crawler->selectLink('お問合せ')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        
        try {
            if($crawler->selectLink('問い合わせ')->link()){
                return $crawler->selectLink('問い合わせ')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try {
            if($crawler->selectLink('問合せ')->link()){
                return $crawler->selectLink('問合せ')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try {
            if($crawler->selectLink('Contact')->link()){
                return $crawler->selectLink('Contact')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try {
            if($crawler->selectLink('CONTACT')->link()){
                return $crawler->selectLink('CONTACT')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try {
            if($crawler->selectLink('contact')->link()){
                return $crawler->selectLink('contact')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try {
            if($crawler->selectLink('お見積り・お問合せ')->link()){
                return $crawler->selectLink('お見積り・お問合せ')->link()->getUri();
            }
        }catch(\Throwable $e){
            
        }
        try{
            $form = $crawler->filter('form')->form()->all();
            if(isset($form)&&(!empty($form))){
                return $url;
            }
            $form = $crawler->selectButton('送信')->form()->all();
            if(isset($form)&&(!empty($form))){
                return $url;
            }
            return false;
            
        }catch (\Throwable $e) {
            return false;
        }
    }

    private function checkSubContactForm($url) {
        $client = new Client();
        $crawler = $client->request('GET', $url);
        try{
            $form = $crawler->filter('form')->form()->all();
            if(isset($form)&&(!empty($form))){
                return true;
            }
            $form = $crawler->selectButton('送信')->form()->all();
            if(isset($form)&&(!empty($form))){
                return true;
            }
            return false;
        }catch (\Throwable $e) {
            return false;
        }
    }
}
