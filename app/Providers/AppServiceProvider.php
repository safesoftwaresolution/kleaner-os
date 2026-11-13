<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('manage-catalog', fn (User $user) => $user->role === UserRole::Owner);
        Gate::define('view-reports', fn (User $user) => $user->role === UserRole::Owner);
        // Customer phones and spending: owner and cashier; operators only wash.
        Gate::define('manage-customers', fn (User $user) => in_array($user->role, [UserRole::Owner, UserRole::Cashier], true));
    }
}
