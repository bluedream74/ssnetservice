<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (\Auth::guard('web')->check()) {
            return redirect(route('mypage'));
        }

        if (!Auth::guard('admin')->check()) {
            return redirect(route('admin.login'));
        }
        
        if (!Auth::guard('admin')->user()->check) {
            return redirect(route('admin.payment'));
        }

        return $next($request);
    }
}
