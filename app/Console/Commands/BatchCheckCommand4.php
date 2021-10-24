<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Company;
use App\Models\User;
use App\Models\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Goutte\Client;
use Illuminate\Support\Facades\Artisan;
// use Symfony\Component\Console\Output\ConsoleOutput;


class BatchCheckCommand4 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:check4';

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
        $check_contact_form = Config::get()->first()->checkContactForm;
        // $output = new ConsoleOutput();
        if($check_contact_form == 1){
            //$limit = intval(config('values.mail_limit'));
			$offset = 60;
            $date=Carbon::now()->timezone('Asia/Tokyo');
          
            Company::whereNotNull('contact_form_url')->update(['check_contact_form'=>1]);

            $companies = Company::where('check_contact_form',0)->skip(3*$offset)->take($offset)->get();
            
            if(sizeof($companies)>0){
                foreach($companies as $company) {
                    try {
                        // $output->writeln("<info>sent count</info>".$sent);
                        Company::where('id',$company->id)->update(['check_contact_form'=>1]);
                        if(isset($company->contact_form_url)&&(!empty($company->contact_form_url))){
                            continue;
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
                                if($this->checkSubContactForm($topPageUrl.'/html/toiawase.html')){
                                    Company::where('id',$company->id)->update(['contact_form_url'=>$topPageUrl.'/html/toiawase.html']);
                                    continue;
                                }
                            }
                        }
                        
                    }catch (\Throwable $e) {
                        continue;
                    }
        
                }
            }else {
                Config::where('id',1)->update(array('checkContactForm'=>'0'));
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
        if(strpos($companyurl,"http")!==false){
            $url=explode('://',$companyurl);
            if(isset($url)){
                $topurl = explode('/',$url[1])[0];
                $topurl = $url[0].'://'.$topurl;
            }
        }else{
            $topurl = explode('/',$companyurl)[0];
            $topurl = "http://".$topurl;
        }
        return $topurl;
    }
    private function checkTopContactForm($url) {
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $contact_form_patterns = array(
            'お見積り・お問合せ',
            'お問合せ・サポート',
            'ホームページから問い合わせ',
            'お問い合わせ',
            'お問合せ',
            'お問合わせ',
            'お問い合せ',
            '問い合せ',
            '問い合わせ',
            '問合せ',
            'Contact',
            'CONTACT',
            'contact');
        foreach($contact_form_patterns as $pattern) {
            if(strpos($crawler->html(),$pattern)!==false){
                $patternStr = $pattern;
                $str = substr($crawler->html(),strpos($crawler->html(),$pattern)-10);
                $pos = strpos($str,'>');
                $pattern = substr($str,$pos);
                $pattern = substr($pattern,1);
                $pattern = substr($pattern,0,strpos($pattern,'<'));
                try {
                    if(empty($pattern)){
                        $pattern = $patternStr;
                    } 
                    if( strpos($pattern,$patternStr) !== false){

                    } else {
                        $pattern = $patternStr;
                    }
                    if($crawler->selectLink($pattern)->link()){
                        $link = $crawler->selectLink($pattern)->link()->getUri();
                        $jsPatterns = array('javascript','JavaScript');
                        foreach($jsPatterns as $js) {
                            if(strpos($link,$js) !== false) {
                               break;
                            }else {
                                return $link;
                            }
                        }
                    }
                }catch(\Throwable $e){
                    
                }
            }
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
