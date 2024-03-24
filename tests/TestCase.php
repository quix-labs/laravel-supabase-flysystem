<?php

namespace QuixLabs\LaravelSupabaseFlysystem\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use QuixLabs\LaravelSupabaseFlysystem\ServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        $migration = include __DIR__.'/../database/migrations/create_flysystem-supabase-adapter_table.php.stub';
        $migration->up();
        */
    }
}
