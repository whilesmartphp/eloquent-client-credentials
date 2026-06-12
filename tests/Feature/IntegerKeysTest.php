<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Models\Client;

class IntegerKeysTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Opt out of UUID keys so clients/tokens use auto-incrementing integers.
        $app['config']->set('client-credentials.uuids', false);
    }

    public function test_clients_and_tokens_use_integer_keys_when_uuids_disabled(): void
    {
        $client = Client::create([
            'name' => 'Integer App',
            'owner_type' => 'App\\Models\\User',
            'owner_id' => 123,
        ]);

        $this->assertTrue($client->getIncrementing());
        $this->assertSame('int', $client->getKeyType());
        $this->assertFalse(Str::isUuid((string) $client->getKey()));

        $token = $client->tokens()->create([
            'token' => 'tok_'.Str::random(20),
        ]);

        $this->assertSame('int', $token->getKeyType());
        $this->assertFalse(Str::isUuid((string) $token->getKey()));
        $this->assertEquals($client->getKey(), $token->client_id);
        $this->assertTrue($token->client->is($client));
    }
}
