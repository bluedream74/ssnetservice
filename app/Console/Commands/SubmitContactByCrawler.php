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
use DB;
use DateTime;
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

    public const RETRY_COUNT = 1;

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
            if ($this->isDebug) {
                $this->error('Out of time');
            }

            sleep(60);
            return 0;
        }

        DB::beginTransaction();
        try
        {
            $companyContacts = CompanyContact::with(['contact'])->lockForUpdate()->where('is_delivered', self::STATUS_RETRY)->limit(env('MAIL_LIMIT'))->get();
            if (count($companyContacts)) {
                $companyContacts->toQuery()->update(['is_delivered' => self::STATUS_SENDING]);
            } else {
                $selectedTime = new DateTime(date('Y-m-d H:i:s'));
                $companyContacts = CompanyContact::with(['contact'])
                    ->lockForUpdate()
                    ->where('is_delivered', self::STATUS_SENDING)
                    ->where('updated_at', '<=', $selectedTime->modify('-' . strval(env('MAIL_LIMIT') * 2) . ' minutes'))
                    ->where('updated_at', '>=', $selectedTime->modify('-1 day'))
                    ->get();
                if (count($companyContacts)) {
                    $companyContacts->toQuery()->update(['is_delivered' => 0]);
                }

                DB::commit();

                sleep(60);

                return 0;
            }
            DB::commit();
        }
        catch (\Exception $e) {
            DB::rollback();
            sleep(60);

            return 0;
        }

        if (!count($companyContacts)) {
            sleep(60);
            return 0;
        }

        foreach ($companyContacts as $companyContact) {
            if (!$companyContact->contact) {
                continue;
            }
            $contact = $companyContact->contact;
            $company = $companyContact->company;


            if ($contact->date
                    && $contact->time
                    && now()->lt(Carbon::createFromTimestamp(strtotime("{$contact->date} {$contact->time}")))) {

                $companyContacts->toQuery()->update(['is_delivered' => 0]);
                sleep(60);

                return 0;
            }

            if (!$company->contact_form_url) {
                if ($this->isDebug) {
                    $this->info('Skip: ' . $companyContact->id);
                }

                continue;

            }

            $this->data = [];
            $this->driver = null;
            $this->html = null;
            $this->htmlText = null;
            $crawler = null;

            if ($this->isDebug) {
                $this->info('==============================================');
                $this->info("Company contact {$companyContact->id}: {$company->contact_form_url}");
            }

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
            if ($this->isDebug) {
                file_put_contents(storage_path("html/{$companyContact->id}_response.html"), $this->html);
            }

            $hasContactForm = $this->findContactForm($crawler);
            if (!$hasContactForm) {
                $iframes = $crawler->filter('iframe')->extract(['src']);
                foreach ($iframes as $i => $iframeURL) {
                    try {
                        // $frameResponse = $this->client->request('GET', $iframeURL);
                        $frameResponse = $this->getPageHTMLUsingBrowser($iframeURL);
                        if ($this->isDebug) {
                            file_put_contents(storage_path("html/{$companyContact->id}_frame{$i}.html"), $frameResponse->html());
                        }
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

            if ($this->isDebug) {
                $this->info('Data: ' . var_export($this->data, true));
            }

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
        $successMessages = config('constant.successMessages');

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
            self::STATUS_FAILURE => '送信失敗',
            self::STATUS_SENT => '送信済み',
            self::STATUS_SENDING => '未対応',
            self::STATUS_NO_FORM => 'フォームなし',
            self::STATUS_NG => 'NGワードあり',
        ];

        if (!array_key_exists($status, $deliveryStatus)) {
            throw new \Exception('Status is not found');
        }

        try {
            $companyContact->company->update(['status' => $deliveryStatus[$status]]);
            $companyContact->update(['is_delivered' => $status]);

            if ($this->isDebug) {
                $reportAction = $status == self::STATUS_SENT ? 'info' : 'error';
                $this->{$reportAction}($message ?? $deliveryStatus[$status]);
            }

            $this->closeBrowser();
        } catch (\Exception $e) {
            return 0;
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
        try {
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
        } catch (\Exception $e) {
            return $hasTextarea;
        }

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

        $mapper = $this->getMapper($companyContact);

        foreach ($mapper as $map) {
            // Check if form key contains any string on 'match' array, then use that value
            if (isset($map['match'], array_flip($map['match'])[$key])) {
                $this->data[$key] = $map['transform'];

                return;
            }
        }
    }

    /**
     * Mapping form pattern.
     *
     * @param mixed $input
     * @param mixed $companyContact
     */
    public function mapFormPattern($companyContact)
    {
        $mapper = $this->getMapper($companyContact);

        foreach ($mapper as $map) {
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

            if (isset($map['key'])) {
                foreach ($map['key'] as $value) {
                    if ((strpos($this->html, "name='" . $value) !== false || strpos($this->html, 'name="' . $value) !== false) && (!isset($this->data[$value]) || empty($this->data[$value]))) {
                        $this->data[$value] = $map['transform'];
                    }
                }
            }
        }
    }

    /**
     * Get Mapper
     *
     * @param mixed $company
     */
    public function getMapper($companyContact)
    {
        $contact = $companyContact->contact;
        $company = $companyContact->company;

        $content = str_replace('%company_name%', $company->name, $contact->content);
        $content = str_replace('%myurl%', route('web.read', [$contact->id, $company->id]), $content);

        if (!$this->isDebug) {
            $content .= PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . '※※※※※※※※' . PHP_EOL . '配信停止希望の方は ' . route('web.stop.receive', 'ajgm2a3jag' . $company->id . '25hgj') . '   こちら' . PHP_EOL . '※※※※※※※※';
        }

        $dataMail = explode('@', $contact->email);
        $configMapper = config('constant.mapper');

        return $mapper = [
            [
                'pattern' => $configMapper['furiganaPattern'],
                'match' => $configMapper['furiganaMatch'],
                'transform' => 'ナシ',
            ],
            [
                'match' => $configMapper['companyMatch'],
                'pattern' => $configMapper['companyPattern'],
                'transform' => $contact->company,
            ],
            [
                'match' => $configMapper['emailMatch'],
                'pattern' => $configMapper['emailPattern'],
                'key' => $configMapper['emailKey'],
                'transform' => $contact->email,
            ],
            [
                'match' => $configMapper['postalCode1Match'],
                'key' => $configMapper['postalCode1Key'],
                'transform' => $contact->postalCode1,
            ],
            [
                'match' => $configMapper['fullPostcode1Match'],
                'key' => $configMapper['fullPostcode1Key'],
                'transform' => $contact->postalCode1 . $contact->postalCode2,
            ],
            [
                'match' => $configMapper['postCode2Match'],
                'key' => $configMapper['postCode2Key'],
                'transform' => $contact->postalCode2,
            ],
            [
                'match' => $configMapper['fullPostCode2Match'],
                'pattern' => $configMapper['fullPostCode2Pattern'],
                'transform' => $contact->postalCode1 . '-' . $contact->postalCode2,
            ],
            [
                'match' => $configMapper['addressMatch'],
                'pattern' => $configMapper['addressPattern'],
                'transform' => $contact->address,
            ],
            [
                'match' => $configMapper['titleMatch'],
                'pattern' => $configMapper['titlePattern'],
                'transform' => $contact->title,
            ],
            [
                'match' => $configMapper['homePageUrlMatch'],
                'transform' => $contact->homepageUrl,
            ],
            [
                'match' => $configMapper['lastNameMatch'],
                'key' => $configMapper['lastNameKey'],
                'transform' => $contact->lastname,
            ],
            [
                'match' => $configMapper['surnameMatch'],
                'key' => $configMapper['surnameKey'],
                'transform' => $contact->surname,
            ],
            [
                'match' => $configMapper['fullnameMatch'],
                'pattern' => $configMapper['fullnamePattern'],
                'transform' => $contact->surname . $contact->lastname,
            ],
            [
                'match' => $configMapper['fullFurnameMatch'],
                'pattern' => $configMapper['fullFurnamePattern'],
                'transform' => $contact->fu_surname . $contact->fu_lastname,
            ],
            [
                'match' => $configMapper['fursurnameMatch'],
                'pattern' => $configMapper['fursurnamePattern'],
                'transform' => $contact->fu_surname,
            ],
            [
                'match' => $configMapper['furlastnameMatch'],
                'pattern' => $configMapper['furlastnamePattern'],
                'transform' => $contact->fu_lastname,
            ],
            [
                'pattern' => $configMapper['areaPattern'],
                'match' => $configMapper['areaMatch'],
                'transform' => $contact->area,
            ],
            [
                'pattern' => $configMapper['fullPhoneNumer1Pattern'],
                'match' => $configMapper['fullPhoneNumer1Match'],
                'transform' => $contact->phoneNumber1 . $contact->phoneNumber2 . $contact->phoneNumber3,
            ],
            [
                'match' => $configMapper['fullPhoneNumber2Match'],
                'key' => $configMapper['fullPhoneNumber2Key'],
                'transform' => $contact->phoneNumber1 . '-' . $contact->phoneNumber2 . '-' . $contact->phoneNumber3,
            ],
            [
                'match' => $configMapper['phoneNumber1match'],
                'key' => $configMapper['phoneNumber1key'],
                'transform' => $contact->phoneNumber1,
            ],
            [
                'match' => $configMapper['phoneNumber2Match'],
                'key' => $configMapper['phoneNumber2Key'],
                'transform' => $contact->phoneNumber2,
            ],
            [
                'match' => $configMapper['phoneNumber3Match'],
                'key' => $configMapper['phoneNumber3Key'],
                'transform' => $contact->phoneNumber3,
            ],
            [
                'match' => $configMapper['address2Match'],
                'transform' => mb_substr($contact->address, 0, 3),
            ],
            [
                'match' => $configMapper['randomNumber1Match'],
                'transform' => 0,
            ],
            [
                'pattern' => $configMapper['randomStringPattern'],
                'transform' => 'なし',
            ],
            [
                'pattern' => $configMapper['orderPattern'],
                'transform' => 'order',
            ],
            [
                'pattern' => $configMapper['randomNumber2Match'],
                'transform' => 35,
            ],
            [
                'pattern' => $configMapper['answerPattern'],
                'transform' => 1,
            ],
            [
                'pattern' => $configMapper['urlPattern'],
                'key' => $configMapper['urlKey'],
                'transform' => $contact->myurl,
            ],
            [
                'match' => $configMapper['yearMatch'],
                'transform' => '2022',
            ],
            [
                'match' => $configMapper['monthMatch'],
                'transform' => '03',
            ],
            [
                'match' => $configMapper['dayMatch'],
                'transform' => '04',
            ],
            [
                'match' => $configMapper['fullDateMatch'],
                'transform' => '2022/03/14',
            ],
            [
                'match' => $configMapper['randomString2Match'],
                'transform' => '管理運営受託業務',
            ],
            [
                'match' => $configMapper['mailConfirm1Match'],
                'transform' => isset($dataMail[0]) ? $dataMail[0] : null,
            ],
            [
                'match' => $configMapper['mailConfirm2Match'],
                'transform' => isset($dataMail[1]) ? $dataMail[1] : null,
            ],
        ];
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
        $response = $this->client->submit($this->form, $this->data);

        $responseHTML = $response->html();
        if ($this->isDebug) {
            file_put_contents(storage_path('html') . '/' . $company->id . '_submit.html', $responseHTML);
        }
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
            $arguments[] = '--disable-dev-shm-usage';
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
