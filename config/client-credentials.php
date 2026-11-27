<?php

use Whilesmart\EloquentClientCredentials\Models\Client;
use Whilesmart\EloquentClientCredentials\Resolvers\DefaultOwnerResolver;
use Whilesmart\EloquentClientCredentials\Resolvers\NoAuthorizationClaims;

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

    /*
    |--------------------------------------------------------------------------
    | Authorization code grant
    |--------------------------------------------------------------------------
    |
    | Lets a user authorize a third-party app, such as an MCP client, to act on
    | their behalf (OAuth 2.1 with PKCE). Off by default. Users approve on the
    | host's own consent page at `consent_url`, which receives an
    | `authorization_request` id and calls the consent routes with the user's
    | normal API token. Access tokens are RS256 JWTs: create the key pair with
    | `php artisan client-credentials:oauth-keys` or set the key config.
    |
    */
    'authorization_code' => [
        'enabled' => (bool) env('CLIENT_CREDENTIALS_AUTHORIZATION_CODE', false),
        'issuer' => env('CLIENT_CREDENTIALS_OAUTH_ISSUER'),
        'audience' => env('CLIENT_CREDENTIALS_OAUTH_AUDIENCE'),
        'consent_url' => env('CLIENT_CREDENTIALS_OAUTH_CONSENT_URL'),
        'consent_prefix' => 'api/oauth',
        'consent_middleware' => ['api', 'auth:sanctum'],
        'route_prefix' => 'oauth',
        'guard' => 'oauth',
        'claims_resolver' => NoAuthorizationClaims::class,
        'scopes' => [
            'mcp:use' => 'Use the MCP server on your behalf',
        ],
        'default_scopes' => ['mcp:use'],
        'protected_resource_metadata' => false,
        'access_token_lifetime' => 3600,
        'refresh_token_lifetime' => 86400 * 30,
        'code_lifetime' => 600,
        'request_lifetime' => 600,
        'token_throttle' => '60,1',
        'lock_wait' => 5,
        'private_key' => env('CLIENT_CREDENTIALS_OAUTH_PRIVATE_KEY'),
        'public_key' => env('CLIENT_CREDENTIALS_OAUTH_PUBLIC_KEY'),
        'encryption_key' => env('CLIENT_CREDENTIALS_OAUTH_ENCRYPTION_KEY'),
        'registration' => [
            'enabled' => true,
            'redirect_uris' => [
                'https://claude.ai/api/mcp/auth_callback',
                'https://chatgpt.com/connector_platform_oauth_redirect',
            ],
            'loopback' => true,
            'throttle' => '10,1',
        ],
    ],
];
