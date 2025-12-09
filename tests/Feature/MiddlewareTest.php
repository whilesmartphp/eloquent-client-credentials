<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBasicAuth;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBearerToken;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateClient;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\Client;

class MiddlewareTest extends TestCase
{
    // AuthenticateClient (Header-based) Tests

    public function test_header_auth_passes_with_valid_credentials(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Client-ID', $client->id);
        $request->headers->set('X-Client-Secret', $client->plainSecret);

        $middleware = new AuthenticateClient();

        $response = $middleware->handle($request, function ($req) {
            $this->assertNotNull($req->get('client'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_header_auth_fails_without_credentials(): void
    {
        $request = Request::create('/test', 'GET');

        $middleware = new AuthenticateClient();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_header_auth_fails_with_invalid_client(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Client-ID', 'nonexistent-id');
        $request->headers->set('X-Client-Secret', 'some-secret');

        $middleware = new AuthenticateClient();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_header_auth_fails_with_invalid_secret(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Client-ID', $client->id);
        $request->headers->set('X-Client-Secret', 'wrong-secret');

        $middleware = new AuthenticateClient();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_header_auth_fails_for_revoked_client(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
            'revoked' => true,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Client-ID', $client->id);
        $request->headers->set('X-Client-Secret', $client->plainSecret);

        $middleware = new AuthenticateClient();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(403, $response->getStatusCode());
    }

    // AuthenticateBasicAuth Tests

    public function test_basic_auth_passes_with_valid_credentials(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $credentials = base64_encode($client->id . ':' . $client->plainSecret);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            $this->assertNotNull($req->get('client'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_basic_auth_fails_without_authorization_header(): void
    {
        $request = Request::create('/test', 'GET');

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertTrue($response->headers->has('WWW-Authenticate'));
    }

    public function test_basic_auth_fails_with_non_basic_authorization(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer some-token');

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_basic_auth_fails_with_invalid_base64(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Basic invalid-base64!!!');

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_basic_auth_fails_with_invalid_client(): void
    {
        $credentials = base64_encode('nonexistent-id:some-secret');

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_basic_auth_fails_with_invalid_secret(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $credentials = base64_encode($client->id . ':wrong-secret');

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_basic_auth_fails_for_revoked_client(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
            'revoked' => true,
        ]);

        $credentials = base64_encode($client->id . ':' . $client->plainSecret);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Basic ' . $credentials);

        $middleware = new AuthenticateBasicAuth();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(403, $response->getStatusCode());
    }

    // AuthenticateBearerToken Tests

    public function test_bearer_auth_passes_with_valid_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'valid-bearer-token';

        AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            $this->assertNotNull($req->get('client'));
            $this->assertNotNull($req->get('accessToken'));
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_bearer_auth_fails_without_token(): void
    {
        $request = Request::create('/test', 'GET');

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_bearer_auth_fails_with_invalid_token(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_bearer_auth_fails_with_expired_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'expired-token';

        AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->subHour(),
            'revoked' => false,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_bearer_auth_fails_with_revoked_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'revoked-token';

        AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addHour(),
            'revoked' => true,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_bearer_auth_passes_with_valid_scope(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'scoped-token';

        AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'scopes' => ['read', 'write'],
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'read');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_bearer_auth_fails_with_insufficient_scope(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'limited-token';

        AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'scopes' => ['read'],
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $plainToken);

        $middleware = new AuthenticateBearerToken();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'admin');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
