<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\Source;
use App\Models\SubSource;
use App\Models\Contact;
use App\Models\CompanyEmail;
use Illuminate\Support\Arr;
use App\Exports\CompanyExport;
use Maatwebsite\Excel\Facades\Excel;

class CompanyController extends BaseController
{
  /**
   * CompanyController constructor
   *
   */
  public function __construct(
    \Illuminate\Contracts\Events\Dispatcher $events
  )
  {
    parent::__construct($events);
  }

  public function show(Company $company)
  {
    $prefectures = array();
    foreach (config('values.prefectures') as $value) {
        $prefectures[$value] = $value;
    }
    return view('admin.company_show', compact('company','prefectures'));
  }

  public function addEmail(Company $company)
  {
    $company->emails()->create([
      'email'   => request()->get('email')
    ]);

    return back()->with(['system.message.success' => '追加しました。']);
  }

  public function removeEmail(Company $company)
  {
    $company->emails()->where('id', request()->get('id'))->delete();

    return response()->json(['data' => 'success']);
  }

  public function addPhone(Company $company)
  {
    $company->phones()->create([
      'phone'   => request()->get('phone')
    ]);

    return back()->with(['system.message.success' => '追加しました。']);
  }

  public function editURL(Company $company)
  {
    $company->update([
      'url'   => request()->get('url')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function editName(Company $company)
  {
    $company->update([
      'name'   => request()->get('name')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function addURL(Company $company)
  {
    $company->update([
      'url'   => request()->get('url')
    ]);

    return back()->with(['system.message.success' => '追加しました。']);
  }

  public function editContacturl(Company $company)
  {
    
    $company->update([
      'contact_form_url'   => request()->get('contact_form_url')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function addContacturl(Company $company)
  {
    $company->update([
      'contact_form_url'   => request()->get('contact_form_url')
    ]);

    return back()->with(['system.message.success' => '追加しました。']);
  }

  public function editcategory(Company $company)
  {
    $company->update([
      'source'   => request()->get('source')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function addcategory(Company $company)
  {
    $company->update([
      'source'   => request()->get('source')
    ]);

    return back()->with(['system.message.success' => '追加しました。']);
  }

  public function editsubcategory(Company $company)
  {
    
    $company->update([
      'subsource'   => request()->get('subsource')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function addsubcategory(Company $company)
  {
    $company->update([
      'subsource'   => request()->get('subsource')
    ]);

    return back()->with(['system.message.success' => '追加しました。']);
  }

  public function updatearea(Company $company)
  {
    $company->update([
      'area'   => request()->get('area')
    ]);

    return back()->with(['system.message.success' => '更新されました。']);
  }

  
  public function removePhone(Company $company)
  {
    $company->phones()->where('id', request()->get('id'))->delete();

    return response()->json(['data' => 'success']);
  }

  public function deleteDuplicate()
  {
    $urls = Company::whereNotNull('url')->select('url')->distinct()->pluck('url');
    // echo Company::whereNotNull('url')->count();
    // dd(sizeof($urls));
    if (sizeof($urls) < Company::whereNotNull('url')->count()) {
      foreach ($urls as $url) {
        $parse = parse_url($url);
        try{
          $host = str_replace('www.', '', $parse['host']);

          if (Company::where('url', 'LIKE', "%{$host}%")->count() > 1) {
            $company = Company::where('url', 'LIKE', "%{$host}%")->oldest()->first();
            if(Company::where('subsource', $company->subsource)->count() == 1) {
              SubSource::where('name',$company->subsource)->delete();
            }
            Company::where('url', 'LIKE', "%{$host}%")
                ->where('id', '!=', $company->id)
                ->delete();
          }
        }
        catch(\Throwable $e) {
          print_r($e->getMessage());continue;
        }
      }
    }

    // $emails = CompanyEmail::whereNotNull('email')->select('email')->distinct()->pluck('email');
    // if (sizeof($emails) < CompanyEmail::whereNotNull('email')->count()) {
    //   foreach ($emails as $email) {
    //     if (CompanyEmail::where('email', $email)->count() > 1) {
    //       $companyEmail = CompanyEmail::where('email', $email)->first();
    //       CompanyEmail::where('email', $email)
    //               ->where('id', '!=', $companyEmail->id)
    //               ->delete();
    //     }
    //   }
    // }

    return back()->with(['system.message.success' => '削除しました。']);
  }

  public function deleteEmail()
  {
    CompanyEmail::where('is_verified', 0)->delete();

    return back()->with(['system.message.success' => '削除しました。']);
  }

  public function deleteCompany()
  {
      $company = Company::where('id',request()->get('id'))->first();
      Company::find(request()->get('id'))->delete();

      $subSource = Company::where('subsource',$company->subsource)->count();
      if($subSource==0){
        SubSource::where('name',$company->subsource)->delete();
      }
      $source = Company::where('source',$company->source)->count();
      if($source==0) {
        Source::where('name',$company->source)->delete();
      }

      return back()->with(['system.message.success' => '削除しました。']);
  }
}
