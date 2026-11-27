<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Http\JsonResponse;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AccessTokenEntity;
use Whilesmart\EloquentClientCredentials\Authorization\Keys;

class MetadataController
{
    public function authorizationServer(): JsonResponse
    {
        $config = config('client-credentials.authorization_code');

        $metadata = [
            'issuer' => AccessTokenEntity::issuer(),
            'authorization_endpoint' => $this->endpoint('client-credentials.authorization.authorize'),
            'token_endpoint' => $this->endpoint('client-credentials.authorization.token'),
            'jwks_uri' => $this->endpoint('client-credentials.authorization.jwks'),
            'scopes_supported' => array_keys($config['scopes'] ?? []),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
        ];

        if ($config['registration']['enabled'] ?? false) {
            $metadata['registration_endpoint'] = $this->endpoint('client-credentials.authorization.register');
        }

        return response()->json($metadata);
    }

    public function protectedResource(): JsonResponse
    {
        $config = config('client-credentials.authorization_code');

        return response()->json([
            'resource' => AccessTokenEntity::audience(),
            'authorization_servers' => [AccessTokenEntity::issuer()],
            'scopes_supported' => array_keys($config['scopes'] ?? []),
            'bearer_methods_supported' => ['header'],
        ]);
    }

    protected function endpoint(string $route): string
    {
        return AccessTokenEntity::issuer().route($route, [], false);
    }

    public function jwks(): JsonResponse
    {
        return response()->json(['keys' => [Keys::jwk()]]);
    }
}
