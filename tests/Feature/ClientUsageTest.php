<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;
use Whilesmart\EloquentClientCredentials\Http\Controllers\TokenController;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateBearerToken;
use Whilesmart\EloquentClientCredentials\Http\Middleware\AuthenticateClient;
use Whilesmart\EloquentClientCredentials\Models\Client;

class ClientUsageTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('api/oauth/token', [TokenController::class, 'issue']);
        $router->get('api/header', fn () => response()->json(['ok' => true]))->middleware(AuthenticateClient::class);
        $router->get('api/bearer', fn () => response()->json(['ok' => true]))->middleware(AuthenticateBearerToken::class);
    }

    private function client(array $attributes = []): Client
    {
        return Client::create(['name' => 'Test App', 'owner_type' => 'App\\Models\\User', 'owner_id' => '123', ...$attributes]);
    }

    public function test_a_new_client_has_not_been_used(): void
    {
        $this->assertNull($this->client()->fresh()->last_used_at);
    }

    public function test_issuing_a_token_records_when_the_client_was_used(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $client = $this->client();

        $token = $this->postJson('/api/oauth/token', [
            'grant_type' => 'client_credentials', 'client_id' => $client->id, 'client_secret' => $client->plainSecret,
        ])->assertOk()->json('access_token');
        $this->assertSame('2026-11-17 10:00:00', $client->fresh()->last_used_at->toDateTimeString());

        Carbon::setTestNow('2026-11-17 11:00:00');
        $this->withToken($token)->getJson('/api/bearer')->assertOk();
        $this->assertSame('2026-11-17 11:00:00', $client->fresh()->last_used_at->toDateTimeString());
    }

    public function test_header_authentication_records_use_and_failures_do_not(): void
    {
        $client = $this->client();

        $this->withHeaders(['X-Client-ID' => $client->id, 'X-Client-Secret' => 'wrong-secret'])->getJson('/api/header')->assertUnauthorized();
        $this->assertNull($client->fresh()->last_used_at);

        $this->withHeaders(['X-Client-ID' => $client->id, 'X-Client-Secret' => $client->plainSecret])->getJson('/api/header')->assertOk();
        $this->assertNotNull($client->fresh()->last_used_at);
    }

    public function test_clients_can_be_filtered_by_type(): void
    {
        $ci = $this->client(['name' => 'CI', 'type' => 'ci']);
        $this->client(['name' => 'API', 'type' => 'api']);
        $this->client(['name' => 'Untyped']);

        $this->assertSame([$ci->id], Client::ofType('ci')->pluck('id')->all());
    }

    public function test_recording_use_does_not_fire_model_events(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $client = $this->client();
        Carbon::setTestNow('2026-11-17 12:00:00');
        $fired = false;
        Client::updated(function () use (&$fired) {
            $fired = true;
        });

        $client->markUsed();

        $this->assertFalse($fired);
        $this->assertSame('2026-11-17 12:00:00', $client->fresh()->last_used_at->toDateTimeString());
    }
}
