<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', function (User $user) {
            return $user->role === 'admin';
        });

        Gate::define('accountant', function (User $user) {
            return $user->role === 'accountant' || $user->role === 'admin';
        });

        Gate::define('manager', function (User $user) {
            return $user->role === 'manager' || $user->role === 'admin';
        });
    }
}
