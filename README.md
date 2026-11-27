# Eloquent Client Credentials

Add OAuth2 client credentials authentication to any Eloquent model.

## Installation

```bash
composer require whilesmart/eloquent-client-credentials
```

Publish the config file:

```bash
php artisan vendor:publish --tag=client-credentials-config
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

```php
// config/client-credentials.php
return [
    'default_model' => \Whilesmart\EloquentClientCredentials\Models\Client::class,

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
        'token_lifetime' => 3600,
        'refresh_token_lifetime' => 86400 * 30,
        'refresh_tokens_enabled' => false,
    ],
];
```

## Usage

### Using the Default Client Model

Enable routes in your config:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'api',
    'middleware' => ['auth:sanctum'],
    'client_routes' => true,
],
```

This registers the following routes:

| Method | URI | Description |
|--------|-----|-------------|
| POST | `/api/oauth/token` | Issue access token |
| POST | `/api/oauth/revoke` | Revoke access token |
| GET | `/api/clients` | List clients |
| POST | `/api/clients` | Create client |
| GET | `/api/clients/{slug}` | Get client |
| PUT | `/api/clients/{slug}` | Update client |
| DELETE | `/api/clients/{slug}` | Delete client |
| POST | `/api/clients/{slug}/regenerate-secret` | Regenerate secret |

### Adding Client Credentials to Your Own Model

Use the `HasClientCredentials` trait:

```php
use Whilesmart\EloquentClientCredentials\Traits\HasClientCredentials;

class ApiApp extends Model
{
    use HasClientCredentials;

    protected $fillable = ['name', 'secret', /* ... */];
}
```

The trait provides:

- `setSecret(string $plainSecret)` - Hash and store a secret
- `verifySecret(string $secret)` - Verify a plain secret against stored hash
- `regenerateSecret()` - Generate and store a new random secret
- `plainSecret` - Access the plain secret (only available immediately after creation/regeneration)

### Owner Resolver

Configure how the owner is resolved for client operations. Create a custom resolver:

```php
use Whilesmart\EloquentClientCredentials\Contracts\OwnerResolverInterface;

class CustomOwnerResolver implements OwnerResolverInterface
{
    public function resolve(Request $request): ?Model
    {
        return $request->user()->currentTeam;
    }
}
```

Register in config:

```php
'owner_resolver' => \App\Resolvers\CustomOwnerResolver::class,
```

You can also pass an owner directly when using the controller programmatically:

```php
$controller = app(ClientController::class);
$controller->store($request, $customOwner);
```

## OAuth2 Token Flow

### Issuing Tokens

**Client Credentials Grant:**

```bash
POST /api/oauth/token
Content-Type: application/json

{
    "grant_type": "client_credentials",
    "client_id": "your-client-id",
    "client_secret": "your-client-secret",
    "scope": "read write"
}
```

Response:

```json
{
    "access_token": "...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "scope": "read write",
    "refresh_token": "..."
}
```

**Refresh Token Grant** (when enabled):

```bash
POST /api/oauth/token
Content-Type: application/json

{
    "grant_type": "refresh_token",
    "refresh_token": "your-refresh-token"
}
```

### Revoking Tokens

```bash
POST /api/oauth/revoke
Authorization: Bearer your-access-token
```

## Authorization Code Grant

Lets a user authorize a third-party app, such as Claude or another MCP client, to act on their behalf. It follows OAuth 2.1: PKCE with S256 on every request, dynamic client registration, rotating refresh tokens, and the discovery documents MCP clients read. It is off by default and does not change the client credentials grant.

The user approves on a consent page in your own frontend, signed in the way they always sign in. The package never needs a browser session on the API.

### Enable

```env
CLIENT_CREDENTIALS_AUTHORIZATION_CODE=true
CLIENT_CREDENTIALS_OAUTH_AUDIENCE=https://api.example.com/mcp
CLIENT_CREDENTIALS_OAUTH_CONSENT_URL=https://app.example.com/oauth/consent
```

```bash
php artisan migrate
php artisan client-credentials:oauth-keys
```

The command writes an RSA key pair to `storage/`. To keep keys out of the filesystem, set `CLIENT_CREDENTIALS_OAUTH_PRIVATE_KEY` and `CLIENT_CREDENTIALS_OAUTH_PUBLIC_KEY` instead. Replacing the keys invalidates every issued access token.

`issuer` defaults to `app.url`. `audience` is the resource the tokens are for, usually your MCP endpoint, and must match the `resource` a client asks for.

### Routes

| Method | URI | Purpose |
|--------|-----|---------|
| GET | `/.well-known/oauth-authorization-server` | Authorization server metadata (RFC 8414) |
| GET | `/.well-known/jwks.json` | Public key that verifies access tokens |
| GET | `/.well-known/oauth-protected-resource/{path}` | Resource metadata (RFC 9728), when `protected_resource_metadata` is on |
| GET | `/oauth/authorize` | Starts authorization and redirects to your consent page |
| POST | `/oauth/token` | Exchanges a code or refresh token |
| POST | `/oauth/register` | Dynamic client registration (RFC 7591) |
| GET | `/api/oauth/authorization-requests/{id}` | What the consent page shows |
| POST | `/api/oauth/authorization-requests/{id}/approve` | Approve; returns `redirect_to` |
| POST | `/api/oauth/authorization-requests/{id}/deny` | Deny; returns `redirect_to` |
| GET | `/api/oauth/connections` | Apps the signed-in user has authorized |
| DELETE | `/api/oauth/connections/{client}` | Disconnect an app and revoke its tokens |

The `api/oauth` routes use `consent_middleware`, `['api', 'auth:sanctum']` by default.

Registration accepts the redirect URIs listed in `registration.redirect_uris` (Claude's and ChatGPT's callbacks by default) and loopback addresses on any port. A loopback client registered on one port may return to `127.0.0.1` or `[::1]` on another; a `localhost` redirect must match exactly. A registered client can only use the grants it registered for, so it cannot use the client credentials grant or the client middleware.

Codes and refresh tokens work once, including when two requests race to use the same one. A failed attempt, such as a wrong PKCE verifier, leaves them usable.

Disconnecting an app revokes its tokens and refuses any code the user approved but the app has not redeemed yet.

Pending consent requests and unused codes live in the cache for a few minutes, so every app server must share one cache store. Single use and disconnects rely on the store's atomic `add` and its locks, which every shared Laravel store (Redis, database, Memcached, file, DynamoDB) provides.

### Consent page

`/oauth/authorize` redirects the browser to `consent_url?authorization_request={id}`. Your page signs the user in as usual, then:

1. `GET /api/oauth/authorization-requests/{id}` with the user's token, and shows the client name, `redirect_host` and scopes.
2. `POST .../approve` or `POST .../deny`.
3. Sends the browser to the `redirect_to` it gets back.

A request expires after `request_lifetime` seconds and can be answered once.

Every redirect back to a client, including errors, carries an `iss` parameter set to the issuer (RFC 9207). A client connected to several authorization servers uses it to check which one answered.

### Protecting routes

The package registers an `oauth` guard. It accepts the access tokens issued here and authenticates the subject that approved them, the `subject_type` and `subject_id` on the token. That is usually a user, but any authenticatable model can approve:

```php
Route::middleware('auth:sanctum,oauth')->group(...);
```

The matching `AccessToken` is on the request as `oauth_access_token`, with its `scopes` and `claims`.

For an MCP server built with `laravel/mcp`, turn on `protected_resource_metadata`. Its 401 responses then carry the `WWW-Authenticate` link clients use to discover this server. Do not also call `Mcp::oauthRoutes()`, which needs Passport.

### Extra claims

To put more in the token, such as a workspace the user picks on the consent page, bind an `AuthorizationClaimsResolver`:

```php
use Whilesmart\EloquentClientCredentials\Contracts\AuthorizationClaimsResolver;

class WorkspaceClaims implements AuthorizationClaimsResolver
{
    public function resolve(Authenticatable $user, Model $client, array $scopes, array $context): array
    {
        $workspace = $user->workspaces()->where('slug', $context['workspace'] ?? null)->first()
            ?? throw ValidationException::withMessages(['context.workspace' => 'Choose a workspace.']);

        return ['workspace' => $workspace->slug];
    }
}
```

```php
'authorization_code' => [
    'claims_resolver' => \App\OAuth\WorkspaceClaims::class,
],
```

The consent page sends the choice as `context` with the approval. Refreshed tokens keep the claims. The standard claims (`iss`, `aud`, `sub`, `exp`, `nbf`, `iat`, `jti`, `scope`, `client_id`) cannot be overridden.

### Verifying tokens in another service

Access tokens are RS256 JWTs. A resource server outside Laravel verifies them with the key at `/.well-known/jwks.json` and checks `iss`, `aud` and `exp`. `scope` holds the granted scopes, space separated.

Signature checks alone do not see revocation: a token from a disconnected app keeps working there until it expires, `access_token_lifetime` (an hour) at most. Keep that lifetime short, or have the service check the token's `jti` against your revocations.

## Middleware

### Bearer Token Authentication

Authenticate requests using OAuth2 bearer tokens:

```php
Route::middleware('client.bearer')->group(function () {
    Route::get('/resource', fn () => 'Protected');
});

// With scope requirement
Route::middleware('client.bearer:admin')->get('/admin', fn () => 'Admin only');
```

### Basic Auth

Authenticate using HTTP Basic Authentication:

```php
Route::middleware('client.basic')->group(function () {
    Route::get('/resource', fn () => 'Protected');
});
```

Credentials: `client_id:client_secret` base64 encoded.

### Header-Based Authentication

Authenticate using custom headers:

```php
Route::middleware('client.auth')->group(function () {
    Route::get('/resource', fn () => 'Protected');
});
```

Headers required:
- `X-Client-ID: your-client-id`
- `X-Client-Secret: your-client-secret`

### Registering Middleware

In your `bootstrap/app.php` or service provider:

```php
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBearerToken;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBasicAuth;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateClient;

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'client.bearer' => AuthenticateBearerToken::class,
        'client.basic' => AuthenticateBasicAuth::class,
        'client.auth' => AuthenticateClient::class,
    ]);
})
```

## Hook System

Add custom logic before/after controller actions:

```php
// config/client-credentials.php
'middleware_hooks' => [
    \App\Hooks\MyClientHook::class,
],
```

Create a hook class:

```php
use Whilesmart\EloquentClientCredentials\Interfaces\MiddlewareHookInterface;

class MyClientHook implements MiddlewareHookInterface
{
    public function before(Request $request, string $action): ?Request
    {
        // Modify request or return null to continue
        return $request;
    }

    public function after(Request $request, JsonResponse $response, string $action): JsonResponse
    {
        // Modify response
        return $response;
    }
}
```

Available hook actions (from `HookAction` enum):
- `CLIENT_STORE`
- `CLIENT_UPDATE`
- `CLIENT_DELETE`
- `CLIENT_REGENERATE_SECRET`
- `TOKEN_ISSUE`
- `TOKEN_REVOKE`

## Models

### Client

Default client model with:
- UUID primary key
- Sluggable name
- Polymorphic owner relationship
- Revocation support
- An optional `type`, for keeping kinds of credentials apart (`Client::ofType('ci')`)
- `last_used_at`, set when the client is issued a token or passes one of the package middlewares; call `markUsed()` when you authenticate clients yourself

### AccessToken

OAuth2 access tokens with:
- UUID primary key
- Polymorphic client relationship
- Scopes support
- Expiration and revocation

### RefreshToken

Refresh tokens with:
- UUID primary key
- Linked to access token (cascades on delete)
- Expiration and revocation

## Publishing Assets

```bash
# Config
php artisan vendor:publish --tag=client-credentials-config

# Migrations
php artisan vendor:publish --tag=client-credentials-migrations

# Language files
php artisan vendor:publish --tag=client-credentials-lang

# Routes
php artisan vendor:publish --tag=client-credentials-routes
```

## Testing

```bash
composer test
```

## License

MIT
