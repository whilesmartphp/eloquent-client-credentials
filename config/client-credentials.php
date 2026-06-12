<?php

use Whilesmart\EloquentClientCredentials\Models\Client;
use Whilesmart\EloquentClientCredentials\Resolvers\DefaultOwnerResolver;

return [
    'default_model' => Client::class,

    /*
    |--------------------------------------------------------------------------
    | UUID primary keys
    |--------------------------------------------------------------------------
    |
    | Use UUID primary keys (and uuid morph / foreign-key columns) for clients,
    | access tokens, and refresh tokens, instead of auto-incrementing integers.
    | Must be set before the migrations run. Set this to false when the owning
    | application uses integer keys and you want client IDs to match.
    |
    */
    'uuids' => (bool) env('CLIENT_CREDENTIALS_UUIDS', true),

    /*
    |--------------------------------------------------------------------------
    | Default Owner Resolver
    |--------------------------------------------------------------------------
    |
    | This class resolves the default owner for client operations.
    | It must implement OwnerResolverInterface and receives the Request.
    | Applications can override this per-request by passing an owner directly.
    |
    */
    'owner_resolver' => DefaultOwnerResolver::class,

    'middleware_hooks' => [],

    'routes' => [
        'enabled' => false,
        'prefix' => 'api',
        'middleware' => [],
        'client_routes' => false,
    ],

    'oauth' => [
        'enabled' => true,
        'token_lifetime' => 3600, // seconds
        'refresh_token_lifetime' => 86400 * 30, // 30 days
        'refresh_tokens_enabled' => false,
    ],
];
