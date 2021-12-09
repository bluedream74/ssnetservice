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
        'check'          => 0,
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
      'check'    =>  1 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが停止しました。']);
  }

  public function checkStart() {
    $user = \Auth::guard('admin')->user();
    User::where('id',$user->id)->update([
      'check'    =>  0 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが開始されました。']);
  }

  public function stopUser() {
    User::where('id',request()->get('id'))->update([
      'check'    =>  0 ,
    ]);
    return back()->with(['system.message.success' => 'サブスクリプションが停止しました。']);
  }

  public function startUser() {
    User::where('id',request()->get('id'))->update([
      'check'    =>  1 ,
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
            $subscription_id = '';
            $charge_id = '';
            $payment_intent_id = '';
            $amount = 0;

            if(isset($event->data->object->subscription) && $event->data->object->subscription != ''){
                $subscription_id = $event->data->object->subscription;
            }
            if(isset($event->data->object->charge) && $event->data->object->charge != ''){
                $charge_id = $event->data->object->charge;
            }
            if(isset($event->data->object->payment_intent) && $event->data->object->payment_intent != ''){
                $payment_intent_id = $event->data->object->payment_intent;
            }
            if(isset($event->data->object->total) && $event->data->object->total != ''){
                $amount = $event->data->object->total;
            }
            \Log::debug('invoice.payment_succeeded subscription_id='.$subscription_id.' charge_id=' . $charge_id . ' payment_intent_id=' . $payment_intent_id);

            // $invoiceResult = null;
            // if($subscription_id){
            //     $invoiceResult = InvoiceResult::where('subscription_id', $subscription_id);
            //     // if($charge_id)
            //     //     $invoiceResult = $invoiceResult->where('charge_id', $charge_id);
            //     if($payment_intent_id)
            //         $invoiceResult = $invoiceResult->where('payment_intent_id', $payment_intent_id);
            //     $invoiceResult = $invoiceResult->orderBy('id', 'desc')->first();
            // }else if($charge_id){
            //     $invoiceResult = InvoiceResult::where('charge_id', $charge_id);
            //     if($payment_intent_id)
            //         $invoiceResult = $invoiceResult->where('payment_intent_id', $payment_intent_id);
            //     $invoiceResult = $invoiceResult->orderBy('id', 'desc')->first();
            // }
            // if($invoiceResult != null){
            //     $invoiceResult->update([
            //         'subscription_id' => $subscription_id,
            //         'charge_id' => $charge_id,
            //         'payment_intent_id' => $payment_intent_id,
            //         'status' => 1
            //     ]);
            // }else{
                $invoice_id = '';
                $invoice = Invoice::where('subscription_id', $subscription_id)
                                    ->orderBy('id', 'desc')->first();
                if($invoice)
                    $invoice_id = $invoice->id;

                $invoiceResult = InvoiceResult::create([
                    'invoice_id' => $invoice_id,
                    'subscription_id' => $subscription_id,
                    'charge_id' => $charge_id,
                    'payment_intent_id' => $payment_intent_id,
                    'status' => 1,
                    'amount' => $amount
                ]);
  //                }                         
            break;
        case 'invoice.payment_failed':
        case 'invoice.payment_action_required': // set state 0
            $subscription_id = '';
            $charge_id = '';
            $payment_intent_id = '';
            $amount = 0;
            if(isset($event->data->object->subscription) && $event->data->object->subscription != ''){
                $subscription_id = $event->data->object->subscription;
            }
            if(isset($event->data->object->charge) && $event->data->object->charge != ''){
                $charge_id = $event->data->object->charge;
            }
            if(isset($event->data->object->payment_intent) && $event->data->object->payment_intent != ''){
                $payment_intent_id = $event->data->object->payment_intent;
            }
            if(isset($event->data->object->total) && $event->data->object->total != ''){
                $amount = $event->data->object->total;
            }
            \Log::debug('invoice.payment_failed subscription_id='.$subscription_id.' charge_id=' . $charge_id . ' payment_intent_id=' . $payment_intent_id);

            // $invoiceResult = null;
            // if($subscription_id){
            //     $invoiceResult = InvoiceResult::where('subscription_id', $subscription_id);
            //     // if($charge_id)
            //     //     $invoiceResult = $invoiceResult->where('charge_id', $charge_id);
            //     if($payment_intent_id)
            //         $invoiceResult = $invoiceResult->where('payment_intent_id', $payment_intent_id);
            //     $invoiceResult = $invoiceResult->orderBy('id', 'desc')->first();
            // }else if($charge_id){
            //     $invoiceResult = InvoiceResult::where('charge_id', $charge_id);
            //     if($payment_intent_id)
            //         $invoiceResult = $invoiceResult->where('payment_intent_id', $payment_intent_id);
            //     $invoiceResult = $invoiceResult->orderBy('id', 'desc')->first();
            // }
            // if($invoiceResult != null){
            //     $invoiceResult->update([
            //         'subscription_id' => $subscription_id,
            //         'charge_id' => $charge_id,
            //         'payment_intent_id' => $payment_intent_id,
            //         'status' => 0
            //     ]);
            // }else{
                $invoice_id = '';
                $invoice = null;
                if($subscription_id != ''){
                    $invoice = Invoice::where('subscription_id', $subscription_id)
                    ->orderBy('id', 'desc')->first();
                }else if($charge_id != ''){
                    $invoice = Invoice::where('charge_id', $charge_id)
                    ->orderBy('id', 'desc')->first();
                }
                if($invoice){
                    $invoice_id = $invoice->id;
                    // check social invoice, and set unpaid and deactivated
                    $social_inovoice = DB::table('gisoft_backend_invoice')
                    ->join('gisoft_invoice_detail_accounts', 'gisoft_backend_invoice.id', '=', 'gisoft_invoice_detail_accounts.invoice_id')
                    ->join('gisoft_social_infos', 'gisoft_invoice_detail_accounts.social_id', '=', 'gisoft_social_infos.id')
                    ->select('gisoft_social_infos.id as social_id', 'gisoft_backend_invoice.id as invoice_id')
                    ->where('gisoft_backend_invoice.id', $invoice_id)
                    ->first();
                    if($social_inovoice){
                        Invoice::where('id', $social_inovoice->invoice_id)
                            ->update(['is_payed' => 0]);

                        Social::where('id', $social_inovoice->social_id)
                            ->update(['is_payed' => 0, 'is_activated' => 0]);
                    }

                    // check user invoice, and set unpaid and deactivated
                    $user_inovoice = DB::table('gisoft_backend_invoice')
                    ->join('gisoft_invoice_detail_users', 'gisoft_backend_invoice.id', '=', 'gisoft_invoice_detail_users.invoice_id')
                    ->join('backend_users', 'gisoft_invoice_detail_users.email', '=', 'backend_users.email')
                    ->select('backend_users.id as user_id', 'gisoft_backend_invoice.id as invoice_id')
                    ->where('gisoft_backend_invoice.id', $invoice_id)
                    ->first();
                    if($user_inovoice){
                        Invoice::where('id', $user_inovoice->invoice_id)
                            ->update(['is_payed' => 0]);

                        User::where('id', $user_inovoice->user_id)
                            ->update(['is_activated' => 0]);

                        ConsultantDetail::where('user_id', $user_inovoice->user_id)
                        ->update(['is_activated' => 0]);

                        UserCompanyDetail::where('user_id', $user_inovoice->user_id)
                        ->update(['is_activated' => 0]);

                    }
                }

                $invoiceResult = InvoiceResult::create([
                    'invoice_id' => $invoice_id,
                    'subscription_id' => $subscription_id,
                    'charge_id' => $charge_id,
                    'payment_intent_id' => $payment_intent_id,
                    'status' => 0,
                    'amount' => $amount
                ]);
  //                }                         
            break;

        case 'charge.refunded': // set state 2
            $subscription_id = '';
            $charge_id = '';
            $payment_intent_id = '';
            $amount = 0;

            if(isset($event->data->object->payment_intent) && $event->data->object->payment_intent != ''){
                $payment_intent_id = $event->data->object->payment_intent;
            }
            if(isset($event->data->object->amount) && $event->data->object->amount != ''){
                $amount = $event->data->object->amount;
            }

            \Log::debug('charge.refunded payment_intent_id='.$payment_intent_id);

            $invoiceResult = InvoiceResult::where('payment_intent_id', $payment_intent_id)->orderBy('id', 'desc')->first();
            if($invoiceResult){
                $subscription_id = $invoiceResult->subscription_id;
                $charge_id = $invoiceResult->charge_id;
            }
            
            $invoice_id = '';
            $invoice = null;
            if($subscription_id != ''){
                $invoice = Invoice::where('subscription_id', $subscription_id)
                ->orderBy('id', 'desc')->first();
            }else if($charge_id != ''){
                $invoice = Invoice::where('charge_id', $charge_id)
                ->orderBy('id', 'desc')->first();
            }

            if($invoice){
                $invoice_id = $invoice->id;

                // check social invoice, and set unpaid and deactivated
                $social_inovoice = DB::table('gisoft_backend_invoice')
                ->join('gisoft_invoice_detail_accounts', 'gisoft_backend_invoice.id', '=', 'gisoft_invoice_detail_accounts.invoice_id')
                ->join('gisoft_social_infos', 'gisoft_invoice_detail_accounts.social_id', '=', 'gisoft_social_infos.id')
                ->select('gisoft_social_infos.id as social_id', 'gisoft_backend_invoice.id as invoice_id')
                ->where('gisoft_backend_invoice.id', $invoice_id)
                ->first();
                if($social_inovoice){
                    Invoice::where('id', $social_inovoice->invoice_id)
                        ->update(['is_payed' => 0]);

                    Social::where('id', $social_inovoice->social_id)
                        ->update(['is_payed' => 0, 'is_activated' => 0]);
                }

                // check user invoice, and set unpaid and deactivated
                $user_inovoice = DB::table('gisoft_backend_invoice')
                ->join('gisoft_invoice_detail_users', 'gisoft_backend_invoice.id', '=', 'gisoft_invoice_detail_users.invoice_id')
                ->join('backend_users', 'gisoft_invoice_detail_users.email', '=', 'backend_users.email')
                ->select('backend_users.id as user_id', 'gisoft_backend_invoice.id as invoice_id')
                ->where('gisoft_backend_invoice.id', $invoice_id)
                ->first();
                if($user_inovoice){
                    Invoice::where('id', $user_inovoice->invoice_id)
                        ->update(['is_payed' => 0]);

                    User::where('id', $user_inovoice->user_id)
                        ->update(['is_activated' => 0]);

                    ConsultantDetail::where('user_id', $user_inovoice->user_id)
                        ->update(['is_activated' => 0]);

                    UserCompanyDetail::where('user_id', $user_inovoice->user_id)
                        ->update(['is_activated' => 0]);
                }                    
            }

            $invoiceResult = InvoiceResult::create([
                'invoice_id' => $invoice_id,
                'subscription_id' => $subscription_id,
                'charge_id' => $charge_id,
                'payment_intent_id' => $payment_intent_id,
                'status' => 2,
                'amount' => $amount
            ]);
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
