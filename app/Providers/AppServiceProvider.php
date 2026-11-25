<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Matches the design handoff's password policy (checkPwRequirements /
        // checkRequirements in the agency and MCMC prototypes): at least 8
        // characters, one uppercase letter, one number, one special character.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());

        // Behind the host's proxy/CDN: see the real visitor IP and the real scheme.
        $proxies = (string) config('security.trusted_proxies');
        TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));

        if (config('security.force_https')) {
            URL::forceScheme('https');
        }
    }
}
