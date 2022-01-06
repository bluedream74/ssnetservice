<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
// use Laravel\Cashier\Cashier;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Cashier::ignoreMigrations();
        $this->app->register(\Laravel\Dusk\DuskServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('layouts.admin', function ($view) {
            $authUser = Auth::user();
            $view->with('authUser', $authUser);
        });
        
        Schema::defaultStringLength(191);
        Paginator::defaultView('vendor.pagination.default');
    }
}
