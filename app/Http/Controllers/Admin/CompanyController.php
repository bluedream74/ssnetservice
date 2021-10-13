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
    return view('admin.company_show', compact('company'));
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
  
  public function removePhone(Company $company)
  {
    $company->phones()->where('id', request()->get('id'))->delete();

    return response()->json(['data' => 'success']);
  }

  public function deleteDuplicate()
  {
    $urls = Company::whereNotNull('url')->select('url')->distinct()->pluck('url');
    if (sizeof($urls) < Company::whereNotNull('url')->count()) {
      foreach ($urls as $url) {
        $parse = parse_url($url);
        $host = str_replace('www.', '', $parse['host']);

        if (Company::where('url', 'LIKE', "%{$host}%")->count() > 1) {
          $company = Company::where('url', 'LIKE', "%{$host}%")->first();
          Company::where('url', 'LIKE', "%{$host}%")
              ->where('id', '!=', $company->id)
              ->delete();
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
