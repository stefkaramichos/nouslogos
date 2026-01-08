<?php

namespace App\Providers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;

class DropboxFilesystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('dropbox', function ($app, $config) {
            $client = new DropboxClient($config['token']);
            $adapter = new DropboxAdapter($client);

            $filesystem = new Filesystem($adapter);

            // Return Laravel's adapter (this is what Laravel expects)
            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
