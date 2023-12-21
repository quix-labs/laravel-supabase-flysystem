<?php

namespace Alancolant\FlysystemSupabaseAdapter\Tests;

use Alancolant\FlysystemSupabaseAdapter\FlysystemSupabaseAdapterServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Alancolant\\FlysystemSupabaseAdapter\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            FlysystemSupabaseAdapterServiceProvider::class,
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
