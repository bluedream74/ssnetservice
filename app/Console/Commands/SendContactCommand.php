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
use Illuminate\Support\Str;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Support\ExpectedConditions;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;
use Facebook\WebDriver\WebDriverCheckboxes;
use Facebook\WebDriver\WebDriverRadios;
use Facebook\WebDriver\WebDriverSelect;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\HttpClient\HttpClient;
use Exception;
use Mockery\Undefined;
use Psy\Readline\Hoa\Console;

use function PHPSTORM_META\type;

class SendContactCommand extends Command
{
    public const STATUS_FAILURE = 1;
    public const STATUS_SENT = 2;
    public const STATUS_SENDING = 3;
    public const STATUS_NO_FORM = 4;
    public const STATUS_NG = 5;
    public const STATUS_REPLY_CONFIRM = 6;

    public const RETRY_COUNT = 1;

    public const FORM_STATUS_TEXT_NO_EXIST = 1;
    public const FORM_STATUS_TEXT_EXIST_EMPTY = 2;
    public const FORM_STATUS_TEXT_EXIST_FULL = 3;
    public const FORM_STATUS_ERROR_FORM = 4;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:contact';

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
    protected $client;
    protected $isDebug = false;
    protected $isShowUnsubscribe;
    protected $isClient;
    protected $requestOptions = [
            'verify_peer' => false,
            'verify_host' => false,
            "headers" => [
                "User-Agent" => "Mozilla/5.0",
            ],
            // "http_errors" => false,
            // "allow_redirects" => true,
            "max_duration" => 10,
            // "connect_timeout" => 5,  // <= サーバーへの接続を5秒まで待機
            "timeout" => 30,  // <= レスポンスを10秒まで待機
        ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(HttpClient::create($this->requestOptions));
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
        $limit = env('MAIL_LIMIT') ? env('MAIL_LIMIT') : 6;

        $today = Carbon::today();
        $startTimeStamp = Carbon::createFromTimestamp(strtotime($today->format('Y-m-d') .' '. $start));
        $endTimeStamp = Carbon::createFromTimestamp(strtotime($today->format('Y-m-d') .' '. $end));
        $now = Carbon::now();
        $startTimeCheck = $now->gte($startTimeStamp);
        $endTimeCheck = $now->lte($endTimeStamp);

        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        $output->writeln("<info>start</info>");
        // if ($startTimeCheck && $endTimeCheck) {
        if (true) { // for test
            // $contacts = Contact::whereRaw("(`date` is NULL OR `time` is NULL OR (CURDATE() > `date` OR (CURDATE() = `date` AND CURTIME() >= `time`)))")
            // ->whereHas('reserve_companies')->get();
            
            // for test
            $contacts = Contact::get();
            
            $index = 0;
            foreach ($contacts as $contact) {
                if ($index >0) {
                    break;
                }
                $index ++;

                DB::beginTransaction();
                try {
                    // $companyContacts = CompanyContact::with(['contact'])->lockForUpdate()->where('contact_id', $contact->id)->where('is_delivered', 0)->limit($limit)->get();
                    
                    // for test
                    $companyContacts = CompanyContact::where('id', 10461)->limit($limit)->get(); // 10471, 10485
                    
                    if (count($companyContacts)) {
                        $companyContacts->toQuery()->update(['is_delivered' => self::STATUS_SENDING]);
                    } else {
                        $selectedTime = new DateTime(date('Y-m-d H:i:s'));
                        $companyContacts = CompanyContact::with(['contact'])
                                ->lockForUpdate()
                                ->where('contact_id', $contact->id)
                                ->where('is_delivered', self::STATUS_SENDING)
                                ->where('updated_at', '<=', $selectedTime->modify('-' . strval($limit * 20) . ' minutes')->format('Y-m-d H:i:s'))
                                ->where('updated_at', '>=', $selectedTime->modify('-2 day')->format('Y-m-d H:i:s'))
                                ->get();
                        if (count($companyContacts)) {
                            $companyContacts->toQuery()->update(['is_delivered' => 0]);
                        }

                        DB::commit();

                        continue;
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();

                    continue;
                }
                
                foreach ($companyContacts as $companyContact) {
                    $endTimeCheck = $now->lte($endTimeStamp);
                    // if (!$endTimeCheck) {
                    //     continue;
                    // }

                    $company = $companyContact->company;
                    try {
                        $this->form = NULL;
                        $this->data = [];

                        if ($company->contact_form_url == '' || $company->status == '会社名') {
                            $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, '');
                            continue;
                        }

                        // $company->contact_form_url = "https://makotohome.co.jp/%e5%95%8f%e3%81%84%e5%90%88%e3%82%8f%e3%81%9b/";
                        $company->contact_form_url = "https://address.zendesk.com/hc/ja/requests/new";
                        $output->writeln("company url : ".$company->contact_form_url);

                        $this->initBrowser();

                        $contactForm = $this->findContactForm($company->contact_form_url);

                        if ($contactForm) {
                            $this->form = $contactForm;
                        }
                        else {
                            $this->updateCompanyContact($companyContact, self::STATUS_NO_FORM, 'Contact form not found');
                            continue;
                        }

                        // Get top nodes including inputs
                        $topNode = $this->findTopNode();
                        $this->topNodes = $topNode->findElements(WebDriverBy::xpath('./*'));

                        // Check the contact form fields
                        sleep(1);
                        $this->checkName($contact);
                        $this->checkFuName($contact);
                        $this->checkCompany($contact);
                        $this->checkEmail($contact);
                        $this->checkConfirmEmail($contact);
                        $this->checkTitle($contact);
                        $this->checkPhoneNumber($contact);
                        $this->checkFaxNumber($contact);
                        $this->checkAddress($contact);
                        $this->checkPostalCode($contact);
                        $this->checkArea($contact);
                        $this->checkStreet1($contact);
                        $this->checkStreet2($contact);
                        $this->checkTimezone($contact);

                        // Get contact message
                        $content = str_replace('%company_name%', $company->name, $contact->content);
                        $content = str_replace('%myurl%', route('web.read', [$contact->id, $company->id]), $content);

                        print_r($this->data);

                        $htmlText = $this->form->getText();

                        if (strpos($htmlText, 'recaptcha') === false) {
                            try {
                                $ret = $this->submitContactForm($company, $content);
                                $this->updateCompanyContact($companyContact, $ret);
                            } catch (\Exception $e) {
                                $this->updateCompanyContact($companyContact, self::STATUS_FAILURE, $e->getMessage());
                            }
                        } else {
                            // recaptcha
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

        // sleep(5);
        die("finish");
    }

    /**
     * Init browser.
     */
    public function initBrowser()
    {
        $options = new ChromeOptions();
        $arguments = ['--disable-gpu', '--no-sandbox', '-disable-features=PageLoadMetrics', '--blink-settings=imagesEnabled=false'];
        if (!$this->isDebug) {
            $arguments[] = '--headless';
        }
        $options->addArguments($arguments);

        $caps = DesiredCapabilities::chrome();
        $caps->setCapability('acceptSslCerts', false);
        $caps->setCapability(ChromeOptions::CAPABILITY, $options);
        $this->driver = RemoteWebDriver::create('http://localhost:4444', $caps, 5000, 500000);
    }
    
    /**
     * Close opening browser.
     */
    public function closeBrowser()
    {
        try {
            if ($this->driver) {
                $this->driver->manage()->deleteAllCookies();
                $this->driver->close();
                $this->driver->quit();
            }
        } catch (\Exception $e) {
        }
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
            self::STATUS_REPLY_CONFIRM => '自動返信確認',
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
     * @return $contact Form
     */
    public function findContactForm($url)
    {
        $existForm = false;
        $contactForm = NULL;
        
        $baseURL = parse_url(trim($url))['host'] ?? null;
        if (!$baseURL) {
            throw new \Exception('Invalid URL');
        }

        $response = $this->driver->get($url);

        // Wait for the page to load completely
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::tagName('body'))
        );

        // Get all forms
        $forms = $this->driver->findElements(WebDriverBy::xpath('//form'));
        foreach ($forms as $form) {
            try {
                $formMethod = strtolower($form->getAttribute('method'));
                $textarea = $form->findElement(WebDriverBy::xpath('.//textarea'));
                if (strcmp($formMethod, 'get') !== 0 && $textarea) {
                    $contactForm = $form;
                    $existForm = true;
                    break;
                }
            }
            catch(\Throwable $e) {}
        }

        if (!$existForm) {
            sleep(5);
            $iframes = $this->driver->findElements(WebDriverBy::tagName('iframe'));
            if (count($iframes)) {
                foreach ($iframes as $iframe) {
                    $this->driver->switchTo()->frame($iframe);

                    // Wait for the page to load completely
                    $this->driver->wait()->until(
                        WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::tagName('body'))
                    );

                    $contactForm = $this->findContactFormInIFrame($iframe);

                    // Check if there is a frame within a frame
                    if (!$contactForm) {
                        $iframes1 = $this->driver->findElements(WebDriverBy::xpath('.//iframe'));
                        foreach ($iframes1 as $iframe1) {
                            $this->driver->switchTo()->frame($iframe1);

                            // Wait for the page to load completely
                            $this->driver->wait()->until(
                                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::tagName('body'))
                            );

                            $contactForm = $this->findContactFormInIFrame($iframe1);

                            if ($contactForm) {
                                break;
                            }

                            $this->driver->switchTo()->defaultContent();
                        }
                    }

                    if ($contactForm) {
                        break;
                    }

                    $this->driver->switchTo()->defaultContent();
                }
            }
        }

        return $contactForm;
    }

    public function findContactFormInIFrame($iframe)
    {
        $contactForm = null;

        try {
            // Find the form element within the iframe
            $form = $this->driver->findElement(WebDriverBy::tagName('form'));

            $formMethod = strtolower($form->getAttribute('method'));

            $textarea = $form->findElement(WebDriverBy::xpath('.//textarea'));
            if (strcmp($formMethod, 'get') !== 0 && $textarea) {
                $contactForm = $form;
            }
        } catch (\Throwable $e) {
            //
        }

        return $contactForm;
    }

    /**
     * Get top node including all input elements.
     *
     * @return $top node
     */
    public function findTopNode()
    {
        $textareaElement = $this->form->findElement(WebDriverBy::xpath('.//textarea'));
        // Get the parent node that contains textarea
        $parentNode = $textareaElement->findElement(WebDriverBy::xpath(".."));
        $class = $parentNode->getAttribute("class");
        $inputNode = $this->findNodeWithInputTag($parentNode);

        // Get top node including all input elements
        $topNode = $inputNode->findElement(WebDriverBy::xpath(".."));
        $tag = $topNode->getTagName();
        $class = $topNode->getAttribute("class");

        return $topNode;
    }

    // Recursive function to find the desired node
    function findNodeWithInputTag($currentNode)
    {
        // If the current node does not contain an input tag, get nodes at the same level, excluding the current node
        // Get all following sibling elements
        $followingSiblings = $currentNode->findElements(WebDriverBy::xpath('following-sibling::*'));
        // Get all preceding sibling elements
        $precedingSiblings = $currentNode->findElements(WebDriverBy::xpath('preceding-sibling::*'));

        // Get all same level elements except the current element
        $sameLevelNodes = array_merge($precedingSiblings, $followingSiblings);

        foreach ($sameLevelNodes as $node) {
            $class = $node->getAttribute("class");
            $inputTags = $node->findElements(WebDriverBy::xpath(".//input[@type='text' or @type='email' or @type='tel']"));

            // If a node with an input tag is found, return it
            if ($inputTags) {
                return $node;
            }
        }

        // If the current node does not contain an input tag, get the parent node
        $parentNode = $currentNode->findElement(WebDriverBy::xpath(".."));
        $class = $parentNode->getAttribute("class");

        if ($parentNode) {
            // Recursively call the function with the parent node
            return $this->findNodeWithInputTag($parentNode);
        }

        // If no node with an input tag is found, return null
        return null;
    }

    /**
     * Get contact form status.
     *
     * @param mixed $form
     *
     * @return $status
     */
    public function getContactFormStatus()
    {
        try {
            $textareas = $this->driver->findElements(WebDriverBy::xpath('//textarea'));

            if ($textareas) {
                foreach ($textareas as $textarea) {
                    // Get the value of the textarea element
                    $textareaValue = $textarea->getAttribute('value');
                    if (!empty($textareaValue)) {
                        return self::FORM_STATUS_TEXT_EXIST_FULL;
                    }
                }
        
                return self::FORM_STATUS_TEXT_EXIST_EMPTY;
            }
        }
        catch(\Exception $e) {}        

        $errorMessages = $this->checkErrorPage();
        if (count($errorMessages)) {
            return self::FORM_STATUS_ERROR_FORM;
        }

        return self::FORM_STATUS_TEXT_NO_EXIST;
    }

    /**
     * Subtmit contact form by using browser.
     *
     * @param mixed $company
     */
    public function submitContactForm($company, $content)
    {
        // Get all elements in the form
        $formElements = $this->form->findElements(WebDriverBy::xpath('.//*'));
        foreach ($formElements as $element) {
            try {
                $tag = $element->getTagName();
                $name = $element->getAttribute("name");
                $type = $element->getAttribute("type");
                $value = $element->getAttribute("value");
                $role = $element->getAttribute("role");
                
                switch($tag) {
                    case 'div':
                        if ($role === "radio") {
                            $element->click();
                        }
                        break;
                    case 'select':
                        $select = new WebDriverSelect($element);
                        $select->selectByIndex(1);
                        break;
                    case 'textarea':
                        $element->sendKeys($content);
                        break;
                    case 'input':
                        if ($type === "radio") {
                            try {
                                $radio = new WebDriverRadios($element);
                                if (strpos($value, "mail") !== false || strpos($value, "メール") !== false) {
                                    $radio->selectByValue($value);
                                }
                                else if (strpos($value, "その他") !== false) {
                                    $radio->selectByValue($value);
                                }
                                else {
                                    $radio->selectByIndex(0);
                                }
                            }
                            catch(\Exception $e) {
                                $element->click();
                            }                            
                            
                            break;
                        }
                        else if ($type === "checkbox") {
                            $this->visibleElement($name);

                            $checkbox = new WebDriverCheckboxes($element);
                            $checkbox->selectByIndex(0);
                            break;
                        }
                        // else if ($type === "text" || $type === "email") { // text input
                        //     $element->clear();
                        //     $element->sendKeys($this->data[$name]);
                        // }
                        
                        break;
                    default:
                        break;     
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if ($this->isDebug) {
            $this->driver->takeScreenshot(storage_path("screenshots/{$company->id}_fill.jpg"));
        }

        // Get submit elements from form
        $submitElements = $this->findSumbitElements($this->form);

        // Get current page text
        $beforePageSource = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();

        // Submit form
        foreach ($submitElements as $element) {
            try {
                $tag = $element->getTagName();
                $type = $element->getAttribute("type");
                $value = $element->getAttribute("value");
                $text = $element->getText();
            } catch (\Exception $exception) {
                continue;
            }
            

            if ($type === "radio" || $type === "checkbox") {
                continue;
            }

            if (in_array($tag, ["input", "button"]) && empty($value) && empty($text)) {
                continue;
            }

            try {
                // submit form
                $element->click();
            } catch (\Exception $exception) {
                // Scroll to the element
                $this->scrollToElement($element);
                $element->click();
            }

            sleep(1);
            
            // Accept alert confirm
            try {
                $this->driver->switchTo()->alert()->accept();
            }
            catch(\Exception $exception) {
                // Do nothing
            }

            // Wait for the AJAX call to finish
            $wait = new WebDriverWait($this->driver, 10);
            
            // $wait->until(function () use ($beforePageSource) {
            //     $afterPageSource = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
            //     return ($beforePageSource !== $afterPageSource);
            // });

            $wait->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::tagName('textarea')));

            // break;
        }

        // Wait for the page to load completely
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::tagName('body'))
        );

        // Check if exist confirm form
        $existConfirm = false;
        $confirmForm = $this->findConfirmFormFromForms();
        if (!$confirmForm) {
            $existConfirm = $this->findConfirmFormFromBody();
        }

        if (!$confirmForm && !$existConfirm) {
            $formStatus = $this->getContactFormStatus();

            switch ($formStatus) {
                case self::FORM_STATUS_TEXT_EXIST_FULL: // 3 - input error form                    
                case self::FORM_STATUS_ERROR_FORM: // 4 - not form (contains error)
                    return self::STATUS_FAILURE;
                case self::FORM_STATUS_TEXT_EXIST_EMPTY: // 2 - new form
                case self::FORM_STATUS_TEXT_NO_EXIST: // 1 - results page
                default:
                    break;
            }

            return self::STATUS_SENT;
        }
        
        // If confirm form exist
        $confirmStep = 0;
        do {
            $confirmStep++;
            try {
                $ret = $this->submitConfirmForm($confirmForm);
                if ($this->isDebug) {
                    $this->driver->takeScreenshot(storage_path("screenshots/{$company->id}_confirm{$confirmStep}.jpg"));
                }                

                return $ret;
            } catch (\Exception $e) {
                continue;
            }
        } while ($confirmStep < self::RETRY_COUNT);

        $this->closeBrowser();

        throw new \Exception('Confirm step is not success');
    }

    /**
     * Subtmit confirm form by using browser.
     *
     * @param mixed $company
     */
    public function submitConfirmForm($confirmForm)
    {
        // Get current page source
        $beforePageSource = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();

        // Get submit elements
        $confirmElements = $this->findSumbitElements($confirmForm);
        if (!$confirmElements) {
            return self::STATUS_FAILURE;
        }
        
        foreach ($confirmElements as $element) {
            try {
                $tag = $element->getTagName();
                $type = $element->getAttribute("type");
                $value = $element->getAttribute("value");
                $text = $element->getText();
            } catch (\Exception $exception) {
                continue;
            }
            
            if ($type === "radio" || $type === "checkbox") {
                continue;
            }

            if (in_array($tag, ["input", "button"]) && empty($value) && empty($text)) {
                continue;
            }

            try {
                // submit form
                $element->click();
            } catch (\Exception $exception) {
                // Scroll to the element
                $this->scrollToElement($element);
                $element->click();
            }

            sleep(1);

            // Wait for the AJAX call to finish
            $wait = new WebDriverWait($this->driver, 10);
            $wait->until(function () use ($beforePageSource) {
                $afterPageSource = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();
                return ($beforePageSource !== $afterPageSource);
            });
        }

        // Wait for the page to load completely
        $this->driver->wait()->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::tagName('body'))
        );

        // Get current page source
        $currentPageSource = $this->driver->findElement(WebDriverBy::tagName('body'))->getText();        

        $successTexts = $this->checkSuccessPage();

        if ($beforePageSource !== $currentPageSource && count($successTexts) > 0) {
            return self::STATUS_SENT;
        }

        return self::STATUS_REPLY_CONFIRM;
    }

    /**
     *  Show hidden element.
     *
     * @return void
     */
    function visibleElement($elementName)
    {
        // visible : none => visible: block
        $this->driver->executeScript("const collections = document.getElementsByName('{$elementName}'); for (let i = 0; i < collections.length; i++) {collections[i].style.display = 'block';}");
    }

    /**
     *  To enable a disabled element.
     *
     * @return void
     */
    function enableElement($element)
    {
        // visible : none => visible: block
        $this->driver->executeScript("arguments[0].removeAttribute('disabled');", [$element]);
    }

    /**
     *  Scroll to the element.
     *
     * @return void
     */
    function scrollToElement($element)
    {
        // Scroll to the element
        $this->driver->executeScript('arguments[0].scrollIntoView(true);', [$element]);
        // $element->getLocationOnScreenOnceScrolledIntoView();
    }

    /**
     * Check if exist confirm form from body.
     *
     * @return $confirm form or null
     */
    public function findConfirmFormFromBody()
    {
        // Get the HTML string of the body
        $body = $this->driver->findElement(WebDriverBy::xpath('//body'));
        $formText = $body->getText();

        // Check if the HTML string contains the sending data
        $countMatchingData = 0;
        foreach ($this->data as $key => $val) {
            $containsSendingData = strpos($formText, $val) !== false;
            if ($containsSendingData) {
                $countMatchingData ++;
            }
        }

        // Check if form is confirm
        $existConfirmForm = false;
        if (($countMatchingData / count($this->data)) * 100 > 70) {
            $existConfirmForm = true;
        }

        return $existConfirmForm;        
    }

    /**
     * Check if exist confirm form from forms.
     *
     * @return $confirm form or null
     */
    public function findConfirmFormFromForms()
    {
        // Check if exist confirm form
        $forms = $this->driver->findElements(WebDriverBy::xpath('//form'));

        $confirmForm = null;
        if ($forms) {
            foreach ($forms as $form) {
                // Get the HTML string of the form element
                $formText = $form->getText();
    
                // Check if the HTML string contains the sending data
                $countMatchingData = 0;
                foreach ($this->data as $key => $val) {
                    $containsSendingData = strpos($formText, $val) !== false;
                    if ($containsSendingData) {
                        $countMatchingData ++;
                    }
                }
    
                // Check if form is confirm
                if (($countMatchingData / count($this->data)) * 100 > 70) {
                    $confirmForm = $form;
                    break;
                }
            }
        }
        
        return $confirmForm;
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

    /**
     * Check name field (surname, lastename, name).
     *
     * @return value
     */
    public function checkName($contact)
    {
        // Define the array of patterns
        // $patterns = array('お名前','名前','担当者','氏名','お名前(かな)','お名前(フルネームで)','ご担当者様名');
        $patterns = array('お名前','ご氏名','名前','担当者','氏名','ご担当者様名');

        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            $countOfInputs = count($inputElements);
            if ($countOfInputs === 1) {
                $nameElement = $inputElements[0];
                $name = $nameElement->getAttribute("name");
                if(!$name) {
                    $name = "name";
                }
                
                $this->data[$name] = $contact->surname . $contact->lastname;

                $nameElement->clear();
                $nameElement->sendKeys($contact->surname . $contact->lastname);
            }
            else if ($countOfInputs === 2) {
                $surElement = $inputElements[0];
                $lastElement = $inputElements[1];
                $usrName = $surElement->getAttribute("name");
                $lastName = $lastElement->getAttribute("name");
                if(!$usrName) {
                    $usrName = "firstname";
                    $lastName = "lastname";
                }
                
                $this->data[$usrName] = $contact->surname;
                $this->data[$lastName] = $contact->lastname;

                $surElement->clear();
                $lastElement->clear();
                $surElement->sendKeys($contact->surname);
                $lastElement->sendKeys($contact->lastname);
            }
        }
    }

    /**
     * Check name field (fu surname, fu lastename, name).
     *
     * @return value
     */
    public function checkFuName($contact)
    {
        // Define the array of patterns
        $patterns =array('カタカナ','フリガナ','カナ','ふりがな','名前（カナ）','名前カナ','よみがな');

        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            $countOfInputs = count($inputElements);
            if ($countOfInputs === 1) {
                $nameElement = $inputElements[0];
                $name = $nameElement->getAttribute("name");
                if (!$name) {
                    $name = "funame";
                }
                
                $this->data[$name] = $contact->fu_surname . $contact->fu_lastname;

                $nameElement->clear();
                $nameElement->sendKeys($contact->fu_surname . $contact->fu_lastname);
            }
            else if ($countOfInputs === 2) {
                $surElement = $inputElements[0];
                $lastElement = $inputElements[1];
                $usrName = $surElement->getAttribute("name");
                $lastName = $lastElement->getAttribute("name");

                if(!$usrName) {
                    $usrName = "fufirstname";
                    $lastName = "fulastname";
                }
                
                $this->data[$usrName] = $contact->fu_surname;
                $this->data[$lastName] = $contact->fu_lastname;

                $surElement->clear();
                $lastElement->clear();
                $surElement->sendKeys($contact->fu_surname);
                $lastElement->sendKeys($contact->fu_lastname);
            }
        }
    }

    /**
     * Check email field (email, confirm).
     *
     * @return value
     */
    public function checkEmail($contact)
    {
        // Define the array of patterns
        $patterns = array('メール', 'Eメール', '確認のためもう一度', 'E-mail', 'Email', 'ご連絡先');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "email";
                }

                $this->data[$name] = $contact->email;

                $inputElement->clear();
                $inputElement->sendKeys($contact->email);
            }
        }
    }

    /**
     * Check email field (email, confirm).
     *
     * @return value
     */
    public function checkConfirmEmail($contact)
    {
        // Define the array of patterns
        $patterns = array('メールアドレス（確認）', '確認のためもう一度', 'E-mail(確認)');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "confirmemail";
                }

                $this->data[$name] = $contact->email;

                $inputElement->clear();
                $inputElement->sendKeys($contact->email);
            }
        }
    }

    /**
     * Check company field.
     *
     * @return value
     */
    public function checkCompany($contact)
    {
        // Define the array of patterns
        $patterns = array('会社名','会社・店名','企業名','貴社名','御社名','法人名','団体名','機関名','屋号','組織名','お店の名前','社名');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "company";
                }

                $this->data[$name] = $contact->company;

                $inputElement->clear();
                $inputElement->sendKeys($contact->company);
            }
        }        
    }

    /**
     * Check title field.
     *
     * @return value
     */
    public function checkTitle($contact)
    {
        // Define the array of patterns
        $patterns = array('件名', '題名', '用件名');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "title";
                }

                $this->data[$name] = $contact->title;

                $inputElement->clear();
                $inputElement->sendKeys($contact->title);
            }
        }
    }

    /**
     * Check phone number field.
     *
     * @return value
     */
    public function checkPhoneNumber($contact)
    {
        // Define the array of patterns
        $patterns = array('電話番号', 'お電話番号', '携帯電話', '連絡先', 'ＴＥＬ', 'TEL');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            $countOfInputs = count($inputElements);
            if ($countOfInputs === 1) {
                $prefix = "";
                $text = $inputNode->getText();
                $placeholder = $inputElements[0]->getAttribute("placeholder");
                $value = $inputElements[0]->getAttribute("value");
                
                // Check if The text or placeholder contains a hyphen.
                // $count = substr_count($text, "-");
                if (strpos($text, "-") !== false || strpos($placeholder, "-") !== false || strpos($value, "-") !== false) {
                    $prefix = "-";
                }

                $telName = $inputElements[0]->getAttribute("name");
                if (!$telName) {
                    $telName = "tel";
                }

                $this->data[$telName] = $contact->phoneNumber1 . $prefix . $contact->phoneNumber2 . $prefix . $contact->phoneNumber3;

                $inputElements[0]->clear();
                $inputElements[0]->sendKeys($contact->phoneNumber1 . $prefix . $contact->phoneNumber2 . $prefix . $contact->phoneNumber3);
            }
            else if ($countOfInputs === 2) {
                $prefix = "";
                $text = $inputNode->getText();
                $placeholder = $inputElements[1]->getAttribute("placeholder");
                $value = $inputElements[1]->getAttribute("value");

                // Check if the text or placeholder contains a hyphen.
                // $count = substr_count($text, "-");
                if (strpos($text, "-") !== false || strpos($placeholder, "-") !== false || strpos($value, "-") !== false) {
                    $prefix = "-";
                }

                $telName1 = $inputElements[0]->getAttribute("name");
                $telName2 = $inputElements[1]->getAttribute("name");
                if (!$telName1) {
                    $telName1 = "tel1";
                    $telName2 = "tel2";
                }

                $this->data[$telName1] = $contact->phoneNumber1;
                $this->data[$telName2] = $contact->phoneNumber2 . $prefix . $contact->phoneNumber3;

                $inputElements[0]->clear();
                $inputElements[1]->clear();
                $inputElements[0]->sendKeys($contact->phoneNumber1);
                $inputElements[1]->sendKeys($contact->phoneNumber2 . $prefix . $contact->phoneNumber3);
            }
            else if ($countOfInputs === 3) {
                $telName1 = $inputElements[0]->getAttribute("name");
                $telName2 = $inputElements[1]->getAttribute("name");
                $telName3 = $inputElements[2]->getAttribute("name");
                if (!$telName1) {
                    $telName1 = "tel1";
                    $telName2 = "tel2";
                    $telName3 = "tel3";
                }

                $this->data[$telName1] = $contact->phoneNumber1;
                $this->data[$telName2] = $contact->phoneNumber2;
                $this->data[$telName3] = $contact->phoneNumber3;

                $inputElements[0]->clear();
                $inputElements[1]->clear();
                $inputElements[2]->clear();
                $inputElements[0]->sendKeys($contact->phoneNumber1);
                $inputElements[1]->sendKeys($contact->phoneNumber2);
                $inputElements[2]->sendKeys($contact->phoneNumber3);
            }
        }
    }

    /**
     * Check fax number field.
     *
     * @return value
     */
    public function checkFaxNumber($contact)
    {
        // Define the array of patterns
        $patterns = array('ＦＡＸ', 'FAX');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            $countOfInputs = count($inputElements);
            if ($countOfInputs === 1) {
                $prefix = "";
                $text = $inputNode->getText();
                $placeholder = $inputElements[0]->getAttribute("placeholder");
                $value = $inputElements[0]->getAttribute("value");
                
                // Check if The text or placeholder contains a hyphen.
                // $count = substr_count($text, "-");
                if (strpos($text, "-") !== false || strpos($placeholder, "-") !== false || strpos($value, "-") !== false) {
                    $prefix = "-";
                }

                $telName = $inputElements[0]->getAttribute("name");
                if (!$telName) {
                    $telName = "fax";
                }

                $this->data[$telName] = $contact->phoneNumber1 . $prefix . $contact->phoneNumber2 . $prefix . $contact->phoneNumber3;

                $inputElements[0]->clear();
                $inputElements[0]->sendKeys($contact->phoneNumber1 . $prefix . $contact->phoneNumber2 . $prefix . $contact->phoneNumber3);
            }
            else if ($countOfInputs === 2) {
                $prefix = "";
                $text = $inputNode->getText();
                $placeholder = $inputElements[1]->getAttribute("placeholder");
                $value = $inputElements[1]->getAttribute("value");

                // Check if the text or placeholder contains a hyphen.
                // $count = substr_count($text, "-");
                if (strpos($text, "-") !== false || strpos($placeholder, "-") !== false || strpos($value, "-") !== false) {
                    $prefix = "-";
                }

                $telName1 = $inputElements[0]->getAttribute("name");
                $telName2 = $inputElements[1]->getAttribute("name");
                if (!$telName1) {
                    $telName1 = "fax1";
                    $telName2 = "fax2";
                }

                $this->data[$telName1] = $contact->phoneNumber1;
                $this->data[$telName2] = $contact->phoneNumber2 . $prefix . $contact->phoneNumber3;

                $inputElements[0]->clear();
                $inputElements[1]->clear();
                $inputElements[0]->sendKeys($contact->phoneNumber1);
                $inputElements[1]->sendKeys($contact->phoneNumber2 . $prefix . $contact->phoneNumber3);
            }
            else if ($countOfInputs === 3) {
                $telName1 = $inputElements[0]->getAttribute("name");
                $telName2 = $inputElements[1]->getAttribute("name");
                $telName3 = $inputElements[2]->getAttribute("name");
                if (!$telName1) {
                    $telName1 = "fax1";
                    $telName2 = "fax2";
                    $telName3 = "fax3";
                }

                $this->data[$telName1] = $contact->phoneNumber1;
                $this->data[$telName2] = $contact->phoneNumber2;
                $this->data[$telName3] = $contact->phoneNumber3;

                $inputElements[0]->clear();
                $inputElements[1]->clear();
                $inputElements[2]->clear();
                $inputElements[0]->sendKeys($contact->phoneNumber1);
                $inputElements[1]->sendKeys($contact->phoneNumber2);
                $inputElements[2]->sendKeys($contact->phoneNumber3);
            }
        }
    }    

    /**
     * Check postal(zip) code field.
     *
     * @return value
     */
    public function checkPostalCode($contact)
    {
        // Define the array of patterns
        $patterns = array('郵便番号', '〒', 'Post', 'Zip');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            $countOfInputs = count($inputElements);
            if ($countOfInputs === 1) {
                $prefix = "";
                $text = $inputNode->getText();
                $placeholder = $inputElements[0]->getAttribute("placeholder");

                // Check if The text or placeholder contains a hyphen.
                if (strpos($text, "-") !== false || strpos($placeholder, "-") !== false) {
                    $prefix = "-";
                }
                
                $zipName = $inputElements[0]->getAttribute("name");
                if (!$zipName) {
                    $zipName = "zip";
                }

                $this->data[$zipName] = $contact->postalCode1 . $prefix . $contact->postalCode2;

                $inputElements[0]->clear();
                $inputElements[0]->sendKeys($contact->postalCode1 . $prefix . $contact->postalCode2);
            }
            else if ($countOfInputs === 2) { // post and address
                $zipName1 = $inputElements[0]->getAttribute("name");
                $zipName2 = $inputElements[1]->getAttribute("name");
                $zipName = $inputElements[0]->getAttribute("name");
                if (!$zipName1) {
                    $zipName1 = "zip1";
                    $zipName2 = "zip1";
                }

                $this->data[$zipName1] = $contact->postalCode1;
                $this->data[$zipName2] = $contact->postalCode2;

                $inputElements[0]->clear();
                $inputElements[1]->clear();

                $inputElements[0]->sendKeys($contact->postalCode1);
                $inputElements[1]->sendKeys($contact->postalCode2);
            }
        }
    }

    /**
     * Check address field.
     *
     * @return value
     */
    public function checkAddress($contact)
    {
        // Define the array of patterns
        $patterns = array('住所','ご住所','ご　住　所','住　所', '所在地');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) { // address
            $countOfInputs = count($inputElements);
            if ($countOfInputs === 1) {
                $addrName = $inputElements[0]->getAttribute("name");
                if (!$addrName) {
                    $addrName = "address";
                }

                $this->data[$addrName] = $contact->address;
                
                $inputElements[0]->clear();
                $inputElements[0]->sendKeys($contact->address);
            }
            else if ($countOfInputs === 2) { // post and address
                $text = $inputNode->getText();
                $value = $inputElements[0]->getAttribute("value");

                if ((strpos($text, "郵便番号") !== false || strpos($text, "〒") !== false || strpos($value, "〒") !== false) && 
                    strpos($text, "-") !== false && strpos($text, "—") === false && strpos($text, "---") === false) {
                    // zip code (include '-')
                    $zipName1 = $inputElements[0]->getAttribute("name");
                    $zipName2 = $inputElements[1]->getAttribute("name");
                    if (!$zipName1) {
                        $zipName1 = "zip1";
                        $zipName2 = "zip2";
                    }

                    $this->data[$zipName1] = $contact->postalCode1;
                    $this->data[$zipName2] = $contact->postalCode2;

                    $inputElements[0]->clear();
                    $inputElements[1]->clear();
                    $inputElements[0]->sendKeys($contact->postalCode1);
                    $inputElements[1]->sendKeys($contact->postalCode2);

                    // address
                    // $nextElements = $this->findNextSiblingNodeWithInputTag($inputNode);
                    // $addrName = $nextElements[0]->getAttribute("name");
                    // if (!$addrName) {
                    //     $addrName = "address";
                    // }

                    // $this->data[$addrName] = $contact->address;

                    // $nextElements[0]->clear();
                    // $nextElements[0]->sendKeys($contact->address);
                }
                else {
                    // zip code
                    $prefix = "";
                    $placeholder = $inputElements[0]->getAttribute("placeholder");

                    // Check if The text or placeholder contains a hyphen.
                    if (strpos($placeholder, "-") !== false) {
                        $prefix = "-";
                    }

                    $zipName = $inputElements[0]->getAttribute("name");
                    if (!$zipName) {
                        $zipName = "zip";
                    }

                    $this->data[$zipName] = $contact->postalCode1 . $prefix . $contact->postalCode2;

                    $inputElements[0]->clear();
                    $inputElements[0]->sendKeys($contact->postalCode1 . $prefix . $contact->postalCode2);
                    
                    // address
                    $addrName = $inputElements[1]->getAttribute("name");
                    if (!$addrName) {
                        $addrName = "address";
                    }

                    $this->data[$addrName] = $contact->address;

                    $inputElements[1]->clear();
                    $inputElements[1]->sendKeys($contact->address);
                }
            }
            else if ($countOfInputs === 3) {
                $text = $inputNode->getText();
                $value = $inputElements[0]->getAttribute("value");

                if ((strpos($text, "郵便番号") !== false || strpos($text, "〒") !== false || strpos($value, "〒") !== false) &&
                     strpos($text, "-") !== false && strpos($text, "—") === false && strpos($text, "---") === false) {
                    // zip code (include '-')
                    $zipName1 = $inputElements[0]->getAttribute("name");
                    $zipName2 = $inputElements[1]->getAttribute("name");
                    if (!$zipName1) {
                        $zipName1 = "zip1";
                        $zipName2 = "zip2";
                    }

                    $this->data[$zipName1] = $contact->postalCode1;
                    $this->data[$zipName2] = $contact->postalCode2;

                    $inputElements[0]->clear();
                    $inputElements[1]->clear();
                    $inputElements[0]->sendKeys($contact->postalCode1);
                    $inputElements[1]->sendKeys($contact->postalCode2);

                    // address
                    $addrName = $inputElements[2]->getAttribute("name");
                    if (!$addrName) {
                        $addrName = "address";
                    }

                    $this->data[$addrName] = $contact->address;

                    $inputElements[2]->clear();
                    $inputElements[2]->sendKeys($contact->address);
                }
                else {
                    // zip code
                    $prefix = "";
                    $placeholder = $inputElements[0]->getAttribute("placeholder");

                    // Check if The text or placeholder contains a hyphen.
                    if (strpos($placeholder, "-") !== false) {
                        $prefix = "-";
                    }

                    $zipName = $inputElements[0]->getAttribute("name");
                    if (!$zipName) {
                        $zipName = "zip";
                    }

                    $this->data[$zipName] = $contact->postalCode1 . $prefix . $contact->postalCode2;

                    $inputElements[0]->clear();
                    $inputElements[0]->sendKeys($contact->postalCode1 . $prefix . $contact->postalCode2);
                    
                    // address1
                    $addrName = $inputElements[1]->getAttribute("name");
                    if (!$addrName) {
                        $addrName = "address1";
                    }

                    $this->data[$addrName] = mb_substr($contact->address, 0, 3);

                    $inputElements[1]->clear();
                    $inputElements[1]->sendKeys($contact->address, 0, 3);

                    // address2
                    $addrName = $inputElements[2]->getAttribute("name");
                    if (!$addrName) {
                        $addrName = "address2";
                    }

                    $this->data[$addrName] = mb_substr($contact->address, 3);
                    
                    $inputElements[2]->clear();
                    $inputElements[2]->sendKeys(mb_substr($contact->address, 3));
                }
            }
        }
    }

    /**
     * Check area field (address1).
     *
     * @return value
     */
    public function checkArea($contact)
    {
        // Define the array of patterns
        $patterns = array('都道府県');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "address1";
                }

                $this->data[$name] = $contact->area;

                $inputElement->clear();
                $inputElement->sendKeys($contact->area);
            }
        }
    }

    /**
     * Check street1 field (address2).
     *
     * @return value
     */
    public function checkStreet1($contact)
    {
        // Define the array of patterns
        $patterns = array('市区町村'); //, '番地');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "street1";
                }

                $this->data[$name] = mb_substr($contact->address, 0, 3);

                $inputElement->clear();
                $inputElement->sendKeys(mb_substr($contact->address, 0, 3));
            }
        }
    }
    
    /**
     * Check street2 field (address3).
     *
     * @return value
     */
    public function checkStreet2($contact)
    {
        // Define the array of patterns
        $patterns = array('丁目番地','番地名','建物', 'マンション', 'アパート');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "street2";
                }

                $this->data[$name] = mb_substr($contact->address, 3);

                $inputElement->clear();
                $inputElement->sendKeys(mb_substr($contact->address, 3));
            }
        }
    }

    /**
     * Check time zone.
     * お電話の場合、ご都合のいい時間帯をご記入ください。
     *
     * @return value
     */
    public function checkTimezone($contact)
    {
        // Define the array of patterns
        $patterns = array('時間帯');
        
        list($isFound, $inputNode, $inputElements) = $this->findInputElementsWithPatterns($patterns);

        if ($inputElements) {
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute("name");
                if (!$name) {
                    $name = "timezone";
                }

                $this->data[$name] = "平日9時～12時";

                $inputElement->clear();
                $inputElement->sendKeys("平日9時～12時");
            }
        }
    }

    function findInputElementsWithPatterns($patterns)
    {
        foreach ($this->topNodes as $index => $topNode) {
            $text = $topNode->getText();
            $text = str_replace("必須", "", $text);
            $text = trim($text);
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) === 0) {
                    $tag = $topNode->getTagName();
                    $inputElements = null;
                    $inputElements = $topNode->findElements(WebDriverBy::xpath(".//input[@type='text' or @type='email' or @type='tel']"));
                    if ($inputElements) {
                        return array(true, $topNode, $inputElements);
                    }
                    else {
                        // get next top node
                        $nextNode = $this->topNodes[$index+1];
                        if ($nextNode) {
                            $tag = $nextNode->getTagName();
                            $inputElements = $nextNode->findElements(WebDriverBy::xpath(".//input[@type='text' or @type='email' or @type='tel']"));
                        }
                        
                        return array(true, $nextNode, $inputElements);
                    }
                }
            }
        }

        return array(false, null, null);
    }

    // Get the next sibling node at the same level
    function findNextSiblingNode($node)
    {
        return $node->findElement(WebDriverBy::xpath("following-sibling::*"));
    }

    // Get the next sibling node at the same level that contains input tag
    function findNextSiblingNodeWithInputTag($node)
    {
        $sameLevelNode = $node->findElement(WebDriverBy::xpath("following-sibling::*"));
        $text = $sameLevelNode->getText();
        $inputTags = $sameLevelNode->findElements(WebDriverBy::xpath(".//input[@type='text' or @type='email' or @type='tel']"));

        if (empty($text) && $inputTags) {
            return $inputTags;
        }

        return null;
    }

    // Get elements for form submission
    function findSumbitElements($form)
    {
        $xpathButton = '
                //button[@type="submit"]//span[contains(text(),"入力内容の確認")]
                | //input[@type="submit" and (contains(@class,"send"))]
                | //input[@type="submit" and (contains(@value,"Send "))]
                | //input[@type="submit" and not(contains(@value,"戻る") or contains(@value,"クリア"))]
                | //input[@type="submit" and contains(@value,"送信")]
                | //a[@class="js-formSend btnsubmit"]
                | //a[@href="kagawa-casting-08.php"]
                | //a[contains(@href,"./conf.php")]
                | //a[contains(@href,"./commit.php")]
                | //a[contains(@class,"form-btn-next")]
                | //a[@id="js__submit"]
                | //a[contains(text(),"次へ")]
                | //a[contains(text(),"確認")]
                | //a[contains(text(),"送信")]
                | //a[contains(@class,"submit-btn")]
                | //button[@class="nttdatajpn-submit-button"]
                | //button[@type="submit" and (contains(@name,"unisphere-submit"))]
                | //button[@type="submit" and (contains(@class,"btn-cmn--red"))]
                | //button[@type="button" and (contains(@class,"ahover"))]
                | //button[@type="submit" ][contains(@class,"btn")]
                | //button[@type="submit" and (contains(@class,"　上記の内容で送信する　"))]
                | //button[@type="submit" and (contains(@class,"mfp_element_submit"))]
                | //button[@type="submit" and @class="btn"]
                | //button[@type="submit" and contains(@value,"送信")]
                | //button[@type="submit"][contains(@class,"btn-cmn--red")]
                | //button[@type="submit"][contains(@data-disable-with-permanent,"true")]
                | //button[@type="submit"][contains(@name,"__送信ボタン")]
                | //button[@type="submit"][contains(@name,"regist") and contains(@value,"送信")]
                | //button[@type="submit"][contains(@name,"_exec")]
                | //button[@type="submit"][contains(@name,"Action")]
                | //button[@type="submit" and  (contains(@data-disable-with-permanent,"true"))]
                | //button[@type="submit"][contains(@value,"send")]
                | //button[@type="submit"][contains(@value,"この内容で無料相談する")]
                | //button[@type="submit"][contains(@value,"送信する")]
                | //button[@type="submit"]//span[contains(text(),"同意して進む")]
                | //button[@type="submit"][contains(@onclick,"return _tx_mailform_submit")]
                | //button[@type="submit"][contains(@class,"_form")]
                | //button[@type="button"][contains(@role,"button")]
                | //button[@type="button"][contains(@value,"確認")]
                | //button[@type="button"][contains(@value,"送信")]
                | //button[@type="button"][contains(@class,"contact-btn")]
                | //button[contains(@class,"mfp_element_button")]
                | //button[contains(@value,"送信")]
                | //button[contains(text(),"上記の内容で登録する")]
                | //button[contains(text(),"次へ")]
                | //button[contains(text(),"確認")]
                | //button[contains(text(),"送　　信")]
                | //button[contains(text(),"送信")]
                | //button[span[contains(text(),"送信")]]
                | //button[span[contains(text(),"確認画面へ")]]
                | //button[span[contains(text(),"上記内容でお問い合せする")]]
                | //div[@role="button"]
                | //img[contains(@alt,"この内容で送信する")]
                | //img[contains(@alt,"内容を確認する")]
                | //img[contains(@alt,"完了画面へ")]
                | //img[contains(@alt,"確認画面に進む")]
                | //img[contains(@alt,"入力確認画面へ")]
                | //img[contains(@alt,"送信する")]
                | //input[@type="button" and @id="submit_confirm"]
                | //input[@type="button" and contains(@id,"button_mfp_goconfirm")]
                | //input[@type="button" and contains(@name,"_check_x")]
                | //input[@type="button" and contains(@name,"_submit_x")]
                | //input[@type="button" and contains(@name,"conf")]
                | //input[@type="button"][contains(@value,"確認画面へ")]
                | //input[@type="image" and contains(@name,"_send2_")]
                | //input[@type="image" and contains(@name,"send")]
                | //input[@type="image" and contains(@src,"../images/entry/btn_send.png")]
                | //input[@type="image" and contains(@value,"SEND")]
                | //input[@type="image"][contains(@alt,"この内容で送信する") and @type!="hidden"]
                | //input[@type="image"][contains(@alt,"この内容で送信する") and @type!="hidden"]
                | //input[@type="image"][contains(@alt,"送信") and @type!="hidden"]
                | //input[@type="image"][contains(@name,"check_entry_button") and @type!="hidden"]
                | //input[@type="image"][contains(@name,"conf") and @type!="hidden"]
                | //input[@type="image"][contains(@value,"この内容で登録する") and @type!="hidden"]
                | //input[@type="image"][contains(@class,"errPosRight") and @type!="hidden"]
                | //input[@type="image"][contains(@src,"http://www.eisho-sunrise.com/images/inquiry/confirm_button.png") and @type!="hidden"]
                | //input[@type="image"][contains(@src,"http://www.eisho-sunrise.com/images/inquiry/send_button.png") and @type!="hidden"]
                | //input[@type="image"][contains(@src,"/images/contact/submit.png") and @type!="hidden"]
                | //input[@type="image"][contains(@value,"送 信") and @type!="hidden"]
                | //input[@type="submit" and contains(@name,"sendmail")]
                | //input[@type="submit" and contains(@name,"submit") and contains(@value, "送信")]
                | //input[@type="submit" and contains(@name,"submitConfirm")]
                | //input[@type="submit" and contains(@value,"　送　信　")]
                | //input[@type="submit" and contains(@value,"入力内容を確認する")]
                | //input[@type="submit" and contains(@value,"入力内容確認")]
                | //input[@type="submit" and contains(@value,"内容確認へ")]
                | //input[@type="submit" and contains(@value,"確認画面へ")]
                | //input[@type="submit" and contains(@value,"送信する")]
                | //input[@type="submit" and contains(@value,"送信する") and contains(@name,"ACMS_POST_Form_Submit")]
                | //input[@type="submit" and contains(@value,"送信する") and contains(@name,"submitSubmit")]
                | //input[@type="submit" and contains(@value,"この内容で送信する")]
                | //input[@type="submit" and contains(@value,"送　信") and contains(@name,"sousin")]
                | //input[@type="submit" and contains(@value,"送　信")]
                | //input[@type="submit" and contains(@class,"formsubmit")]
                | //input[contains(@alt,"次へ") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //input[contains(@alt,"確認") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //input[contains(@value,"次へ") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //input[contains(@value,"確 認") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //input[contains(@value,"確認") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //input[contains(@value,"送　信") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //input[contains(@value,"送信") and @type!="hidden" and @type!="checkbox" and @type!="radio"]
                | //label[@for="sf_KojinJouhou__c" and not(contains(@value,"戻る") or contains(@value,"クリア"))]
            ';
        
        if ($form) {
            return $form->findElements(WebDriverBy::xpath($xpathButton));
        }

        return $this->driver->findElements(WebDriverBy::xpath($xpathButton));
    }

    public function checkSuccessPage()
    {
        return $this->driver->findElements(WebDriverBy::xpath(config('constant.xpathMessage')));
    }
    public function checkErrorPage()
    {
        $xpathMessage = '
            //*[contains(text(),"アクセスできません")]
            | //*[contains(text(),"入力下さい")]
        ';

        return $this->driver->findElements(WebDriverBy::xpath($xpathMessage));
    }

    public function getCharset(string $htmlContent)
    {
        preg_match('/\<meta[^\>]+charset *= *["\']?([a-zA-Z\-0-9_:.]+)/i', $htmlContent, $matches);
        return $matches;
    }
    
}
