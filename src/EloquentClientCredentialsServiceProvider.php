<?php

namespace Whilesmart\EloquentClientCredentials;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EloquentClientCredentialsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/client-credentials.php', 'client-credentials');
    }

    public function boot(): void
    {
        $this->registerRoutes();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'client-credentials');

        $this->publishes([
            __DIR__.'/../config/client-credentials.php' => config_path('client-credentials.php'),
        ], 'client-credentials-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'client-credentials-migrations');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/client-credentials'),
        ], 'client-credentials-lang');

        $this->publishes([
            __DIR__.'/../routes/client-credentials.php' => base_path('routes/client-credentials.php'),
        ], 'client-credentials-routes');
    }

    protected function registerRoutes(): void
    {
        if (! config('client-credentials.routes.enabled', false)) {
            return;
        }

        Route::prefix(config('client-credentials.routes.prefix', 'api'))
            ->middleware(config('client-credentials.routes.middleware', []))
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/client-credentials.php');
            });
    }
}
