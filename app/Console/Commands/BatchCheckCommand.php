<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Admin\DashboardController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Goutte\Client;


class BatchCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:check';

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
        if($check_contact_form=="1"){
            $limit = intval(config('values.mail_limit'));

            $date=Carbon::now()->timezone('Asia/Tokyo');
          
            $companies = Company::where('check_contact_form',0)->get();
            
            $sent = 0;
            if(sizeof($companies)>0){
                foreach($companies as $company) {
                    try {
                        $sent++;
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
                            }
                        }
                        
                    }catch (\Throwable $e) {
                        $myfile = fopen("company.txt", "a") or die("Unable to open file!");
                        fwrite($myfile, $e->getMessage()."error   -------------start----------".$date."\r\n");
                        fclose($myfile);
                        continue;
                    }
        
                    if ($sent >= $limit) return 0;
                }
            }else {

            }
            return 0;
        }
        
        return 0;
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
        if($crawler->selectLink('お問い合わせ')){
            return $crawler->selectLink('お問い合わせ')->link()->getUri();
        }
        if($crawler->selectLink('お問合せ')){
            return $crawler->selectLink('お問合せ')->link()->getUri();
        }
        if($crawler->selectLink('問い合わせ')){
            return $crawler->selectLink('問い合わせ')->link()->getUri();
        }
        if($crawler->selectLink('問合せ')){
            return $crawler->selectLink('問合せ')->link()->getUri();
        }
        $form = $crawler->filter('form input');
        try{
            if(isset($form)&&(!empty($form->getNode(0)))){
                return $url;
            }else{
                return false;
            }
        }catch (\Throwable $e) {
            return false;
        }
    }

    private function checkSubContactForm($url) {
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $form = $crawler->filter('form');
        if(isset($form)&&(!empty($form->getNode(0)))){
            return true;
        }else{
            return false;
        }
    }
}
