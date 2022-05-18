<?php

namespace App\Console\Commands;

use App\Models\Config;
use Illuminate\Console\Command;
use App\Models\Contact;
use App\Models\CompanyContact;
use Goutte\Client;
use DateTime;
use DB;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use LaravelAnticaptcha\Anticaptcha\ImageToText;
use Illuminate\Support\Carbon;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverCheckboxes;
use Facebook\WebDriver\WebDriverRadios;
use Facebook\WebDriver\WebDriverSelect;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\HttpClient\HttpClient;
use Exception;

class SendEmails1Command extends Command
{
    public const STATUS_FAILURE = 1;
    public const STATUS_SENT = 2;
    public const STATUS_SENDING = 3;
    public const STATUS_NO_FORM = 4;
    public const STATUS_NG = 5;

    public const RETRY_COUNT = 1;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:emails1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
    protected $form;
    protected $formHtml;
    protected $checkform;
    protected $driver;
    protected $html;
    protected $htmlText;
    protected $data;
    protected $isDebug = false;
    protected $isShowUnsubscribe;
    protected $isClient;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(HttpClient::create(['verify_peer' => false, 'verify_host' => false]));
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $config = Config::get()->first();
        $start = $config->start;
        $end = $config->end;
        $this->isShowUnsubscribe = $config->is_show_unsubscribe;
        $limit = env('MAIL_LIMIT') ? env('MAIL_LIMIT') : 30;

        $today = Carbon::today();
        $startTimeStamp = Carbon::createFromTimestamp(strtotime($today->format('Y-m-d') .' '. $start));
        $endTimeStamp = Carbon::createFromTimestamp(strtotime($today->format('Y-m-d') .' '. $end));
        $now = Carbon::now();
        $startTimeCheck = $now->gte($startTimeStamp);
        $endTimeCheck = $now->lte($endTimeStamp);

        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $output->writeln("<info>start</info>");
        if ($startTimeCheck && $endTimeCheck) {
            $contacts = Contact::whereRaw("(`date` is NULL OR `time` is NULL OR (CURDATE() >= `date` AND CURTIME() >= `time`))")
                                ->whereHas('reserve_companies')->get();

            foreach ($contacts as $contact) {
                DB::beginTransaction();
                try {
                    $companyContacts = CompanyContact::with(['contact'])->lockForUpdate()->where('is_delivered', 0)->limit($limit)->get();
                    if (count($companyContacts)) {
                        $companyContacts->toQuery()->update(['is_delivered' => self::STATUS_SENDING]);
                    } else {
                        $selectedTime = new DateTime(date('Y-m-d H:i:s'));
                        $companyContacts = CompanyContact::with(['contact'])
                                ->lockForUpdate()
                                ->where('is_delivered', self::STATUS_SENDING)
                                ->where('updated_at', '<=', $selectedTime->modify('-' . strval($limit * 2) . ' minutes')->format('Y-m-d H:i:s'))
                                ->where('updated_at', '>=', $selectedTime->modify('-1 day')->format('Y-m-d H:i:s'))
                                ->get();
                        if (count($companyContacts)) {
                            $companyContacts->toQuery()->update(['is_delivered' => 0]);
                        }

                        DB::commit();

                        sleep(60);

                        return 0;
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();

                    sleep(60);

                    return 0;
                }
                
                foreach ($companyContacts as $companyContact) {
                    $endTimeCheck = $now->lte($endTimeStamp);
                    if (!$endTimeCheck) {
                        continue;
                    }

                    $company = $companyContact->company;
                    try {
                        $data = [];
                        $this->form = "";
                        $this->isClient = true;
                        $this->checkform = "";
                        $html = "";
                        $htmlText = "";
                        $footerhtml = "";
                        $charset = 'UTF-8';

                        if ($company->contact_form_url == '') {
                            continue;
                        }
                        $output->writeln("company url : ".$company->contact_form_url);
                        $crawler = $this->client->request('GET', $company->contact_form_url);

                        $charset = $this->getCharset($crawler->html());
                        try {
                            $charset = isset($charset[1]) && $charset[1] ? $charset[1] : 'UTF-8';
                        } catch (\Throwable $e) {
                            $charset = 'UTF-8';
                            $output->writeln($e);
                        }

                        $hasContactForm = $this->findContactForm($crawler);
                        if (!$hasContactForm) {
                            try {
                                $this->initBrowser();
                                $crawler = $this->getPageHTMLUsingBrowser($company->contact_form_url);
                                $this->isClient = false;
                            } catch (\Exception $e) {
                                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
                                continue;
                            }

                            $hasContactForm = $this->findContactForm($crawler);
                            if (!$hasContactForm) {
                                $iframes = array_merge($crawler->filter('iframe')->extract(['src']), $crawler->filter('iframe')->extract(['data-src']));
                                foreach ($iframes as $i => $iframeURL) {
                                    try {
                                        $frameResponse = $this->client->request('GET', $iframeURL);
                                        $hasFrameContactForm = $this->findContactForm($frameResponse);
                                        if ($hasFrameContactForm) {
                                            $hasContactForm = true;
                                            $this->isClient = true;
                                            break;
                                        } else {
                                            $frameResponse = $this->getPageHTMLUsingBrowser($iframeURL);
                                            $hasFrameContactForm = $this->findContactForm($frameResponse);
                                            if ($hasFrameContactForm) {
                                                $hasContactForm = true;
                                                $this->isClient = false;
                                                break;
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        continue;
                                    }
                                }
                            }
                    
                            if (!$hasContactForm) {
                                $this->updateCompanyContact($companyContact, self::STATUS_NO_FORM, 'Contact form not found');
                                continue;
                            }
                        }

                        if (empty($this->form) || (!strcasecmp($this->form->getMethod(), 'get'))) {
                            $this->updateCompanyContact($companyContact, self::STATUS_NO_FORM, 'Contact form not found');
                            continue;
                        }

                        $html = $this->html;
                        $htmlText = $this->htmlText;

                        try {
                            $nonStrings = array("営業お断り","サンプル","有料","代引き","着払い","資料請求","カタログ");
                            $continue_check=false;
                            foreach ($nonStrings as $str) {
                                if ((strpos($this->html, $str)!==false)) {
                                    $this->updateCompanyContact($companyContact, self::STATUS_NG, 'NG word');
                                    $continue_check=true;
                                    break;
                                }
                            }
                            if ($continue_check) {
                                continue;
                            }
                        } catch (\Throwable $e) {
                            $output->writeln($e);
                        }

                        if (!empty($this->form->getValues())) {
                            foreach ($this->form->all() as $key=>$value) {
                                if (isset($data[$key])||(!empty($data[$key]))) {
                                    continue;
                                }
                                if (!strcasecmp($value->isHidden(), 'hidden')) {
                                    $data[$key] = $value->getValue();
                                }
                            }
                                
                            foreach ($this->form->all() as $key =>$val) {
                                try {
                                    $type = $val->getType();
                                    if ($val->isReadOnly()) {
                                        continue;
                                    }
                                    switch ($type) {
                                            case 'select':
                                                $areaCheck=true;
                                                foreach ($val->getOptions() as $value) {
                                                    if ($value['value'] == $contact->area) {
                                                        $data[$key] = $contact->area;
                                                        $areaCheck=false;
                                                    }
                                                }
                                                if ($areaCheck) {
                                                    $size = sizeof($this->form[$key]->getOptions());
                                                    $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                                }
                                                break;
                                            case 'radio':
                                                if (in_array('その他', $this->form[$key]->getOptions())) {
                                                    foreach ($this->form[$key]->getOptions() as $item) {
                                                        if ($item['value']== 'その他') {
                                                            $data[$key] = $item['value'];
                                                        }
                                                    }
                                                } else {
                                                    if (($key=="性別")||(($key=="sex"))) {
                                                        $data[$key] = $this->form[$key]->getOptions()[0]['value'];
                                                    } elseif ($value=="男性") {
                                                        $data[$key] = "男性";
                                                    } else {
                                                        $size = sizeof($this->form[$key]->getOptions());
                                                        $data[$key] = $this->form[$key]->getOptions()[$size-1]['value'];
                                                    }
                                                }
                                                break;
                                            case 'checkbox':
                                                $data[$key] = $this->form[$key]->getOptions()[0]['value'];
                                                break;
                                            case 'textarea':
                                                if ((strpos($key, 'captcha') === false) && (strpos($key, 'address') === false)) {
                                                    $content = str_replace('%company_name%', $company->name, $contact->content);
                                                    $content = str_replace('%myurl%', route('web.read', [$contact->id,$company->id]), $content);
                                                    $data[$key] = $content;
                                                    if ($this->isShowUnsubscribe) {
                                                        $data[$key] .= PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . '※※※※※※※※' . PHP_EOL . '配信停止希望の方は ' . route('web.stop.receive', 'ajgm2a3jag' . $company->id . '25hgj') . '   こちら' . PHP_EOL . '※※※※※※※※';
                                                    }
                                                }
                                                break;
                                            case 'email':
                                                $data[$key] = $contact->email;
                                                break;
                                            default:
                                                break;
                                        }
                                } catch (\Throwable $e) {
                                    $output->writeln($e);
                                    continue;
                                }
                            }
                            $addrCheck=false;
                            foreach ($this->form->getValues() as $key => $value) {
                                if (isset($data[$key])||(!empty($data[$key]))) {
                                    continue;
                                }
                                    
                                $emailTexts = array('company','cn','kaisha','cop','corp','会社','社名');
                                $furiTexts = array('company-kana','company_furi','フリガナ','kcn','ふりがな');
                                $furi_check=true;
                                foreach ($emailTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        foreach ($furiTexts as $furi) {
                                            if (strpos($key, $furi)!==false) {
                                                if (isset($data[$key]) && !empty($data[$key])) {
                                                    continue;
                                                }
                                                $data[$key] = 'ナシ';
                                                $furi_check=false;
                                                break;
                                            }
                                        }
                                        if ($furi_check) {
                                            $data[$key] = $contact->company;
                                            continue;
                                        }
                                    }
                                }

                                $addressTexts = array('住所','addr','add_detail');
                                foreach ($addressTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (!$addrCheck) {
                                            if (isset($data[$key]) && !empty($data[$key])) {
                                                continue;
                                            }
                                            $data[$key] = $contact->address;
                                            $addrCheck=true;
                                            continue;
                                        }
                                    }
                                }

                                $addressTexts = array('mail_add');
                                foreach ($addressTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (isset($data[$key]) && !empty($data[$key])) {
                                            continue;
                                        }
                                        $data[$key] = $contact->email;
                                        continue;
                                    }
                                }
        
                                $titleTexts = array('title','subject','件名');
                                foreach ($titleTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (isset($data[$key]) && !empty($data[$key])) {
                                            continue;
                                        }
                                        $data[$key] = $contact->title;
                                        break;
                                    }
                                }
        
                                $urlTexts = array('URL','url','HP');
                                foreach ($urlTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (isset($data[$key]) && !empty($data[$key])) {
                                            continue;
                                        }
                                        $data[$key] = $contact->homepageUrl;
                                        break;
                                    }
                                }

                                $urlTexts = array('丁目番地','建物名');
                                foreach ($urlTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (isset($data[$key]) && !empty($data[$key])) {
                                            continue;
                                        }
                                        $data[$key] = '0';
                                        break;
                                    }
                                }

                                $urlTexts = array('郵便番号');
                                foreach ($urlTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (isset($data[$key]) && !empty($data[$key])) {
                                            continue;
                                        }
                                        $data[$key] = $contact->postalCode1.$contact->postalCode2;
                                        break;
                                    }
                                }

                                $urlTexts = array('市区町村');
                                foreach ($urlTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        if (isset($data[$key]) && !empty($data[$key])) {
                                            continue;
                                        }
                                        $data[$key] = mb_substr($contact->address, 0, 3);
                                        break;
                                    }
                                }
                            }
                        } else {
                            $this->updateCompanyContact($companyContact, self::STATUS_NO_FORM, 'Contact form values not found');
                            continue;
                        }
                            
                            
                        $compPatterns = array('会社名','企業名','貴社名','御社名','法人名','団体名','機関名','屋号','組織名','屋号','お店の名前','社名');
                        foreach ($compPatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $substr = mb_substr($html, strpos($html, $val), 30);
                                $pos = strpos($str, 'name=');
                                if ($pos > 3000) {
                                    continue;
                                } else {
                                    $nameStr = substr($str, $pos);
                                    $nameStr = substr($nameStr, 6);
                                    $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                    foreach ($this->form->all() as $key=>$val) {
                                        if ($key==$nameStr) {
                                            if (isset($data[$nameStr]) && !empty($data[$nameStr])) {
                                                break;
                                            } else {
                                                $data[$nameStr] = $contact->company;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
        
                        foreach ($this->form->getValues() as $key => $value) {
                            if (isset($data[$key])||(!empty($data[$key]))) {
                                continue;
                            }
                            if (($value!=='' || strpos($key, 'wpcf7')!==false)&&(strpos($value, '例')===false)) {
                                $data[$key] = $value;
                            } else {
                                if (strpos($key, 'ご担当者名')!==false) {
                                    $data[$key] = $contact->surname." ".$contact->lastname;
                                    continue;
                                }
                                if ((strpos($key, 'セイ')!==false)||((strpos($key, 'せい')!==false))) {
                                    $data[$key] = $contact->fu_surname;
                                } elseif ((strpos($key, 'メイ')!==false)||(strpos($key, 'めい')!==false)) {
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
                        foreach ($nonPatterns as $val) {
                            if (strpos($html, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$nameStr) {
                                        if (isset($data[$nameStr]) && !empty($data[$nameStr])) {
                                            break;
                                        } else {
                                            $data[$nameStr] = 'なし';
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        try {
                            $nonPatterns = array('都道府県');
                            foreach ($nonPatterns as $val) {
                                if (strpos($htmlText, $val)!==false) {
                                    $str = substr($html, strpos($html, $val)-6);
                                    $nameStr = substr($str, strpos($str, 'name='));
                                    $nameStr = substr($nameStr, 6);
                                    $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                    foreach ($this->form->all() as $key=>$val) {
                                        if ($key==$nameStr) {
                                            if (isset($data[$nameStr]) && !empty($data[$nameStr])) {
                                                break;
                                            } else {
                                                $data[$nameStr] = $contact->area;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            $output->writeln($e);
                        }
                        $name_count = 0;
                        $kana_count = 0;
                        $postal_count = 0;
                        $phone_count = 0;
                        $fax_count=0;
                        foreach ($this->form->getValues() as $key => $value) {
                            if (isset($data[$key])||(!empty($data[$key]))) {
                                continue;
                            }
                            if (($value!=='' || strpos($key, 'wpcf7')!==false)&&(strpos($value, '例')===false)) {
                                $data[$key] = $value;
                            } else {
                                if (strpos($key, 'kana')!==false || strpos($key, 'フリガナ')!==false || strpos($key, 'Kana')!==false|| strpos($key, 'namek')!==false || strpos($key, 'f-')!==false ||  strpos($key, 'ふり')!==false|| strpos($key, 'kn')!==false) {
                                    $kana_count++;
                                } elseif ((strpos($key, 'shop')!==false || strpos($key, 'company')!==false || strpos($key, 'cp')!==false)) {
                                } elseif ((strpos($key, 'nam')!==false || strpos($key, '名前')!==false || strpos($key, '氏名')!==false)) {
                                    $name_count++;
                                }
                                if (strpos($key, 'post')!==false || strpos($key, '郵便番号')!==false || strpos($key, 'yubin')!==false || strpos($key, 'zip')!==false || strpos($key, '〒')!==false || strpos($key, 'pcode')!==false) {
                                    $postal_count++;
                                }
                                if (strpos($key, 'tel')!==false || strpos($key, 'TEL')!==false || strpos($key, 'phone')!==false || strpos($key, '電話番号')!==false) {
                                    $phone_count++;
                                }
                                if (strpos($key, 'fax')!==false || strpos($key, 'FAX')!==false) {
                                    $fax_count++;
                                }
                            }
                        }

                        if ($kana_count==2) {
                            $n=0;
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'kana')!==false || strpos($key, 'フリガナ')!==false || strpos($key, 'Kana')!==false|| strpos($key, 'namek')!==false || strpos($key, 'f-')!==false ||  strpos($key, 'ふり')!==false|| strpos($key, 'kn')!==false) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    if ($n==0) {
                                        $data[$key] = $contact->fu_surname;
                                        $n++;
                                    } elseif ($n==1) {
                                        $data[$key] = $contact->fu_lastname;
                                        $n++;
                                    }
                                }
                            }
                        }

                        if ($name_count==2) {
                            $n=0;
                            foreach ($this->form->getValues() as $key => $value) {
                                if ((strpos($key, 'shop')!==false || strpos($key, 'company')!==false || strpos($key, 'cp')!==false)) {
                                } elseif ((strpos($key, 'nam')!==false || strpos($key, '名前')!==false || strpos($key, '氏名')!==false)) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    if ($n==0) {
                                        $data[$key] = $contact->surname;
                                        $n++;
                                    } elseif ($n==1) {
                                        $data[$key] = $contact->lastname;
                                        $n++;
                                    }
                                }
                            }
                        } elseif ($name_count==1) {
                            if ((strpos($key, 'shop')!==false || strpos($key, 'company')!==false || strpos($key, 'cp')!==false)) {
                            } elseif ((strpos($key, 'nam')!==false || strpos($key, '名前')!==false || strpos($key, '氏名')!==false)) {
                                if (isset($data[$key]) && !empty($data[$key])) {
                                    continue;
                                }
                                $data[$key] = $contact->surname." ".$contact->lastname;
                            }
                        }

                        if ($postal_count==2) {
                            $n=0;
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'post')!==false || strpos($key, '郵便番号')!==false || strpos($key, 'yubin')!==false || strpos($key, 'zip')!==false || strpos($key, '〒')!==false || strpos($key, 'pcode')!==false) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    if ($n==0) {
                                        $data[$key] = $contact->postalCode1;
                                        $n++;
                                    } elseif ($n==1) {
                                        $data[$key] = $contact->postalCode2;
                                        $n++;
                                    }
                                }
                            }
                        } elseif ($postal_count==1) {
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'post')!==false || strpos($key, '郵便番号')!==false || strpos($key, 'yubin')!==false || strpos($key, 'zip')!==false || strpos($key, '〒')!==false || strpos($key, 'pcode')!==false) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    $data[$key] = $contact->postalCode1.$contact->postalCode2;
                                }
                            }
                        }

                        if ($phone_count==3) {
                            $n=0;
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'tel')!==false || strpos($key, 'TEL')!==false || strpos($key, 'phone')!==false || strpos($key, '電話番号')!==false) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    if ($n==0) {
                                        $data[$key] = $contact->phoneNumber1;
                                        $n++;
                                    } elseif ($n==1) {
                                        $data[$key] = $contact->phoneNumber2;
                                        $n++;
                                    } elseif ($n==2) {
                                        $data[$key] = $contact->phoneNumber3;
                                        $n++;
                                    }
                                }
                            }
                        } elseif ($phone_count==1) {
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'tel')!==false || strpos($key, 'TEL')!==false || strpos($key, 'phone')!==false || strpos($key, '電話番号')!==false) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                }
                            }
                        }

                        if ($fax_count==3) {
                            $n=0;
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'fax')!==false || strpos($key, 'FAX')!==false) {
                                    if ($n==0) {
                                        $data[$key] = $contact->phoneNumber1;
                                        $n++;
                                    } elseif ($n==1) {
                                        $data[$key] = $contact->phoneNumber2;
                                        $n++;
                                    } elseif ($n==2) {
                                        $data[$key] = $contact->phoneNumber3;
                                        $n++;
                                    }
                                }
                            }
                        } elseif ($fax_count==1) {
                            foreach ($this->form->getValues() as $key => $value) {
                                if (strpos($key, 'fax')!==false || strpos($key, 'FAX')!==false) {
                                    if (isset($data[$key]) && !empty($data[$key])) {
                                        continue;
                                    }
                                    $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                }
                            }
                        }
                            
                        $messages =array('名前','担当者','氏名','お名前(かな)');
                        foreach ($messages as $message) {
                            if (strpos($htmlText, $message)!==false) {
                                $str = mb_substr($html, mb_strpos($html, $message), 200);
                                if (strpos($str, '姓')!==false) {
                                    $name_count=2;
                                }
                                if (strpos($str, 'name1')!==false) {
                                    $name_count=2;
                                }
                            }
                        }

                        $messages =array('カタカナ','フリガナ','カナ','ふりがな');
                        foreach ($messages as $message) {
                            if (strpos($htmlText, $message)!==false) {
                                $str = mb_substr($htmlText, mb_strpos($htmlText, $message), 40);
                                if ((strpos($str, 'メイ')!==false)&&(strpos($str, 'セイ')!==false)) {
                                    $kana_count=2;
                                }
                                if ((strpos($str, '名')!==false)&&(strpos($str, '姓')!==false)) {
                                    $kana_count=2;
                                }
                                if ((strpos($str, 'kana1')!==false)&&(strpos($str, 'kana2')!==false)) {
                                    $kana_count=2;
                                }
                            }
                        }

                        $namePatterns = array('名前','氏名','担当者','差出人','ネーム');
                        foreach ($namePatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-10);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$name) {
                                        if (isset($data[$name]) && !empty($data[$name])) {
                                            break;
                                        } else {
                                            if ($name_count==2) {
                                                $data[$name] = $contact->surname;
                                                $nameStr = substr($nameStr, strpos($nameStr, 'name='));
                                                $nameStr = substr($nameStr, 6);
                                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                                foreach ($this->form->all() as $key=>$val) {
                                                    if ($key==$name) {
                                                        $data[$name] = $contact->lastname;
                                                        break;
                                                    }
                                                }
                                                break;
                                            } else {
                                                $data[$name] = $contact->surname.' '.$contact->lastname;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $namePatterns = array('郵便番号','〒');
                        foreach ($namePatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$name) {
                                        if (isset($data[$name]) && !empty($data[$name])) {
                                            break;
                                        } else {
                                            if ($postal_count==2) {
                                                $data[$name] = $contact->postalCode1;
                                                $nameStr = substr($nameStr, strpos($nameStr, 'name='));
                                                $nameStr = substr($nameStr, 6);
                                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                                foreach ($this->form->all() as $key=>$val) {
                                                    if ($key==$name) {
                                                        $data[$name] = $contact->postalCode2;
                                                    }
                                                }
                                                break;
                                            } else {
                                                $data[$name] = $contact->postalCode1.$contact->postalCode2;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                            
                        $namePatterns =array('FAX番号');
                        foreach ($namePatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                if (strpos($str, 'input')) {
                                    $nameStr = substr($str, strpos($str, 'name='));
                                    $nameStr = substr($nameStr, 6);
                                    $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                    foreach ($this->form->all() as $key=>$val) {
                                        if ($key==$nameStr) {
                                            if (isset($data[$nameStr]) && !empty($data[$nameStr])) {
                                                break;
                                            } else {
                                                $data[$nameStr] = $contact->postalCode1.$contact->postalCode2;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $namePatterns = array('ふりがな','フリガナ','お名前（カナ）');
                        foreach ($namePatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$name) {
                                        if (isset($data[$name]) && !empty($data[$name])) {
                                            break;
                                        } else {
                                            if ($kana_count==2) {
                                                $data[$name] = $contact->fu_surname;
                                                $nameStr = substr($nameStr, strpos($nameStr, 'name='));
                                                $nameStr = substr($nameStr, 6);
                                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                                foreach ($this->form->all() as $key=>$val) {
                                                    if ($key==$name) {
                                                        $data[$name] = $contact->fu_lastname;
                                                    }
                                                }
                                                break;
                                            } else {
                                                $data[$name] = $contact->fu_surname.' '.$contact->fu_lastname;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                            
                        $addPatterns = array('住所','所在地','市区','町名');
                        foreach ($addPatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                if (strpos($str, 'input')) {
                                    $nameStr = substr($str, strpos($str, 'name='));
                                    $nameStr = substr($nameStr, 6);
                                    $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                    foreach ($this->form->all() as $key=>$val) {
                                        if ($key==$nameStr) {
                                            if (isset($data[$nameStr])) {
                                                break;
                                            } else {
                                                $data[$nameStr] = $contact->address;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        $mailPatterns = array('メールアドレス','Mail アドレス');
                        foreach ($mailPatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$nameStr) {
                                        if (isset($data[$nameStr])) {
                                            break;
                                        } else {
                                            $data[$nameStr] = $contact->email;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        $mailPatterns = array('オーダー');
                        foreach ($mailPatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$nameStr) {
                                        if (isset($data[$nameStr])) {
                                            break;
                                        } else {
                                            $data[$nameStr] = "order";
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                            
                        $phonePatterns = array('電話','携帯電話','連絡先','TEL','Phone');
                        foreach ($phonePatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $checkstr = substr($html, strpos($html, $val)-6, 100);
                                if (strpos($checkstr, 'meta')!==false) {
                                    continue;
                                }
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$name) {
                                        if (isset($data[$name]) && !empty($data[$name])) {
                                            break;
                                        } else {
                                            if ($phone_count>=3) {
                                                $data[$name] = $contact->phoneNumber1;
                                                $nameStr = substr($nameStr, strpos($nameStr, 'name='));
                                                $nameStr = substr($nameStr, 6);
                                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                                foreach ($this->form->all() as $key=>$val) {
                                                    if ($key==$name) {
                                                        $data[$name] = $contact->phoneNumber2;
                                                    }
                                                }
                                                $nameStr = substr($nameStr, strpos($nameStr, 'name='));
                                                $nameStr = substr($nameStr, 6);
                                                $name = substr($nameStr, 0, strpos($nameStr, '"'));
                                                foreach ($this->form->all() as $key=>$val) {
                                                    if ($key==$name) {
                                                        $data[$name] = $contact->phoneNumber3;
                                                    }
                                                }
                                                break;
                                            } else {
                                                $data[$name] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $titlePatterns = array('件名','Title','Subject','題名','用件名');
                        foreach ($titlePatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$nameStr) {
                                        if (isset($data[$nameStr]) && !empty($data[$nameStr])) {
                                            break;
                                        } else {
                                            $data[$nameStr] = $contact->title;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                            
                        $nonPatterns = array('年齢',"築年数");
                        foreach ($nonPatterns as $val) {
                            if (strpos($htmlText, $val)!==false) {
                                $str = substr($html, strpos($html, $val)-6);
                                $nameStr = substr($str, strpos($str, 'name='));
                                $nameStr = substr($nameStr, 6);
                                $nameStr = substr($nameStr, 0, strpos($nameStr, '"'));
                                foreach ($this->form->all() as $key=>$val) {
                                    if ($key==$nameStr) {
                                        if (isset($data[$nameStr]) && !empty($data[$nameStr])) {
                                            break;
                                        } else {
                                            $data[$nameStr] = 35;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                            
                        $kana_count_check = 0;
                        $name_count_check = 0;
                        $phone_count_check = 0;
                        $postal_count_check = 0;
                        $surname_check=false;
                        foreach ($this->form->getValues() as $key => $val) {
                            if (isset($data[$key])||(!empty($data[$key]))) {
                                continue;
                            }
                            if (($val!=='' || strpos($key, 'wpcf7')!==false||strpos($key, 'captcha')!==false)) {
                                if (strpos($val, '例')!==false) {
                                } else {
                                    continue;
                                }
                            }
                            if (strpos($key, 'kana')!==false || strpos($key, 'フリガナ')!==false || strpos($key, 'Kana')!==false|| strpos($key, 'ふり')!==false|| strpos($key, 'namek')!==false ||  strpos($key, 'kn')!==false) {
                                if ($kana_count == 1) {
                                    $data[$key] = $contact->fu_surname.' '.$contact->fu_lastname;
                                    continue;
                                } elseif ($kana_count ==2) {
                                    if (!isset($kana_count_check) || ($kana_count_check == 0)) {
                                        $data[$key] = $contact->fu_surname;
                                    } else {
                                        $data[$key] = $contact->fu_lastname;
                                    }
                                    $kana_count_check=1;
                                    continue;
                                }
                            } elseif (strpos($key, 'nam')!==false || strpos($key, 'お名前')!==false) {
                                if ($name_count == 1) {
                                    $data[$key] = $contact->surname.' '.$contact->lastname;
                                    continue;
                                } elseif ($name_count == 2) {
                                    if (!isset($name_count_check) || ($name_count_check == 0)) {
                                        $data[$key] = $contact->surname;
                                    } else {
                                        $data[$key] = $contact->lastname;
                                    }
                                    $name_count_check=1;
                                    continue;
                                }
                            }
                            if (strpos($key, '姓')!==false) {
                                $data[$key] = $contact->surname;
                                $surname_check=true;
                                continue;
                            }
                            if (strpos($key, '名')!==false) {
                                if ($surname_check) {
                                    $data[$key] = $contact->lastname;
                                    continue;
                                } else {
                                    $data[$key] = $contact->surname." ".$contact->lastname;
                                    continue;
                                }
                            }
                            if ($fax_count == 1) {
                                $titleTexts = array('fax','FAX');
                                foreach ($titleTexts as $text) {
                                    if (strpos($key, $text)!==false) {
                                        $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                        break;
                                    }
                                }
                            }

                            if (strpos($key, 'post')!==false || strpos($key, 'yubin')!==false || strpos($key, '郵便番号')!==false|| strpos($key, 'zip')!==false|| strpos($key, '〒')!==false || strpos($key, 'pcode')!==false) {
                                if ($postal_count==1) {
                                    $data[$key] = $contact->postalCode1.$contact->postalCode2;
                                    continue;
                                } elseif ($postal_count==2) {
                                    if (!isset($postal_count_check) && ($postal_count_check ==0)) {
                                        $data[$key] = $contact->postalCode1;
                                    } else {
                                        $data[$key] = $contact->postalCode2;
                                    }
                                    $postal_count_check=1;
                                    continue;
                                }
                            }
                            $emailTexts = array('mail','Mail','mail_confirm','ールアドレス','M_ADR','部署','E-Mail','メールアドレス','confirm');
                            foreach ($emailTexts as $text) {
                                if (strpos($key, $text)!==false) {
                                    $data[$key] = $contact->email;
                                    break;
                                }
                            }
                                
                            if (strpos($key, 'tel')!==false || strpos($key, 'phone')!==false || strpos($key, '電話番号')!==false || strpos($key, 'TEL')!==false) {
                                if ($phone_count ==1) {
                                    $data[$key] = $contact->phoneNumber1.$contact->phoneNumber2.$contact->phoneNumber3;
                                    continue;
                                } elseif ($phone_count >= 3) {
                                    if (!isset($phone_count_check) || ($phone_count_check ==0)) {
                                        $data[$key] = $contact->phoneNumber1;
                                        $phone_count_check=1;
                                        continue;
                                    } elseif (isset($phone_count_check) && ($phone_count_check ==1)) {
                                        $data[$key] = $contact->phoneNumber2;
                                        $phone_count_check = 2;
                                        continue;
                                    } elseif (isset($phone_count_check) && ($phone_count_check ==2)) {
                                        $data[$key] = $contact->phoneNumber3;
                                        continue;
                                    }
                                }
                            }
                        }
                        //end
                        foreach ($this->form->all() as $key => $val) {
                            if ((isset($data[$key]) || strpos($key, 'wpcf7')!==false ||strpos($key, 'captcha')!==false||strpos($key, 'url')!==false)) {
                                continue;
                            } else {
                                try {
                                    $type = $val->getType();
                                    switch ($type) {
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
                                } catch (\Throwable $e) {
                                    $output->writeln($e);
                                }
                            }
                        }

                        $this->data = $data;
                        
                        if (strpos($crawler->html(), 'recaptcha') === false) {
                            try {
                                if ($this->isClient) {
                                    $this->submitByUsingCrawler($company);
                                } else {
                                    $this->submitByUsingBrower($company, $this->data);
                                }
                                $this->updateCompanyContact($companyContact, self::STATUS_SENT);
                            } catch (\Exception $e) {
                                $this->updateCompanyContact($companyContact, self::STATUS_SENT, $e->getMessage());
                            }
                        } else {
                            try {
                                if (isset($captcha_sitekey)) {
                                    unset($captcha_sitekey);
                                }
                                // $captchaImg = $crawler->filter('.captcha img')->extract(['src'])[0];
                                if (strpos($crawler->html(), 'api.js?render')!==false) {
                                    $key_position = strpos($crawler->html(), 'api.js?render');
                                    if (isset($key_position)) {
                                        $captcha_sitekey = substr($crawler->html(), $key_position+14, 40);
                                    }
                                } elseif (strpos($crawler->html(), 'changeCaptcha')!==false) {
                                    $key_position = strpos($crawler->html(), 'changeCaptcha');
                                    if (isset($key_position)) {
                                        $captcha_sitekey = substr($crawler->html(), $key_position+15, 40);
                                    }
                                } elseif (strpos($crawler->text(), 'sitekey')!==false) {
                                    $key_position = strpos($crawler->text(), 'sitekey');
                                    if (isset($key_position)) {
                                        if ((substr($crawler->text(), $key_position+9, 1)=="'"||(substr($crawler->text(), $key_position+9, 1)=='"'))) {
                                            $captcha_sitekey = substr($crawler->text(), $key_position+10, 40);
                                        } elseif ((substr($crawler->text(), $key_position+11, 1)=="'"||(substr($crawler->text(), $key_position+11, 1)=='"'))) {
                                            $captcha_sitekey = substr($crawler->text(), $key_position+12, 40);
                                        }
                                    }
                                }
                                if (!isset($captcha_sitekey) || str_contains($captcha_sitekey, ",")) {
                                    if (strpos($crawler->html(), 'data-sitekey')!==false) {
                                        $key_position = strpos($crawler->html(), 'data-sitekey');
                                        if (isset($key_position)) {
                                            $captcha_sitekey = substr($crawler->html(), $key_position+14, 40);
                                        }
                                    } elseif (strpos($crawler->html(), 'wpcf7submit')!==false) {
                                        $key_position = strpos($crawler->html(), 'wpcf7submit');
                                        if (isset($key_position)) {
                                            $str = substr($crawler->html(), $key_position);
                                            $captcha_sitekey = substr($str, strpos($str, 'grecaptcha')+13, 40);
                                        }
                                    }
                                }
                                
                                if (strpos($crawler->html(), 'recaptcha')!==false) {
                                    if (isset($captcha_sitekey) && !str_contains($captcha_sitekey, ",")) {
                                        $api = new NoCaptchaProxyless();
                                        $api->setVerboseMode(true);
                                        $api->setKey(config('anticaptcha.key'));
                                        //recaptcha key from target website
                                        $api->setWebsiteURL($company->contact_form_url);
                                        $api->setWebsiteKey($captcha_sitekey);
                                        try {
                                            if (!$api->createTask()) {
                                                continue;
                                            }
                                        } catch (\Throwable $e) {
                                            // file_put_contents('ve.txt',$e->getMessage());
                                            $output->writeln($e);
                                        }
                                        
                                        $taskId = $api->getTaskId();
                                        
                                        if (!$api->waitForResult()) {
                                            continue;
                                        } else {
                                            $recaptchaToken = $api->getTaskSolution();
                                            if ((strpos($html, 'g-recaptcha')!==false)&&(strpos($html, 'g-recaptcha-response')==false)) {
                                                $domdocument = new \DOMDocument();
                                                $ff = $domdocument->createElement('input');
                                                $ff->setAttribute('name', 'g-recaptcha-response');
                                                $ff->setAttribute('value', $recaptchaToken);
                                                $formField = new \Symfony\Component\DomCrawler\Field\InputFormField($ff);
                                                $this->form->set($formField);
                                            } else {
                                                foreach ($this->form->all() as $key=>$val) {
                                                    if (strpos($key, 'recaptcha')!==false) {
                                                        $data[$key] = $recaptchaToken;
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } catch (\Throwable $e) {
                                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE);
                                $output->writeln($e);
                                continue;
                            }

                            sleep(3);
                            $crawler = $this->client->submit($this->form, $data);

                            $checkMessages = array("ありがとうございま","有難うございま","送信されました","送信しました","送信いたしました","自動返信メール","内容を確認させていただき","成功しました","完了いたしま");
                            $thank_check=true;
                            foreach ($checkMessages as $message) {
                                if (strpos($crawler->html(), $message) !==false) {
                                    $thank_check=false;
                                }
                            }
                                
                            $check = false;
                            if ($thank_check) {
                                foreach ($checkMessages as $message) {
                                    if (strpos($crawler->html(), $message)!==false) {
                                        $this->updateCompanyContact($companyContact, self::STATUS_SENT);
                                        $check =true;
                                        break;
                                    }
                                }
                            }
                            if (!$check) {
                                try {
                                    $crawler->filter('form')->each(function ($form) {
                                        try {
                                            if (strcasecmp($form->form()->getMethod(), 'get')) {
                                                if ((strpos($form->form()->getName(), 'login')!==false)||(strpos($form->form()->getName(), 'search')!==false)) {
                                                } else {
                                                    $this->checkform = $form->form();
                                                    $form->filter('input')->each(function ($input) {
                                                        try {
                                                            if ((strpos($input->outerhtml(), '送信')!==false)||(strpos($input->outerhtml(), 'back')!==false)||(strpos($input->outerhtml(), '修正')!==false)) {
                                                            } else {
                                                                $this->checkform = $input->form();
                                                            }
                                                        } catch (\Throwable $e) {
                                                        }
                                                    });
                                                }
                                            }
                                        } catch (\Throwable $e) {
                                            $output->writeln($e);
                                        }
                                    });
    
                                    if (empty($this->checkform)) {
                                        $iframes = array_merge($crawler->filter('iframe')->extract(['src']), $crawler->filter('iframe')->extract(['data-src']));
                                        foreach ($iframes as $i => $iframeURL) {
                                            try {
                                                $frameResponse = $this->client->request('GET', $iframeURL);
                                                if ($this->findContactForm($frameResponse)) {
                                                    $this->checkform = $this->form;
                                                    break;
                                                } else {
                                                    $frameResponse = $this->getPageHTMLUsingBrowser($iframeURL);
                                                    if ($this->findContactForm($frameResponse)) {
                                                        $this->checkform = $this->form;
                                                        break;
                                                    }
                                                }
                                            } catch (\Exception $e) {
                                                continue;
                                            }
                                        }
                                    }
                                    // if(empty($this->checkform)){
                                    //     $company->update(['status' => '送信失敗']);
                                    //     $companyContact->update([
                                    //         'is_delivered' => 1
                                    //     ]);
                                    //     $output->writeln("送信失敗1");
                                    //     continue;
                                    // }
                                    // var_dump($this->checkform);
                                    if (isset($this->checkform) && is_object($this->checkform) && !empty($this->checkform->all())) {
    
                                            // $this->checkform->setValues($data);
                                        $crawler = $this->client->submit($this->checkform);
                                        // var_dump($crawler);

                                        if (strpos($crawler->html(), "失敗") !== false) {
                                            $this->updateCompanyContact($companyContact, self::STATUS_FAILURE);
                                            continue;
                                        }

                                        $this->updateCompanyContact($companyContact, self::STATUS_SENT);
                                        continue;
                                    } else {
                                        $this->updateCompanyContact($companyContact, self::STATUS_SENT);
                                        continue;
                                    }
                                } catch (\Throwable $e) {
                                    $this->updateCompanyContact($companyContact, self::STATUS_SENT);
                                    $output->writeln($e);
                                    continue;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        $this->updateCompanyContact($companyContact, self::STATUS_FAILURE);
                        $output->writeln($e);
                        continue;
                    }
                    $output->writeln("end company");
                }
            }
        }
        return 0;
    }

    public function getCharset(string $htmlContent)
    {
        preg_match('/\<meta[^\>]+charset *= *["\']?([a-zA-Z\-0-9_:.]+)/i', $htmlContent, $matches);
        return $matches;
    }

    /**
     * Get page using browser.
     */
    public function getPageHTMLUsingBrowser(string $url)
    {
        $baseURL = parse_url(trim($url))['host'] ?? null;
        if (!$baseURL) {
            throw new \Exception('Invalid URL');
        }
        $response = $this->driver->get($url);

        return new Crawler($response->getPageSource(), $url, $baseURL);
    }

    /**
     * Init browser.
     */
    public function initBrowser()
    {
        $options = new ChromeOptions();
        $arguments = ['--disable-gpu', '--no-sandbox'];
        if (!$this->isDebug) {
            $arguments[] = '--headless';
        }
        $options->addArguments($arguments);

        $caps = DesiredCapabilities::chrome();
        $caps->setCapability('acceptSslCerts', false);
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);

        $this->driver = RemoteWebDriver::create('http://localhost:4444', $caps, 5000);
    }

    /**
     * Update company contact and company.
     *
     * @param mixed $companyContact
     * @param null  $message
     */
    public function updateCompanyContact($companyContact, int $status, $message = null)
    {
        $this->closeBrowser();

        $deliveryStatus = [
            self::STATUS_FAILURE => '送信失敗',
            self::STATUS_SENT => '送信済み',
            self::STATUS_SENDING => '未対応',
            self::STATUS_NO_FORM => 'フォームなし',
            self::STATUS_NG => 'NGワードあり',
        ];

        if (!array_key_exists($status, $deliveryStatus)) {
            throw new \Exception('Status is not found');
        }

        $companyContact->company->update(['status' => $deliveryStatus[$status]]);
        $companyContact->update(['is_delivered' => $status]);

        $reportAction = $status == self::STATUS_SENT ? 'info' : 'error';
        $this->{$reportAction}($message ?? $deliveryStatus[$status]);
    }
    
    
    /**
     * Close opening browser.
     */
    public function closeBrowser()
    {
        try {
            if ($this->driver) {
                $this->driver->manage()->deleteAllCookies();
                $this->driver->quit();
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * Whether the response contains contact form or not.
     *
     * @param mixed $response
     *
     * @return bool
     */
    public function findContactForm($response)
    {
        $hasTextarea = false;
        $response->filter('form')->each(function ($form) use (&$hasTextarea) {
            $inputs = $form->form()->all();
            foreach ($inputs as $input) {
                $isTextarea = $input->getType() == 'textarea' && !$input->isReadOnly();
                if ($isTextarea) {
                    $this->form = $form->form();
                    $this->html = $form->outerhtml();
                    $this->htmlText = $form->text();
                    $hasTextarea = true;
                    break;
                }
            }
        });

        return $hasTextarea;
    }


    /**
     * Submit using POST method.
     *
     * @param mixed $company
     * @param mixed $response
     */
    public function confirmByUsingCrawler($company, $response, int $confirmStep)
    {
        $confirmForm = null;
        $response->filter('form')->each(function ($form) use (&$confirmForm) {
            $isConfirmForm = !preg_match('/(login|search)/i', $form->form()->getName());
            if ($isConfirmForm) {
                $confirmForm = $form->form();
            }
        });

        if (!$confirmForm) {
            $iframes = $response->filter('iframe')->extract(['src']);
            foreach ($iframes as $iframeURL) {
                try {
                    $frameResponse = $this->client->request('GET', $iframeURL);
                    $response->filter('form')->each(function ($form) use (&$confirmForm) {
                        $isConfirmForm = !preg_match('/(login|search)/i', $form->form()->getName());
                        if ($isConfirmForm) {
                            $confirmForm = $form->form();
                        }
                    });
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        if (!$confirmForm) {
            throw new \Exception('Confirm form not found');
        }

        $this->data = array_map('strval', $this->data);
        $response = $this->client->submit($confirmForm, $this->data);
        $confirmHTML = $response->html();
        if ($this->isDebug) {
            file_put_contents(storage_path("html/{$company->id}_confirm{$confirmStep}.html"), $confirmHTML);
        }

        return $this->hasSuccessMessage($confirmHTML);
    }

    /**
     * Submit using POST method.
     *
     * @param mixed $company
     */
    public function submitByUsingCrawler($company)
    {
        try {
            $this->data = array_map('strval', $this->data);
            $response = $this->client->submit($this->form, $this->data);
            $responseHTML = $response->html();

            if ($this->isDebug) {
                file_put_contents(storage_path('html') . '/' . $company->id . '_submit.html', $responseHTML);
            }
            $isSuccess = $this->hasSuccessMessage($responseHTML);

            if ($isSuccess) {
                return;
            }
        } catch (\Exception $e) {
        }

        $confirmStep = 0;
        do {
            $confirmStep++;
            try {
                $isSuccess = $this->confirmByUsingCrawler($company, $response, $confirmStep);

                if ($isSuccess) {
                    return;
                }
            } catch (\Exception $e) {
                continue;
            }
        } while ($confirmStep < self::RETRY_COUNT);

        throw new \Exception('Confirm step is not success');
    }

    /**
     * Subtmit by using browser.
     *
     * @param mixed $company
     */
    public function submitByUsingBrower($company)
    {
        $formInputs = $this->form->all();
        foreach ($formInputs as $formKey => $formInput) {
            if (((strpos($formKey, 'wpcf7') !== false) || !isset($this->data[$formKey]) || empty($this->data[$formKey])) && !in_array($formInput->getType(), ['select'])) {
                continue;
            }
            try {
                $type = $formInput->getType();
                switch ($type) {
                    case 'checkbox':
                        $validKey = preg_replace('/\[\d+\]$/', '[]', $formKey);
                        $elementInput = $this->driver->findElement(WebDriverBy::cssSelector("input[type=\"{$type}\"][name=\"{$validKey}\"]"));
                        $checkbox = new WebDriverCheckboxes($elementInput);
                        $checkbox->selectByIndex(0);

                        break;
                    case 'radio':
                        $validKey = $formKey;
                        $elementInput = $this->driver->findElement(WebDriverBy::cssSelector("input[type=\"{$type}\"][name=\"{$formKey}\"]"));
                        $radio = new WebDriverRadios($elementInput);
                        $radio->selectByIndex(0);
                        break;
                    case 'select':
                        $select = new WebDriverSelect($this->driver->findElement(WebDriverBy::cssSelector("select[name=\"{$formKey}\"]")));
                        $select->selectByIndex(1);
                        break;
                    case 'hidden':
                        break;
                    case 'textarea':
                        $this->driver->findElement(WebDriverBy::cssSelector("textarea[name=\"{$formKey}\"]"))->sendKeys($this->data[$formKey]);
                        break;
                    default:
                        $this->driver->findElement(WebDriverBy::cssSelector("input[name=\"{$formKey}\"]"))->sendKeys($this->data[$formKey]);
                        break;
                }
            } catch (\Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
                if (isset($elementInput)) {
                    if ($elementInput->getAttribute('id')) {
                        $elementLabel = $this->driver->findElement(WebDriverBy::cssSelector("label[for=\"{$elementInput->getAttribute('id')}\"]"));
                        if ($elementLabel) {
                            $elementLabel->click();
                        }
                    } else {
                        $this->driver->executeScript('return document.querySelector(`input[type="' . $type . '"][name="' . $validKey . '"]`).parentNode.click()');
                    }
                }

                continue;
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($this->isDebug) {
            $this->driver->takeScreenshot(storage_path("screenshots/{$company->id}_fill.jpg"));
        }

        $confirmStep = 0;
        do {
            $confirmStep++;
            try {
                $isSuccess = $this->confirmByUsingBrowser($this->driver);
                if ($this->isDebug) {
                    $this->driver->takeScreenshot(storage_path("screenshots/{$company->id}_confirm{$confirmStep}.jpg"));
                }

                if ($isSuccess) {
                    $this->closeBrowser();

                    return;
                }
            } catch (\Exception $e) {
                continue;
            }
        } while ($confirmStep < self::RETRY_COUNT);

        $this->closeBrowser();

        throw new \Exception('Confirm step is not success');
    }

    /**
     * Hit confirm step.
     *
     * @param mixed $driver
     */
    public function confirmByUsingBrowser($driver)
    {
        $confirmElements = $driver->findElements(WebDriverBy::xpath(config('constant.xpathButton')));

        foreach ($confirmElements as $element) {
            try {
                $element->click();

                // Accept alert confirm
                $driver->switchTo()->alert()->accept();
            } catch (\Exception $exception) {
                // Do nothing
            }
        }

        $successTexts = $driver->findElements(WebDriverBy::xpath(config('constant.xpathMessage')));

        return count($successTexts) > 0;
    }

    /**
     * Is success or not.
     *
     * @return bool
     */
    public function hasSuccessMessage(string $htmlContent)
    {
        $successMessages = config('constant.successMessages');

        return $this->containsAny($htmlContent, $successMessages);
    }

    /**
     * Check if string contains any string.
     *
     * @return bool
     */
    public function containsAny(string $string, array $list)
    {
        return collect($list)->contains(function ($item) use ($string) {
            return strpos($string, $item) !== false;
        });
    }
}
