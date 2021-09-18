<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use Goutte\Client;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Illuminate\Support\Carbon;

class SendEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:emails';

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
        $limit = intval(config('values.mail_limit'));

        $date=Carbon::now()->timezone('Asia/Tokyo');
      
        $contacts = Contact::whereHas('reserve_companies')->get();
        $sent = 0;
        foreach ($contacts as $contact) {
            foreach ($contact->reserve_companies as $companyContact) {
                
                $company = $companyContact->company;
                
                try {
                    $client = new Client();
                    if($company->contact_form_url=='')continue;
                    $crawler = $client->request('GET', $company->contact_form_url);
                    if(strpos($crawler->text(),"営業お断り")!==false)continue;
                    $form='';
                    $form = $crawler->filter('form')->form();
                    $data = [];$captcha_sitekey_check=false;$wp=false;
    
                    $captcha_sitekey = $crawler->filter('.g-recaptcha')->extract(['data-sitekey']);
                    if(isset($captcha_sitekey) && !empty($captcha_sitekey)){
                        $captcha_sitekey = $captcha_sitekey[0];$captcha_sitekey_check=true;
                    }
                    $sitekey='';
                    $sitekey = $crawler->filter('script#wpcf7-recaptcha-js-extra');
                    if(isset($sitekey) && !empty($sitekey)){
                        try{
                            $sitekey = $sitekey->text();
                            $key_position = strpos($sitekey,'sitekey');
                            if(isset($key_position)){
                                $captcha_sitekey = substr($sitekey,$key_position+10,40);$captcha_sitekey_check=true;$wp=true;
                            }
                        }catch (\Throwable $e) {
                            $myfile = fopen("error.txt", "a") or die("Unable to open file!");
                            fwrite($myfile, $e->getMessage()."   -------------start----------".$date."\r\n");
                            fclose($myfile);
                        }
                       
                    }
                    if($captcha_sitekey_check){
                        $api = new NoCaptchaProxyless();
                        $api->setVerboseMode(true);
                        //your anti-captcha.com account key
                        $api->setKey(config('anticaptcha.key'));
                         
                        //recaptcha key from target website
                        $api->setWebsiteURL($company->contact_form_url);
                        $api->setWebsiteKey($captcha_sitekey);
                        
                        if (!$api->createTask()) {
                            continue;                            
                        }
                        $taskId = $api->getTaskId();
                       
                        if (!$api->waitForResult()) {
                            continue;
                        } else {
                            $recaptchaToken = $api->getTaskSolution();
                            
                            if($wp){
                                $data['_wpcf7_recaptcha_response'] = $recaptchaToken; //g-recaptcha-response
                            } else {
                                $data['g-recaptcha-response'] = $recaptchaToken; //g-recaptcha-response
                            }
                        }
                    }
                    
                    foreach($form->all() as $key =>$val){
                       
                        try{
                            $type = $val->getType();
                            if($type == 'select'){
                                $data[$key] = $form[$key]->getOptions()[1]['value'];
                            }else if($type =='radio') {
                                $data[$key] = $form[$key]->getOptions()[0]['value'];
                            }else if($type =='checkbox') {
                                $data[$key] = $form[$key]->getOptions()[0]['value'];
                            }
                        }catch(\Throwable $e){
                            continue;
                        }
                    }
                    if(!empty($form->getValues())){
                        $name_count = 0;$kana_count = 0;$postal_count = 0;$phone_count = 0;
                        foreach($form->getValues() as $key => $value) {
                            if(isset($data[$key]))continue;
                            if(($value!=='' || strpos($key,'wpcf7')!==false)&&($key!=='_wpcf7_recaptcha_response')){
                                $data[$key] = $value;
                            }else {
                               if((strpos($key,'nam')!==false || strpos($key,'お名前')!==false  )&& !strpos($key,'kana')!==false){
                                   $name_count++;
                               }
                               if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false){
                                    $kana_count++;
                               }
                               if(strpos($key,'post')!==false || strpos($key,'郵便番号')!==false){
                                   $postal_count++;
                               }
                               if(strpos($key,'tel')!==false || strpos($key,'電話番号')!==false){
                                   $phone_count++;
                               }
                            }
                        }

                        foreach($form->getValues() as $key => $val) {
                            if(($val!=='' || strpos($key,'wpcf7')!==false)) continue;

                            if($name_count==1 && (strpos($key,'nam')!==false || strpos($key,'お名前')!==false ) && !strpos($key,'kana')!==false){
                                $data[$key] = $contact->surname.' '.$contact->lastname;


                            }else if($name_count==2 && (strpos($key,'nam')!==false || strpos($key,'お名前')!==false ) && !strpos($key,'kana')!==false){
                                if(!isset($name_count_check)){
                                    $data[$key] = $contact->surname;
                                }else {
                                    $data[$key] = $contact->lastname;
                                }
                                $name_count_check=1;
                            }
                            if($kana_count==1 && (strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false)){
                                $data[$key] = $contact->fu_surname.' '.$contact->fu_lastname;
                            }else if($kana_count==2 && (strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false)){
                                if(!isset($kana_count_check)){
                                    $data[$key] = $contact->fu_surname;
                                }else {
                                    $data[$key] = $contact->fu_lastname;
                                }
                                $kana_count_check=1;
                            }
    
                            if(strpos($key,'company')!==false || strpos($key,'cn')!==false ) {
                                $data[$key] = $contact->company;
                            }

                            if($postal_count==1 && (strpos($key,'post')!==false || strpos($key,'郵便番号')!==false)){
                                $data[$key] = $contact->postalCode1.' '.$contact->postalCode2;
                            }else if($postal_count==2 && (strpos($key,'post')!==false || strpos($key,'郵便番号')!==false)){
                                if(!isset($postal_count_check)){
                                    $data[$key] = $contact->postalCode1;
                                }else {
                                    $data[$key] = $contact->postalCode2;
                                }
                                $postal_count_check=1;
                            }
                           if(strpos($key,'mail')!==false || strpos($key, 'mail_confirm')!==false || strpos($key, 'ールアドレス')!==false) {
                               $data[$key] = $contact->email;
                           }
                           if(strpos($key,'title')!==false ||(strpos($key,'text')!==false)) {
                                $data[$key] = $contact->title;
                            }
                           if(strpos($key,'textarea')!==false || strpos($key,'body')!==false || strpos($key,'content')!==false || strpos($key,'message')!==false ) {
 			       $content = str_replace('%company_name%', $company->name, $contact->content);

			       $content = nl2br($content);
                               $data[$key] = $content;
                              
                               $data[$key] .='  配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら';
                           }
                           if($phone_count ==1 && (strpos($key,'tel')!==false || strpos($key,'電話番号')!==false)) {
                                $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                            }else if($phone_count ==3 && (strpos($key,'tel')!==false || strpos($key,'電話番号')!==false)) {
                                if(!isset($phone_count_check)){
                                    $data[$key] = $contact->phoneNumber1;
                                    $phone_count_check=1;
                                }else if(isset($phone_count_check) && ($phone_count_check ==1)) {
                                    $data[$key] = $contact->phoneNumber2;
                                }else if(isset($phone_count_check) && ($phone_count_check ==2)) {
                                    $data[$key] = $contact->phoneNumber3;
                                }
                            }
                        }

                        foreach($form->getValues() as $key => $val) {
                          if(isset($data[$key]) &&($data[$key] !== "")){
                            continue;
                          } else {
                            $data[$key] = " ";
                          }
                        }

                        if(isset($data['g-recaptcha-response']) || isset($data['_wpcf7_recaptcha_response'])){
                            $crawler = $client->request($form->getMethod(), $form->getUri(), $data);
                        }else {
                            $form->setValues($data);
                            $crawler = $client->submit($form);
                        }

                        if(strpos($crawler->text(),"ありがとうございま")!==false){
                            $company->update([
                                'status'        => '送信済み'
                            ]);
                            $companyContact->update([
                                'is_delivered' => 2
                            ]);
                        }else if(strpos($crawler->text(),"失敗")!==false){
                            $company->update([
                                'status'        => '送信失敗'
                            ]);
                            $companyContact->update([
                                'is_delivered' => 1
                            ]);
                        }else {
                            $form='';
                            $form = $crawler->filter('form');
                            if(isset($form) && !empty($form)){
                                $form = $form->form();
                                $data = $form->getValues();
                                $form->setValues($data);
                                $crawler = $client->submit($form);

                                if(strpos($crawler->text(),"ありがとうございま")){
                                    $company->update([
                                        'status'        => '送信済み'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 2
                                    ]);
                            }else {
                                    $company->update([
                                        'status'        => '送信失敗'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 1
                                    ]);
                                }
                            }
                        }
                    }else {
                        $company->update([
                            'status'        => '送信失敗'
                        ]);
                        $companyContact->update([
                            'is_delivered' => 1
                        ]);
                    }
                }  
                catch (\Throwable $e) {
                    $myfile = fopen("company.txt", "a") or die("Unable to open file!");
                    fwrite($myfile, $e->getMessage()."error   -------------start----------".$date."\r\n");
                    fclose($myfile);
                    $company->update(['status' => '送信失敗']);
                    $companyContact->update([
                        'is_delivered' => 1
                    ]);
                }

                $sent++;

                if ($contact->is_confirmed == 0) { // Sending email to syt.iphone@gmail.com
                    try {
                        // \App\Jobs\SendMagazineEmailJob::dispatch("syt.iphone@gmail.com", new \App\Notifications\MailMagazineNotification($contact, "syt.iphone@gmail.com", $company->name), $company);
                        Mail::to("syt.iphone@gmail.com")
                            ->send(new \App\Mail\CustomEmail($contact, "syt.iphone@gmail.com", $company->name, $company));

                        $contact->update(['is_confirmed' => 1]);
                        sleep(4);
                    } catch (\Throwable $e) {
                        \Log::error("KKKKK:  " . $e->getMessage());
                    }
                }

                if ($sent >= $limit) return 0;
            }

            if ($sent >= $limit) return 0;
        }

        return 0;
    }
}
