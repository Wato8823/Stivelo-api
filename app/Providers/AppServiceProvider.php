<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        $this->configureRateLimiting();
        $this->configureModelStrictness();
    }

    /**
     * Limitation de débit (CDC §9) : endpoints publics, création de demande
     * et tentative de connexion.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute((int) config('catalog.rate_limits.public_per_minute', 120))
                ->by($request->ip());
        });

        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute((int) config('catalog.rate_limits.orders_per_minute', 5))
                ->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'message' => 'Trop de demandes envoyées depuis cette connexion. Merci de patienter une minute.',
                    'code' => 'too_many_requests',
                    'request_id' => $request->attributes->get('request_id'),
                ], 429, $headers));
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute((int) config('catalog.rate_limits.login_per_minute', 5))->by($key);
        });
    }

    /**
     * En développement, toute erreur de programmation doit être visible :
     * attribut non déclaré, accès à une colonne inexistante, N+1 évitable.
     */
    private function configureModelStrictness(): void
    {
        if ($this->app->isProduction()) {
            return;
        }

        Model::preventSilentlyDiscardingAttributes();
        Model::preventAccessingMissingAttributes();

        if (config('app.lazy_loading_check', false)) {
            Model::preventLazyLoading();
        }
    }
}
