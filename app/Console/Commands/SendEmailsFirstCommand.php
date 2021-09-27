<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use Goutte\Client;
use LaravelAnticaptcha\Anticaptcha\ImageToText;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Output\ConsoleOutput;

class SendEmailsFirstCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:emailsFirst';

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
        $ProcessCount = intval(config('values.ProcessCount'));

        $date=Carbon::now()->timezone('Asia/Tokyo');

        $output = new ConsoleOutput();
        
        $contacts = Contact::whereHas('reserve_companies')->get();
        $sent = 0;
        foreach ($contacts as $contact) {

            foreach ($contact->reserve_companies as $companyContact) {
                
                $company = $companyContact->company;
                
                try {
                   
                    $client = new Client();
                    if($company->contact_form_url=='')continue;

                    $output->writeln($company->contact_form_url);

                    $crawler = $client->request('GET', $company->contact_form_url);
                    // file_put_contents('html.txt',$crawler->html());
                    if(strpos($crawler->text(),"営業お断り")!==false)continue;

                    try{
                        $form = $crawler->filter('form')->form();
                    }catch (\Throwable $e) {
                        $form = $crawler->selectButton('送信')->form();
                    }
                   
                    $data = [];
                    try {
                        if(strpos($crawler->html(),'api.js?render')!==false){
                            $key_position = strpos($crawler->html(),'api.js?render');
                            if(isset($key_position)){
                                $captcha_sitekey = substr($crawler->html(),$key_position+14,40);
                            }
                        }else if(strpos($crawler->html(),'changeCaptcha')!==false){
                            $key_position = strpos($crawler->html(),'changeCaptcha');
                            if(isset($key_position)){
                                $captcha_sitekey = substr($crawler->html(),$key_position+15,40);
                            }
                        }else if(strpos($crawler->html(),'wpcf7submit')!==false){
                            $key_position = strpos($crawler->html(),'wpcf7submit');
                            if(isset($key_position)){
                                $str = substr($crawler->html(),$key_position);
                                $captcha_sitekey = substr($str,strpos($str,'grecaptcha')+13,40);
                            }
                        }else if(strpos($crawler->text(),'sitekey')!==false){
                            $key_position = strpos($crawler->text(),'sitekey');
                            if(isset($key_position)){
                                if((substr($crawler->text(),$key_position+9,1)=="'"||(substr($crawler->text(),$key_position+9,1)=='"'))){
                                    $captcha_sitekey = substr($crawler->text(),$key_position+10,40);
                                }else if((substr($crawler->text(),$key_position+11,1)=="'"||(substr($crawler->text(),$key_position11,1)=='"'))){
                                    $captcha_sitekey = substr($crawler->text(),$key_position+12,40);
                                }
                            }
                        }
                        try{
                            $image = $crawler->selectImage('captcha')->image();
                            $imageurl = $image->getUri();
                        }catch(\Throwable $e){
                            
                        }
                        
                        if(isset($captcha_sitekey)){
                            
                            try{
                                $index = '#captchaImage'.$captcha_sitekey;
                                $imageurl = $crawler->filter($index)->image()->getUri();
                            }catch(\Throwable $e){
                                
                            }
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
                                foreach($form->all() as $key=>$val) {
                                    if(strpos($key,'wpcf7_recaptcha')!==false){
                                        $data['_wpcf7_recaptcha_response'] = $recaptchaToken;
                                    }else if(strpos($key,'g-recaptcha-response')!==false){
                                        $data['g-recaptcha-response'] = $recaptchaToken;
                                    }else if(strpos($key,'recaptcha_response')!==false){
                                        $data['recaptcha_response'] = $recaptchaToken;
                                    }
                                }
                            }
                        }
                        if(isset($imageurl)&&!empty($imageurl)){

                            $apiImage = new ImageToText();
                            $apiImage->setVerboseMode(true);
                                    
                            //your anti-captcha.com account key
                            $apiImage->setKey(config('anticaptcha.key'));

                            //setting file
                            $apiImage->setFile($imageurl);

                            if (!$apiImage->createTask()) {
                                continue;
                            }

                            $taskId = $apiImage->getTaskId();


                            if (!$apiImage->waitForResult()) {
                                continue;
                            } else {
                                $captchaText = $apiImage->getTaskSolution();
                                foreach($form->all() as $key=>$val) {
                                    if(strpos($key,'captcha-170')!==false){
                                        $data['captcha-170'] = $recaptchaToken;
                                    }else if(strpos($key,'captcha')!==false){
                                        $data['captcha'] = $recaptchaToken;
                                    }
                                }
                            }
                           
                        }
                    }catch(\Throwable $e){
                        file_put_contents('error.txt',$e->getMessage());

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
                            if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                $data[$key] = $value;
                            }else {
                               if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'namek')!==false){
                                    $kana_count++;
                               }else if((strpos($key,'nam')!==false || strpos($key,'お名前')!==false )){
                                    $name_count++;
                                }
                               if(strpos($key,'post')!==false || strpos($key,'郵便番号')!==false || strpos($key,'yubin')!==false || strpos($key,'zip')!==false){
                                   $postal_count++;
                               }
                               if(strpos($key,'tel')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false){
                                   $phone_count++;
                               }
                            }
                        }
                        $num_count=0;
                        foreach($form->getValues() as $key => $val) {
                            
                            if(($val!=='' || strpos($key,'wpcf7')!==false||strpos($key,'captcha')!==false)) {
                                if(strpos($val,'例')!==false){

                                }else{
                                    continue;
                                }
                            }
                            $num_count++;
                            
                            if($kana_count==1 && (strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false)){
                                $data[$key] = $contact->fu_surname.' '.$contact->fu_lastname;
                            }else if($kana_count==2 && (strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false)){
                                if(!isset($kana_count_check)){
                                    $data[$key] = $contact->fu_surname;
                                }else {
                                    $data[$key] = $contact->fu_lastname;
                                }
                                $kana_count_check=1;
                            }else if($name_count==1 && (strpos($key,'nam')!==false || strpos($key,'お名前')!==false )){
                                $data[$key] = $contact->surname.' '.$contact->lastname;
                            }else if($name_count==2 && (strpos($key,'nam')!==false || strpos($key,'お名前')!==false )){
                                if(!isset($name_count_check)){
                                    $data[$key] = $contact->surname;
                                }else {
                                    $data[$key] = $contact->lastname;
                                }
                                $name_count_check=1;
                            }
    
                            $emailTexts = array('company','cn','kaisha','cop','corp');
                            foreach($emailTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->company;
                                }
                            }

                            if($postal_count==1 && (strpos($key,'post')!==false || strpos($key,'yubin')!==false || strpos($key,'郵便番号')!==false|| strpos($key,'zip')!==false)){
                                $data[$key] = $contact->postalCode1.'-'.$contact->postalCode2;
                            }else if($postal_count==2 && (strpos($key,'post')!==false || strpos($key,'郵便番号')!==false)){
                                if(!isset($postal_count_check)){
                                    $data[$key] = $contact->postalCode1;
                                }else {
                                    $data[$key] = $contact->postalCode2;
                                }
                                $postal_count_check=1;
                            }

                            $emailTexts = array('mail','mail_confirm','ールアドレス','M_ADR');
                            foreach($emailTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->email;
                                }
                            }
                            $addressTexts = array('add');
                            foreach($addressTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->address;
                                }
                            }
                            $titleTexts = array('title','subject');
                            foreach($titleTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->title;
                                }
                            }

                            $messageTexts = array('textarea','body','content','comment','inquiry','note','message','MESSAGE','honbun','お問い合わせ内容','userData[お問い合わ内容]');
                            foreach($messageTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $content = str_replace('%company_name%', $company->name, $contact->content);
                                    $content = nl2br($content);
                                    $data[$key] = $content;
                                    $data[$key] .='  配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら';
                                }
                            }
                            $titleTexts = array('fax');
                            foreach($titleTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;
                                }
                            }
                           if($phone_count ==1 && (strpos($key,'tel')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false)) {
                                $data[$key] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;
                            }else if($phone_count ==3 && (strpos($key,'tel')!==false  || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false)) {
                                if(!isset($phone_count_check)){
                                    $data[$key] = $contact->phoneNumber1;
                                    $phone_count_check=1;
                                }else if(isset($phone_count_check) && ($phone_count_check ==1)) {
                                    $data[$key] = $contact->phoneNumber2;
                                }else if(isset($phone_count_check) && ($phone_count_check ==2)) {
                                    $data[$key] = $contact->phoneNumber3;
                                }
                            }
                            if(($num_count==2) && !isset($data[$key])){
                                $data[$key] = $contact->email;
                            }
                            if(($num_count==5) && !isset($data[$key])){
                                $content = str_replace('%company_name%', $company->name, $contact->content);
                                $content = nl2br($content);
                                $data[$key] = $content;
                                $data[$key] .='  配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら';
                            }
                        }

                        foreach($form->getValues() as $key => $val) {
                            if((isset($data[$key]) || strpos($key,'wpcf7')!==false ||strpos($key,'captcha')!==false||strpos($key,'url')!==false)) {
                                continue;
                            }else {
                                $data[$key] = "054";
                            }
                        }

                        $crawler = $client->request($form->getMethod(), $form->getUri(), $data);
                        // file_put_contents('error.txt',$crawler->html());
                            
                        if(strpos($crawler->html(),"有難うございま")!==false || strpos($crawler->html(),"送信されました")!==false ||strpos($crawler->html(),"&#12354;&#12426;&#12364;&#12392;&#12358;&#12372;&#12374;&#12356;")!==false||  strpos($crawler->html(),"完了")!==false){
                            $output->writeln("success");
                            $company->update([
                                'status'        => '送信済み'
                            ]);
                            $companyContact->update([
                                'is_delivered' => 2
                            ]);
                        }else if(strpos($crawler->html(),"失敗しま")!==false){
                            $output->writeln("failed");
                            $company->update([
                                'status'        => '送信失敗'
                            ]);
                            $companyContact->update([
                                'is_delivered' => 1
                            ]);
                        }else {
                            $form='';
                            // 送信する
                            try{
                                $form = $crawler->selectButton('送信する')->form();
                            }catch (\Throwable $e) {
                                $form = $crawler->filter('form')->form();

                            }
                            
                            if(isset($form) && !empty($form)){
                                
                                $crawler = $client->submit($form);

                                if(strpos($crawler->html(),"ありがとうございま")!==false|| strpos($crawler->html(),"有難うございま")!==false || strpos($crawler->html(),"送信されました")!==false || strpos($crawler->html(),"完了")!==false){
                                    $output->writeln("success");
                                    $company->update([
                                        'status'        => '送信済み'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 2
                                    ]);
                            }else {
                                    $output->writeln("failed");
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
                        $output->writeln("failed");
                        $company->update([
                            'status'        => '送信失敗'
                        ]);
                        $companyContact->update([
                            'is_delivered' => 1
                        ]);
                    }
                }  
                catch (\Throwable $e) {
                    $output->writeln("failed");
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
