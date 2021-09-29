<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $guards = ['admin', 'web'];
        $redirects = ['admin.dashboard', 'mypage'];
        foreach ($guards as $key => $item) {
            if (\Auth::guard($item)->check()) {
                return redirect(route($redirects[$key]));
            }
        }

        return $next($request);
    }
}
