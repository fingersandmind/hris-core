<?php

namespace Jmal\Hris;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Jmal\Hris\Contracts\AuthorizationResolverInterface;
use Jmal\Hris\Contracts\ScopeResolverInterface;

class HrisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hris.php', 'hris');

        $this->app->singleton(ScopeResolverInterface::class, fn () => new (config('hris.scope.resolver')));

        $this->app->singleton(AuthorizationResolverInterface::class, fn () => new (config('hris.authorization.resolver')));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/hris.php' => config_path('hris.php'),
            ], 'hris-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'hris-migrations');
        }
    }

    protected function registerRoutes(): void
    {
        Route::prefix(config('hris.routes.prefix'))
            ->middleware(config('hris.routes.middleware'))
            ->group(__DIR__.'/../routes/web.php');
    }
}
