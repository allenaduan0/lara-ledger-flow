<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('authentication', fn (Request $request) => [
            Limit::perMinute(10)->by('auth:'.$request->ip()),
            Limit::perMinute(5)->by('auth-email:'.hash('sha256', strtolower((string) $request->input('email'))).'|'.$request->ip()),
        ]);

        RateLimiter::for('financial', fn (Request $request) => Limit::perMinute(30)->by('financial:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('authenticated-api', fn (Request $request) => Limit::perMinute(120)->by('api:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(60)->by('admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
