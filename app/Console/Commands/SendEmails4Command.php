<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\Config;
use Goutte\Client;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use LaravelAnticaptcha\Anticaptcha\ImageToText;
use Illuminate\Support\Carbon;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Exception;


class SendEmails4Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:emails4';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    protected $form;
    protected $formHtml;
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
        $offset = env('MAIL_LIMIT');

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
                    try{
                        sleep(2*3);
                        $companyContacts = $contact->companies()->where('is_delivered', 0)->skip(0)->take($offset)->get();
                        $companyContacts->toQuery()->update(['is_delivered'=> 3]);
                    }catch (\Throwable $e) {
                        
                    }
                
                    foreach ($companyContacts as $companyContact) {
                        $endTimeCheck = $now->lte($endTimeStamp);
                        if(!$endTimeCheck)continue;
                        sleep(2);
                        $company = $companyContact->company;
                        try {
                            $data = [];
                            $this->form = "";$this->checkform="";$html="";$html_text="";$footerhtml="";
                            $charset = 'UTF-8';
                            $client = new Client();
                            if($company->contact_form_url=='')continue;
                            $output->writeln("company url : ".$company->contact_form_url);
                            $crawler = $client->request('GET', $company->contact_form_url);

                            $charset = $this->getCharset($crawler->html());
                            try{
                                $charset = $charset[1];
                            }catch (\Throwable $e) {
                                $charset = 'UTF-8';
                            }
                            
                            if(count($crawler->filter('textarea'))==0) {
                                $company->update(['status' => 'フォームなし']);
                                $companyContact->update([
                                    'is_delivered' => 4
                                ]);
                                $output->writeln("フォームなし");
                                continue;
                            }
                            $this->html=$crawler->html();
                            $this->html_text=$crawler->text();
                            
                            $crawler->filter('form')->each(function($form) {
                                try {
                                    $check=false;
                                    foreach($form->form()->all() as $val) {
                                        $type = $val->getType();
                                        if($val->isReadOnly()){
                                            continue;
                                        }
                                        switch($type) {
                                            case 'textarea': 
                                                $check=true;
                                                break;
                                            default:
                                                break;
                                        }
                                        if($check) {
                                            $this->form = $form->form();
                                            $this->html = $form->outerhtml();
                                            $this->html_text = $form->text();
                                            break;
                                        }
                                    }
                                }catch(\Throwable $e){

                                }
                            });
                            if(empty($this->form)) {
                                $iframes = $crawler->filter('iframe')->extract(['src']);
                                foreach($iframes as $iframe) {
                                    $clientFrame = new Client();
                                    $crawlerFrame = $clientFrame->request('GET', $iframe);
                                    $crawlerFrame->filter('form')->each(function($form) {
                                        try {
                                            $check=false;
                                            foreach($form->form()->all() as $val) {
                                                $type = $val->getType();
                                                if($val->isReadOnly()){
                                                    continue;
                                                }
                                                switch($type) {
                                                    case 'textarea': 
                                                        $check=true;
                                                        break;
                                                    default:
                                                        break;
                                                }
                                                if($check) {
                                                    $this->form = $form->form();
                                                    $this->html = $form->outerhtml();
                                                    $this->html_text = $form->text();
                                                    break;
                                                }
                                            }
                                        }catch(\Throwable $e){
        
                                        }
                                    });
                                }
                            }
                            if(empty($this->form) || (!strcasecmp($this->form->getMethod(),'get'))){
                                $company->update([
                                    'status'        => '送信失敗'
                                ]);
                                $companyContact->update([
                                    'is_delivered' => 1
                                ]);
                                $output->writeln("フォームなし");
                                continue;
                            } 
                            $output->writeln("continue");
                            $html = $this->html;
                            $html_text = $this->html_text;

                            try {
                                $footerhtml=$crawler->filter('#footer')->html();
                            }catch(\Throwable $e){
                                $output->writeln("footerなし");
                            }
                            $nonStrings = array("営業お断り","サンプル","有料","代引き","着払い","資料請求","カタログ");$continue_check=false;
                            foreach($nonStrings as $str) {
                                if((strpos($footerhtml,$str)!==false)) {
                                    $company->update(['status' => 'NGワードあり']);
                                    $companyContact->update([
                                        'is_delivered' => 5
                                    ]);
                                    $continue_check=true;
                                    break;
                                }
                            }
                            if($continue_check)continue;

                            // if(strcasecmp($charset,'utf-8')) {
                            //     $contact->surname = mb_convert_encoding($contact->surname,'UTF-8',$charset);
                            //     $contact->lastname = mb_convert_encoding($contact->lastname,'UTF-8',$charset);
                            //     $contact->fu_surname = mb_convert_encoding($contact->fu_surname,'UTF-8',$charset);
                            //     $contact->fu_lastname = mb_convert_encoding($contact->fu_lastname,'UTF-8',$charset);
                            //     $contact->company = mb_convert_encoding($contact->company,'UTF-8',$charset);
                            //     $contact->title = mb_convert_encoding($contact->title,'UTF-8',$charset);
                            //     $contact->content = mb_convert_encoding($contact->content,'UTF-8',$charset);
                            //     $contact->area = mb_convert_encoding($contact->area,'UTF-8',$charset);
                            // }
        
                            if(!empty($this->form->getValues())){

                                foreach($this->form->all() as $key=>$value) {
                                    if(isset($data[$key])||(!empty($data[$key])))continue;
                                    if(!strcasecmp($value->isHidden(),'hidden')) {
                                        $data[$key] = $value->getValue();
                                    }
                                }
                                
                                foreach($this->form->all() as $key =>$val){
                                    try{
                                        $type = $val->getType();
                                        if($val->isReadOnly()){
                                            continue;
                                        }
                                        switch($type) {
                                            case 'select': 
                                                $areaCheck=true;
                                                foreach($val->getOptions() as $value) {
                                                    if($value['value'] == $contact->area) {
                                                        $data[$key] = $contact->area;$areaCheck=false;
                                                    }
                                                }
                                                if($areaCheck) {
                                                    $size = sizeof($this->form[$key]->getOptions());
                                                    $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                                }
                                                break;
                                            case 'radio': 
                                                if(in_array('その他' ,$this->form[$key]->getOptions())) {
                                                    foreach($this->form[$key]->getOptions() as $item) {
                                                        if($item['value']== 'その他'){
                                                            $data[$key] = $item['value'];
                                                        }
                                                    }
                                                } else {
                                                    if(($key=="性別")||(($key=="sex")))$data[$key] = $this->form[$key]->getOptions()[0]['value'];
                                                    else if($value=="男性"){
                                                        $data[$key] = "男性";
                                                    }
                                                    else{
                                                        $size = sizeof($this->form[$key]->getOptions());
                                                        $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                                    }
                                                }
                                                break;
                                            case 'checkbox': 
                                                $data[$key] = $this->form[$key]->getOptions()[0]['value'];
                                                break;
                                            case 'textarea': 
                                                if((strpos($key,'captcha') === false) && (strpos($key,'address') === false)){
                                                    $content = str_replace('%company_name%', $company->name, $contact->content);
                                                    $content = str_replace('%myurl%', route('web.read', [$contact->id,$company->id]), $content);
                                                    $data[$key] = $content;
                                                    $data[$key] .=PHP_EOL .PHP_EOL .PHP_EOL .PHP_EOL .'※※※※※※※※'.PHP_EOL .'配信停止希望の方は '.route('web.stop.receive', 'ajgm2a3jag'.$company->id.'25hgj').'   こちら'.PHP_EOL.'※※※※※※※※';
                                                }
                                                break;
                                            case 'email': 
                                                $data[$key] = $contact->email;
                                                break;
                                            default:
                                                break;
                                        }
                                        
                                    }catch(\Throwable $e){
                                        continue;
                                    }
                                }
                                $addrCheck=false;
                                foreach($this->form->getValues() as $key => $value) {
                                    if(isset($data[$key])||(!empty($data[$key])))continue;
                                    
                                    $emailTexts = array('company','cn','kaisha','cop','corp','会社','社名');
                                    $furiTexts = array('company-kana','company_furi','フリガナ','kcn','ふりがな');$furi_check=true;
                                    foreach($emailTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            foreach($furiTexts as $furi) {
                                                if(strpos($key, $furi)!==false){
                                                    if(isset($data[$key]) && !empty($data[$key])){
                                                        continue;
                                                    }
                                                    $data[$key] = 'ナシ';$furi_check=false;break;
                                                }
                                            }
                                            if($furi_check) {
                                                $data[$key] = $contact->company;continue;
                                            }
                                        }
                                    }

                                    $addressTexts = array('住所','addr','add_detail');
                                    foreach($addressTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(!$addrCheck){
                                                if(isset($data[$key]) && !empty($data[$key])){
                                                    continue;
                                                }
                                                $data[$key] = $contact->address;$addrCheck=true;continue;
                                            }
                                        }
                                    }

                                    $addressTexts = array('mail_add');
                                    foreach($addressTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(isset($data[$key]) && !empty($data[$key])){
                                                continue;
                                            }
                                            $data[$key] = $contact->email;continue;
                                        }
                                    }
        
                                    $titleTexts = array('title','subject','件名');
                                    foreach($titleTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(isset($data[$key]) && !empty($data[$key])){
                                                continue;
                                            }
                                            $data[$key] = $contact->title;break;
                                        }
                                    }
        
                                    $urlTexts = array('URL','url','HP');
                                    foreach($urlTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(isset($data[$key]) && !empty($data[$key])){
                                                continue;
                                            }
                                            $data[$key] = $contact->homepageUrl;break;
                                        }
                                    }

                                    $urlTexts = array('丁目番地','建物名');
                                    foreach($urlTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(isset($data[$key]) && !empty($data[$key])){
                                                continue;
                                            }
                                            $data[$key] = '0';break;
                                        }
                                    }

                                    $urlTexts = array('郵便番号');
                                    foreach($urlTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(isset($data[$key]) && !empty($data[$key])){
                                                continue;
                                            }
                                            $data[$key] = $contact->postalCode1.$contact->postalCode2;break;
                                        }
                                    }

                                    $urlTexts = array('市区町村');
                                    foreach($urlTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            if(isset($data[$key]) && !empty($data[$key])){
                                                continue;
                                            }
                                            $data[$key] = mb_substr($contact->address,0,3);break;
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
                                $output->writeln("フォームなし");
                                continue;
                            }
                            
                            
                            $compPatterns = array('会社名','企業名','貴社名','御社名','法人名','団体名','機関名','屋号','組織名','屋号','お店の名前','社名');
                            foreach($compPatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
                                    $substr = mb_substr($html,strpos($html,$val),30);
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
                                if(isset($data[$key])||(!empty($data[$key])))continue;
                                if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                    $data[$key] = $value;
                                }else {
                                    if(strpos($key, 'ご担当者名')!==false){
                                        $data[$key] = $contact->surname." ".$contact->lastname;continue;
                                    }
                                    if((strpos($key,'セイ')!==false)||((strpos($key,'せい')!==false))){
                                        $data[$key] = $contact->fu_surname;
                                    }else if((strpos($key,'メイ')!==false)||(strpos($key,'めい')!==false)){
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
                                if(strpos($html,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
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
                            try{
                                $nonPatterns = array('都道府県');
                                foreach($nonPatterns as $val) {
                                    if(strpos($html_text,$val)!==false) {
                                        $str = substr($html,strpos($html,$val)-6);
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
                            }catch(\Throwable $e){
                                $output->writeln($e->getMessage());
                            }
                            $name_count = 0;$kana_count = 0;$postal_count = 0;$phone_count = 0;$fax_count=0;
                            foreach($this->form->getValues() as $key => $value) {
                                if(isset($data[$key])||(!empty($data[$key])))continue;
                                if(($value!=='' || strpos($key,'wpcf7')!==false)&&(strpos($value,'例')===false)){
                                    $data[$key] = $value;
                                }else {
                                    if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'namek')!==false || strpos($key,'f-')!==false ||  strpos($key,'ふり')!==false|| strpos($key,'kn')!==false ){
                                        $kana_count++;
                                    }else  if((strpos($key,'shop')!==false || strpos($key,'company')!==false || strpos($key,'cp')!==false)){

                                    }else if((strpos($key,'nam')!==false || strpos($key,'名前')!==false || strpos($key,'氏名')!==false)){
                                        $name_count++;
                                    }
                                    if(strpos($key,'post')!==false || strpos($key,'郵便番号')!==false || strpos($key,'yubin')!==false || strpos($key,'zip')!==false || strpos($key,'〒')!==false || strpos($key,'pcode')!==false){
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

                            if($kana_count==2) {
                                $n=0;
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'kana')!==false || strpos($key,'フリガナ')!==false || strpos($key,'Kana')!==false|| strpos($key,'namek')!==false || strpos($key,'f-')!==false ||  strpos($key,'ふり')!==false|| strpos($key,'kn')!==false ){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        if($n==0) {
                                            $data[$key] = $contact->fu_surname;$n++;
                                        }else if($n==1) {
                                            $data[$key] = $contact->fu_lastname;$n++;
                                        }
                                    }
                                }
                            }

                            if($name_count==2) {
                                $n=0;
                                foreach($this->form->getValues() as $key => $value) {
                                    if((strpos($key,'shop')!==false || strpos($key,'company')!==false || strpos($key,'cp')!==false)){

                                    }else if((strpos($key,'nam')!==false || strpos($key,'名前')!==false || strpos($key,'氏名')!==false)){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        if($n==0) {
                                            $data[$key] = $contact->surname;$n++;
                                        }else if($n==1) {
                                            $data[$key] = $contact->lastname;$n++;
                                        }
                                    }
                                }
                            }else if($name_count==1) {
                                if((strpos($key,'shop')!==false || strpos($key,'company')!==false || strpos($key,'cp')!==false)){

                                }else if((strpos($key,'nam')!==false || strpos($key,'名前')!==false || strpos($key,'氏名')!==false)){
                                    if(isset($data[$key]) && !empty($data[$key])){
                                        continue;
                                    }
                                    $data[$key] = $contact->surname." ".$contact->lastname;
                                }
                            }

                            if($postal_count==2) {
                                $n=0;
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'post')!==false || strpos($key,'郵便番号')!==false || strpos($key,'yubin')!==false || strpos($key,'zip')!==false || strpos($key,'〒')!==false || strpos($key,'pcode')!==false){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        if($n==0) {
                                            $data[$key] = $contact->postalCode1;$n++;
                                        }else if($n==1) {
                                            $data[$key] = $contact->postalCode2;$n++;
                                        }
                                    }
                                }
                            }else if($postal_count==1){
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'post')!==false || strpos($key,'郵便番号')!==false || strpos($key,'yubin')!==false || strpos($key,'zip')!==false || strpos($key,'〒')!==false || strpos($key,'pcode')!==false){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        $data[$key] = $contact->postalCode1.$contact->postalCode2;
                                    }
                                }
                            }

                            if($phone_count==3) {
                                $n=0;
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'tel')!==false || strpos($key,'TEL')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        if($n==0) {
                                            $data[$key] = $contact->phoneNumber1;$n++;
                                        }else if($n==1) {
                                            $data[$key] = $contact->phoneNumber2;$n++;
                                        }else if($n==2) {
                                            $data[$key] = $contact->phoneNumber3;$n++;
                                        }
                                    }
                                }
                            }else if($phone_count==1) {
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'tel')!==false || strpos($key,'TEL')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                    }
                                }
                            }

                            if($fax_count==3) {
                                $n=0;
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'fax')!==false || strpos($key,'FAX')!==false ){
                                        if($n==0) {
                                            $data[$key] = $contact->phoneNumber1;$n++;
                                        }else if($n==1) {
                                            $data[$key] = $contact->phoneNumber2;$n++;
                                        }else if($n==2) {
                                            $data[$key] = $contact->phoneNumber3;$n++;
                                        }
                                    }
                                }
                            }else if($fax_count==1){
                                foreach($this->form->getValues() as $key => $value) {
                                    if(strpos($key,'fax')!==false || strpos($key,'FAX')!==false ){
                                        if(isset($data[$key]) && !empty($data[$key])){
                                            continue;
                                        }
                                        $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                    }
                                }
                            }
                            
                            $messages =array('名前','担当者','氏名','お名前(かな)');
                            foreach($messages as $message) {
                                if(strpos($html_text,$message)!==false) {
                                    $str = mb_substr($html,mb_strpos($html,$message),200);
                                    if(strpos($str,'姓')!==false)
                                        $name_count=2;
                                }
                            }

                            $messages =array('カタカナ','フリガナ','カナ','ふりがな');
                            foreach($messages as $message) {
                                if(strpos($html_text,$message)!==false) {
                                    $str = mb_substr($html_text,mb_strpos($html_text,$message),40);
                                    if((strpos($str,'メイ')!==false)&&(strpos($str,'セイ')!==false))$kana_count=2;
                                    if((strpos($str,'名')!==false)&&(strpos($str,'姓')!==false))$kana_count=2;
                                }
                            }

                            $namePatterns = array('名前','氏名','担当者','差出人','ネーム');
                            foreach($namePatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-10);
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
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
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
                                                    $data[$name] = $contact->postalCode1.$contact->postalCode2;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $namePatterns =array('FAX番号');
                            foreach($namePatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
                                    if(strpos($str,'input')){
                                        $nameStr = substr($str,strpos($str,'name='));
                                        $nameStr = substr($nameStr,6);
                                        $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                        foreach($this->form->all() as $key=>$val) {
                                            if($key==$nameStr){
                                                if(isset($data[$nameStr]) && !empty($data[$nameStr])){
                                                    break;
                                                }else {
                                                    $data[$nameStr] = $contact->postalCode1.$contact->postalCode2;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $namePatterns = array('ふりがな','フリガナ','お名前（カナ）');
                            foreach($namePatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
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
                            
                            $addPatterns = array('住所','所在地','市区','町名');
                            foreach($addPatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
                                    if(strpos($str,'input')){
                                        $nameStr = substr($str,strpos($str,'name='));
                                        $nameStr = substr($nameStr,6);
                                        $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                        foreach($this->form->all() as $key=>$val) {
                                            if($key==$nameStr){
                                                if(isset($data[$nameStr])){
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
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            if(isset($data[$nameStr])){
                                                break;
                                            }else {
                                                $data[$nameStr] = $contact->email;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            $mailPatterns = array('オーダー');
                            foreach($mailPatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
                                    $nameStr = substr($str,strpos($str,'name='));
                                    $nameStr = substr($nameStr,6);
                                    $nameStr = substr($nameStr,0,strpos($nameStr,'"'));
                                    foreach($this->form->all() as $key=>$val) {
                                        if($key==$nameStr){
                                            if(isset($data[$nameStr])){
                                                break;
                                            }else {
                                                $data[$nameStr] = "order";
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $phonePatterns = array('電話','携帯電話','連絡先','TEL','Phone');
                            foreach($phonePatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $checkstr = substr($html,strpos($html,$val)-6,100);
                                    if(strpos($checkstr,'meta')!==false){
                                        continue;
                                    }
                                    $str = substr($html,strpos($html,$val)-6);
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
                                                    $data[$name] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $titlePatterns = array('件名','Title','Subject','題名','用件名');
                            foreach($titlePatterns as $val) {
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
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
                                if(strpos($html_text,$val)!==false) {
                                    $str = substr($html,strpos($html,$val)-6);
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
                            $surname_check=false;
                            foreach($this->form->getValues() as $key => $val) {
                                if(isset($data[$key])||(!empty($data[$key])))continue;
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
                                if(strpos($key,'姓')!==false){
                                    $data[$key] = $contact->surname;$surname_check=true;continue;
                                }
                                if(strpos($key,'名')!==false){
                                    if($surname_check) {
                                        $data[$key] = $contact->lastname;continue;
                                    }else{
                                        $data[$key] = $contact->surname." ".$contact->lastname;continue;
                                    }
                                }
                                if($fax_count == 1) {
                                    $titleTexts = array('fax','FAX');
                                    foreach($titleTexts as $text) {
                                        if(strpos($key,$text)!==false){
                                            $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;break;
                                        }
                                    }
                                }

                                if(strpos($key,'post')!==false || strpos($key,'yubin')!==false || strpos($key,'郵便番号')!==false|| strpos($key,'zip')!==false|| strpos($key,'〒')!==false || strpos($key,'pcode')!==false){
                                    if($postal_count==1){
                                        $data[$key] = $contact->postalCode1.$contact->postalCode2;continue;
                                    }else if($postal_count==2){
                                        if(!isset($postal_count_check) && ($postal_count_check ==0)){
                                            $data[$key] = $contact->postalCode1;
                                        }else {
                                            $data[$key] = $contact->postalCode2;
                                        }
                                        $postal_count_check=1;continue;
                                    }
                                }
                                $emailTexts = array('mail','Mail','mail_confirm','ールアドレス','M_ADR','部署','E-Mail','メールアドレス','confirm');
                                foreach($emailTexts as $text) {
                                    if(strpos($key,$text)!==false){
                                        $data[$key] = $contact->email;break;
                                    }
                                }
                                
                                if(strpos($key,'tel')!==false || strpos($key,'phone')!==false || strpos($key,'電話番号')!==false || strpos($key,'TEL')!==false){
                                    if($phone_count ==1){
                                        $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;continue;
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
                            //end
                            foreach($this->form->all() as $key => $val) {
                                if((isset($data[$key]) || strpos($key,'wpcf7')!==false ||strpos($key,'captcha')!==false||strpos($key,'url')!==false)) {
                                    continue;
                                }else {
                                    try{
                                        $type = $val->getType();
                                        switch($type){
                                            case 'number':
                                                $data[$key] = 1;
                                                break;
                                            case 'date':
                                                $data[$key] = date("Y-m-d", strtotime("+1 day"));
                                                break;
                                            case 'select':
                                                $size = sizeof($this->form[$key]->getOptions());
                                                $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                                break;
                                            case 'default':
                                                $data[$key] = "きょうわ";
                                                break;
                                        }
                                    }catch(\Throwable $e){
                                        $output->writeln($e->getMessage());
                                    }
                                    
                                }
                            }
                                
                            $javascriptCheck=false;
                            if((strpos($this->html,'onclick')!==false)||(strpos($this->html,'recaptcha')!==false)||($this->form->getUri()==$company->contact_form_url)){
                                $javascriptCheck=true;
                            }else if((strpos($this->html,'type="submit"')!==false)){
                                $javascriptCheck=false;
                            }else{
                                $javascriptCheck=true;
                            }
                            if($javascriptCheck) {
                                
                                try {
                                    try {
                                        
                                        $options = new ChromeOptions();
                                        $options->addArguments(["--headless","--disable-gpu", "--no-sandbox"]);
    
                                        $caps = DesiredCapabilities::chrome();
                                        $caps->setCapability('acceptSslCerts', false);
                                        $caps->setCapability(ChromeOptions::CAPABILITY, $options);
                                        // $caps->setPlatform("Linux");
                                        $serverUrl = 'http://localhost:4444';
    
                                        $driver = RemoteWebDriver::create($serverUrl, $caps,5000);
    
                                        $driver->get($company->contact_form_url);
    
                                        $driver->manage()->window()->setSize(new WebDriverDimension(1225, 1996));
                                        $names = collect($data);
    
                                        foreach($names as $key => $name){
                                            foreach($this->form->all() as $key1=>$value1) {
                                                if((strpos($key,'wpcf7')!==false)){
                                                    continue;
                                                }
                                                if($key==$key1) {
                                                    try {
                                                        switch($value1->getType()){
                                                            case 'checkbox':
                                                                $driver->findElement(WebDriverBy::cssSelector('input[type="checkbox"][name="'.$key.'"]'))->click();
                                                                break;
                                                            case 'select':
                                                                $driver->findElement(WebDriverBy::cssSelector('select[name="'.$key.'"] option[value="'.$name.'"]'))->click();
                                                                break;
                                                            case 'radio':
                                                                $driver->findElement(WebDriverBy::cssSelector('input[type="radio"][name="'.$key.'"]'))->click();
                                                                break;
                                                            case 'hidden':
                                                                break;
                                                            case 'textarea':
                                                                $driver->findElement(WebDriverBy::cssSelector('textarea[name="'.$key.'"]'))->sendKeys($name);
                                                                break;
                                                            default:
                                                                $driver->findElement(WebDriverBy::cssSelector('input[name="'.$key.'"]'))->sendKeys($name);
                                                                break;
                                                        }
                                                    }catch (Exception $e) {
                                                        // dd($e->getMessage());
                                                    }
                                                    // $driver->takeScreenshot('log.png');
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        // $driver->takeScreenshot('log.png');
                                        $confirmElements = $driver->findElements(WebDriverBy::xpath('//button[contains(text(),"確認")] | //input[contains(@value,"確認") and @type!="hidden"] | //a[contains(text(),"確認")]'));
                                        $sendElements = $driver->findElements(WebDriverBy::xpath('//button[contains(text(),"送信")] | //input[contains(@value,"送信") and @type!="hidden"] | //a[contains(text(),"送信")]'));
                                        $nextElements = $driver->findElements(WebDriverBy::xpath('//button[contains(text(),"次へ")] | //input[contains(@value,"次へ") and @type!="hidden"] | //a[contains(text(),"次へ")]'));
                                        if(count($confirmElements)>=1) {
                                            foreach($confirmElements as $confirmElement) {
                                                try {
                                                    $confirmElement->click();
                                                }catch (Exception $exception) {
    
                                                }
                                            }
                                        }
                                        if(count($sendElements)>=1) {
                                            foreach($sendElements as $sendElement){
                                                try {
                                                    $sendElement->click();
                                                }catch (Exception $exception) {
                                                    
                                                }
                                            }
                                        }
                                        if(count($nextElements)>=1) {
                                            foreach($nextElements as $nextElement){
                                                try {
                                                    $nextElement->click();
                                                }catch (Exception $exception) {
                                                    
                                                }
                                            }
                                        }
                                    } catch (Exception $e) {
                                        $company->update([
                                            'status'        => '送信済み'
                                        ]);
                                        $companyContact->update([
                                            'is_delivered' => 2
                                        ]);
                                    }
                                    // $driver->takeScreenshot('log.png');
                                    try {
                                        $checkConfirmElements = $driver->findElements(WebDriverBy::xpath('//*[contains(text(),"ありがとうございま")] | //*[contains(text(),"有難うございま")] | //*[contains(text(),"送信しました")] | //*[contains(text(),"送信されました")] | //*[contains(text(),"成功しました")] | //*[contains(text(),"完了いたしま")]| //*[contains(text(),"送信いたしました")]| //*[contains(text(),"内容を確認させていただき")]| //*[contains(text(),"自動返信メール")]'));
                                        if(count($checkConfirmElements)>=1) {
                                            $company->update([
                                                'status'        => '送信済み'
                                            ]);
                                            $companyContact->update([
                                                'is_delivered' => 2
                                            ]);
                                        }else {
                                            $confirmElements="";$sendElements="";$nextElements="";
                                            $confirmElements = $driver->findElements(WebDriverBy::xpath('//button[contains(text(),"確認")] | //input[contains(@value,"確認") and @type!="hidden"] | //a[contains(text(),"確認")]'));
                                            $sendElements = $driver->findElements(WebDriverBy::xpath('//button[contains(text(),"送信")] | //input[contains(@value,"送信") and @type!="hidden"] | //a[contains(text(),"送信")]'));
                                            $nextElements = $driver->findElements(WebDriverBy::xpath('//button[contains(text(),"次へ")] | //input[contains(@value,"次へ") and @type!="hidden"] | //a[contains(text(),"次へ")]'));
                                            if(count($confirmElements)>=1) {
                                                foreach($confirmElements as $confirmElement) {
                                                    try {
                                                        $confirmElement->click();
                                                    }catch (Exception $exception) {
    
                                                    }
                                                }
                                            }
                                            if(count($sendElements)>=1) {
                                                foreach($sendElements as $sendElement){
                                                    try {
                                                        $sendElement->click();
                                                    }catch (Exception $exception) {
                                                        
                                                    }
                                                }
                                            }
                                            if(count($nextElements)>=1) {
                                                foreach($nextElements as $nextElement){
                                                    try {
                                                        $nextElement->click();
                                                    }catch (Exception $exception) {
                                                        
                                                    }
                                                }
                                            }
                                            // $checkConfirmElements = $driver->findElements(WebDriverBy::xpath('//*[contains(text(),"ありがとうございま")] | //*[contains(text(),"有難うございま")] | //*[contains(text(),"送信しました")] | //*[contains(text(),"送信されました")] | //*[contains(text(),"成功しました")] | //*[contains(text(),"完了いたしま")]| //*[contains(text(),"送信いたしました")]| //*[contains(text(),"内容を確認させていただき")]| //*[contains(text(),"自動返信メール")]'));
                                            $company->update([
                                                'status'        => '送信済み'
                                            ]);
                                            $companyContact->update([
                                                'is_delivered' => 2
                                            ]);
                                        }
                                        
                                    } catch (Exception $e) {
                                        $company->update([
                                            'status'        => '送信済み'
                                        ]);
                                        $companyContact->update([
                                            'is_delivered' => 2
                                        ]);
                                    }
                                    $driver->manage()->deleteAllCookies();
                                    $driver->quit();
                                }catch (Exception $e) {
                                    $company->update([
                                        'status'        => '送信済み'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 2
                                    ]);
                                    $driver->manage()->deleteAllCookies();
                                    $driver->quit();
                                }
                            
                            }else {
                                
                                try {
                                    // $captchaImg = $crawler->filter('.captcha img')->extract(['src'])[0];
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
                                
                                    if(strpos($crawler->html(),'recaptcha')!==false) {
                                        if(isset($captcha_sitekey) && !str_contains($captcha_sitekey,",")){
                                        
                                            $api = new NoCaptchaProxyless();
                                            $api->setVerboseMode(true);
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
                                                if((strpos($html,'g-recaptcha')!==false)&&(strpos($html,'g-recaptcha-response')==false)) {
                                                    $domdocument = new \DOMDocument();
                                                    $ff = $domdocument->createElement('input');
                                                    $ff->setAttribute('name', 'g-recaptcha-response');
                                                    $ff->setAttribute('value', $recaptchaToken);
                                                    $formField = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
                                                    $this->form->set($formField);
                                                }else {
                                                    foreach($this->form->all() as $key=>$val) {
                                                        if(strpos($key,'recaptcha')!==false){
                                                            $data[$key] = $recaptchaToken;break;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }catch(\Throwable $e){
                                    $company->update([
                                        'status'        => '送信失敗'
                                    ]);
                                    $companyContact->update([
                                        'is_delivered' => 1
                                    ]);
                                    continue;
                                }

                                $checkMessages = array("ありがとうございま","有難うございま","送信されました","送信しました","送信いたしました","自動返信メール","内容を確認させていただき","成功しました","完了いたしま");
                                $thank_check=true;
                                foreach($checkMessages as $message) {
                                    if(strpos($crawler->html(),$message) !==false ) {
                                        $thank_check=false;
                                    }
                                }
                                $crawler = $client->submit($this->form,$data);
                                $failedCheck=true;
                                
                                $check = false;
                                if($thank_check) {
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
                                }
                                if(!$check){
                                    try{
                                        $crawler->filter('form')->each(function($form) {
                                            try {
                                                if(strcasecmp($form->form()->getMethod(),'get')){
                                                    if((strpos($form->form()->getName(),'login')!==false)||(strpos($form->form()->getName(),'search')!==false)){
            
                                                    }else {
                                                        $this->checkform = $form->form();
                                                        $form->filter('input')->each(function($input) {
                                                            try {
                                                                if((strpos($input->outerhtml(),'送信')!==false)||(strpos($input->outerhtml(),'back')!==false)||(strpos($input->outerhtml(),'修正')!==false)){
    
                                                                }else {
                                                                    $this->checkform = $input->form();
                                                                }
                                                            }catch(\Throwable $e){
                            
                                                            }
                                                        }); 
                                                    }
                                                }
                                            }catch(\Throwable $e){
            
                                            }
                                        });
    
                                        if(empty($this->checkform)){
                                            $iframes = $crawler->filter('iframe')->extract(['src']);
                                            foreach($iframes as $iframe) {
                                                $clientFrame = new Client();
                                                $crawlerFrame = $clientFrame->request('GET', $iframe);
                                                $crawlerFrame->filter('form')->each(function($form) {
                                                    try {
                                                        if(strcasecmp($form->form()->getMethod(),'get')){
                                                            if((strpos($form->form()->getName(),'login')!==false)||(strpos($form->form()->getName(),'search')!==false)){
                    
                                                            }else {
                                                                $this->checkform = $form->form();
                                                                $form->filter('input')->each(function($input) {
                                                                    try {
                                                                        if((strpos($input->outerhtml(),'送信')!==false)||(strpos($input->outerhtml(),'back')!==false)||(strpos($input->outerhtml(),'修正')!==false)){
            
                                                                        }else {
                                                                            $this->checkform = $input->form();
                                                                        }
                                                                    }catch(\Throwable $e){
                                    
                                                                    }
                                                                }); 
                                                            }
                                                        }
                                                    }catch(\Throwable $e){
                    
                                                    }
                                                });
                                            }
                                            
                                        } 
                                        if(empty($this->checkform)){
                                            $company->update(['status' => '送信失敗']);
                                            $companyContact->update([
                                                'is_delivered' => 1
                                            ]);
                                            $output->writeln("failed");
                                            continue;
                                        }
                                        
                                        if(isset($this->checkform) && !empty($this->checkform->all())){
    
                                            // $this->checkform->setValues($data);
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
                                                continue;
                                            }
                                        }else {
                                            $company->update([
                                                'status'        => '送信済み'
                                            ]);
                                            $companyContact->update([
                                                'is_delivered' => 2
                                            ]);
                                            continue;
                                        }
                                    }catch (\Throwable $e) {
                                        $output->writeln($e->getMessage());
                                        $company->update([
                                            'status'        => '送信済み'
                                        ]);
                                        $companyContact->update([
                                            'is_delivered' => 2
                                        ]);
                                        continue;
                                    }
                                }
                            }
                           
                        } catch (\Throwable $e) {
                            $company->update(['status' => 'フォームなし']);
                            $companyContact->update([
                                'is_delivered' => 4
                            ]);
                            $output->writeln($e->getMessage());
                            continue;
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
