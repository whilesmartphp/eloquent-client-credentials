<?php

return [
    'default_model' => \Whilesmart\EloquentClientCredentials\Models\Client::class,

    /*
    |--------------------------------------------------------------------------
    | Default Owner Resolver
    |--------------------------------------------------------------------------
    |
    | This callable resolves the default owner for client operations.
    | It receives the Request and should return the owner model instance.
    | Applications can override this per-request by passing an owner directly.
    |
    */
    'owner_resolver' => function ($request) {
        return $request->user();
    },

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
