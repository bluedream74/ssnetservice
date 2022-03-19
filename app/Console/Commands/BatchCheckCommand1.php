<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Config;
use DB;
use Goutte\Client;
use Illuminate\Console\Command;

class BatchCheckCommand1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:check';

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
    protected $register_url = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $registerUrl = Config::get()->first()->registerUrl;
        if ($registerUrl) {
            $this->register_url();
            Config::where('id', 1)->update(['registerUrl' => '0']);
            // Config::where('id',1)->update(array('checkContactForm'=>'1'));
            // return 0;
        }
        $check_contact_form = Config::get()->first()->checkContactForm;
        if ($check_contact_form == 1) {
            $offset = 5;
            DB::beginTransaction();
            try {
                $companies = Company::where('check_contact_form', 0)->lockForUpdate()->skip(0)->take($offset)->get();
                if (count($companies)) {
                    $companies->toQuery()->update(['check_contact_form' => 1]);
                } else {
                    Config::where('id', 1)->update(['checkContactForm' => '0']);
                    sleep(60);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();

                return 0;
            }

            if (sizeof($companies) > 0) {
                foreach ($companies as $company) {
                    // sleep(2);
                    try {
                        // Company::where('id',$company->id)->update(['check_contact_form'=>1]);
                        if (isset($company->contact_form_url) && (!empty($company->contact_form_url))) {
                            continue;
                        }
                        $topPageUrl = $this->getTopUrl($company->url);
                        $check_url = $this->checkTopContactForm($topPageUrl);
                        if (isset($check_url) && !empty($check_url)) {
                            Company::where('id', $company->id)->update(['contact_form_url' => $check_url]);
                        } else {
                            $url_patterns = [
                                'contact',
                                'contact.php',
                                'contact.html',
                                'inquiry',
                                'inquiry.php',
                                'inquiry.html',
                                'mail.html',
                                'form',
                                'form.html',
                                'otoiawase.html',
                                'toiawase.html',
                                'mail/index.html',
                                'toiawase',
                                'html/toiawase.html',
                                'html/company.html',
                                'html/contact.html',
                                'feedback.html',
                                'postmail.html',
                                'info.html',
                                'quote',
                                'inq',
                                'contactform',
                                'contact-us',
                                'contactus',
                                'company/contact',
                                'consulting.html',
                            ];
                            foreach ($url_patterns as $url_pattern) {
                                if ($this->checkSubContactForm($topPageUrl . '/' . $url_pattern)) {
                                    Company::where('id', $company->id)->update(['contact_form_url' => $topPageUrl . '/' . $url_pattern]);
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            }
        } else {
            sleep(60);
        }

        return 0;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    private function register_url()
    {
        $csvFile = public_path('registerList.csv');
        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle);
        }
        fclose($file_handle);
        foreach ($line_of_text as $key => $value) {
            if (($key == 0) || (!$value)) {
                continue;
            }
            Company::where('check_contact_form', 0)->where('url', 'LIKE', '%' . $value[0] . '%')->update(['contact_form_url' => $value[1], 'check_contact_form' => 1]);
        }
    }

    private function getTopUrl($companyurl)
    {
        $topurl = '';
        if (strpos($companyurl, 'http') !== false) {
            $url = explode('://', $companyurl);
            if (isset($url)) {
                $topurl = explode('/', $url[1])[0];
                if (isset(explode('/', $url[1])[1]) && (explode('/', $url[1])[1] == 'jp')) {
                    $topurl .= '/' . explode('/', $url[1])[1];
                }
                $topurl = $url[0] . '://' . $topurl;
            }
        } else {
            $topurl = explode('/', $companyurl)[0];
            $topurl = 'http://' . $topurl;
        }

        return $topurl;
    }

    private function checkTopContactForm($url)
    {
        $client = new Client();
        $crawler = $client->request('GET', $url);
        $except_patterns = ['__nuxt'];
        foreach ($except_patterns as $pattern) {
            if (strpos($crawler->html(), $pattern) !== false) {
                return false;
            }
        }
        $contact_form_patterns = [
            'お見積り・お問合せ',
            'お問合せ・サポート',
            'ホームページから問い合わせ',
            'お問い合わせ',
            'お問合せ',
            'お問合わせ',
            'お問い合せ',
            '問い合せ',
            '問い合わせ',
            '問合せ',
            'コンタクト',
            'Contact',
            'CONTACT',
            'contact',
            'inquiry',
            'こちらから', ];
        foreach ($contact_form_patterns as $pattern) {
            if (strpos($crawler->html(), $pattern) !== false) {
                $patternStr = $pattern;
                $str = substr($crawler->html(), strpos($crawler->html(), $pattern) - 10);
                $pos = strpos($str, '>');
                $pattern = substr($str, $pos);
                $pattern = substr($pattern, 1);
                $pattern = substr($pattern, 0, strpos($pattern, '<'));
                try {
                    if (empty($pattern) || (strlen($pattern) > 30)) {
                        $pattern = $patternStr;
                    }
                    if (strpos($pattern, $patternStr) !== false) {
                    } else {
                        $pattern = $patternStr;
                    }
                    if ($crawler->selectLink($pattern)->link()) {
                        $link = $crawler->selectLink($pattern)->link()->getUri();
                        $jsPatterns = ['javascript', 'JavaScript', 'mailto'];
                        foreach ($jsPatterns as $js) {
                            if (strpos($link, $js) !== false) {
                                break;
                            }

                            return $link;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        $alt_patterns = [
            'お問合せ',
            'お問い合わせ',
            '依頼する',
        ];

        foreach ($alt_patterns as $alt_pattern) {
            try {
                $link = $crawler->selectImage($alt_pattern)->parents()->attr('href');
                if (strpos($link, 'http') !== false) {
                    return $link;
                }

                return 'http://' . $link;
            } catch (\Throwable $e) {
            }
        }

        $iframes = $crawler->filter('iframe')->extract(['src']);
        foreach ($iframes as $iframe) {
            $clientFrame = new Client();
            $crawlerFrame = $clientFrame->request('GET', $url . '/' . $iframe);
            foreach ($contact_form_patterns as $pattern) {
                if (strpos($crawlerFrame->html(), $pattern) !== false) {
                    $patternStr = $pattern;
                    $str = substr($crawlerFrame->html(), strpos($crawlerFrame->html(), $pattern) - 10);
                    $pos = strpos($str, '>');
                    $pattern = substr($str, $pos);
                    $pattern = substr($pattern, 1);
                    $pattern = substr($pattern, 0, strpos($pattern, '<'));
                    try {
                        if (empty($pattern) || (strlen($pattern) > 30)) {
                            $pattern = $patternStr;
                        }
                        if (strpos($pattern, $patternStr) !== false) {
                        } else {
                            $pattern = $patternStr;
                        }
                        if ($crawlerFrame->selectLink($pattern)->link()) {
                            $link = $crawlerFrame->selectLink($pattern)->link()->getUri();
                            $jsPatterns = ['javascript', 'JavaScript'];
                            foreach ($jsPatterns as $js) {
                                if (strpos($link, $js) !== false) {
                                    break;
                                }

                                return $link;
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }
            }
        }
        try {
            $form = $crawler->filter('textarea')->getNode(0);
            if (isset($form) && (!empty($form))) {
                return $url;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function checkSubContactForm($url)
    {
        $client = new Client();
        $crawler = $client->request('GET', $url);
        try {
            $form1 = $crawler->filter('textarea')->getNode(0);
            if (isset($form1) && (!empty($form1))) {
                return $url;
            }
            $form2 = $crawler->filter('#__nuxt')->getNode(0);
            if (isset($form2) && (!empty($form2))) {
                if (strpos($crawler->html(), 'Loading') !== false) {
                    return false;
                }

                return $url;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
