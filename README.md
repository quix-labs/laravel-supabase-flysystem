# Laravel Supabase Storage Adapter

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alancolant/flysystem-supabase-adapter.svg?style=flat-square)](https://packagist.org/packages/alancolant/flysystem-supabase-adapter)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/alancolant/flysystem-supabase-adapter/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/alancolant/flysystem-supabase-adapter/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/alancolant/flysystem-supabase-adapter/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/alancolant/flysystem-supabase-adapter/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/alancolant/flysystem-supabase-adapter.svg?style=flat-square)](https://packagist.org/packages/alancolant/flysystem-supabase-adapter)

___
Easily integrate Supabase as a storage driver in Laravel with this Flysystem adapter.

Simplify file storage and retrieval using Laravel's convenient storage system while leveraging the powerful features of Supabase.
___

## Requirements
* PHP version >= 8.1
* Laravel 10.x
* Fileinfo `ext-fileinfo`  (probably present)


## Installation

To install the package, use Composer:
```bash
composer require alancolant/flysystem-supabase-adapter
```

#### Configuration

After installation, configure the Supabase driver in Laravel's `config/filesystems.php`.

Add the following to the disks array:

```php
'supabase' => [
    'driver' => 'supabase',
    'key'    => env('SUPABASE_STORAGE_KEY'), // Use a privileged key; read-only does not work
    'bucket' => env('SUPABASE_STORAGE_BUCKET'),
    'endpoint' => env('SUPABASE_STORAGE_ENDPOINT'),

    'url'      => null, // <- Automatically generated; change here if you are using a proxy

    'public'                      => true,  // Default to true
    'defaultUrlGeneration'        => null, // 'signed' | 'public' <- default depends on public

    'defaultUrlGenerationOptions' => [
        'download'  => false,
        'transform' => [],
    ],

    'signedUrlExpires' => 60*60*24, // 1 day <- default to 1 hour (3600)
],
```


## Usage

```php
// Example code for using the Supabase driver with Laravel Storage
Storage::disk('supabase')->put('file.txt', 'contents');

// Custom function to generate a public URL
Storage::disk('supabase')->getAdapter()->getPublicUrl('completelyPublicFile.png', [
    'download'  => false, // Set this to true if you want the user's browser to automatically trigger download
    
    // Transform only applied if the file is detected as an image; else ignored
    'transform' => [ 
        'width' => 200,
        //... All options -> https://supabase.com/docs/guides/storage/serving/image-transformations#transformation-options
    ]]);

// Custom function to generate a signed URL
Storage::disk('supabase')->getAdapter()->getSignedUrl('veryConfidentialFile.png', [
    'expiresIn' => 60 * 5, // 5 minutes
    //... Same options as getPublicUrl
]);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [COLANT Alan](https://github.com/alancolant)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
