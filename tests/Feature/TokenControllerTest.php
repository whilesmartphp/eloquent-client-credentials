<?php

namespace Tests\Feature;

use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\Client;
use Whilesmart\EloquentClientCredentials\Models\RefreshToken;

class TokenControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['client-credentials.routes.enabled' => true]);
        config(['client-credentials.routes.prefix' => 'api']);
    }

    protected function defineRoutes($router): void
    {
        $router->post('api/oauth/token', [
            \Whilesmart\EloquentClientCredentials\Http\Controllers\TokenController::class,
            'issue',
        ])->name('client-credentials.token.issue');

        $router->post('api/oauth/revoke', [
            \Whilesmart\EloquentClientCredentials\Http\Controllers\TokenController::class,
            'revoke',
        ])->name('client-credentials.token.revoke');
    }

    public function test_can_issue_token_with_client_credentials(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'scope',
            ])
            ->assertJson(['token_type' => 'Bearer']);

        $this->assertDatabaseHas('client_access_tokens', [
            'client_id' => $client->id,
            'client_type' => get_class($client),
        ]);
    }

    public function test_can_issue_token_with_scopes(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
            'scope' => 'read write',
        ]);

        $response->assertStatus(200)
            ->assertJson(['scope' => 'read write']);

        $accessToken = AccessToken::where('client_id', $client->id)->first();
        $this->assertEquals(['read', 'write'], $accessToken->scopes);
    }

    public function test_cannot_issue_token_with_invalid_credentials(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_issue_token_for_nonexistent_client(): void
    {
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'nonexistent-id',
            'client_secret' => 'some-secret',
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_issue_token_for_revoked_client(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
            'revoked' => true,
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $response->assertStatus(403);
    }

    public function test_token_includes_refresh_token_when_enabled(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => true]);

        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['refresh_token']);

        $this->assertDatabaseCount('client_refresh_tokens', 1);
    }

    public function test_token_excludes_refresh_token_when_disabled(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => false]);

        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret,
        ]);

        $response->assertStatus(200)
            ->assertJsonMissing(['refresh_token']);
    }

    public function test_can_refresh_token(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => true]);

        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainRefreshToken = 'plain-refresh-token';

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'old-access-token'),
            'scopes' => ['read'],
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $plainRefreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'refresh_token',
                'scope',
            ]);

        $this->assertTrue($accessToken->fresh()->revoked);
    }

    public function test_cannot_refresh_with_invalid_token(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => true]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => 'invalid-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_refresh_when_disabled(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => false]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => 'some-token',
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_refresh_with_revoked_refresh_token(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => true]);

        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainRefreshToken = 'plain-refresh-token';

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays(30),
            'revoked' => true,
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $plainRefreshToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_refresh_with_expired_refresh_token(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => true]);

        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainRefreshToken = 'plain-refresh-token';

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'access-token'),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->subDay(),
            'revoked' => false,
        ]);

        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $plainRefreshToken,
        ]);

        $response->assertStatus(401);
    }

    public function test_can_revoke_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'plain-access-token';

        AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $response = $this->postJson('/api/oauth/revoke', [], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);

        $accessToken = AccessToken::where('token', hash('sha256', $plainToken))->first();
        $this->assertTrue($accessToken->revoked);
    }

    public function test_revoke_also_revokes_refresh_tokens(): void
    {
        config(['client-credentials.oauth.refresh_tokens_enabled' => true]);

        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainToken = 'plain-access-token';

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $refreshToken = RefreshToken::create([
            'access_token_id' => $accessToken->id,
            'token' => hash('sha256', 'refresh-token'),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        $response = $this->postJson('/api/oauth/revoke', [], [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        $response->assertStatus(200);

        $this->assertTrue($refreshToken->fresh()->revoked);
    }

    public function test_cannot_revoke_without_token(): void
    {
        $response = $this->postJson('/api/oauth/revoke');

        $response->assertStatus(401);
    }

    public function test_cannot_revoke_invalid_token(): void
    {
        $response = $this->postJson('/api/oauth/revoke', [], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_grant_type(): void
    {
        $response = $this->postJson('/api/oauth/token', [
            'grant_type' => 'password',
            'client_id' => 'some-id',
            'client_secret' => 'some-secret',
        ]);

        $response->assertStatus(422);
    }
}
