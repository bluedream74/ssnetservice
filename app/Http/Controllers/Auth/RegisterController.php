<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Mail\User\Register;
use App\Mail\User\Verify;
use App\Models\Role;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\Helpers\EmailService;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        if (!empty($data['email'])) {
            \App\Models\User::where('email', $data['email'])
                            ->where('is_active', 0)->forceDelete();
        }

        $customAttributes = ['password' => 'パスワード'];
        return Validator::make($data, [
            // 'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,NULL,id,deleted_at,NULL'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], $customAttributes);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $roles = Role::pluck('id', 'name')->toArray();

        return User::create([
            'name' => '', // $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $roles[Role::STUDENT] ?? UserRole::SUBSCRIBER,
            'confirmation_code' => sha1(time()),
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        $user = $this->create($request->all());

        return $this->sendMail($user);
    }

    public function sendMail(User $user)
    {
        if ($user->is_active !== 1) {
            $data = [
                'url_verify' => route('user.verify', ['code' => $user->confirmation_code]),
            ];
            \Log::error(route('user.verify', ['code' => $user->confirmation_code]));

            \App\Jobs\SendEmailJob::dispatch($user, new \App\Notifications\CustomEmailNotification($data, 'student.verify'));
        }

        return redirect(route('register.complete'))->with('user_id', $user->id);
    }

    public function verifyUser($code)
    {
        if (!$code) {
            return redirect(route('student.login'))->with('error', 'URLが無効になりました。');
        }

        $user = User::where('confirmation_code', $code)->first();

        if (!$user) {
            return redirect(route('student.login'))->with('error', 'URLが無効になりました。');
        }

        $user->confirmation_code = null;
        $user->is_active = 1;
        $user->save();

        $this->guard()->login($user);

        return redirect(route('student.register.info'));
    }
}
