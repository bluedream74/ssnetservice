<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use Goutte\Client;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Illuminate\Support\Carbon;

class SendEmailsThirdCommand extends Command
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

        $date=Carbon::now()->timezone('Asia/Tokyo');
      
        $contacts = Contact::whereHas('reserve_companies')->get();
        $sent = 0;
        foreach ($contacts as $contact) {
            $count = 0;
            $companyContacts = $contact->companies()->where('is_delivered', 0)->skip(0)->take(10)->get();
            foreach ($companyContacts as $companyContact) {
                    
                $company = $companyContact->company;
                
                try {
                    $data = [];
                    $client = new Client();
                    if($company->contact_form_url=='')continue;
                    // if(strpos($company->url,"https://apptime.co.jp")!==false || strpos($company->url,"https://www.amr.co.jp")!==false){
                        // $postUrl = "https://apptime.co.jp/mail.php";
                        // $data['cmd'] = 'contactSend';
                        // $data['contact_name'] = $contact->surname.' '.$contact->lastname;
                        // $data['contact_affili'] = $contact->company;
                        // $data['contact_email'] = $contact->email;
                        // $data['contact_tel'] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;
                        // $data['contact_text'] = $contact->company;
                        // $content = str_replace('%company_name%', $company->name, $contact->content);
                        // $data['contact_text'] = $content;
                        // $data['contact_text'] .='  配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら';
                        // $crawler = $client->request('POST', $postUrl, $data);
                    //     $company->update([
                    //         'status'        => '送信済み'
                    //     ]);
                    //     $companyContact->update([
                    //         'is_delivered' => 2
                    //     ]);
                    // }
                    
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
                        // try{
                        //     $image = $crawler->selectImage('captcha')->image();
                        //     $imageurl = $image->getUri();
                        // }catch(\Throwable $e){
                            
                        // }
                        if((strpos($key,'wpcf7_recaptcha')!==false) || (strpos($key,'g-recaptcha-response')!==false) || (strpos($key,'recaptcha_response')!==false)) {
                            if(isset($captcha_sitekey)){
                            
                                // try{
                                //     $index = '#captchaImage'.$captcha_sitekey;
                                //     $imageurl = $crawler->filter($index)->image()->getUri();
                                // }catch(\Throwable $e){
                                    
                                // }
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
                        }
                        
                        // if(isset($imageurl)&&!empty($imageurl)){

                        //     $apiImage = new ImageToText();
                        //     $apiImage->setVerboseMode(true);
                                    
                        //     //your anti-captcha.com account key
                        //     $apiImage->setKey(config('anticaptcha.key'));

                        //     //setting file
                        //     $apiImage->setFile($imageurl);
                        //     $imageurl = "";
                        //     if (!$apiImage->createTask()) {
                        //         continue;
                        //     }

                        //     $taskId = $apiImage->getTaskId();


                        //     if (!$apiImage->waitForResult()) {
                        //         continue;
                        //     } else {
                        //         $captchaText = $apiImage->getTaskSolution();
                        //         foreach($form->all() as $key=>$val) {
                        //             if(strpos($key,'captcha-170')!==false){
                        //                 $data['captcha-170'] = $recaptchaToken;
                        //             }else if(strpos($key,'captcha')!==false){
                        //                 $data['captcha'] = $recaptchaToken;
                        //             }
                        //         }
                        //     }
                            
                        // }
                    }catch(\Throwable $e){
                        // file_put_contents('error.txt',$e->getMessage());

                    }
                    

                    if(!empty($form->getValues())){
                        $name_count = 0;$kana_count = 0;$postal_count = 0;$phone_count = 0;
                        foreach($form->getValues() as $key => $value) {
                            $emailTexts = array('company','cn','kaisha','cop','corp','会社名');
                            foreach($emailTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->company;continue;
                                }
                            }

                            $addressTexts = array('ご住所');
                            foreach($addressTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->address;continue;
                                }
                            }

                            $titleTexts = array('title','subject','件名');
                            foreach($titleTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->title;break;
                                }
                            }

                            $urlTexts = array('URL','url');
                            foreach($urlTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->homepageUrl;break;
                                }
                            }

                            $messageTexts = array('textarea','body','content','comment','naiyo','bikou','detail','inquiry','note','message','MESSAGE','honbun','youken','内容','備考欄','詳細','contact');
                            foreach($messageTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $content = str_replace('%company_name%', $company->name, $contact->content);
                                    $data[$key] = $content;
                                    $data[$key] .=PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .'※※※※※※※※'.PHP_EOL .'配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら'.PHP_EOL.'※※※※※※※※';break;
                                }
                            }
                            $titleTexts = array('fax');
                            foreach($titleTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;break;
                                }
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
                    
                    $compPatterns = array('会社名','企業名','貴社名','御社名','法人名','団体名','機関名','屋号','組織名','屋号','お店の名前');
                    foreach($compPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $pos = strpos($str,'name=');
                            if($pos > 3000) {
                                continue;
                            }else {
                                $nameStr = substr($str,$pos);
                                $nameStr = substr($nameStr,6);
                                $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                    break;
                                }else {
                                    $data[$nameStr] = $contact->company;
                                    break;
                                }
                                
                            }
                            
                        }
                    }

                    if(!empty($form->getValues())){
                        $name_count = 0;$kana_count = 0;$postal_count = 0;$phone_count = 0;
                        foreach($form->getValues() as $key => $value) {
                            if(isset($data[$key])&&(!empty($data[$key])))continue;
                            if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                $data[$key] = $value;
                            }else {
                                if(strpos($key,'セイ')!==false){
                                    $data[$key] = $contact->fu_surname;
                                }else if(strpos($key,'メイ')!==false){
                                    $data[$key] = $contact->fu_lastname;
                                }else if(strpos($key,'姓')!==false){
                                    $data[$key] = $contact->surname;
                                }else if((strpos($key,'名')!==false)&&(strpos($key,'名前')===false)&&(strpos($key,'氏名')===false)){
                                    $data[$key] = $contact->lastname;
                                }
                            }
                        }
                    }
                    $nonPatterns = array('部署');
                    foreach($nonPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $data[$nameStr] = 'なし';
                                break;
                            }
                            
                        }
                    }
                    if(!empty($form->getValues())){
                        $name_count = 0;$kana_count = 0;$postal_count = 0;$phone_count = 0;
                        foreach($form->getValues() as $key => $value) {
                            if(isset($data[$key])&&(!empty($data[$key])))continue;
                            if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                $data[$key] = $value;
                            }else {
                                
                                if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'namek')!==false ){
                                    $kana_count++;
                                }else if((strpos($key,'nam')!==false || strpos($key,'名前')!==false || strpos($key,'氏名')!==false)){
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
                    }
                    $namePatterns = array('名前','氏名','担当者','差出人','ネーム');
                    foreach($namePatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-10);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $name = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$name]) && !empty($data[$name])){
                                break;
                            }else {
                                if($name_count==2){
                                    $data[$name] = $contact->surname;
                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    $data[$name] = $contact->lastname;
                                    break;
                                }else {
                                    $data[$name] = $contact->surname.' '.$contact->lastname;
                                    break;
                                }
                            }
                        }
                    }
                    $namePatterns = array('郵便番号');
                    foreach($namePatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $name = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$name]) && !empty($data[$name])){
                                break;
                            }else {
                                if($postal_count==2){
                                    $data[$name] = $contact->postalCode1;
                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    $data[$name] = $contact->postalCode2;
                                    break;
                                }else {
                                    $data[$name] = $contact->postalCode1."-".$contact->postalCode2;
                                    break;
                                }
                            }
                        }
                    }
                    $namePatterns = array('ふりがな','フリガナ','カナ');
                    foreach($namePatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $name = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$name]) && !empty($data[$name])){
                                break;
                            }else {
                                if($kana_count==2){
                                    $data[$name] = $contact->fu_surname;
                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    $data[$name] = $contact->fu_lastname;
                                    break;
                                }else {
                                    $data[$name] = $contact->fu_surname.' '.$contact->fu_lastname;
                                    break;
                                }
                            }
                        }
                    }
                    
                    $addPatterns = array('住所','所在地','市区','番地','町名');
                    foreach($addPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            if(strpos($str,'input')){
                                $nameStr = substr($str,strpos($str,'name='));
                                $nameStr = substr($nameStr,6);
                                $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                    break;
                                }else {
                                    $data[$nameStr] = $contact->address;
                                    break;
                                }
                            }
                        }
                    }
                    $addPatterns = array('URL');
                    foreach($addPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $data[$nameStr] = $contact->homepageUrl;
                                break;
                            }
                        }
                    }
                    $mailPatterns = array('メールアドレス','Mail アドレス');
                    foreach($mailPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $data[$nameStr] = $contact->email;
                                break;
                            }
                        }
                    }
                    $nonPatterns = array('都道府県');
                    foreach($nonPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $data[$nameStr] = $contact->area;
                                break;
                            }
                            
                        }
                    }
                    $phonePatterns = array('電話番号','携帯電話','連絡先','TEL','Phone');
                    foreach($phonePatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $name = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$name]) && !empty($data[$name])){
                                break;
                            }else {
                                if($phone_count==3){
                                    $data[$name] = $contact->phoneNumber1;
                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    $data[$name] = $contact->phoneNumber2;
    
                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    $data[$name] = $contact->phoneNumber3;
                                    break;
                                }else {
                                    $data[$name] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;
                                    break;
                                }
                            }
                        }
                    }
                    $titlePatterns = array('件名','Title','Subject','題名','用件名');
                    foreach($titlePatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $data[$nameStr] = $contact->title;
                                break;
                            }
                        }
                    }

                    
                    $nonPatterns = array('年齢');
                    foreach($nonPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $data[$nameStr] = 35;
                                break;
                            }
                            
                        }
                    }

                    
                    
                    $contentPatterns = array('ご相談内容','ご質問','お問い合わせ内容','詳しい内容','備考','要望','詳細','概要','内容');
                    foreach($contentPatterns as $val) {
                        if(strpos($crawler->html(),$val)!==false) {
                            $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                            $nameStr = substr($str,strpos($str,'name='));
                            $nameStr = substr($nameStr,6);
                            $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                break;
                            }else {
                                $content = str_replace('%company_name%', $company->name, $contact->content);
                                $data[$nameStr] = $content;
                                $data[$key] .=PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .'※※※※※※※※'.PHP_EOL .'配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら'.PHP_EOL.'※※※※※※※※';break;
                            }
                        }
                    }
                    
                    
                    if(!empty($form->getValues())){
                        $kana_count_check = 0;$name_count_check = 0;$phone_count_check = 0;$postal_count_check = 0;
                        foreach($form->getValues() as $key => $val) {
                            if(isset($data[$key])&&(!empty($data[$key])))continue;
                            if(($val!=='' || strpos($key,'wpcf7')!==false||strpos($key,'captcha')!==false)) {
                                if(strpos($val,'例')!==false){

                                }else{
                                    continue;
                                }
                            }
                            if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'namek')!==false){
                                if($kana_count == 1){
                                    $data[$key] = $contact->fu_surname.' '.$contact->fu_lastname;continue;
                                }else if($kana_count ==2) {
                                    if(!isset($kana_count_check) || ($kana_count_check == 0 )){
                                        $data[$key] = $contact->fu_surname;
                                    }else {
                                        $data[$key] = $contact->fu_lastname;
                                    }
                                    $kana_count_check=1;continue;
                                }
                            }else if(strpos($key,'nam')!==false || strpos($key,'お名前')!==false){
                                if($name_count == 1) {
                                    $data[$key] = $contact->surname.' '.$contact->lastname;continue;
                                }else if($name_count == 2) {
                                    if(!isset($name_count_check) || ($name_count_check == 0)){
                                        $data[$key] = $contact->surname;
                                    }else {
                                        $data[$key] = $contact->lastname;
                                    }
                                    $name_count_check=1;continue;
                                }
                            }
                            if(strpos($key,'post')!==false || strpos($key,'yubin')!==false || strpos($key,'郵便番号')!==false|| strpos($key,'zip')!==false){
                                if($postal_count==1){
                                    $data[$key] = $contact->postalCode1.'-'.$contact->postalCode2;continue;
                                }else if($postal_count==2){
                                    if(!isset($postal_count_check) && ($postal_count_check ==0)){
                                        $data[$key] = $contact->postalCode1;
                                    }else {
                                        $data[$key] = $contact->postalCode2;
                                    }
                                    $postal_count_check=1;continue;
                                }
                            }
                            $emailTexts = array('mail','mail_confirm','ールアドレス','M_ADR','部署','E-Mail');
                            foreach($emailTexts as $text) {
                                if(strpos($key,$text)!==false){
                                    $data[$key] = $contact->email;break;
                                }
                            }
                            
                            if(strpos($key,'tel')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false){
                                if($phone_count ==1){
                                    $data[$key] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;continue;
                                }else if($phone_count ==3){
                                    if(!isset($phone_count_check) || ($phone_count_check ==0) ){
                                        $data[$key] = $contact->phoneNumber1;
                                        $phone_count_check=1;continue;
                                    }else if(isset($phone_count_check) && ($phone_count_check ==1)) {
                                        $data[$key] = $contact->phoneNumber2;
                                        $phone_count_check = 2;continue;
                                    }else if(isset($phone_count_check) && ($phone_count_check ==2)) {
                                        $data[$key] = $contact->phoneNumber3;continue;
                                    }
                                }
                            }
                        }
                        //exception method start
                        if(strpos($company->contact_form_url,"alfa-field.co.jp")!==false){
                            $data['mailform1'] = $contact->surname.' '.$contact->lastname;
                            $data['mailform2'] = $contact->fu_surname.' '.$contact->fu_lastname;
                            $data['mailform3'] = $contact->email;
                            $data['mailform4'] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;
                            $data['mailform5'] = $contact->postalCode1.'-'.$contact->postalCode2;
                            $data['mailform6'] = $contact->postalCode1;
                            $data['mailform7'] = $contact->postalCode2;
                            $data['mailform8'] = $contact->address;
                            $data['mailform9'] = $contact->address;
                            $data['mailform10'] = $contact->address;
                            $data['mailform11'] = "無料見積りのご依頼";
                            $content = str_replace('%company_name%', $company->name, $contact->content);
                            $data['mailform12'] = $content;
                            $data['mailform12'] .='  配信停止希望の方は  '.route('web.stop.receive', $company->id).'   こちら';
                        }
                        if(strpos($company->contact_form_url,"ksa.jp")!==false){
                            $data['key'] = '319254';
                        }

                        //end
                        foreach($form->getValues() as $key => $val) {
                            if((isset($data[$key]) || strpos($key,'wpcf7')!==false ||strpos($key,'captcha')!==false||strpos($key,'url')!==false)) {
                                continue;
                            }else {
                                $data[$key] = " ";
                            }
                        }

                        $crawler = $client->request($form->getMethod(), $form->getUri(), $data);
                        
                        // file_put_contents('html.txt',$crawler->html());
                        $checkMessages = array("ありがとうございま","有難うございま","送信されました","&#12354;&#12426;&#12364;&#12392;&#12358;&#12372;&#12374;&#12356;","完了","内容を確認させていただき");
                        $check = false;
                        foreach($checkMessages as $message) {
                            if(strpos($crawler->html(),$message)!==false){
                                $company->update([
                                    'status'        => '送信済み'
                                ]);
                                $companyContact->update([
                                    'is_delivered' => 2
                                ]);
                                $check =true;break;
                            }
                        }
                        if(!$check){
                            $form='';
                            try{
                                $form = $crawler->selectButton('送信する')->form();
                            }catch (\Throwable $e) {
                                
                            }
                            try{
                                $form = $crawler->filter('form')->form();
                            }catch (\Throwable $e) {
                                
                            }
                            try{
                                if(isset($form) && !empty($form)){
                                
                                    $crawler = $client->submit($form);
                                    // file_put_contents('html.txt',$crawler->html());
                                    $check =false;
                                    foreach($checkMessages as $message) {
                                        if(strpos($crawler->html(),$message)!==false){
                                            $company->update([
                                                'status'        => '送信済み'
                                            ]);
                                            $companyContact->update([
                                                'is_delivered' => 2
                                            ]);
                                            $check =true;break;
                                        }
                                    }
                                    if(!$check){
                                        $company->update([
                                            'status'        => '送信済み'
                                        ]);
                                        $companyContact->update([
                                            'is_delivered' => 2
                                        ]);
                                    }
                                }else {
                                    $company->update([
                                        'status'        => '送信済み'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 2
                                    ]);
                                }
                            }catch (\Throwable $e) {
                                $company->update([
                                    'status'        => '送信失敗'
                                ]);
                                $companyContact->update([
                                    'is_delivered' => 1
                                ]);
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
