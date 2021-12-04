<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class UserController extends BaseController
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

  public function index()
  {
    $users = User::where('role_id','1')->where('is_active','1')->where('id','!=','1')->get();
    return view('admin.users', compact('users'));
  }

  public function deleteUser()
  {
      $user = User::where('id',request()->get('id'))->delete();

      return back()->with(['system.message.success' => '削除しました。']);
  }

  public function addUser(Request $request)
  {
      User::create([
        'name'           => request()->get('name'),
        'role_id'        => 1,
        'is_active'      => 1,
        'email'          => request()->get('email'),
        'password'       => Hash::make(request()->get('password')),
        'avatar'         => null,
        'remember_token' => str_random(10),
      ]);

      return back()->with(['system.message.success' => '追加しました。']);
  }

  public function edit($user_id)
  {
      $user = User::where('id',$user_id)->first();
      return view('admin.userEdit',compact('user'));
  }

  public function editName(User $user)
  {
    $user->update([
      'name'   => request()->get('name')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function editEmail(User $user)
  {
    $user->update([
      'email'   => request()->get('email')
    ]);

    return back()->with(['system.message.success' => '編集されました。']);
  }

  public function editPassword(User $user)
  {
    $user->update([
      'password'   => Hash::make(request()->get('password'))
    ]);

    return back()->with(['system.message.success' => '更新されました。']);
  }

}
