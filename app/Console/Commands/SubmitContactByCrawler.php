<?php

namespace App\Console\Commands;

use App\Models\CompanyContact;
use App\Models\Config;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverCheckboxes;
use Facebook\WebDriver\WebDriverRadios;
use Facebook\WebDriver\WebDriverSelect;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\HttpClient\HttpClient;

class SubmitContactByCrawler extends Command
{
    public const STATUS_FAILURE = 1;
    public const STATUS_SENT = 2;
    public const STATUS_SENDING = 3;
    public const STATUS_NO_FORM = 4;
    public const STATUS_NG = 5;
    public const STATUS_RETRY = 10;

    public const RETRY_COUNT = 4;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submit:contact-crawler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $driver;
    protected $form;
    protected $html;
    protected $htmlText;
    protected $data;
    protected $isDebug = false;

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
        $this->isDebug = config('app.debug');

        if (
            ($config->start && $config->end)
            && (
                now()->lt(Carbon::createFromTimestamp(strtotime(now()->format('Y-m-d') . ' ' . $config->start)))
                || now()->gt(Carbon::createFromTimestamp(strtotime(now()->format('Y-m-d') . ' ' . $config->end)))
            )
        ) {
            $this->error('Out of time');

            return 0;
        }

        $companyContacts = CompanyContact::with(['contact'])->lockForUpdate()->where('is_delivered', self::STATUS_RETRY)->limit(env('MAIL_LIMIT'))->get();
        if (count($companyContacts)) {
            $companyContacts->toQuery()->update(['is_delivered' => self::STATUS_SENDING]);
        }

        foreach ($companyContacts as $companyContact) {
            if (!$companyContact->contact) {
                continue;
            }
            $contact = $companyContact->contact;
            $company = $companyContact->company;

            if (
                !$company->contact_form_url
                || (
                    $contact->date
                    && $contact->time
                    && now()->lt(Carbon::createFromTimestamp(strtotime("{$contact->date} {$contact->time}")))
                )
            ) {
                $this->info('Skip: ' . $companyContact->id);

                return 0;
            }

            $this->data = [];
            $this->driver = null;
            $this->html = null;
            $this->htmlText = null;
            $crawler = null;

            $this->info('==============================================');
            $this->info("Company contact {$companyContact->id}: {$company->contact_form_url}");

            try {
                $this->initBrowser();
            } catch (\Exception $e) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
                continue;
            }

            try {
                // $crawler = $this->client->request('GET', $company->contact_form_url);
                $crawler = $this->getPageHTMLUsingBrowser($company->contact_form_url);
            } catch (\Exception $e) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
                continue;
            }

            if (!$crawler) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, 'Cannot get content from url');
                continue;
            }

            $this->html = $crawler->html();
            $this->htmlText = $crawler->text();
            file_put_contents(storage_path("html/{$companyContact->id}_response.html"), $this->html);

            $hasContactForm = $this->findContactForm($crawler);
            if (!$hasContactForm) {
                $iframes = $crawler->filter('iframe')->extract(['src']);
                foreach ($iframes as $i => $iframeURL) {
                    try {
                        // $frameResponse = $this->client->request('GET', $iframeURL);
                        $frameResponse = $this->getPageHTMLUsingBrowser($iframeURL);
                        file_put_contents(storage_path("html/{$companyContact->id}_frame{$i}.html"), $frameResponse->html());
                        $hasFrameContactForm = $this->findContactForm($frameResponse);
                        if ($hasFrameContactForm) {
                            $hasContactForm = true;
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

            $footerHTML = '';
            try {
                $footerHTML = $crawler->filter('#footer')->html();
            } catch (\Exception $e) {
                // Do nothing
            }

            $nonStrings = ['営業お断り', 'サンプル', '有料', '代引き', '着払い', '資料請求', 'カタログ'];
            $isFooterInvalid = $this->containsAny($footerHTML, $nonStrings);
            if ($isFooterInvalid) {
                $this->updateCompanyContact($companyContact, self::STATUS_NG, 'Content is unacceptable');
                continue;
            }

            if (!$this->form->getValues()) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, 'Form does not have values');
                continue;
            }

            $sections = [
                'kana' => [
                    'part' => [],
                    'match' => ['kana', 'フリガナ', 'Kana', 'namek', 'f-', 'ふり', 'kn', 'furigana'],
                    'transform' => [$contact->fu_surname, $contact->fu_lastname],
                ],
                'name' => [
                    'part' => [],
                    'match' => ['nam', '名前', '氏名'],
                    'transform' => [$contact->surname, $contact->lastname],
                ],
                'postal' => [
                    'part' => [],
                    'match' => ['post', '郵便番号', 'yubin', 'zip', '〒', 'pcode'],
                    'transform' => [$contact->postalCode1, $contact->postalCode2],
                ],
                'phone' => [
                    'part' => [],
                    'match' => ['tel', 'TEL', 'phone', '電話番号'],
                    'transform' => [$contact->phoneNumber1, $contact->phoneNumber2, $contact->phoneNumber3],
                ],
                'fax' => [
                    'part' => [],
                    'match' => ['fax', 'FAX'],
                    'transform' => [$contact->phoneNumber1, $contact->phoneNumber2, $contact->phoneNumber3],
                ],
            ];

            foreach ($this->form->all() as $key => $input) {
                if ((!isset($this->data[$key]) || empty($this->data[$key])) && $input->isHidden() != 'hidden' && strpos($key, 'wpcf7') !== false) {
                    $this->data[$key] = $input->getValue();
                }

                if ($input->isReadOnly()) {
                    continue;
                }

                try {
                    $this->mapForm($key, $input, $companyContact);
                } catch (\Exception $e) {
                    continue;
                }

                // Stop adding key if key is already set in $sections
                $isPartExisted = false;
                foreach ($sections as $sectionKey => $section) {
                    if ($this->containsAny($key, $section['match']) && !$isPartExisted) {
                        $sections[$sectionKey]['part'][] = $key;
                        $isPartExisted = true;
                    }
                }
            }

            foreach ($sections as $section) {
                if (count($section['part']) >= count($section['transform'])) {
                    // get the last x items in part then transform them to the last x items in transform
                    $matches = array_values(array_slice($section['part'], -count($section['transform']), count($section['transform']), true));
                    foreach ($matches as $i => $match) {
                        if (isset($this->data[$matches[$i]]) || (!empty($this->data[$matches[$i]]))) {
                            continue;
                        }
                        $this->data[$matches[$i]] = $section['transform'][$i];
                    }
                } elseif (count($section['part']) == 1) {
                    // merge all part to the first part if there is only one part
                    if (isset($this->data[$section['part'][0]]) || (!empty($this->data[$section['part'][0]]))) {
                        continue;
                    }
                    $this->data[$section['part'][0]] = implode('', $section['transform']);
                }
            }

            $this->info('Data: ' . var_export($this->data, true));

            $javascriptCheck = strpos($crawler->html(), 'recaptcha') === false;
            if ($javascriptCheck) {
                try {
                    $this->submitByUsingBrower($company, $this->data);
                    $this->updateCompanyContact($companyContact, self::STATUS_SENT);
                } catch (\Exception $e) {
                    $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
                }
                continue;
            }

            // captcha, try to solve it
            try {
                if (isset($captcha_sitekey)) {
                    unset($captcha_sitekey);
                }
                // $captchaImg = $crawler->filter('.captcha img')->extract(['src'])[0];
                if (strpos($crawler->html(), 'api.js?render') !== false) {
                    $key_position = strpos($crawler->html(), 'api.js?render');
                    if (isset($key_position)) {
                        $captcha_sitekey = substr($crawler->html(), $key_position + 14, 40);
                    }
                } elseif (strpos($crawler->html(), 'changeCaptcha') !== false) {
                    $key_position = strpos($crawler->html(), 'changeCaptcha');
                    if (isset($key_position)) {
                        $captcha_sitekey = substr($crawler->html(), $key_position + 15, 40);
                    }
                } elseif (strpos($crawler->text(), 'sitekey') !== false) {
                    $key_position = strpos($crawler->text(), 'sitekey');
                    if (isset($key_position)) {
                        if ((substr($crawler->text(), $key_position + 9, 1) == "'" || (substr($crawler->text(), $key_position + 9, 1) == '"'))) {
                            $captcha_sitekey = substr($crawler->text(), $key_position + 10, 40);
                        } elseif ((substr($crawler->text(), $key_position + 11, 1) == "'" || (substr($crawler->text(), $key_position + 11, 1) == '"'))) {
                            $captcha_sitekey = substr($crawler->text(), $key_position + 12, 40);
                        }
                    }
                }
                if (!isset($captcha_sitekey) || str_contains($captcha_sitekey, ',')) {
                    if (strpos($crawler->html(), 'data-sitekey') !== false) {
                        $key_position = strpos($crawler->html(), 'data-sitekey');
                        if (isset($key_position)) {
                            $captcha_sitekey = substr($crawler->html(), $key_position + 14, 40);
                        }
                    } elseif (strpos($crawler->html(), 'wpcf7submit') !== false) {
                        $key_position = strpos($crawler->html(), 'wpcf7submit');
                        if (isset($key_position)) {
                            $str = substr($crawler->html(), $key_position);
                            $captcha_sitekey = substr($str, strpos($str, 'grecaptcha') + 13, 40);
                        }
                    }
                }

                if (strpos($crawler->html(), 'recaptcha') !== false && !$this->isDebug) {
                    if (isset($captcha_sitekey) && !str_contains($captcha_sitekey, ',')) {
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
                        } catch (\Exception $e) {
                            $this->error($e->getMessage());
                        }

                        $taskId = $api->getTaskId();

                        if (!$api->waitForResult()) {
                            continue;
                        }
                        $recaptchaToken = $api->getTaskSolution();
                        if ((strpos($this->html, 'g-recaptcha') !== false) && (strpos($this->html, 'g-recaptcha-response') == false)) {
                            $domdocument = new \DOMDocument();
                            $ff = $domdocument->createElement('input');
                            $ff->setAttribute('name', 'g-recaptcha-response');
                            $ff->setAttribute('value', $recaptchaToken);
                            $formField = new InputFormField($ff);
                            $this->form->set($formField);
                        } else {
                            foreach ($this->form->all() as $key => $val) {
                                if (strpos($key, 'recaptcha') !== false) {
                                    $this->data[$key] = $recaptchaToken;
                                    break;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
                continue;
            }

            try {
                $this->submitByUsingCrawler($company, $this->data);
                $this->updateCompanyContact($companyContact, self::STATUS_SENT);
            } catch (\Exception $e) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
            }
        }
    }

    /**
     * Is success or not.
     *
     * @return bool
     */
    public function hasSuccessMessage(string $htmlContent)
    {
        $successMessages = ['ありがとうございま', '有難うございま', '送信されま', '送信しました', '送信いたしま', '自動返信メール', '内容を確認させていただき', '成功しました', '完了いたしま', '受け付けま'];

        return $this->containsAny($htmlContent, $successMessages);
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
                }
            }
        });

        return $hasTextarea;
    }

    /**
     * Mapping form.
     *
     * @param mixed $input
     * @param mixed $companyContact
     */
    public function mapForm(string $key, $input, $companyContact)
    {
        $contact = $companyContact->contact;
        $company = $companyContact->company;
        $type = $input->getType();
        switch ($type) {
            case 'select':
                $hasArea = false;
                $options = $input->getOptions();
                foreach ($options as $option) {
                    if ($option['value'] == $contact->area) {
                        $this->data[$key] = $contact->area;
                        $hasArea = true;
                    }
                }
                if (!$hasArea) {
                    $this->data[$key] = $options[count($options) - 1]['value'];
                }
                break;
            case 'radio':
                $options = $input->getOptions();
                $choosenKey = in_array($key, ['性別', 'sex']) ? 0 : count($options) - 1;
                $this->data[$key] = $options[$choosenKey]['value'];
                foreach ($options as $option) {
                    if ($option['value'] == 'その他') {
                        $this->data[$key] = $option['value'];
                    }
                }
                break;
            case 'checkbox':
                $this->data[$key] = $input->getOptions()[0]['value'];
                break;
            case 'textarea':
                if (!preg_match('/(captcha|address)/i', $key)) {
                    $content = str_replace('%company_name%', $company->name, $contact->content);
                    $content = str_replace('%myurl%', route('web.read', [$contact->id, $company->id]), $content);
                    $this->data[$key] = $content;
                }
                break;
            case 'email':
                $this->data[$key] = $contact->email;
                break;
            case 'number':
                $this->data[$key] = 1;
                break;
            case 'date':
                $this->data[$key] = date('Y-m-d', strtotime('+1 day'));
                break;
            case 'default':
                $this->data[$key] = 'きょうわ';
                break;
            default:
                break;
        }

        if (isset($this->data[$key]) && !empty($this->data[$key])) {
            return;
        }

        $mapper = [
            [
                'pattern' => ['氏名（カナ）', 'フリガナ'],
                'match' => ['company-kana', 'company_furi', 'フリガナ', 'kcn', 'ふりがな',
                    'singleAnswer(ANSWER3-1)', 'singleAnswer(ANSWER3-2)',
                    'department', 'f000003200', 'f000003202', 'f000003194',
                    'ext_04', 'kana', 'フリガナ(必須)',
                    'ReqKind',  'cde_Gst_Furigana',
                    'f000027212', 'f000027213', 'singleAnswer(ANSWER3402)', 'qEnq5464', 'qEnq5465',
                    'company-kana', 'company_furi', 'フリガナ', 'kcn', 'ふりがな', 'NAME_F', 'kana_name_sei',
                    'e_26', 'input9', 'busyo',
                    'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID10$fldValue$txtSingleTextBox',
                    'aform-field-187', 'furi1', 'furi2', 'f000224117', 'f000224108', 'RequestForm$Attr-2-2', 'RequestForm$Attr-2-4',
                    'txtName',
                ],
                'transform' => 'ナシ',
            ],
            [
                'match' => ['company', 'cn', 'kaisha', 'cop', 'corp', '会社', '社名', 'タイトル',
                    'txtCompanyName', 'f000003193', 'singleAnswer(ANSWER3405)', 'singleAnswer(ANSWER3406)',
                    'company', 'cn', 'kaisha', 'cop', 'corp', '会社', '社名', 'タイトル', 'fCompany', 'UserCompanyName', 'en1244884030',
                    'item_maker', 'organization', 'f000104023', 'txtCompany', 'txtDepart', 'section', 'product', 'dataCompany',
                    'e2', 'RequestForm$Attr-2-1', 'RequestForm$Attr-2-3', 'singleAnswer(ANSWER162)', 'singleAnswer(ANSWER262)',
                    'txtCompName', 'txtPostName',
                ],
                'pattern' => ['会社名', '企業名', '貴社名', '御社名', '法人名', '団体名', '機関名',
                    '屋号', '組織名', 'お店の名前', '社名', '店舗名', '職種',
                    '会社名', '機関名', 'お名前 フリガナ (全角カナ)', ],
                'transform' => $contact->company,
            ],
            [
                'match' => ['mail_add', 'mail', 'Mail', 'mail_confirm', 'ールアドレス', 'M_ADR', '部署',
                    'E-Mail', 'メールアドレス', 'Email', 'email', 'f000026560', '03.E-メール', '03.E-メール2',
                    'mail_address_confirm', 'qEnq5463', 'f000003203', 'f000003203:cf',
                    'singleAnswer(ANSWER4)', 'mail_add', 'mail', 'Mail', 'mail_confirm',
                    'ールアドレス', 'M_ADR', '部署',
                    'singleAnswer(ANSWER4-R)', 'c_q18_confirm',
                    'mailaddress', 'i_email', 'i_email_check', 'email(必須)', 'confirm_email(必須)',
                    'c_q8', 'c_q8_confirm', 'f000027220', 'f000027221', 'en1262055277_match', 'f012956240',
                    'input30', 'your-email', 're_mail', 'e_2274_re', 'mailaddress_confirm', 'query[3]',
                    'EMAIL', 'EMAIL2', 'email_confirm', 'INQ_MAIL_ADDR_CONF', 'mail_address2', 'mailConfirm',
                    'f000104021', 'f000104021:cf', 'MAIL_CONF', 'RE_MAILADDRESS', 'RequestForm$Attr-5-2',
                    'singleAnswer(ANSWER163)', 'item_12', 'c_q37_confirm', 'c_q25_confirm', 'e_28',
                ],
                'pattern' => ['メールアドレス', 'メールアドレス(確認用)', 'Mail アドレス', 'E-mail (半角)', 'ペライチに登録しているメールアドレス', 'メールアドレス［確認］
                （E-mail）', 'メールアドレス（確認用）', 'メールアドレス（確認）'],
                'key' => ['singleAnswer(ANSWER4)', 'singleAnswer(ANSWER4-R)', 'mailaddress', 'mailaddress2', 'email', 'f012956240:cf', 'f000224114', 'f000224114:cf'],
                'transform' => $contact->email,
            ],
            [
                'match' => ['addressnum', 'zip', 'zipcode1',
                    'f000026563:a', 'txt_zipcode[]', 'zip-code', 'ZIP1', 'zipcode[data][0]', 'f013017420:a', 'txtZip1', ],
                'key' => ['ZipcodeL', 'j_zip_code_1', 'f000003518:a', 'item_14_zip1', 'C019_LAST'],
                'transform' => $contact->postalCode1,
            ],
            [
                'match' => ['郵便番号', 'zipcode', 'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID15$fldValue$txtSingleTextBox',
                    'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID20$fldValue$txtSingleTextBox', ],
                'key' => ['zipcode', 'postal'],
                'transform' => $contact->postalCode1 . $contact->postalCode2,
            ],
            [
                'match' => ['field_2437489_2', 'f000003518:t',
                    'zip[data][1]', 'item_14_zip2', 'c_q10_right',
                    'zip2', 'j_zip_code_2', 'c_q3_right', 'f000026563:t', 'txt_zipcode[]',
                    'zip-code-4', 'ZIP2', 'field_2437489_3', 'zipcode[data][1]', 'f013017420:t', 'zip02',
                    'ZIPCODE2_HOME', 'c_q31_right', 'txtZip2',
                ],
                'key' => ['zip1', '郵便番号(必須)', 'C019_FIRST'],
                'transform' => $contact->postalCode2,
            ],
            [
                'match' => ['fZipCode', 'efo-form01-apa-zip', '郵便番号', 'addressnum', 'postal-code',
                    'en1240790938', 'input34', 'RequestForm$Attr-4-1',
                ],
                'pattern' => ['郵便番号', '〒', '郵便番号 (半角数字のみ)'],
                'transform' => $contact->postalCode1 . '-' . $contact->postalCode2,
            ],
            [
                'match' => ['住所', 'addr', 'add_detail', 'town', 'f000003520', 'f000003521', 'add2',
                    'c_q21', 'block', 'ext_08', 'fCity', 'fBuilding', 'efo-form01-district',
                    '住所', 'addr', 'item117', 'UserAddress', '番地', '建物名・施設名',
                    'f000027223', 'f000027225', 'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID17$fldValue$txtSingleTextBox',
                    'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID18$fldValue$txtSingleTextBox', 'query[10]',
                    'ADDR_2', 'ADDR_3', 'building', 'f000224118', 'add_01', 'add_02', 'RequestForm$Attr-4-4', 'txtAddress', 'e_25', 'e_27',
                ],
                'pattern' => ['住所', '所在地', '市区',
                    '町名', '建物名・施設名', 'item117', 'ご住所', '市区町村郡/町名/丁目', 'C020', ],
                'transform' => $contact->address,
            ],
            [
                'match' => ['title', 'subject', '件名', 'pref', 'job', 'form_fields[field_42961a5]', 'executive', 'text', 'singleAnswer(ANSWER263)'],
                'pattern' => ['件名', 'Title', 'Subject', '題名', '用件名'],
                'transform' => $contact->title,
            ],
            [
                'match' => ['URL', 'url', 'HP'],
                'transform' => $contact->homepageUrl,
            ],
            [
                'match' => ['姓', 'lastname', 'name1', 'singleAnswer(ANSWER2-1)', 'f000003197', 'i_name_sei', 'fFirstName', 'お名前（漢字）[]', 'c_q16_first', 'sei', 'Public::Application::Userenquete_D__P__D_name2', 'f000027211', 'LastName', 'query[1][1]', '162441_68591pi_162441_68591', 'txtName2', 'customer[last_name]', 'singleAnswer(ANSWER158)', 'c_q4_second', 'c_q10_second'],
                'key' => ['f013008539', 'seiName'],
                'transform' => $contact->lastname,
            ],
            [
                'match' => ['名', 'firstname', 'name2', 'given_name', 'txtNameMei', 'singleAnswer(ANSWER2-2)', 'f000003198', 'i_name_mei', 'name-mei', 'c_q23_second', 'fLastName', 'お名前（漢字）[]', 'c_q16_second', 'f000027210', 'fname',  'f013017368', 'mei', 'FirstName', 'txtName1', 'customer[first_name]', 'RequestForm$Attr-3-1', 'singleAnswer(ANSWER159)'],
                'key' => ['f013008540'],
                'transform' => $contact->surname,
            ],
            [
                'match' => [
                    'ご担当者名', 'お名前(必須)', 'UserName', 'singleAnswer(ANSWER3400)', 'qEnq5461', 'qEnq5462', 'ご担当者名', 'NAME', 'f013008540', 'f013017369',
                    'input8', 'your-name', 'dnn$ctr434$ViewMailForm$grdMain$PageID3$repCategory$ctl01$repField$ItemID9$fldValue$txtSingleTextBox',
                    'f000104020', 'C016', 'G012', 'RequestForm$Attr-3-2', 'c_q24_second',
                ],
                'pattern' => ['名前', '氏名', '担当者', '差出人', 'ネーム', 'お名前(漢字)', 'お名前(必須)', 'お名前', 'おなまえ'],
                'transform' => $contact->surname . $contact->lastname,
            ],
            [
                'match' => ['your-name-ruby', 'form_answers[parts][8df997826280be8a58fc27fc61ad3da96f63fccf][6bc765a30a0115f51a47c62b94196fa3ef7d3df8]', 'kana_s'],
                'pattern' => ['名前', '氏名', '担当者', '差出人', 'ネーム', 'お名前(漢字)', 'お名前(必須)', 'お名前'],
                'transform' => $contact->fu_surname . $contact->fu_lastname,
            ],
            [
                'match' => ['セイ', 'せい', 'lastname_kana', 'sei_kana', 'kana_sei', 'furi_sei', 'txtNameSeiFuri',
                    'i_kana_sei', 'name-furi-sei', 'c_q22_first', 'fFirstNamey', 'c_q17_first',
                    'Public::Application::Userenquete_D__P__D_name1_ka', 'first_kana', 'sei_k', 'meiName',
                    'aform-field-276-firstname-kana', '担当者名：姓（カナ）', 'aform-field-166-firstname-kana',
                    'NAME_F_SEI', 'RequestForm$Attr-3-3', 'firstkana', 'singleAnswer(ANSWER161)', 'c_q29_first', ],
                'pattern' => ['名 フリガナ'],
                'transform' => $contact->fu_surname,
            ],
            [
                'match' => [
                    'メイ', 'めい', 'firstname_kana', 'mei_kana', 'kana_mei', 'e_8276', 'furi_neme', 'i_kana_mei', 'name-furi-mei', 'c_q22_second', 'fLastNamey', 'c_q17_second', 'Public::Application::Userenquete_D__P__D_name2_ka', 'last_kana', 'mei_k', 'query[2][1]',
                    'form_answers[parts][235b8adea4b8bc1685dd57688c0f9cab0d03ca86][a2ea06f5af2b22a6fb316451a4e00ba3b32a0781]', '担当者名：名（カナ）',
                    'customer[last_name_reading]', 'NAME_F_MEI', 'RequestForm$Attr-3-4', 'lastkana', 'singleAnswer(ANSWER160)', 'c_q29_second',
                ],
                'pattern' => ['姓 フリガナ'],
                'transform' => $contact->fu_lastname,
            ],
            [
                'pattern' => ['都道府県'],
                'match' => ['info_perception_etc', 't_message', 'お問い合わせ内容(必須)', 'fSection', 'fPosition', 'fOption1', 'fOption3', 'position', 'industry', 'Public::Application::Userenquete_D__P__D_division', 'item_spec', 'your-message'],
                'transform' => $contact->area,
            ],
            [
                'pattern' => ['fax', 'FAX番号', '電話', '携帯電話', '連絡先', 'TEL', 'Phone', '電話番号2', '電話番号', '確認のため再度ご入力下さい。', 'C021', 'c_q30'],
                'match' => ['FAX', 'singleAnswer(ANSWER3408)', '電話番号'],
                'transform' => $contact->phoneNumber1 . $contact->phoneNumber2 . $contact->phoneNumber3,
            ],
            [
                'match' => ['FAX', 'txtTEL', 'singleAnswer(ANSWER5)', 'singleAnswer(ANSWER6)', 'input/zip_code', 'telnum',
                    'fTel', 'fFax', '市区町村', 'input35', 'cp_tels', 'RequestForm$Attr-5-1', 'singleAnswer(ANSWER164)',
                ],
                'key' => ['txtTEL', 'tel'],
                'transform' => $contact->phoneNumber1 . '-' . $contact->phoneNumber2 . '-' . $contact->phoneNumber3,
            ],
            [
                'match' => [
                    'f000003204:a', 'f000009697:a', 'i_tel1', 'tel[data][0]', 'tel00_s', 'tel_:a',
                    'c_q9_areacode', 'TelNumber1', 'f000026565:a', 'txt_tel[]', 'form-tel[data][0]',
                    'inputs[fax1]',  'tel_no_1', 'f012956241:a', 'Tel1', 'phone', 'query[11][0]',
                    'query[5][0]', 'f000224113:a', 'f000224112:a', 'c_q28_areacode', 'txtPhonea',
                ],
                'key' => ['PhoneL', 'tel[data][0]', 'item_16_phone1', 'item_17_phone1', 'e_28[tel1]', 'tel01', 'phone', 'tel-num[data][0]', 'fax-num[data][0]', 'inq_tel[data][0]', 'TEL1'],
                'transform' => $contact->phoneNumber1,
            ],
            [
                'match' => [
                    'PhoneC', 'f000003204:e', 'f000009697:e', 'i_tel2', 'tel[data][1]', 'item_16_phone2',
                    'tel01_s', 'tel_:e', 'c_q9_citycode', 'TelNumber2', 'f000026565:e',
                    'txt_tel_1', 'tel_no_2', 'f012956241:e', 'tel02', 'Tel2', 'query[11][1]',
                    'query[5][1]', 'tkph971-2',  'f000224112:e', 'f000224113:e', 'c_q27_citycode', 'c_q28_citycode',
                    'c_q27_subscribercode', 'txtPhoneb', 'txtPhonec',
                ],
                'key' => ['tel-num[data][1]', 'fax-num[data][1]', 'inq_tel[data][1]', 'inq_tel[data][2]', 'TEL2'],
                'transform' => $contact->phoneNumber2,
            ],
            [
                'match' => ['PhoneR', 'f000003204:n', 'f000009697:n', 'i_tel3', 'tel[data][2]', 'item_16_phone3', 'tel02_s', 'tel_:n', 'c_q9_subscribercode', 'TelNumber3', 'f000026565:n', 'txt_tel_2', 'tel_no_3', 'f012956241:n', 'tel03', 'Tel3', 'query[11][2]', 'query[5][2]', 'tkph971-3', 'f000224112:n', 'f000224113:n'],
                'key' => ['TEL3'],
                'transform' => $contact->phoneNumber2,
            ],
            [
                'match' => ['ext_07', '市区町村', 'fHouseNumber'],
                'transform' => mb_substr($contact->address, 0, 3),
            ],
            [
                'match' => ['丁目番地', '建物名'],
                'transform' => 0,
            ],
            [
                'pattern' => ['部署'],
                'transform' => 'なし',
            ],
            [
                'pattern' => ['オーダー'],
                'transform' => 'order',
            ],
            [
                'pattern' => ['年齢', '築年数'],
                'transform' => 35,
            ],
            [
                'pattern' => ['answer[category]'],
                'transform' => 1,
            ],
            [
                'pattern' => ['fUrl', '作成中ページの公開用URL'],
                'key' => ['e_29', 'customer[web_site]'],
                'transform' => $contact->myurl,
            ],
            [
                'match' => ['f012956299:y', 'Birthday1'],
                'transform' => '2022',
            ],
            [
                'match' => ['f012956299:m'],
                'transform' => '03',
            ],
            [
                'match' => ['f012956299:d'],
                'transform' => '04',
            ],
        ];

        foreach ($mapper as $map) {
            // Check if form key contains any string on 'match' array, then use that value
            if (isset($map['match']) && $this->containsAny($key, $map['match'])) {
                $this->data[$key] = $map['transform'];
            }

            // Check if html contains any string on 'pattern' array, then search the next input with that name in html and use that value
            if (isset($map['pattern'])) {
                foreach ($map['pattern'] as $pattern) {
                    if (strpos($this->htmlText, $pattern) !== false) {
                        $stringToSearch = substr($this->html, strpos($this->html, $pattern) - 6);
                        preg_match('/name="(?<name>[A-z0-9-]+)"/m', $stringToSearch, $match);
                        if (isset($match['name']) && (!isset($this->data[$match['name']]) || empty($this->data[$match['name']]))) {
                            $this->data[$match['name']] = $map['transform'];
                        }
                    }

                    if (isset($map['key'])) {
                        foreach ($map['key'] as $value) {
                            $this->data[$value] = $map['transform'];
                        }
                    }
                }
            }
        }
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

        $response = $this->client->submit($confirmForm, $this->data);
        $confirmHTML = $response->html();
        file_put_contents(storage_path("html/{$company->id}_confirm{$confirmStep}.html"), $confirmHTML);

        return $this->hasSuccessMessage($confirmHTML);
    }

    /**
     * Submit using POST method.
     *
     * @param mixed $company
     */
    public function submitByUsingCrawler($company)
    {
        $response = $this->client->submit($this->form, $this->data);

        $responseHTML = $response->html();
        file_put_contents(storage_path('html') . '/' . $company->id . '_submit.html', $responseHTML);
        $isSuccess = $this->hasSuccessMessage($responseHTML);

        if ($isSuccess) {
            return;
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
            if ((strpos($formKey, 'wpcf7') !== false) || !isset($this->data[$formKey]) || empty($this->data[$formKey])) {
                continue;
            }
            try {
                $type = $formInput->getType();
                switch ($type) {
                    case 'checkbox':
                        $validKey = preg_replace('/\[\d+\]$/', '[]', $formKey);
                        $checkbox = new WebDriverCheckboxes($this->driver->findElement(WebDriverBy::cssSelector("input[type=\"{$type}\"][name=\"{$validKey}\"]")));
                        $checkbox->selectByIndex(0);
                        break;
                    case 'radio':
                        $radio = new WebDriverRadios($this->driver->findElement(WebDriverBy::cssSelector("input[type=\"{$type}\"][name=\"{$formKey}\"]")));
                        $radio->selectByIndex(0);
                        break;
                    case 'select':
                        $select = new WebDriverSelect($this->driver->findElement(WebDriverBy::cssSelector("select[name=\"{$formKey}\"]")));
                        $select->selectByValue($this->data[$formKey]);
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
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->driver->takeScreenshot(storage_path("screenshots/{$company->id}_fill.jpg"));

        $confirmStep = 0;
        do {
            $confirmStep++;
            try {
                $isSuccess = $this->confirmByUsingBrowser($this->driver);
                $this->driver->takeScreenshot(storage_path("screenshots/{$company->id}_confirm{$confirmStep}.jpg"));

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
        $confirmElements = $driver->findElements(WebDriverBy::xpath('
            //button[contains(text(),"確認")]
            | //button[@type="submit"][contains(@data-disable-with-permanent, "true")]
            | //input[@type="submit" and contains(@value,"入力内容を確認する")]
            | //input[@type="submit" and contains(@value,"送信")]
            | //button[@type="submit" and contains(@value,"送信")]
            | //input[@type="submit" and contains(@value,"内容確認へ")]
            | //input[@type="submit" and contains(@value,"入力内容確認")]
            | //input[@type="submit" and contains(@value,"送信する")]
            | //*[contains(text(), "この内容で送信する")]
            | //*[contains(text(),"に同意する")]
            | //*[contains(text(),"確認する")]
            | //a[@id="js__submit"]
            | //a[contains(text(),"次へ")]
            | //a[contains(text(),"確認")]
            | //a[contains(text(),"送信")]
            | //button[@type="submit"][contains(@value,"send")]
            | //button[@type="submit"][contains(@name,"_exec")]
            | //button[@type="submit" and (contains(@class,"　上記の内容で送信する　"))]
            | //button[@class="nttdatajpn-submit-button"]
            | //button[@type="button" and (contains(@class,"ahover"))]
            | //button[@type="submit" and (contains(@class,"mfp_element_submit"))]
            | //button[@type="submit"][contains(@name,"__送信ボタン")]
            | //button[@type="submit" ][contains(@class, "btn")]
            | //button[@type="submit"][contains(@value,"この内容で無料相談する")]
            | //button[@type="submit"]//span[contains(text(),"同意して進む")]
            | //button[@type="submit" and @class="btn"]
            | //button[contains(@value,"送信")]
            | //button[contains(text(),"上記の内容で登録する")]
            | //button[contains(text(),"次へ")]
            | //button[contains(text(),"送　　信")]
            | //button[contains(text(),"送信")]
            | //button[span[contains(text(),"送信")]]
            | //input[@type="button" and @id="submit_confirm"]
            | //input[@type="image"][contains(@value,"この内容で登録する") and @type!="hidden"]
            | //input[@type="image"][contains(@alt,"この内容で送信する") and @type!="hidden"]
            | //input[@type="image"][contains(@alt,"この内容で送信する") and @type!="hidden"]
            | //input[contains(@alt,"確認") and @type!="hidden"]
            | //input[@type="image"][contains(@name,"conf") and @type!="hidden"]
            | //input[@type="submit" and contains(@value,"送信する")]
            | //input[@type="submit" and not(contains(@value,"戻る") or contains(@value,"クリア"))]
            | //input[contains(@alt,"次へ") and @type!="hidden"]
            | //input[contains(@value,"次へ") and @type!="hidden"]
            | //input[contains(@value,"確 認") and @type!="hidden"]
            | //input[contains(@value,"確認") and @type!="hidden"]
            | //input[contains(@value,"送　信") and @type!="hidden"]
            | //input[contains(@value,"送信") and @type!="hidden"]
            | //input[@type="checkbox"]
            | //label[@for="sf_KojinJouhou__c" and not(contains(@value,"戻る") or contains(@value,"クリア"))]
        '));

        foreach ($confirmElements as $element) {
            try {
                $element->click();

                // Accept alert confirm
                $driver->switchTo()->alert()->accept();
            } catch (\Exception $exception) {
                // Do nothing
            }
        }

        $successTexts = $driver->findElements(WebDriverBy::xpath('
            //*[contains(text(),"ありがとうございま")]
            | //*[contains(text(),"メール送信が正常終了")]
            | //*[contains(text(),"内容を確認させていただき")]
            | //*[contains(text(),"受け付けま")]
            | //*[contains(text(),"問い合わせを受付")]
            | //*[contains(text(),"完了いたしま")]
            | //*[contains(text(),"完了しまし")]
            | //*[contains(text(),"成功しました")]
            | //*[contains(text(),"有難うございま")]
            | //*[contains(text(),"自動返信メール")]
            | //*[contains(text(),"送信いたしま")]
            | //*[contains(text(),"送信されま")]
            | //*[contains(text(),"送信しました")]
            | //*[contains(text(),"送信完了")]
            | //*[text()[contains(.,"受け付けました")]]
            | //*[text()[contains(.,"ございました")]]
            | //*[contains(text(),"ありがとうございます")]
            | //*[text()[contains(.,"お問い合わせを承りました")]]
            | //*[text()[contains(.,"ご返事させていただきます")]]
            | //*[contains(text(),"お申し込みを承りました")]
            | //*[contains(text(),"ご連絡させて頂")]
            | //*[contains(text(),"ご連絡させていただき")]
            | //*[contains(text(),"受けしました")]
        '));

        return count($successTexts) > 0;
    }

    /**
     * Get page using browser.
     */
    public function getPageHTMLUsingBrowser(string $url)
    {
        $baseURL = parse_url($url)['host'] ?? null;
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
     * Close opening browser.
     */
    public function closeBrowser()
    {
        if ($this->driver) {
            $this->driver->manage()->deleteAllCookies();
            $this->driver->quit();
        }
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
