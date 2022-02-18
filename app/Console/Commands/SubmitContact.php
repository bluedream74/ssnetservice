<?php

namespace App\Console\Commands;

use App\Models\CompanyContact;
use App\Models\Config;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Goutte\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\HttpClient\HttpClient;

class SubmitContact extends Command
{
    public const STATUS_NOT_SUPPORTED = 1;
    public const STATUS_SENT = 2;
    public const STATUS_FAILURE = 3;
    public const STATUS_NO_FORM = 4;
    public const STATUS_NG = 5;

    public const RETRY_COUNT = 4;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submit:contact';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
            now()->lt(Carbon::createFromTimestamp(strtotime(now()->format('Y-m-d') . ' ' . $config->start)))
            || now()->gt(Carbon::createFromTimestamp(strtotime(now()->format('Y-m-d') . ' ' . $config->end)))
        ) {
            $this->error('Out of time');

            return 0;
        }

        $companyContacts = CompanyContact::with(['contact'])->where('is_delivered', 0)->limit(env('MAIL_LIMIT'))->get();

        foreach ($companyContacts as $companyContact) {
            if (!$companyContact->contact) {
                continue;
            }
            $contact = $companyContact->contact;
            $company = $companyContact->company;

            if (
                !$company->contact_form_url
                || !$contact->date
                || !$contact->time
                || now()->lt(Carbon::createFromTimestamp(strtotime("{$contact->date} {$contact->time}")))
            ) {
                $this->info('Skip: ' . $companyContact->id);

                return 0;
            }

            $companyContact->update(['is_delivered' => self::STATUS_FAILURE]);

            $this->data = [];
            $this->html = null;
            $this->htmlText = null;
            $crawler = null;

            $this->info('==============================================');
            $this->info("Company contact {$companyContact->id}: {$company->contact_form_url}");

            try {
                $crawler = $this->client->request('GET', $company->contact_form_url);
            } catch (\Exception $e) {
                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
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
                foreach ($iframes as $iframeURL) {
                    try {
                        $frameResponse = $this->client->request('GET', $iframeURL);
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
                        if (isset($data[$matches[$i]]) || (!empty($data[$matches[$i]]))) {
                            continue;
                        }
                        $this->data[$matches[$i]] = $section['transform'][$i];
                    }
                } elseif (count($section['part']) == 1) {
                    // merge all part to the first part if there is only one part
                    if (isset($data[$section['part'][0]]) || (!empty($data[$section['part'][0]]))) {
                        continue;
                    }
                    $this->data[$section['part'][0]] = implode('', $section['transform']);
                }
            }

            $javascriptCheck = strpos($this->html, 'recaptcha') === false;
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
        $successMessages = ['ありがとうございま', '有難うございま', '送信されました', '送信しました', '送信いたしました', '自動返信メール', '内容を確認させていただき', '成功しました', '完了いたしま'];

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
        $deliveryStatus = [
            self::STATUS_NOT_SUPPORTED => '送信失敗',
            self::STATUS_SENT => '送信済み',
            self::STATUS_FAILURE => 'フォームなし',
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
                'match' => ['company-kana', 'company_furi', 'フリガナ', 'kcn', 'ふりがな'],
                'transform' => 'ナシ',
            ],
            [
                'match' => ['company', 'cn', 'kaisha', 'cop', 'corp', '会社', '社名'],
                'pattern' => ['会社名', '企業名', '貴社名', '御社名', '法人名', '団体名', '機関名', '屋号', '組織名', '屋号', 'お店の名前', '社名', '店舗名'],
                'transform' => $contact->company,
            ],
            [
                'match' => ['住所', 'addr', 'add_detail'],
                'pattern' => ['住所', '所在地', '市区', '町名'],
                'transform' => $contact->address,
            ],
            [
                'match' => ['mail_add', 'mail', 'Mail', 'mail_confirm', 'ールアドレス', 'M_ADR', '部署', 'E-Mail', 'メールアドレス', 'confirm'],
                'pattern' => ['メールアドレス', 'Mail アドレス'],
                'transform' => $contact->email,
            ],
            [
                'match' => ['title', 'subject', '件名'],
                'pattern' => ['件名', 'Title', 'Subject', '題名', '用件名'],
                'transform' => $contact->title,
            ],
            [
                'match' => ['URL', 'url', 'HP'],
                'transform' => $contact->homepageUrl,
            ],
            [
                'match' => ['ご担当者名'],
                'pattern' => ['名前', '氏名', '担当者', '差出人', 'ネーム'],
                'transform' => $contact->surname . $contact->lastname,
            ],
            [
                'match' => ['姓'],
                'transform' => $contact->surname,
            ],
            [
                'match' => ['名'],
                'transform' => $contact->lastname,
            ],
            [
                'pattern' => ['ふりがな', 'フリガナ', 'お名前（カナ）'],
                'transform' => $contact->fu_surname . $contact->fu_lastname,
            ],
            [
                'match' => ['セイ', 'せい'],
                'pattern' => ['名 フリガナ'],
                'transform' => $contact->fu_surname,
            ],
            [
                'match' => ['メイ', 'めい'],
                'pattern' => ['姓 フリガナ'],
                'transform' => $contact->fu_lastname,
            ],
            [
                'match' => ['郵便番号'],
                'pattern' => ['郵便番号', '〒'],
                'transform' => $contact->postalCode1 . $contact->postalCode2,
            ],
            [
                'pattern' => ['都道府県'],
                'transform' => $contact->area,
            ],
            [
                'pattern' => ['FAX番号', '電話', '携帯電話', '連絡先', 'TEL', 'Phone'],
                'match' => ['fax', 'FAX'],
                'transform' => $contact->phoneNumber1 . $contact->phoneNumber2 . $contact->phoneNumber3,
            ],
            [
                'match' => ['市区町村'],
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
        $options = new ChromeOptions();
        $arguments = ['--disable-gpu', '--no-sandbox'];
        if (!$this->isDebug) {
            $arguments[] = '--headless';
        }
        $options->addArguments($arguments);

        $caps = DesiredCapabilities::chrome();
        $caps->setCapability('acceptSslCerts', false);
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);

        $driver = RemoteWebDriver::create('http://localhost:4444', $caps, 5000);

        $driver->get($company->contact_form_url);

        $driver->manage()->window()->setSize(new WebDriverDimension(1225, 1996));

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
                        $driver->findElement(WebDriverBy::cssSelector("input[type=\"{$type}\"][name=\"{$validKey}\"]"))->click();
                        break;
                    case 'radio':
                        $driver->findElement(WebDriverBy::cssSelector("input[type=\"{$type}\"][name=\"{$formKey}\"]"))->click();
                        break;
                    case 'select':
                        $driver->findElement(WebDriverBy::cssSelector("select[name=\"{$formKey}\"] option[value=\"{$this->data[$formKey]}\"]"))->click();
                        break;
                    case 'hidden':
                        break;
                    case 'textarea':
                        $driver->findElement(WebDriverBy::cssSelector("textarea[name=\"{$formKey}\"]"))->sendKeys($this->data[$formKey]);
                        break;
                    default:
                        $driver->findElement(WebDriverBy::cssSelector("input[name=\"{$formKey}\"]"))->sendKeys($this->data[$formKey]);
                        break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $driver->takeScreenshot(storage_path("screenshots/{$company->id}_fill.jpg"));

        $confirmStep = 0;
        do {
            $confirmStep++;
            try {
                $isSuccess = $this->confirmByUsingBrowser($driver);
                $driver->takeScreenshot(storage_path("screenshots/{$company->id}_confirm{$confirmStep}.jpg"));

                if ($isSuccess) {
                    $driver->manage()->deleteAllCookies();
                    $driver->quit();

                    return;
                }
            } catch (\Exception $e) {
                continue;
            }
        } while ($confirmStep < self::RETRY_COUNT);

        $driver->manage()->deleteAllCookies();
        $driver->quit();

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
            | //input[contains(@value,"確認") and @type!="hidden"]
            | //a[contains(text(),"確認")]
            | //button[contains(text(),"送信")]
            | //input[contains(@value,"送信") and @type!="hidden"]
            | //a[contains(text(),"送信")]
            | //button[contains(text(),"次へ")]
            | //input[contains(@value,"次へ") and @type!="hidden"]
            | //input[contains(@alt,"次へ") and @type!="hidden"]
            | //a[contains(text(),"次へ")]
            | //*[contains(text(),"に同意する")]
        '));

        foreach ($confirmElements as $element) {
            try {
                $element->click();
            } catch (\Exception $exception) {
                // Do nothing
            }
        }

        $successTexts = $driver->findElements(WebDriverBy::xpath('
            //*[contains(text(),"ありがとうございま")]
            | //*[contains(text(),"有難うございま")]
            | //*[contains(text(),"送信しました")]
            | //*[contains(text(),"送信されました")]
            | //*[contains(text(),"成功しました")]
            | //*[contains(text(),"完了いたしま")]
            | //*[contains(text(),"送信いたしました")]
            | //*[contains(text(),"内容を確認させていただき")]
            | //*[contains(text(),"自動返信メール")]
        '));

        return count($successTexts) > 0;
    }

    /**
     * Check if string contains any string.
     *
     * @return bool
     */
    public function containsAny(string $string, array $list)
    {
        return collect($list)->contains(function ($list) use ($string) {
            return strpos($string, $list) !== false;
        });
    }
}
