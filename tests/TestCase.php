<?php

namespace Jmal\Hris\Tests;

use Jmal\Hris\HrisServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            HrisServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('hris.scope.column', 'branch_id');
        $app['config']->set('hris.user_model', 'Illuminate\\Foundation\\Auth\\User');
    }
}
