<?php

namespace Whilesmart\EloquentClientCredentials;

use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;
use Whilesmart\EloquentClientCredentials\Authorization\TokenGuard;
use Whilesmart\EloquentClientCredentials\Console\GenerateOAuthKeysCommand;
use Whilesmart\EloquentClientCredentials\Contracts\AuthorizationClaimsResolver;

class EloquentClientCredentialsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/client-credentials.php', 'client-credentials');

        $this->app->scoped(GrantContext::class);
        $this->app->bind(AuthorizationClaimsResolver::class, fn ($app) => $app->make(
            config('client-credentials.authorization_code.claims_resolver')
        ));
    }

    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerAuthorizationCode();

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

    protected function registerAuthorizationCode(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GenerateOAuthKeysCommand::class]);
        }

        if (! config('client-credentials.authorization_code.enabled', false)) {
            return;
        }

        $guard = config('client-credentials.authorization_code.guard', 'oauth');

        if (! config("auth.guards.{$guard}")) {
            config(["auth.guards.{$guard}" => ['driver' => 'client-credentials-oauth', 'provider' => null]]);
        }

        Auth::resolved(function ($auth) {
            $auth->extend('client-credentials-oauth', function ($app) {
                return tap(new RequestGuard(new TokenGuard, $app['request']), function ($guard) use ($app) {
                    $app->refresh('request', $guard, 'setRequest');
                });
            });
        });

        if (! $this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/authorization-code.php');
        }
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
