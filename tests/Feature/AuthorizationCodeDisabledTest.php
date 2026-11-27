<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthorizationCodeDisabledTest extends TestCase
{
    public function test_the_authorization_code_routes_are_off_by_default(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')->assertNotFound();
        $this->get('/oauth/authorize')->assertNotFound();
        $this->postJson('/oauth/register', ['redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']])->assertNotFound();
        $this->assertArrayNotHasKey('oauth', config('auth.guards'));
    }
}
