<?php

namespace App\Http\Middleware;

use Closure;

class StudentAuthenticate
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (\Auth::guard('admin')->check()) {
            return redirect(route('admin.dashboard'));
        }

        if (!auth('web')->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ]);
            }

            return redirect(route('web.login'));
        }

        return $next($request);
    }
}
