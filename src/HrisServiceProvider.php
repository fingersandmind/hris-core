<?php

namespace Jmal\Hris;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jmal\Hris\Contracts\AuthorizationResolverInterface;
use Jmal\Hris\Contracts\ScopeResolverInterface;
use Jmal\Hris\Contracts\TaxCalculatorInterface;
use Jmal\Hris\Services\BirTaxCalculator;
use Jmal\Hris\Services\PagIbigCalculator;
use Jmal\Hris\Services\PayrollService;
use Jmal\Hris\Services\PhilHealthCalculator;
use Jmal\Hris\Services\SssCalculator;

class HrisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hris.php', 'hris');

        $this->app->singleton(ScopeResolverInterface::class, fn () => new (config('hris.scope.resolver')));

        $this->app->singleton(AuthorizationResolverInterface::class, fn () => new (config('hris.authorization.resolver')));

        // Contribution calculators
        $this->app->singleton('hris.sss', SssCalculator::class);
        $this->app->singleton('hris.philhealth', PhilHealthCalculator::class);
        $this->app->singleton('hris.pagibig', PagIbigCalculator::class);
        $this->app->singleton(TaxCalculatorInterface::class, BirTaxCalculator::class);

        $this->app->tag(['hris.sss', 'hris.philhealth', 'hris.pagibig'], 'hris.contribution_calculators');

        $this->app->singleton(PayrollService::class, function ($app) {
            $calculators = iterator_to_array($app->tagged('hris.contribution_calculators'));

            return new PayrollService(
                $app->make(\Jmal\Hris\Services\AttendanceService::class),
                $app->make(TaxCalculatorInterface::class),
                $calculators,
            );
        });
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
