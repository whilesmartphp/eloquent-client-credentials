<?php

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\AccessTokenRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\AuthCodeRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\ClientRepository;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\RefreshTokenRepository;
use Whilesmart\EloquentClientCredentials\Contracts\AuthorizationClaimsResolver;
use Whilesmart\EloquentClientCredentials\Http\Controllers\TokenController;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBasicAuth;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBearerToken;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateClient;
use Whilesmart\EloquentClientCredentials\Models\AuthorizationCode;
use Whilesmart\EloquentClientCredentials\Models\Client;
use Whilesmart\EloquentClientCredentials\Models\RefreshToken;
use Workbench\App\Models\User;

class AuthorizationCodeTest extends TestCase
{
    private const CLAUDE_CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

    private static ?array $keys = null;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        if (self::$keys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);
            self::$keys = [$private, openssl_pkey_get_details($key)['key']];
        }

        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.url', 'https://api.example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('client-credentials.authorization_code', array_merge(
            $app['config']->get('client-credentials.authorization_code'),
            [
                'enabled' => true,
                'audience' => 'https://api.example.test/mcp',
                'consent_url' => 'https://app.example.test/oauth/consent',
                'consent_middleware' => ['auth'],
                'protected_resource_metadata' => true,
                'private_key' => self::$keys[0],
                'public_key' => self::$keys[1],
            ],
        ));
    }

    protected function defineRoutes($router): void
    {
        $router->get('mcp', fn (Request $request) => response()->json([
            'user' => $request->user()->getKey(),
            'scopes' => $request->attributes->get('oauth_access_token')->scopes,
        ]))->middleware('auth:oauth');

        $router->get('legacy', fn () => response()->json(['ok' => true]))->middleware(AuthenticateBearerToken::class);
        $router->get('legacy-header', fn () => response()->json(['ok' => true]))->middleware(AuthenticateClient::class);
        $router->get('legacy-basic', fn () => response()->json(['ok' => true]))->middleware(AuthenticateBasicAuth::class);
        $router->post('api/oauth/token', [TokenController::class, 'issue']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
    }

    public function test_metadata_describes_the_authorization_server(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJson([
                'issuer' => 'https://api.example.test',
                'authorization_endpoint' => 'https://api.example.test/oauth/authorize',
                'token_endpoint' => 'https://api.example.test/oauth/token',
                'registration_endpoint' => 'https://api.example.test/oauth/register',
                'jwks_uri' => 'https://api.example.test/.well-known/jwks.json',
                'code_challenge_methods_supported' => ['S256'],
                'grant_types_supported' => ['authorization_code', 'refresh_token'],
                'scopes_supported' => ['mcp:use'],
            ]);
    }

    public function test_protected_resource_metadata_uses_the_route_name_laravel_mcp_looks_for(): void
    {
        $this->assertSame(
            '/.well-known/oauth-protected-resource/mcp',
            route('mcp.oauth.protected-resource.nested', ['path' => 'mcp'], false),
        );

        $this->getJson('/.well-known/oauth-protected-resource/anything')
            ->assertOk()
            ->assertJson([
                'resource' => 'https://api.example.test/mcp',
                'authorization_servers' => ['https://api.example.test'],
            ]);
    }

    public function test_registration_accepts_claude_and_loopback_callbacks_only(): void
    {
        $this->postJson('/oauth/register', ['redirect_uris' => [self::CLAUDE_CALLBACK], 'client_name' => 'Claude'])
            ->assertCreated()
            ->assertJson(['token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code', 'refresh_token']])
            ->assertJsonMissing(['client_secret']);

        foreach (['http://127.0.0.1:33418/callback', 'http://localhost:6274/oauth/callback', 'http://[::1]:8080/cb'] as $uri) {
            $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])->assertCreated();
        }

        foreach (['https://evil.example/callback', 'http://claude.ai/api/mcp/auth_callback', 'https://claude.ai/api/mcp/auth_callback/x'] as $uri) {
            $this->postJson('/oauth/register', ['redirect_uris' => [$uri]])
                ->assertStatus(400)
                ->assertJson(['error' => 'invalid_redirect_uri']);
        }

        $client = Client::where('name', 'Claude')->firstOrFail();
        $this->assertNull($client->secret);
        $this->assertFalse($client->confidential);
    }

    public function test_a_confidential_client_receives_a_secret_once(): void
    {
        $response = $this->postJson('/oauth/register', [
            'redirect_uris' => [self::CLAUDE_CALLBACK],
            'token_endpoint_auth_method' => 'client_secret_post',
        ])->assertCreated();

        $client = Client::findOrFail($response->json('client_id'));
        $this->assertTrue($client->verifySecret($response->json('client_secret')));
    }

    public function test_a_user_authorizes_a_client_and_the_token_reaches_the_resource(): void
    {
        $user = $this->user();
        $clientId = $this->register();
        [$verifier, $challenge] = $this->pkce();

        $pendingId = $this->pendingId($this->authorize($clientId, $challenge, ['state' => 'xyz']));

        $this->actingAs($user)->getJson("/api/oauth/authorization-requests/{$pendingId}")
            ->assertOk()
            ->assertJson(['data' => [
                'client' => ['id' => $clientId, 'name' => 'Claude'],
                'redirect_host' => 'claude.ai',
                'scopes' => [['id' => 'mcp:use', 'description' => 'Use the MCP server on your behalf']],
            ]]);

        $redirect = $this->approve($user, $pendingId)->assertOk()->json('data.redirect_to');
        $this->assertStringStartsWith(self::CLAUDE_CALLBACK.'?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame('xyz', $query['state']);

        $tokens = $this->exchange($clientId, $query['code'], $verifier)->assertOk()->json();
        $this->assertSame('Bearer', $tokens['token_type']);

        $claims = $this->claims($tokens['access_token']);
        $this->assertSame('https://api.example.test', $claims['iss']);
        $this->assertSame(['https://api.example.test/mcp'], (array) $claims['aud']);
        $this->assertSame($user->getKey(), $claims['sub']);
        $this->assertSame($clientId, $claims['client_id']);
        $this->assertSame('mcp:use', $claims['scope']);

        $this->resource($tokens['access_token'])->assertOk()->assertJson([
            'user' => $user->getKey(),
            'scopes' => ['mcp:use'],
        ]);
    }

    public function test_refresh_rotates_both_tokens(): void
    {
        $user = $this->user();
        [$clientId, $tokens] = $this->connect($user);

        $refreshed = $this->refresh($clientId, $tokens['refresh_token'])->assertOk()->json();

        $this->resource($tokens['access_token'])->assertUnauthorized();
        $this->resource($refreshed['access_token'])->assertOk()->assertJson(['user' => $user->getKey()]);

        $this->refresh($clientId, $tokens['refresh_token'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_grant']);
    }

    public function test_pkce_is_required_and_must_use_s256(): void
    {
        $clientId = $this->register();
        [, $challenge] = $this->pkce();

        foreach ([
            $this->authorize($clientId, null),
            $this->authorize($clientId, $challenge, ['code_challenge_method' => 'plain']),
            $this->authorize($clientId, $challenge, ['code_challenge_method' => null]),
        ] as $response) {
            $response->assertStatus(400)->assertJson(['error' => 'invalid_request']);
        }
    }

    public function test_the_code_needs_the_matching_verifier_and_works_once(): void
    {
        $user = $this->user();
        $clientId = $this->register();
        [$verifier, $challenge] = $this->pkce();

        $code = $this->code($this->approve($user, $this->pendingId($this->authorize($clientId, $challenge))));

        $this->exchange($clientId, $code, Str::random(64))->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
        $this->exchange($this->register(), $code, $verifier)->assertStatus(400);
        $this->exchange($clientId, $code, $verifier)->assertOk();
        $this->exchange($clientId, $code, $verifier)->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    }

    public function test_an_unregistered_redirect_uri_is_refused_without_redirecting(): void
    {
        $clientId = $this->register();
        [, $challenge] = $this->pkce();

        $this->authorize($clientId, $challenge, ['redirect_uri' => 'https://evil.example/callback'])
            ->assertStatus(401)
            ->assertJson(['error' => 'invalid_client']);
    }

    public function test_a_loopback_redirect_may_use_another_port(): void
    {
        $clientId = $this->register('http://127.0.0.1:33418/callback');
        [, $challenge] = $this->pkce();

        $this->pendingId($this->authorize($clientId, $challenge, ['redirect_uri' => 'http://127.0.0.1:51234/callback']));
    }

    public function test_a_different_resource_is_refused(): void
    {
        $clientId = $this->register();
        [, $challenge] = $this->pkce();

        $this->authorize($clientId, $challenge, ['resource' => 'https://other.example/mcp'])
            ->assertStatus(400)
            ->assertJson(['error' => 'invalid_target']);

        $this->pendingId($this->authorize($clientId, $challenge, ['resource' => 'https://api.example.test/mcp/']));
    }

    public function test_denying_sends_access_denied_to_the_client(): void
    {
        $user = $this->user();
        $clientId = $this->register();
        [, $challenge] = $this->pkce();
        $pendingId = $this->pendingId($this->authorize($clientId, $challenge, ['state' => 'abc']));

        $redirect = $this->actingAs($user)->postJson("/api/oauth/authorization-requests/{$pendingId}/deny")
            ->assertOk()
            ->json('data.redirect_to');

        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('abc', $query['state']);
        $this->assertArrayNotHasKey('code', $query);
    }

    public function test_an_authorization_request_can_be_answered_once(): void
    {
        $user = $this->user();
        $clientId = $this->register();
        [, $challenge] = $this->pkce();
        $pendingId = $this->pendingId($this->authorize($clientId, $challenge));

        $this->approve($user, $pendingId)->assertOk();
        $this->approve($user, $pendingId)->assertNotFound();
        $this->actingAs($user)->postJson("/api/oauth/authorization-requests/{$pendingId}/deny")->assertNotFound();
    }

    public function test_the_consent_routes_need_a_signed_in_user(): void
    {
        $clientId = $this->register();
        [, $challenge] = $this->pkce();
        $pendingId = $this->pendingId($this->authorize($clientId, $challenge));

        $this->getJson("/api/oauth/authorization-requests/{$pendingId}")->assertUnauthorized();
        $this->postJson("/api/oauth/authorization-requests/{$pendingId}/approve")->assertUnauthorized();
    }

    public function test_a_user_lists_and_disconnects_authorized_apps(): void
    {
        $user = $this->user();
        $other = $this->user();
        [$clientId, $tokens] = $this->connect($user);
        $this->connect($other);

        $this->actingAs($user)->getJson('/api/oauth/connections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client.id', $clientId)
            ->assertJsonPath('data.0.scopes', ['mcp:use']);

        $this->actingAs($user)->deleteJson("/api/oauth/connections/{$clientId}")->assertOk();

        $this->resource($tokens['access_token'])->assertUnauthorized();
        $this->refresh($clientId, $tokens['refresh_token'])->assertStatus(400);
        $this->actingAs($user)->getJson('/api/oauth/connections')->assertJsonCount(0, 'data');
        $this->actingAs($other)->getJson('/api/oauth/connections')->assertJsonCount(1, 'data');
    }

    public function test_host_claims_reach_the_token_and_survive_refresh(): void
    {
        $this->app->bind(AuthorizationClaimsResolver::class, fn () => new class implements AuthorizationClaimsResolver
        {
            public function resolve(Authenticatable $user, Model $client, array $scopes, array $context): array
            {
                if (($context['workspace'] ?? null) !== 'acme') {
                    throw ValidationException::withMessages(['context.workspace' => 'Pick a workspace you belong to.']);
                }

                return ['workspace' => 'acme', 'sub' => 'someone-else'];
            }
        });

        $user = $this->user();
        $clientId = $this->register();
        [$verifier, $challenge] = $this->pkce();
        $pendingId = $this->pendingId($this->authorize($clientId, $challenge));

        $this->approve($user, $pendingId, ['workspace' => 'other'])->assertUnprocessable();
        $code = $this->code($this->approve($user, $pendingId, ['workspace' => 'acme']));

        $tokens = $this->exchange($clientId, $code, $verifier)->assertOk()->json();
        $claims = $this->claims($tokens['access_token']);
        $this->assertSame('acme', $claims['workspace']);
        $this->assertSame($user->getKey(), $claims['sub']);

        $refreshed = $this->refresh($clientId, $tokens['refresh_token'])->assertOk()->json();
        $this->assertSame('acme', $this->claims($refreshed['access_token'])['workspace']);
    }

    public function test_the_token_id_is_not_accepted_as_a_client_credentials_token(): void
    {
        [, $tokens] = $this->connect($this->user());

        $this->withToken($this->claims($tokens['access_token'])['jti'])->getJson('/legacy')->assertUnauthorized();
        $this->withToken($tokens['access_token'])->getJson('/legacy')->assertUnauthorized();
    }

    public function test_a_registered_client_cannot_use_the_client_credentials_grant(): void
    {
        $registered = $this->postJson('/oauth/register', [
            'redirect_uris' => [self::CLAUDE_CALLBACK],
            'token_endpoint_auth_method' => 'client_secret_basic',
        ])->assertCreated()->json();

        $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $registered['client_id'],
            'client_secret' => $registered['client_secret'],
        ])->assertUnauthorized();

        $this->withHeaders(['X-Client-ID' => $registered['client_id'], 'X-Client-Secret' => $registered['client_secret']])
            ->getJson('/legacy-header')
            ->assertUnauthorized();

        $this->fresh();
        $this->withHeaders(['Authorization' => 'Basic '.base64_encode($registered['client_id'].':'.$registered['client_secret'])])
            ->getJson('/legacy-basic')
            ->assertUnauthorized();

        $legacy = Client::create(['name' => 'Server app']);
        $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $legacy->id,
            'client_secret' => $legacy->plainSecret,
        ])->assertOk();
    }

    public function test_revoking_a_client_stops_its_tokens(): void
    {
        [$clientId, $tokens] = $this->connect($this->user());

        Client::findOrFail($clientId)->update(['revoked' => true]);

        $this->resource($tokens['access_token'])->assertUnauthorized();
        $this->refresh($clientId, $tokens['refresh_token'])->assertStatus(401)->assertJson(['error' => 'invalid_client']);
    }

    public function test_a_code_consumed_by_a_concurrent_request_issues_nothing(): void
    {
        $user = $this->user();
        $clientId = $this->register();
        [$verifier, $challenge] = $this->pkce();
        $this->code($this->approve($user, $this->pendingId($this->authorize($clientId, $challenge))));
        $code = AuthorizationCode::firstOrFail();

        $context = new GrantContext;
        $context->authorizationCodeId = $code->id;
        $this->assertTrue((new AuthCodeRepository($context))->consume($code->id));

        $this->expectException(OAuthServerException::class);
        (new AccessTokenRepository($context))->persistNewAccessToken(
            (new AccessTokenRepository($context))->getNewToken((new ClientRepository)->getClientEntity($clientId), [], (string) $user->getKey())
        );
    }

    public function test_a_refresh_token_consumed_by_a_concurrent_request_issues_nothing(): void
    {
        $this->connect($this->user());
        $refreshToken = RefreshToken::firstOrFail();

        $context = new GrantContext;
        $context->previousRefreshTokenId = $refreshToken->token;
        $repository = new RefreshTokenRepository($context);

        $repository->revokeRefreshToken($refreshToken->token);

        $this->expectException(OAuthServerException::class);
        $repository->revokeRefreshToken($refreshToken->token);
    }

    public function test_removing_the_refresh_grant_stops_refresh(): void
    {
        [$clientId, $tokens] = $this->connect($this->user());

        Client::findOrFail($clientId)->update(['grant_types' => ['authorization_code']]);

        $this->refresh($clientId, $tokens['refresh_token'])->assertStatus(400)->assertJson(['error' => 'unauthorized_client']);
    }

    public function test_a_refresh_with_a_wider_scope_does_not_use_up_the_token(): void
    {
        config(['client-credentials.authorization_code.scopes.admin' => 'Administer']);
        [$clientId, $tokens] = $this->connect($this->user());

        $this->fresh();
        $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $tokens['refresh_token'],
            'scope' => 'mcp:use admin',
        ])->assertStatus(400)->assertJson(['error' => 'invalid_scope']);

        $this->refresh($clientId, $tokens['refresh_token'])->assertOk();
    }

    public function test_a_client_registered_without_refresh_gets_no_refresh_token(): void
    {
        $user = $this->user();
        $clientId = $this->postJson('/oauth/register', [
            'redirect_uris' => [self::CLAUDE_CALLBACK],
            'grant_types' => ['authorization_code'],
        ])->assertCreated()->json('client_id');
        [$verifier, $challenge] = $this->pkce();
        $code = $this->code($this->approve($user, $this->pendingId($this->authorize($clientId, $challenge))));

        $tokens = $this->exchange($clientId, $code, $verifier)->assertOk()->json();

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayNotHasKey('refresh_token', $tokens);
    }

    public function test_a_token_signed_by_another_key_is_refused(): void
    {
        [, $tokens] = $this->connect($this->user());
        [$header, $payload] = explode('.', $tokens['access_token']);

        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_sign("{$header}.{$payload}", $signature, $other, OPENSSL_ALGO_SHA256);
        $forged = "{$header}.{$payload}.".rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $this->resource($forged)->assertUnauthorized();
    }

    public function test_jwks_publishes_the_key_named_in_the_token_header(): void
    {
        [, $tokens] = $this->connect($this->user());
        $header = json_decode(base64_decode(strtr(explode('.', $tokens['access_token'])[0], '-_', '+/')), true);

        $this->getJson('/.well-known/jwks.json')
            ->assertOk()
            ->assertJsonPath('keys.0.kid', $header['kid'])
            ->assertJsonPath('keys.0.alg', 'RS256');
    }

    private function user(): User
    {
        return User::create(['name' => 'Ada', 'email' => Str::random(8).'@example.test', 'password' => 'secret']);
    }

    private function register(string $redirectUri = self::CLAUDE_CALLBACK): string
    {
        return $this->postJson('/oauth/register', ['redirect_uris' => [$redirectUri], 'client_name' => 'Claude'])
            ->assertCreated()
            ->json('client_id');
    }

    private function pkce(): array
    {
        $verifier = Str::random(64);

        return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    }

    private function authorize(string $clientId, ?string $challenge, array $extra = []): TestResponse
    {
        $this->fresh();

        $query = array_filter([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::CLAUDE_CALLBACK,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            ...$extra,
        ], fn ($value) => $value !== null);

        if (isset($extra['redirect_uri']) === false) {
            $query['redirect_uri'] = Client::findOrFail($clientId)->redirect_uris[0];
        }

        return $this->get('/oauth/authorize?'.http_build_query($query));
    }

    private function pendingId(TestResponse $response): string
    {
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://app.example.test/oauth/consent?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return $query['authorization_request'];
    }

    private function approve(User $user, string $pendingId, array $context = []): TestResponse
    {
        $this->fresh();

        return $this->actingAs($user)->postJson("/api/oauth/authorization-requests/{$pendingId}/approve", ['context' => $context]);
    }

    private function code(TestResponse $approval): string
    {
        parse_str(parse_url($approval->assertOk()->json('data.redirect_to'), PHP_URL_QUERY), $query);

        return $query['code'];
    }

    private function exchange(string $clientId, string $code, string $verifier): TestResponse
    {
        $this->fresh();

        return $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'code' => $code,
            'redirect_uri' => Client::findOrFail($clientId)->redirect_uris[0],
            'code_verifier' => $verifier,
        ]);
    }

    private function refresh(string $clientId, string $refreshToken): TestResponse
    {
        $this->fresh();

        return $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'refresh_token' => $refreshToken,
        ]);
    }

    private function connect(User $user): array
    {
        $clientId = $this->register();
        [$verifier, $challenge] = $this->pkce();
        $code = $this->code($this->approve($user, $this->pendingId($this->authorize($clientId, $challenge))));

        return [$clientId, $this->exchange($clientId, $code, $verifier)->assertOk()->json()];
    }

    private function resource(string $token): TestResponse
    {
        $this->fresh();

        return $this->withToken($token)->getJson('/mcp');
    }

    private function claims(string $jwt): array
    {
        return json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true);
    }

    /**
     * Each request in production gets a fresh container; tests share one, so
     * cached guard users and the request-scoped grant context are dropped here.
     */
    private function fresh(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetScopedInstances();
        $this->flushHeaders();
    }
}
