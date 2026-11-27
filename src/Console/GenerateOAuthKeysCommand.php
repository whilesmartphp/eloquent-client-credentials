<?php

namespace Whilesmart\EloquentClientCredentials\Console;

use Illuminate\Console\Command;
use Whilesmart\EloquentClientCredentials\Authorization\Keys;

class GenerateOAuthKeysCommand extends Command
{
    protected $signature = 'client-credentials:oauth-keys {--force : Overwrite existing keys}';

    protected $description = 'Create the RSA key pair that signs authorization code access tokens';

    public function handle(): int
    {
        $private = Keys::path('private');
        $public = Keys::path('public');

        if ((file_exists($private) || file_exists($public)) && ! $this->option('force')) {
            $this->error('OAuth keys already exist. Use --force to overwrite them, which invalidates every issued token.');

            return self::FAILURE;
        }

        $key = openssl_pkey_new(['private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        file_put_contents($private, $privateKey);
        file_put_contents($public, openssl_pkey_get_details($key)['key']);
        chmod($private, 0600);

        $this->info('OAuth keys written to '.dirname($private).'.');

        return self::SUCCESS;
    }
}
