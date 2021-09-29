<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserLoginRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Redirect;
use App\Models\Student;

class AuthController extends Controller
{
    use AuthenticatesUsers;

    protected $guardName;
    protected $redirectTo;

    public function __construct()
    {
        $this->middleware('auth.' . $this->guardName)
            ->except([
                'showLoginForm',
                'logout',
                'login',
                'redirectToProvider',
                'handleProviderCallback'
            ]);
        
        $this->middleware('guest:' . $this->guardName)->except(['logout', 'handleProviderCallback']);
    }

    public function guard()
    {
        return Auth::guard($this->guardName);
    }

    protected function getRoleByGuard()
    {
        $roles = Role::pluck('id', 'name')->toArray();

        switch ($this->guardName) {
            case 'web':
                return $roles[Role::STUDENT] ?? UserRole::SUBSCRIBER;
            case 'admin':
                return $roles[Role::ADMINISTRATOR] ?? UserRole::ADMINISTRATOR;
            default:
                return '';
        }
    }

    public function login(UserLoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $credentials['is_active'] = true;
        $credentials['role_id'] = $this->getRoleByGuard();

        if ($this->guard()->attempt($credentials, $request->filled('remember'))) {
            if ($request->session()->has('login_after')) {
                $url = $request->session()->pull('login_after');
                $request->session()->forget('login_after');
                return redirect($url);
            }
            return $this->sendLoginResponse($request);
        }

        return $this->sendFailedLoginResponse($request);
    }

    public function loginPost()
    {
        $credentials = request()->only('email', 'password');
        $credentials['is_active'] = true;
        $credentials['role_id'] = $this->getRoleByGuard();

        if ($this->guard()->attempt($credentials)) {
            return Redirect::intended();
        }
        return back();
    }

    public function showLoginForm()
    {
        return view($this->guardName . '.login');
    }

    public function me()
    {
        return $this->guard()->user();
    }

    public function logout()
    {
        $this->guard()->logout();

        return redirect(route($this->guardName . '.login'));
    }
}
