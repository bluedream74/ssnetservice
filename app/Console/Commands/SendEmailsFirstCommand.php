<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\Config;
use Goutte\Client;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Illuminate\Support\Carbon;

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
    protected $form;
    protected $checkform;

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
        $offset = (int)(Config::get()->first()->mailLimit)*0.4;

        $start = Config::get()->first()->start;
        $end = Config::get()->first()->end;

        $today = Carbon::today();
        $startTimeStamp = Carbon::createFromTimestamp(strtotime($today->format('Y-m-d') .' '. $start));
        $endTimeStamp = Carbon::createFromTimestamp(strtotime($today->format('Y-m-d') .' '. $end));
        $now = Carbon::now();
        $startTimeCheck = $now->gte($startTimeStamp);
        $endTimeCheck = $now->lte($endTimeStamp);

        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $output->writeln("<info>start</info>");
        if( $startTimeCheck && $endTimeCheck ){

            $contacts = Contact::whereHas('reserve_companies')->get();
            foreach ($contacts as $contact) {
    
                $startDate = Contact::where('id',$contact->id)->get()->first()->date;
                $startTime = Contact::where('id',$contact->id)->get()->first()->time;
    
                $startCheck = false;
                if(is_null($startDate) || is_null($startTime)){
                    $startCheck = true;
                }else {
                    $startTimeStamp = Carbon::createFromTimestamp(strtotime($startDate .' '. $startTime));
                    $now = Carbon::now();
                    if($now->gte($startTimeStamp)) {
                        $startCheck = true;
                    }
                }
                
                if($startCheck) {
                    
                    $companyContacts = $contact->companies()->where('is_delivered', 0)->skip(0)->take($offset)->get();
                    $companyContacts->toQuery()->update(['is_delivered'=> 3]);
                
                    foreach ($companyContacts as $companyContact) {
                        sleep(2);
                        $company = $companyContact->company;
                        try {
                            $data = [];
                            $charset = 'UTF-8';
                            $client = new Client();
                            if($company->contact_form_url=='')continue;
                            $output->writeln("company url : ".$company->contact_form_url);
                            
                            $crawler = $client->request('GET', $company->contact_form_url);
                            
                            // $nonStrings = array("営業お断り","カタログ","サンプル","有料","代引き","着払い");
                            // $pos = strpos($crawler->text(),"カタログ");
                            if(
                                (strpos($crawler->text(),"営業お断り")!==false)
                                // ||(($pos!==false)&&($pos>50))
                                // ||(strpos($crawler->text(),"サンプル")!==false)
                                ||(strpos($crawler->text(),"有料")!==false)
                                ||(strpos($crawler->text(),"代引き")!==false)
                                ||(strpos($crawler->text(),"着払い")!==false)
                            ){
                                $company->update(['status' => '送信失敗']);
                                $companyContact->update([
                                    'is_delivered' => 1
                                ]);
                                continue;
                            }

                            try{
                                $this->form = $crawler->filter('form')->form();
                            }catch (\Throwable $e) {
                                
                            }
                            try{
                                $this->form = $crawler->filter('form button')->form();
                            }catch (\Throwable $e) {
                                
                            }

                            try{
                                if(empty($this->form->all())){
                                    $inputs = $crawler->filter('form input')->extract(array('type'));
                                    if(in_array('submit', $inputs)) {
                                        $crawler->filter('form input')->each(function($input) {
                                            if($input->extract(array('type'))[0]=="submit") {
                                                $this->form = $input->form();
                                            }
                                        });
                                    }
                                }
                            }catch (\Throwable $e) {
                                
                            }

                            if(!isset($this->form)){
                                $company->update(['status' => '送信失敗']);
                                $companyContact->update([
                                    'is_delivered' => 1
                                ]);
                                continue;
                            }
                            // $charset = $this->getCharset($crawler->html());
                            
                            // try{
                            //     $charset = $charset[1];
                            //     if(strcasecmp($charset,'utf-8')) {
                            //         $contact->surname = mb_convert_encoding($contact->surname,$charset,'UTF-8');
                            //         $contact->lastname = mb_convert_encoding($contact->lastname,$charset,'UTF-8');
                            //         $contact->fu_surname = mb_convert_encoding($contact->fu_surname,$charset,'UTF-8');
                            //         $contact->fu_lastname = mb_convert_encoding($contact->fu_lastname,$charset,'UTF-8');
                            //         $contact->company = mb_convert_encoding($contact->company,$charset,'UTF-8');
                            //         $contact->title = mb_convert_encoding($contact->title,$charset,'UTF-8');
                            //         $contact->content = mb_convert_encoding($contact->content,$charset,'UTF-8');
                            //         // $contact->area = mb_convert_encoding($contact->area,$charset,'UTF-8');
                            //         $contact->address = mb_convert_encoding($contact->address,$charset,'UTF-8');
                            //         $company->name = mb_convert_encoding($company->name,$charset,'UTF-8');
                            //     }
                            // }catch (\Throwable $e) {
                            //     $charset = 'UTF-8';
                            // }
                            
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
                                }else if(strpos($crawler->text(),'sitekey')!==false){
                                    $key_position = strpos($crawler->text(),'sitekey');
                                    if(isset($key_position)){
                                        if((substr($crawler->text(),$key_position+9,1)=="'"||(substr($crawler->text(),$key_position+9,1)=='"'))){
                                            $captcha_sitekey = substr($crawler->text(),$key_position+10,40);
                                        }else if((substr($crawler->text(),$key_position+11,1)=="'"||(substr($crawler->text(),$key_position+11,1)=='"'))){
                                            $captcha_sitekey = substr($crawler->text(),$key_position+12,40);
                                        }
                                    }
                                }
                                if(!isset($captcha_sitekey) || str_contains($captcha_sitekey,",")){ 
                                    if(strpos($crawler->html(),'data-sitekey')!==false){
                                        $key_position = strpos($crawler->html(),'data-sitekey');
                                        if(isset($key_position)){
                                            $captcha_sitekey = substr($crawler->html(),$key_position+14,40);
                                        }
                                    }else if(strpos($crawler->html(),'wpcf7submit')!==false){
                                        $key_position = strpos($crawler->html(),'wpcf7submit');
                                        if(isset($key_position)){
                                            $str = substr($crawler->html(),$key_position);
                                            $captcha_sitekey = substr($str,strpos($str,'grecaptcha')+13,40);
                                        }
                                    }
                                }
                                
                                if((strpos($crawler->html(),'wpcf7_recaptcha')!==false) || (strpos($crawler->html(),'g-recaptcha-response')!==false) || (strpos($crawler->html(),'recaptcha_response')!==false)) {
                                    if(isset($captcha_sitekey) && !str_contains($captcha_sitekey,",")){
                                    
                                        $api = new NoCaptchaProxyless();
                                        $api->setVerboseMode(true);
                                        //your anti-captcha.com account key
                                        $api->setKey(config('anticaptcha.key'));
                                        
                                        //recaptcha key from target website
                                        $api->setWebsiteURL($company->contact_form_url);
                                        $api->setWebsiteKey($captcha_sitekey);
                                        try{
                                            if (!$api->createTask()) {
                                                continue;
                                            }
                                        }catch(\Throwable $e){
                                            // file_put_contents('ve.txt',$e->getMessage());
                                        }
                                       
                                        $taskId = $api->getTaskId();
                                    
                                        if (!$api->waitForResult()) {
                                            continue;
                                        } else {
                                            $recaptchaToken = $api->getTaskSolution();
                                            foreach($this->form->all() as $key=>$val) {
                                                if(strpos($key,'wpcf7_recaptcha')!==false){
                                                    $data['_wpcf7_recaptcha_response'] = $recaptchaToken;break;
                                                }else if(strpos($key,'g-recaptcha-response')!==false){
                                                    $data['g-recaptcha-response'] = $recaptchaToken;break;
                                                }else if(strpos($key,'recaptcha_response')!==false){
                                                    $data['recaptcha_response'] = $recaptchaToken;break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }catch(\Throwable $e){
                                $output->writeln($e->getMessage());
                            }
        
                            if(!empty($this->form->getValues())){

                                foreach($this->form->all() as $key=>$value) {
                                    if(!strcasecmp($value->isHidden(),'hidden')) {
                                        $data[$key] = $value->getValue();
                                    }
                                }
                                foreach($this->form->getValues() as $key => $value) {
                                    if(isset($data[$key])||(!empty($data[$key])))continue;
                                    $emailTexts = array('company','cn','kaisha','cop','corp','会社','社名');
                                    foreach($emailTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            $data[$key] = $contact->company;continue;
                                        }
                                    }
        
                                    $addressTexts = array('住所','addr','add_detail');
                                    foreach($addressTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            $data[$key] = $contact->address;continue;
                                        }
                                    }

                                    $addressTexts = array('mail_add');
                                    foreach($addressTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            $data[$key] = $contact->email;continue;
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
        
                                }
                            }else {
                                $company->update([
                                    'status'        => '送信失敗'
                                ]);
                                $companyContact->update([
                                    'is_delivered' => 1
                                ]);
                                continue;
                            }
                            
                            foreach($this->form->all() as $key =>$val){
                                try{
                                    $type = $val->getType();
                                    if($val->isReadOnly()){
                                        continue;
                                    }
                                    if($type == 'select'){
                                        if(count($val->getOptions()) >= 47){
                                            $data[$key] = $contact->area;
                                        }else {
                                            $size = sizeof($this->form[$key]->getOptions());
                                            $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                        }
                                    }else if($type =='radio') {
                                        if(in_array('その他' ,$this->form[$key]->getOptions())) {
                                            foreach($this->form[$key]->getOptions() as $item) {
                                                if($item['value']== 'その他'){
                                                    $data[$key] = $item['value'];
                                                }
                                            }
                                        } else {
                                            $size = sizeof($this->form[$key]->getOptions());
                                            $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                        }
                                        
                                    }else if($type =='checkbox') {
                                        $data[$key] = $this->form[$key]->getOptions()[0]['value'];
                                    }else if(($type =='textarea') && (strpos($key,'captcha') === false)) {
                                        $content = str_replace('%company_name%', $company->name, $contact->content);
                                        $content = str_replace('%myurl%', route('web.read', [$contact->id,$company->id]), $content);
                                        $data[$key] = $content;
                                        $data[$key] .=PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .'※※※※※※※※'.PHP_EOL .'配信停止希望の方は 111 '.route('web.stop.receive', 'ajgm2a3jag'.$company->id.'25hgj').'   こちら'.PHP_EOL.'※※※※※※※※';
                                    }
                                    
                                }catch(\Throwable $e){
                                    continue;
                                }
                            }
                            
                            $compPatterns = array('会社名','企業名','貴社名','御社名','法人名','団体名','機関名','屋号','組織名','屋号','お店の名前','社名');
                            foreach($compPatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $pos = strpos($str,'name=');
                                    if($pos > 3000) {
                                        continue;
                                    }else {
                                        $nameStr = substr($str,$pos);
                                        $nameStr = substr($nameStr,6);
                                        $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                        foreach($this->form->all() as $key=>$val) {
                                            if($key==$nameStr){
                                                if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                    break;
                                                }else {
                                                    $data[$nameStr] = $contact->company;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                    
                                }
                            }
        
                            foreach($this->form->getValues() as $key => $value) {
                                if(isset($data[$key])&&(!empty($data[$key])))continue;
                                if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                    $data[$key] = $value;
                                }else {
                                    if(strpos($key, 'ご担当者名')!==false){
                                        $data[$key] = $contact->surname." ".$contact->lastname;continue;
                                    }
                                    if(strpos($key,'セイ')!==false){
                                        $data[$key] = $contact->fu_surname;
                                    }else if(strpos($key,'メイ')!==false){
                                        $data[$key] = $contact->fu_lastname;
                                    }
                                    // else if(strpos($key,'姓')!==false){
                                    //     $data[$key] = $contact->surname;
                                    // }else if((strpos($key,'名')!==false)&&(strpos($key,'名前')===false)&&(strpos($key,'氏名')===false)){
                                    //     $data[$key] = $contact->lastname;
                                    // }
                                }
                            }
                            $nonPatterns = array('部署');
                            foreach($nonPatterns as $val) {
                                if(strpos($crawler->html(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                break;
                                            }else {
                                                $data[$nameStr] = 'なし';
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            $nonPatterns = array('都道府県');
                            foreach($nonPatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                break;
                                            }else {
                                                $data[$nameStr] = $contact->area;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            $name_count = 0;$kana_count = 0;$postal_count = 0;$phone_count = 0;$fax_count=0;
                                foreach($this->form->getValues() as $key => $value) {
                                    if(isset($data[$key])&&(!empty($data[$key])))continue;
                                    if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                        $data[$key] = $value;
                                    }else {
                                        if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'namek')!==false || strpos($key,'f-')!==false ||  strpos($key,'ふり')!==false|| strpos($key,'kn')!==false ){
                                            $kana_count++;
                                        }else if((strpos($key,'nam')!==false || strpos($key,'名前')!==false || strpos($key,'氏名')!==false)){
                                            $name_count++;
                                        }
                                        if(strpos($key,'post')!==false || strpos($key,'郵便番号')!==false || strpos($key,'yubin')!==false || strpos($key,'zip')!==false || strpos($key,'〒')!==false){
                                            $postal_count++;
                                        }
                                        if(strpos($key,'tel')!==false || strpos($key,'TEL')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false){
                                            $phone_count++;
                                        }
                                        if(strpos($key,'fax')!==false || strpos($key,'FAX')!==false ){
                                            $fax_count++;
                                        }
                                    }
                                }
                                $str = substr($crawler->text(),strpos($crawler->text(),'お名前'),30);
                                if((strpos($str,'名')!==false)&&(strpos($str,'姓')!==false))$name_count=2;
                                $str = substr($crawler->text(),strpos($crawler->text(),'フリガナ'),40);
                                if((strpos($str,'メイ')!==false)&&(strpos($str,'セイ')!==false))$kana_count=2;
                                $str = substr($crawler->text(),strpos($crawler->text(),'カナ'),40);
                                if((strpos($str,'メイ')!==false)&&(strpos($str,'セイ')!==false))$kana_count=2;

                            $namePatterns = array('名前','氏名','担当者','差出人','ネーム');
                            foreach($namePatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-10);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$name){
                                            if(isset($data[$name]) && !empty($data[$name])){
                                                break;
                                            }else {
                                                if($name_count==2){
                                                    $data[$name] = $contact->surname;
                                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                                    $nameStr = substr($nameStr,6);
                                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                                    foreach($this->form->all() as $key=>$val) {
                                                        if($key==$name){
                                                            $data[$name] = $contact->lastname;break;
                                                        }
                                                    }
                                                    break;
                                                }else {
                                                    $data[$name] = $contact->surname.' '.$contact->lastname;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $namePatterns = array('郵便番号','〒');
                            foreach($namePatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$name){
                                            if(isset($data[$name]) && !empty($data[$name])){
                                                break;
                                            }else {
                                                if($postal_count==2){
                                                    $data[$name] = $contact->postalCode1;
                                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                                    $nameStr = substr($nameStr,6);
                                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                                    foreach($this->form->all() as $key=>$val) {
                                                        if($key==$name){
                                                            $data[$name] = $contact->postalCode2;
                                                        }
                                                    }
                                                    break;
                                                }else {
                                                    $data[$name] = $contact->postalCode1."-".$contact->postalCode2;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $namePatterns =array('FAX番号');
                            foreach($namePatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    if(strpos($str,'input')){
                                        $nameStr = substr($str,strpos($str,'name='));
                                        $nameStr = substr($nameStr,6);
                                        $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                        foreach($this->form->all() as $key=>$val) {
                                            if($key==$nameStr){
                                                if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                    break;
                                                }else {
                                                    $data[$nameStr] = $contact->postalCode1.' '.$contact->postalCode2;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $namePatterns = array('ふりがな','フリガナ','お名前（カナ）');
                            foreach($namePatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$name){
                                            if(isset($data[$name]) && !empty($data[$name])){
                                                break;
                                            }else {
                                                if($kana_count==2){
                                                    $data[$name] = $contact->fu_surname;
                                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                                    $nameStr = substr($nameStr,6);
                                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                                    foreach($this->form->all() as $key=>$val) {
                                                        if($key==$name){
                                                            $data[$name] = $contact->fu_lastname;
                                                        }
                                                    }
                                                    break;
                                                }else {
                                                    $data[$name] = $contact->fu_surname.' '.$contact->fu_lastname;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $addPatterns = array('住所','所在地','市区','番地','町名');
                            foreach($addPatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    if(strpos($str,'input')){
                                        $nameStr = substr($str,strpos($str,'name='));
                                        $nameStr = substr($nameStr,6);
                                        $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                        foreach($this->form->all() as $key=>$val) {
                                            if($key==$nameStr){
                                                if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                    break;
                                                }else {
                                                    $data[$nameStr] = $contact->address;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                           
                            $mailPatterns = array('メールアドレス','Mail アドレス');
                            foreach($mailPatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            $data[$nameStr] = $contact->email;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            $phonePatterns = array('電話番号','携帯電話','連絡先','TEL','Phone');
                            foreach($phonePatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $checkstr = substr($crawler->html(),strpos($crawler->html(),$val)-6,100);
                                    if(strpos($checkstr,'meta')!==false){
                                        continue;
                                    }
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$name){
                                            if(isset($data[$name]) && !empty($data[$name])){
                                                break;
                                            }else {
                                                if($phone_count>=3){
                                                    $data[$name] = $contact->phoneNumber1;
                                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                                    $nameStr = substr($nameStr,6);
                                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                                    foreach($this->form->all() as $key=>$val) {
                                                        if($key==$name){
                                                            $data[$name] = $contact->phoneNumber2;
                                                        }
                                                    }
                                                    $nameStr = substr($nameStr,strpos($nameStr,'name='));
                                                    $nameStr = substr($nameStr,6);
                                                    $name = substr($nameStr,0,strpos($nameStr,'"'));
                                                    foreach($this->form->all() as $key=>$val) {
                                                        if($key==$name){
                                                            $data[$name] = $contact->phoneNumber3;
                                                        }
                                                    }
                                                    break;
                                                }else {
                                                    $data[$name] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $titlePatterns = array('件名','Title','Subject','題名','用件名');
                            foreach($titlePatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                break;
                                            }else {
                                                $data[$nameStr] = $contact->title;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
        
                            
                            $nonPatterns = array('年齢',"築年数");
                            foreach($nonPatterns as $val) {
                                if(strpos($crawler->text(),$val)!==false) {
                                    $str = substr($crawler->html(),strpos($crawler->html(),$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                break;
                                            }else {
                                                $data[$nameStr] = 35;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $kana_count_check = 0;$name_count_check = 0;$phone_count_check = 0;$postal_count_check = 0;
                            foreach($this->form->getValues() as $key => $val) {
                                if(isset($data[$key])&&(!empty($data[$key])))continue;
                                if(($val!=='' || strpos($key,'wpcf7')!==false||strpos($key,'captcha')!==false)) {
                                    if(strpos($val,'例')!==false){
    
                                    }else{
                                        continue;
                                    }
                                }
                                if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'ふり')!==false|| strpos($key,'namek')!==false ||  strpos($key,'kn')!==false ){
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
                                if($fax_count == 1) {
                                    $titleTexts = array('fax','FAX');
                                    foreach($titleTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            $data[$key] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;break;
                                        }
                                    }
                                }

                                if(strpos($key,'post')!==false || strpos($key,'yubin')!==false || strpos($key,'郵便番号')!==false|| strpos($key,'zip')!==false|| strpos($key,'〒')!==false){
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
                                $emailTexts = array('mail','mail_confirm','ールアドレス','M_ADR','部署','E-Mail','メールアドレス');
                                foreach($emailTexts as $text) {
                                    if(strpos($key,$text)!==false){
                                        $data[$key] = $contact->email;break;
                                    }
                                }
                                
                                if(strpos($key,'tel')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false || strpos($key,'TEL')!==false){
                                    if($phone_count ==1){
                                        $data[$key] = $contact->phoneNumber1."-".$contact->phoneNumber2."-".$contact->phoneNumber3;continue;
                                    }else if($phone_count >= 3){
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
                            
                            if(strpos($company->contact_form_url,"ksa.jp")!==false){
                                $data['key'] = '319254';
                            }
    
                            //end
                            foreach($this->form->getValues() as $key => $val) {
                                if((isset($data[$key]) || strpos($key,'wpcf7')!==false ||strpos($key,'captcha')!==false||strpos($key,'url')!==false)) {
                                    continue;
                                }else {
                                    $data[$key] = "なし";
                                }
                            }
    
                            if(isset($data['g-recaptcha-response']) || isset($data['_wpcf7_recaptcha_response'])){
                                $crawler = $client->request($this->form->getMethod(), $this->form->getUri(), $data);
                            }else {
                                $this->form->setValues($data);
                                $crawler = $client->submit($this->form);
                            }
                            
                            $checkMessages = array("ありがとうございま","有難うございま","送信されました","送信しました","送信いたしました","自動返信メール","完了","内容を確認させていただき");
                            $failedMessages = array('必須項目','問題','ありません');
                            $failedCheck=true;
                            foreach($failedMessages as $message) {
                                if(strpos($crawler->html(),$message)!==false){
                                    $company->update([
                                        'status'        => '送信失敗'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 1
                                    ]);
                                    $failedCheck = false;break;
                                }
                            }

                            if($failedCheck) {
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
                                    try{
                                        $this->checkform = $crawler->filter('form button')->form();
                                        $inputs = $crawler->filter('form input')->extract(array('type'));
                                        if(in_array('submit', $inputs)) {
                                            $crawler->filter('form input')->each(function($input) {
                                                if($input->extract(array('type'))[0]=="submit") {
                                                    $this->checkform = $input->form();
                                                }
                                            });
                                        }
                                    
                                        if(isset($this->checkform) && !empty($this->checkform)){

                                            // foreach($this->form->getValues() as $key=>$val) {
                                            //     if(isset($this->checkform->getValues()[$key])){
                                            //         if(strcmp($val,$this->checkform->getValues()[$key])){
                                            //             $this->checkform->fields->set($key, $val);
                                            //         }
                                            //     }
                                            //     // else {
                                            //     //     $this->checkform->set($this->form[$key]);
                                            //     //     $this->checkform->fields->set($key, $val);
                                            //     // }
                                            // }
                                            // if(strcasecmp($charset,'utf-8')) {
                                            //     foreach($this->checkform->getValues() as $key => $val) {
                                            //         $val = mb_convert_encoding($val,$charset,'UTF-8');
                                            //         $this->checkform->fields->set($key, $val);
                                            //     }
                                            // }
                                            $crawler = $client->submit($this->checkform);
                                            
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
                                                    'status'        => '送信失敗'
                                                ]);
                                                $companyContact->update([
                                                    'is_delivered' => 1
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
                                        $output->writeln($e->getMessage());
                                        $company->update([
                                            'status'        => '送信済み'
                                        ]);
                                        $companyContact->update([
                                            'is_delivered' => 2
                                        ]);
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            $output->writeln($e->getMessage());
                            try{
                                $company->update([
                                    'status'        => '送信失敗'
                                ]);
                                $companyContact->update([
                                    'is_delivered' => 1
                                ]);
                            }catch (\Throwable $e) {
                                $output->writeln($e->getMessage());
                            }
                        }
                        $output->writeln("end company");
                    }
                }
            }
        }
        return 0;
    }

    public function getCharset(string $htmlContent) {
        preg_match('/\<meta[^\>]+charset *= *["\']?([a-zA-Z\-0-9_:.]+)/i', $htmlContent, $matches);
        return $matches;
    }
}
