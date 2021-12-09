<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\User;
use App\Models\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Stripe;

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
        'paycheck'          => 0,
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

  public function payment() {
    $user = \Auth::guard('admin')->user();
    $config = Config::where('id',1)->first();
    foreach(getPlans() as $key=>$val) {
      if($key == $config->plan) {
        $planKey=$key;$planVal=$val;
      }
    }
    return view('admin.payment',compact('user','planKey','planVal'));
  }

  public function paymentUpdate(Request $request) {

    $user = \Auth::guard('admin')->user();
    $input = $request->all();
    $token =  $request->stripeToken;
    $paymentMethod = $request->paymentMethod;
    try {

        Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        
        if (is_null($user->stripe_id)) {
            $stripeCustomer = $user->createAsStripeCustomer();
        }

        \Stripe\Customer::createSource(
            $user->stripe_id,
            ['source' => $token]
        );

        $user->newSubscription('test',$input['plan'])
            ->create($paymentMethod, [
            'email' => $user->email,
        ]);

        return back()->with('success','サブスクリプションが完了しました。');
    } catch (Exception $e) {
        return back()->with('success',$e->getMessage());
    }

    
    User::where('id',$user->id)->update([
      'stripe_id'    =>  request()->get('stripe_id') ,
      'card_brand'   =>  request()->get('card_brand'),
    ]);
    return back()->with(['system.message.success' => '更新されました。']);
  }

  public function checkStop() {
    $user = \Auth::guard('admin')->user();
    User::where('id',$user->id)->update([
      'paycheck'    =>  1 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが停止しました。']);
  }

  public function checkStart() {
    $user = \Auth::guard('admin')->user();
    User::where('id',$user->id)->update([
      'paycheck'    =>  0 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが開始されました。']);
  }

  public function stopUser() {
    User::where('id',request()->get('id'))->update([
      'paycheck'    =>  0 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが停止しました。']);
  }

  public function startUser() {
    User::where('id',request()->get('id'))->update([
      'paycheck'    =>  1 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが開始されました。']);
  }

  public function config() {
    $config = Config::where('id',1)->first();
    return view('admin.master_config',compact('config'));
  }
  
  public function webhook(Request $request) {
    // You can find your endpoint's secret in your webhook settings
    $endpoint_secret = config('services.stripe.webhooksecret');

    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
    $event = null;

    try
    {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } 
    catch(\UnexpectedValueException $e)
    {
            // Invalid payload
            return response()->json([
                'message' => 'Invalid payload',
            ], 200);
    }
    catch(\Stripe\Exception\SignatureVerificationException $e)
    {
        // Invalid signature
        return response()->json([
            'message' => 'Invalid signature',
        ], 200);
    }


      switch ($event->type) {
        case 'invoice.payment_succeeded': // set state 1
            User::where('email', $event->data->object->customer_email)->update(array('paycheck'=>1));
            break;
        case 'invoice.payment_failed':
            break;
        default:
            break;
    }
    http_response_code(200);
  }

  public function planUpdate() {
    Config::where('id',1)->update([
      'plan'    =>  request()->get('plan') ,
    ]);
    return back()->with(['system.message.success' => 'プランが設定されました。']);
  }
}
