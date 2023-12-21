<?php

namespace Alancolant\FlysystemSupabaseAdapter\Tests;

<<<<<<< HEAD
use Alancolant\FlysystemSupabaseAdapter\FlysystemSupabaseAdapterServiceProvider;
=======
use Alancolant\FlysystemSupabaseAdapter\ServiceProvider;
>>>>>>> cb5e866 (fix ci)
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

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
