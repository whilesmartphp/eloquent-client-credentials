<?php

namespace Tests\Feature;

use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\Client;

class ClientCredentialsTest extends TestCase
{
    public function test_can_create_client(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'description' => 'A test application',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $this->assertNotNull($client->id);
        $this->assertEquals('Test App', $client->name);
        $this->assertNotNull($client->slug);
        $this->assertNotNull($client->plainSecret);
    }

    public function test_can_verify_secret(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $plainSecret = $client->plainSecret;

        $this->assertTrue($client->verifySecret($plainSecret));
        $this->assertFalse($client->verifySecret('wrong-secret'));
    }

    public function test_can_regenerate_secret(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $originalSecret = $client->plainSecret;
        $newSecret = $client->regenerateSecret();

        $this->assertNotEquals($originalSecret, $newSecret);
        $this->assertTrue($client->verifySecret($newSecret));
        $this->assertFalse($client->verifySecret($originalSecret));
    }

    public function test_can_scope_by_owner(): void
    {
        Client::create([
            'name' => 'User 1 App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '1',
        ]);

        Client::create([
            'name' => 'User 2 App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '2',
        ]);

        $clients = Client::where('owner_type', 'App\\Models\\User')
            ->where('owner_id', '1')
            ->get();

        $this->assertCount(1, $clients);
        $this->assertEquals('User 1 App', $clients->first()->name);
    }

    public function test_can_create_access_token(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $token = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'test-token'),
            'scopes' => ['read', 'write'],
            'expires_at' => now()->addHour(),
            'revoked' => false,
        ]);

        $this->assertNotNull($token->id);
        $this->assertTrue($token->isValid());
        $this->assertFalse($token->isExpired());
    }

    public function test_expired_token_is_invalid(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $token = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'test-token'),
            'expires_at' => now()->subHour(),
            'revoked' => false,
        ]);

        $this->assertTrue($token->isExpired());
        $this->assertFalse($token->isValid());
    }

    public function test_revoked_token_is_invalid(): void
    {
        $client = Client::create([
            'name' => 'Test App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => '123',
        ]);

        $token = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', 'test-token'),
            'expires_at' => now()->addHour(),
            'revoked' => true,
        ]);

        $this->assertFalse($token->isValid());
    }
}
