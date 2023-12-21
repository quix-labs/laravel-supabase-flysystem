<?php

namespace Alancolant\FlysystemSupabaseAdapter;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use League\Flysystem\Filesystem;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {

        Storage::extend('supabase', function (Application $app, array $config) {
            $adapter = new SupabaseAdapter($config);

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
    }
}
