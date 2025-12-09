<?php

return [
    'default_model' => \Whilesmart\EloquentClientCredentials\Models\Client::class,

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
    'owner_resolver' => \Whilesmart\EloquentClientCredentials\Resolvers\DefaultOwnerResolver::class,

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
