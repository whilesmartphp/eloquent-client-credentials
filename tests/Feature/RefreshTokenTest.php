<?php

namespace Tests\Feature;

use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\Client;
use Whilesmart\EloquentClientCredentials\Models\RefreshToken;

class RefreshTokenTest extends TestCase
{
    public function test_can_create_refresh_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $this->assertNotNull($refreshToken->id);
        $this->assertEquals($accessToken->id, $refreshToken->access_token_id);
    }

    public function test_refresh_token_belongs_to_access_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $this->assertInstanceOf(AccessToken::class, $refreshToken->accessToken);
        $this->assertEquals($accessToken->id, $refreshToken->accessToken->id);
    }

    public function test_refresh_token_is_valid(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $this->assertTrue($refreshToken->isValid());
        $this->assertFalse($refreshToken->isExpired());
    }

    public function test_expired_refresh_token_is_invalid(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->subDay(),
            'revoked' => false,
        ]);

        $this->assertTrue($refreshToken->isExpired());
        $this->assertFalse($refreshToken->isValid());
    }

    public function test_revoked_refresh_token_is_invalid(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => true,
        ]);

        $this->assertFalse($refreshToken->isValid());
    }

    public function test_can_revoke_refresh_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $this->assertTrue($refreshToken->isValid());

        $refreshToken->revoke();

        $this->assertFalse($refreshToken->fresh()->isValid());
        $this->assertTrue($refreshToken->fresh()->revoked);
    }

    public function test_refresh_token_cascades_on_access_token_delete(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $refreshTokenId = $refreshToken->id;

        $accessToken->delete();

        $this->assertNull(RefreshToken::find($refreshTokenId));
    }
}
