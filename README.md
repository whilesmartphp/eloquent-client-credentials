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

Configure how the owner is resolved for client operations:

```php
'owner_resolver' => function ($request) {
    return $request->user();
},
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
