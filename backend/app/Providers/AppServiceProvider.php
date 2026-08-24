<?php

namespace App\Providers;

use App\Models\User;
use App\Support\GoogleIdToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            GoogleIdToken::class,
            fn () => new GoogleIdToken((string) config('services.google.client_id')),
        );
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

        RateLimiter::for('track', fn (Request $request) => Limit::perMinute(120)->by('track:' . $request->ip()));
    }
}
