<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Support\Facades\Validator;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    protected function showResetForm($token)
    {
        $item = \App\Models\PasswordReset::where('token', $token)->first();
        if (empty($item)) return redirect(route('home'));

        $email = $item->email;
        return view('auth.passwords.reset', compact('email'));
    }

    protected function reset()
    {
        $customAttributes = [
            'password' => 'パスワード',
        ];

        $validator = Validator::make(request()->all(), [
            'password' => [
                    'required',
                    'confirmed',
                    'max:255'
                ]
            ],
            [],
            $customAttributes
        );

        $validatedData = $validator->validate();
        
        $user = \App\Models\User::where('email', request()->get('email'))->first();
        if (isset($user)) {
            $user->update([
                'password'      => bcrypt(request()->get('password'))
            ]);
        }

        \App\Models\PasswordReset::where('email', request()->get('email'))->delete();

        return view('auth.passwords.reset_complete');
    }
}
