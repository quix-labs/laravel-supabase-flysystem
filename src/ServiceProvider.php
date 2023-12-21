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

        Storage::macro('signedUrl', function (...$args): string {
            /** @var $this FilesystemAdapter */
            $adapter = $this->getAdapter();
            if ($adapter instanceof SupabaseAdapter) {
                return $adapter->getSignedUrl(...$args);
            }
            if (method_exists($this, 'signedUrl') || $this->hasMacro('signedUrl')) {
                return $this->signedUrl(...$args);
            }
            throw new \RuntimeException('This driver does not support retrieving signed URLs.');
        });
        Storage::macro('publicUrl', function (...$args): string {
            /** @var $this FilesystemAdapter */
            $adapter = $this->getAdapter();
            if ($adapter instanceof SupabaseAdapter) {
                return $adapter->getPublicUrl(...$args);
            }

            if (method_exists($this, 'publicUrl') || $this->hasMacro('publicUrl')) {
                return $this->publicUrl(...$args);
            }
            throw new \RuntimeException('This driver does not support retrieving signed URLs.');
        });
    }

    /**
     * Register the application services.
     */
    public function register()
    {
    }
}
