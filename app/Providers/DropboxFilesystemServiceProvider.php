<?php

namespace App\Providers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Spatie\Dropbox\Client as DropboxClient;
use Spatie\FlysystemDropbox\DropboxAdapter;
use GuzzleHttp\Client as GuzzleClient;

class DropboxFilesystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('dropbox', function ($app, $config) {

            // 1) Get a fresh short-lived access token using the refresh token
            $http = new GuzzleClient();

            $response = $http->post('https://api.dropboxapi.com/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $config['refresh_token'],
                    'client_id' => $config['app_key'],
                    'client_secret' => $config['app_secret'],
                ],
                'timeout' => 20,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new \RuntimeException('Dropbox: could not obtain access_token from refresh_token.');
            }

            // 2) Create the Dropbox client with the fresh access token (string)
            $client = new DropboxClient($data['access_token']);

            $adapter = new DropboxAdapter($client);
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
