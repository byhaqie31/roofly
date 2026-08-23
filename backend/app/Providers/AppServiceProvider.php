<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
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
        JsonResource::withoutWrapping();

        // Super-admins pass every ability check (spec § 5). Returning null
        // lets Spatie's own Gate::before resolve normal permissions.
        Gate::before(function ($user) {
            return ($user instanceof User && $user->is_super_admin) ? true : null;
        });
    }
}
