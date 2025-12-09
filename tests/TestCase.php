<?php

namespace Tests;

use Cviebrock\EloquentSluggable\ServiceProvider as SluggableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Whilesmart\EloquentClientCredentials\EloquentClientCredentialsServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SluggableServiceProvider::class,
            EloquentClientCredentialsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
