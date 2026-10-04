<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Builder::defaultStringLength(191);

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by("login-email:{$email}"),
                Limit::perMinute(20)->by("login-ip:{$request->ip()}"),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(10)->by("register:{$request->ip()}");
        });

        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(20)->by('oauth:'.($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('sync', function (Request $request) {
            return Limit::perMinute(5)->by('sync:'.$request->user()->id);
        });

        RateLimiter::for('mutation', function (Request $request) {
            return Limit::perMinute(60)->by('mutation:'.$request->user()->id);
        });
    }
}
