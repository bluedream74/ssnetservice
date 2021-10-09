<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Source;
use App\Models\SubSource;
use App\Models\Contact;
use Illuminate\Support\Arr;
use App\Exports\CompanyExport;
use App\Exports\ContactExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use App\Imports\CompanyImport;
use Goutte\Client;
use LaravelAnticaptcha\Anticaptcha\NoCaptchaProxyless;
use Illuminate\Support\Facades\Crypt;

class DashboardController extends BaseController
{
    /**
     * ScheduleController constructor
     *
     */
    public function __construct(
        \Illuminate\Contracts\Events\Dispatcher $events
    )
    {
        parent::__construct($events);
        ini_set('max_execution_time', -1);
        ini_set('default_socket_timeout', 6000);
    }

    public function index(Request $request)
    {
      
        $query = $this->makeQuery(request()->all());
        $prefectures = array();
        foreach (config('values.prefectures') as $value) {
            $prefectures[$value] = $value;
        }
        $companies = $query->paginate(20);
        return view('admin.index', compact('companies', 'prefectures'));
    }

    private function makeQuery($attributes)
    {
        $query = Company::query();
        $query->sortable();
        if (!empty($value = Arr::get($attributes, 'q'))) {
            $query->where(function ($query) use ($value) {
                $query->where('name', 'like', "%{$value}%")
                    ->orWhere('id', 'like', "%{$value}%")
                    ->orWhere('url', 'like', "%{$value}%")
                    ->orWhere('area', 'like', "%{$value}%");
            });
        }

        if (!empty($value = Arr::get($attributes, 'source'))) {
            $value = Source::where('sort_no',$value)->first()->name;
            $query->where('source', $value);
        }

        if (!empty($value = Arr::get($attributes, 'subsource'))) {
            $value = SubSource::where('sort_no',$value)->first()->name;
            $query->where('subsource', $value);
        }

        if (!empty($value = Arr::get($attributes, 'area'))) {
            $query->where('area', 'like', "%{$value}%");
        }

        if (!empty($value = Arr::get($attributes, 'status'))) {
            $query->where('status', $value);
        }

        

        if (!empty($value = Arr::get($attributes, 'phone'))) {
            if (intval($value) === 1) {
                $query->whereHas('phones');
            } else {
                $query->whereDoesntHave('phones');
            }
        }

        if (!empty($value = Arr::get($attributes, 'origin'))) {
            if($value==1) {
                $query->whereNotNull('contact_form_url');
            }
            if($value==2) {
                $query->whereNull('contact_form_url');
            }
        }
        
        return $query;
    }

    public function fetch(Request $request)
    {
        return view('admin.fetch');
    }

    public function contact(Request $request)
    {
        $contacts = Contact::orderByDesc('created_at')->paginate(20);
        foreach($contacts as $contact) {
            $contact->sent_count = $contact->companies()->where('is_delivered','2')->count();
            $contact->stand_by_count = $contact->companies()->where('is_delivered','0')->count();
        }
        return view('admin.contact', compact('contacts'));
    }

    public function doFetch()
    {
        $mailService = new \App\Services\MailService(); 

        $siteSource = intval(request()->get('source'));

        $url = getUrls()[intval(request()->get('source'))];

        $from = intval(request()->get('from'));
        $to = intval(request()->get('to'));

        $totalCount = 0;
        $hosts = array();
        for ($i = $from; $i <= $to; $i++) {
            try {
                $results = array();

                $companyResults = [];
                if ($siteSource === 3) {
                    $html = $i === 1 ? $this->getHTMLContent("{$url}") : $this->getHTMLContent("{$url}/page/{$i}");
                    
                    if ($html === '') return back()->with(['system.message.success' => "空のHTMLを取得しました。"]);
                    $companies = $this->getSiteUrls($html);
                    
                    foreach ($companies as $key => $company) {
                        $r = $this->fetchWebKansaSite($company);
                        if (isset($r)) $companyResults[] = $r;
                    }
                } elseif ($siteSource === 4 || $siteSource === 5) { // 建設関東 / 建設関西
                    $html = $this->getHTMLContent("{$url}?page={$i}");
                    if ($html === '') return back()->with(['system.message.success' => "空のHTMLを取得しました。"]);
                    $companyResults = $this->getSiteUrlsForConstruct($html);
                } elseif ($siteSource === 6) { // デザイン事務所の会社
                    $html = $this->getHTMLContent("{$url}?page={$i}");
                    if ($html === '') return back()->with(['system.message.success' => "空のHTMLを取得しました。"]);
                    $companyResults = $this->getSiteUrlsForType6($html);
                } elseif ($siteSource === 7) { // スタートアップ
                    $html = $this->getHTMLContent("{$url}?page={$i}");
                    if ($html === '') return back()->with(['system.message.success' => "空のHTMLを取得しました。"]);
                    $companyResults = $this->getSiteUrlsForType7($html);
                } elseif (in_array($siteSource, [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22])) { // スタートアップ
                    $html = $this->getHTMLContent("{$url}?page={$i}");
                    if ($html === '') return back()->with(['system.message.success' => "空のHTMLを取得しました。"]);
                    $companyResults = $this->getSiteUrlsForType8($html);
                } else {
                    $html = $this->getHTMLContent("{$url}?pn={$i}");
                    if ($html === '') return back()->with(['system.message.success' => "空のHTMLを取得しました。"]);
                    $companyResults = $this->everything_in_tags($html, 'figcaption');
                }

                foreach ($companyResults as $key => $company) {
                    $parse = parse_url($company['url']);
                    $host = str_replace('www.', '', $parse['host']);
                    if (Company::where('url', 'like', "%{$host}%")->count() === 0) {
                        // $companyHTML = $this->getHTMLContent($company['url']);
                        // $company['phones'] = $this->findPhonenumber($companyHTML);
                        // $company['emails'] = $this->findEmail($companyHTML);
                        $company['phones'] = [];
                        $company['emails'] = [];
                        
                       
                        $siteUrl = str_replace("http://", "", str_replace("https://", "", $company['url']));
                        $siteUrl = explode("/", $siteUrl)[0];
                        $siteUrl = str_replace("www.", "", $siteUrl);

                        $company['emails'][] = "info@{$siteUrl}";
                        $company['emails'][] = "contact@{$siteUrl}";
                        $company['emails'][] = "support@{$siteUrl}";
                        $company['emails'][] = "customer@{$siteUrl}";
                        $company['emails'][] = "webmaster@{$siteUrl}";
                        $company['emails'][] = "postmaster@{$siteUrl}";
                        $company['emails'][] = "admin@{$siteUrl}";

                        $results[] = $company;

                        $hosts[] = $host;
                    } else {
                        Company::where('url', 'like', "%{$host}%")
                                ->update([
                                    'area' => $company['area']
                                ]);
                    }
                }

                foreach ($results as $result) {
                    try {
                        // $confirmedEmail = null;
                        // foreach ($result['emails'] as $email) {
                        //     try {
                        //         list ($status, $res) = $mailService->lookup($email);
                        //         if ($status && isset($res['status']) && $res['status'] == 'valid') {
                        //             $confirmedEmail = $email;
                        //             break;
                        //         }
                        //         sleep(1);
                        //     } catch (\Throwable $e) {
                        //         \Log::error($e->getMessage());
                        //     }
                        // }

                        // if (isset($confirmedEmail)) {
                        //     $company = Company::updateOrCreate([
                        //         'name'      => $result['name'],
                        //         'source'    => request()->get('source'),
                        //     ], [
                        //         'url'       => $result['url'],
                        //         'is_origin_email'   => $result['is_origin_email'],
                        //         'area'      => $result['area']
                        //     ]);

                        //     $company->emails()->updateOrCreate([
                        //         'email'         => $confirmedEmail,
                        //         'is_verified'   => 1
                        //     ]);

                        //     // foreach ($result['phones'] as $phone) {
                        //     //     $company->phones()->updateOrCreate([
                        //     //         'phone'         => $phone
                        //     //     ]);
                        //     // }

                        //     $totalCount++;
                        // }

                        $company = Company::updateOrCreate([
                            'name'      => $result['name'],
                            'source'    => request()->get('source'),
                        ], [
                            'url'       => $result['url'],
                            'area'      => $result['area']
                        ]);


                        foreach ($result['emails'] as $email) {
                            try {
                                $company->emails()->updateOrCreate([
                                    'email'         => $email,
                                    'is_verified'   => 0
                                ]);
                            } catch (\Throwable $e) {
                                \Log::error($e->getMessage());
                            }
                        }

                        $totalCount++;
                    } catch (\Throwable $e) {
                        \Log::error("FETCH GGG  :  " . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                \Log::error("FETCH HHH  :  " . $e->getMessage());
            }
        }

        sleep(1);

        foreach ($hosts as $host) {
            if (Company::where('url', 'LIKE', "%{$host}%")->count() > 1) {
                $company = Company::where('url', 'LIKE', "%{$host}%")->first();
                Company::where('url', 'LIKE', "%{$host}%")
                    ->where('id', '!=', $company->id)
                    ->delete();
            }
        }
        
        return back()->with(['system.message.success' => "{$totalCount}の会社の検索が完了しました。"]);
    }

    public function batchCheck(){
        $CHECK_CONTACT_FORM = config('values.check_contact_form');
        $key = 'CHECK_CONTACT_FORM';
		
        if($CHECK_CONTACT_FORM=="0"){
            $this->upsert($key, 1);
            Artisan::call('config:cache');
            //Artisan::call('queue:restart');	
            usleep(3000000);
            Company::where('check_contact_form',1)->update(['check_contact_form'=>0]);
            return back()->with(['system.message.info' => "一括チェックしています。"]);
        }else {
            $this->upsert($key, 0);
            Artisan::call('config:cache');
            //Artisan::call('queue:restart');
            usleep(3000000);
            return back()->with(['system.message.info' => "一括チェックが停止されました。"]);
        }
    }
    
    
    private function getHTMLContent($url)
    {
        try {
            $html = file_get_contents($url);
        } catch (\Throwable $e) {
            return "";
        }

        return $html;
    }

    private function getSiteUrls($string)
    {
        $pattern = '<a[^>]*>(.*?)</a>';
        preg_match_all($pattern, $string, $matches);
        
        $results = [];
        foreach ($matches[0] as $match) {
            if (strpos($match, 'class="companies-item"') !== false) {
                $temp = explode('class="companies-item"', $match);
                $temps = explode('target="_blank"', $temp[1]);
                $temps = explode('"', $temps[0]);
                $results[] = $temps[1];
            }
        }
        
        return $results;
    }

    private function fetchWebKansaSite($companyUrl)
    {
        $html = $this->getHTMLContent("{$companyUrl}");
        
        // Area
        $strings = explode('<ul class="company-tags">', $html);
        $areaStr = $strings[1];
        $areas = explode('<li class="company-tags-item">', $areaStr);

        $newAreas = array();
        foreach ($areas as $area) {
            $pattern = '#<a[^>]*>(.*?)</a>#s';
            preg_match_all($pattern, $area, $matches);
            if (sizeof($matches[1]) > 0) {
                $newAreas[] = trim(str_replace('\n', '', str_replace('"', '', $matches[1][0])));
            }
        }

        $area = implode("、", $newAreas);

        $strings = explode('id="info"', $html);

        $pattern = '#<dl[^>]*>(.*?)</dl>#s';
        preg_match_all($pattern, $strings[1], $matches);

        if (sizeof($matches[1]) > 0) {
            $origin = $matches[1][0];
            $temps = explode('<dt>会社名</dt>', $origin);
            $temps = explode('</dd>', $temps[1]);
            $temps = explode('<dd>', $temps[0]);
            $name = $temps[1];

            $temps = explode('<dt>URL</dt>', $origin);
            $temps = explode('</dd>', $temps[1]);
            $temps = explode('<dd>', $temps[0]);
            $temps = explode('href="', $temps[1]);
            $temps = explode('"', $temps[1]);
            $url = $temps[0];

            return [
                'name' => $name,
                'url' => $url,
                'area' => $area
            ];
        }

        return null;
    }

    private function everything_in_tags($string, $tagname)
    {
        $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
        preg_match_all($pattern, $string, $matches);

        $areas = array();
        $htmlAreas = $this->find_area($string, 'h3');
        foreach ($htmlAreas as $htmlArea) {
            $area = str_replace('）', '', str_replace('（', '', $this->find_area($htmlArea, 'span')[0]));
            $areas[] = $area;
        }

        foreach ($matches[1] as $key => $match) {
            $results[] = $this->findData($match, 'a', $areas[$key]);
        }
        
        return $results;
    }

    private function find_area($string, $tagname) {
        $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
        preg_match_all($pattern, $string, $matches);

        return $matches[1];
    }

    private function findData($string, $tagname, $area = '')
    {
        $pattern = "#<\s*?$tagname\b[^>]*>(.*?)</$tagname\b[^>]*>#s";
        preg_match($pattern, $string, $matches);

        return [
            'name'      => str_replace('出典：', '', str_replace($matches[0], '', $string)),
            'url'       => str_replace('&nbsp;', '', $matches[1]),
            'area'      => $area
        ];
    }

    private function findPhonenumber($string)
    {
        $pattern = "/(\d{2}-\d{4}-\d{4}|\d{3}-\d{4}-\d{4}|\d{3}-\d{3}-\d{4}|\d{4}-\d{2}-\d{4})/";
        preg_match_all($pattern, $string, $matches);

        return $matches[1];
    }

    private function findEmail($string)
    {
        $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
        preg_match_all($pattern, $string, $matches);

        $res = array();

        foreach ($matches[1] as $key => $match) {
            if (!in_array($match, ['png', 'jpg', 'PNG', 'JPEG', 'gif', 'GIF'])) {
                $res[] = $matches[0][$key];
            }
        }

        return $res;
    }

   

    public function sendContact()
    {
        \DB::beginTransaction();

        try {
            $query = $this->makeQuery(request()->all());

            $query->whereIn('status', ['未対応', '送信失敗']);

            $companies = $query->get();

            if (sizeof($companies) === 0) {
                return back()
                        ->with(['system.message.info' => "未対応会社はありません。"]);
            }

            $contact = Contact::create([
                'surname'           => request()->get('surname'),
                'lastname'          => request()->get('lastname'),
                'fu_surname'        => request()->get('fu_surname'),
                'fu_lastname'       => request()->get('fu_lastname'),
                'email'             => request()->get('email'),
                'title'             => request()->get('title'),
                'myurl'             => request()->get('myurl'),
                'content'           => request()->get('content'),
                'homepageUrl'       => request()->get('homepageUrl'),
                'area'              => request()->get('zone'),
                'postalCode1'       => request()->get('postalCode1'),
                'postalCode2'       => request()->get('postalCode2'),
                'address'           => request()->get('address'),
                'phoneNumber1'      => request()->get('phoneNumber1'),
                'phoneNumber2'      => request()->get('phoneNumber2'),
                'phoneNumber3'      => request()->get('phoneNumber3'),
                'company'           => request()->get('company')
            ]);

            $total = 0;
            foreach ($companies as $company) {
                if(!isset($company->contact_form_url)||(empty($company->contact_form_url))){    
                    continue;
                }
                $contact->companies()->create([
                    'company_id'        => $company->id
                ]);
                $total++;
            }

            $contact->update([
                'contacts_num'        => $total
            ]);

            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();

            return back()
                ->with(['system.message.danger' => "送信できませんでした。"]);
        }

        return back()
                ->with(['system.message.success' => "送信しています。"]);

       
    }

    public function contactShow(Contact $contact)
    {
        $prefectures = array();
        foreach (config('values.prefectures') as $value) {
            $prefectures[$value] = $value;
        }
        $query = $contact->companies();
        if (!empty($value = Arr::get(request()->all(), 'status'))) {
            $query->where('is_delivered',$value);
        }

        
        $companies = $query->paginate(20);

        return view('admin.contact_show', compact('contact', 'companies','prefectures'));
    }

    public function sendShowContact(Contact $contact)
    {
        \DB::beginTransaction();

        try {
            $query = $contact->companies();
            if (!empty($value = Arr::get(request()->all(), 'status'))) {
                $query->where('is_delivered',$value);
            }
            $companies = $query->get();

            $contact = Contact::create([
                'surname'           => request()->get('surname'),
                'lastname'          => request()->get('lastname'),
                'fu_surname'        => request()->get('fu_surname'),
                'fu_lastname'       => request()->get('fu_lastname'),
                'email'             => request()->get('email'),
                'title'             => request()->get('title'),
                'myurl'             => request()->get('myurl'),
                'content'           => request()->get('content'),
                'homepageUrl'       => request()->get('homepageUrl'),
                'area'              => request()->get('zone'),
                'postalCode1'       => request()->get('postalCode1'),
                'postalCode2'       => request()->get('postalCode2'),
                'address'           => request()->get('address'),
                'phoneNumber1'      => request()->get('phoneNumber1'),
                'phoneNumber2'      => request()->get('phoneNumber2'),
                'phoneNumber3'      => request()->get('phoneNumber3'),
                'company'           => request()->get('company')
            ]);

            $total = 0;
            foreach ($companies as $companyContact) {
                $company = $companyContact->company;
                $contact->companies()->create([
                    'company_id'        => $company->id
                ]);
                $total++;
            }

            $contact->update([
                'contacts_num'        => $total
            ]);

            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();

            return back()
                ->with(['system.message.danger' => "送信できませんでした。"]);
        }

        return back()
                ->with(['system.message.success' => "送信しています。"]);
    }

    public function deleteEmail()
    {
        \App\Models\CompanyEmail::where('id', request()->get('id'))->delete();
        return response()->json(['status' => 'success']);
    }

    public function updateCompanyStatus()
    {
        \App\Models\Company::where('id', request()->get('id'))->update(['status' => request()->get('status')]);
        return response()->json(['status' => 'success']);
    }

    public function show(Company $company)
    {
        return view('admin.company_show', compact('company'));
    }

    public function deleteContact()
    {
        \App\Models\Contact::find(request()->get('id'))->delete();

        return back()->with(['system.message.success' => '削除しました。']);
    }

    

    private function getSiteUrlsForConstruct($string)
    {
        $pattern = '#<div class="company-list"[^>]*>(.*?)</div>#s';
        preg_match_all($pattern, $string, $matches);

        $results = [];
        foreach ($matches[1] as $match) {
            $temps = explode('<div class="company-list__wrapper">', $match);
            $results[] = [
                'url' => "https://careecon.jp" . str_replace('">', '', str_replace('<a href="', '', $temps[0])),
                'area' => ''
            ];
        }

        $pattern = '#<li class="project-list-icon__list--small project-list-icon__item"[^>]*>(.*?)</li>#s';
        preg_match_all($pattern, $string, $matches);

        foreach ($matches[1] as $key => $match) {
            $pattern = '#<p[^>]*>(.*?)</p>#s';
            preg_match_all($pattern, $match, $matches);
            if (sizeof($matches[1]) > 0 && isset($results[$key])) {
                $results[$key]['area'] = str_replace(' / ', '、', $matches[1][0]);
            }
        }

        $companyResults = [];
        foreach ($results as $result) {
            try {
                $html = $this->getHTMLContent($result['url']);

                $name = null;
                $pattern = '#<h1 class="com-profile-summary__company-name"[^>]*>(.*?)</h1>#s';
                preg_match_all($pattern, $html, $matches);
                if (sizeof($matches[1]) > 0) {
                    $name = $matches[1][0];
                }

                if (isset($name)) {
                    $pattern = '#<div class="com-profile-detail__layout-images"[^>]*>(.*?)</div>#s';
                    preg_match_all($pattern, $html, $matches);

                    if (sizeof($matches[1]) > 0) {
                        $html = $matches[1][0];

                        $pattern = '#<a target="_new"[^>]*>(.*?)</a>#s';
                        preg_match_all($pattern, $html, $matches);

                        if (sizeof($matches[1]) > 0) {
                            $companyResults[] = [
                                'url' => $matches[1][0],
                                'name' => $name,
                                'area' => $result['area']
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
        
        return $companyResults;
    }

    private function getSiteUrlsForType6($string)
    {
        $pattern = '#<figcaption class="clearfix"[^>]*>(.*?)</figcaption>#s';
        preg_match_all($pattern, $string, $matches);

        $companyResults = [];
        foreach ($matches[1] as $match) {
            try {
                $name = "";
                $area = "";
                $url = "";
                $pattern = '#<a[^>]*class="nt"[^>]*>(.*?)</a>#s';
                preg_match_all($pattern, $match, $matches);

                if (sizeof($matches[1]) > 0) $name = $matches[1][0];

                $pattern = '#<a[^>]*class="nurl"[^>]*>(.*?)</a>#s';
                preg_match_all($pattern, $match, $matches);

                if (sizeof($matches[1]) > 0) $url = $matches[1][0];

                $pattern = '#<a[^>]*class="nclass"[^>]*>(.*?)</a>#s';
                preg_match_all($pattern, $match, $matches);

                if (sizeof($matches[1]) > 0) $area = $matches[1][1];

                if ($name != '' && $url != '') {
                    $companyResults[] = [
                        'url' => $url,
                        'name' => $name,
                        'area' => $area
                    ];
                }
            } catch (\Throwable $e) {}
        }

        return $companyResults;
    }

    public function configIndex()
    {
        return view('admin.config');
    }

    public function updateConfig(Request $request)
    {
        $keys = [ 
                'MAIL_LIMIT'
            ];

        foreach($keys as $key) {
            if ($request->has($key)) {
                $newValue = $request->get($key);
                $this->upsert($key, $newValue);
            }
        }

        Artisan::call('config:cache');
        Artisan::call('queue:restart');
        sleep(1);

        return back()->with(['system.message.success' => '保存しました。']);
    }

    private function upsert($key, $value)
    {
        $envFilePath = app()->environmentFilePath();
        $contents = file_get_contents($envFilePath);
        if (preg_match("/ /", $value)) {
            $value = '"'.$value.'"';
        }
        if (preg_match("/^{$key}=[^\n\r]*/m", $contents)) {
            file_put_contents($envFilePath, preg_replace("/^{$key}=[^\n\r]*/m", $key.'='.$value, $contents));
        } else {
            file_put_contents($envFilePath, $contents . "\n{$key}={$value}");
        }
    }

    private function getSiteUrlsForType7($string)
    {
        $pattern = '#<a[^>]*class="m_company_link"[^>]*>(.*?)</a>#s';
        preg_match_all($pattern, $string, $matches);

        $results = [];
        foreach ($matches[0] as $match) {
            $results[] = [
                'url' => "https://amater.as" . str_replace('<a href="', '', str_replace('" class="m_company_link"></a>', '', $match)),
                'area' => ''
            ];
        }

        $pattern = '#<div class="spec company"[^>]*>(.*?)</div>#s';
        preg_match_all($pattern, $string, $matches);

        foreach ($matches[1] as $key => $match) {
            $pattern = '#<p[^>]*>(.*?)</p>#s';
            preg_match_all($pattern, $match, $matches);
            if (sizeof($matches[1]) > 0 && isset($results[$key])) {
                $results[$key]['area'] = $matches[1][0];
            }
        }

        $companyResults = [];
        foreach ($results as $result) {
            try {
                $html = $this->getHTMLContent($result['url']);

                $name = null;
                $pattern = '#<table>(.*?)</table>#s';
                preg_match_all($pattern, $html, $matches);

                $pattern = '#<tr>(.*?)</tr>#s';
                preg_match_all($pattern, $matches[1][0], $matches);

                if (sizeof($matches[1]) > 0) {
                    $pattern = '#<td>(.*?)</td>#s';
                    preg_match_all($pattern, $matches[1][0], $matches2);
                    $name = $matches2[1][1];
                }

                $html = $matches[1][sizeof($matches[1]) - 1];
                $pattern = '#<a[^>]*>(.*?)</a>#s';
                preg_match_all($pattern, $html, $matches2);

                if (sizeof($matches2[1]) > 0) {
                    $companyResults[] = [
                        'url' => $matches2[1][0],
                        'name' => $name,
                        'area' => $result['area']
                    ];
                }
            } catch (\Throwable $e) {}
        }

        return $companyResults;
    }

    private function getSiteUrlsForType8($string)
    {
        $pattern = '#<h4[^>]*class="searches__result__list__header__title"[^>]*>(.*?)</h4>#s';
        preg_match_all($pattern, $string, $matches);
        if (sizeof($matches[1]) == 0) \Log::error("EMPTY HERE:  " . $string);
        $results = [];
        foreach ($matches[1] as $match) {
            $temps = explode('">', $match);
            $url = str_replace('<a href="', '', $temps[0]);
            $name = str_replace('</a>', '', $temps[1]);
            $results[] = [
                'url' => "https://baseconnect.in" . $url,
                'name' => $name
            ];
        }
        $companyResults = [];
        foreach ($results as $key => $result) {
            try {
                $html = $this->getHTMLContent($result['url']);

                $name = null;
                $pattern = '#<p class="node__box__heading__link node__box__heading__link-othersite">(.*?)</p>#s';
                preg_match_all($pattern, $html, $matches);
                $url = '';
                if (sizeof($matches[1]) > 0) {
                    $temps = explode('"', $matches[1][0]);
                    $url = $temps[1];
                }

                $area = '';
                $pattern = '#<p value="https://github.com/zenorocha/clipboard.js.git">(.*?)</p>#s';
                preg_match_all($pattern, $html, $matches);

                if (sizeof($matches[1]) > 0) {
                    $temps = explode('<br>', $matches[1][0]);
                    $address = $temps[1];
                    foreach (config('values.prefectures') as $value) {
                        if (strpos($address, $value) !== false) {
                            $area = $value;
                            break;
                        }
                    }
                }

                if (isset($url) && $url !== '') {
                    $companyResults[] = [
                        'url' => $url,
                        'name' => $result['name'],
                        'area' => $area
                    ];
                }
            } catch (\Throwable $e) {}
        }

        return $companyResults;
    }

    
    public function reset()
    {
        $query = $this->makeQuery(request()->all());
        
        $query->where('status', '送信済み')->update(['status' => '未対応']);
    
        return back();
    }

    public function stopReceive($encrypted)
    {
        $companyId = Crypt::decryptString($encrypted);
        $company = Company::where('id',$companyId)->get();
        
        $company->toQuery()->update(['status' => '拒絶']);

        return view('done');
    }

    public function exportCSV()
    {
        return Excel::download(new CompanyExport(request()->all()), '会社一覧.csv', \Maatwebsite\Excel\Excel::CSV);
    }
    public function exportContactCSV(Contact $contact)
    {
        return Excel::download(new ContactExport($contact, request()->all()), 'フォーム一覧.csv', \Maatwebsite\Excel\Excel::CSV);
    }
    public function importCSV()
    {
        try {
            
          Excel::import(new CompanyImport, request()->file('file'));
         
        } catch (\Throwable $e) {
            return back()->with(['system.message.error' => __('CSVファイルのアップロードに失敗しました')]);
            
        }
        
        return back()->with(['system.message.success' => __(':itemが完了しました。', ['item' => __('アップロード(CSV)')])]);
    }
    public function redirect() {
        return redirect(route('admin.dashboard'));
    }
    public function read($companyId,$contacId) {
        $counts = CompanyContact::where('company_id',$companyId)->where('contact_id',$contacId)->first()->counts;
        CompanyContact::where('company_id',$companyId)->where('contact_id',$contacId)->update(array('counts'=>$counts+1));
        $myurl = Contact::where('id',$contacId)->first()->myurl;
        return redirect($myurl);
    }
}
