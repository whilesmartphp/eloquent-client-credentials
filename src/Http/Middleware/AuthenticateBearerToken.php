<?php

namespace Whilesmart\EloquentClientCredentials\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;

class AuthenticateBearerToken
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'message' => __('client-credentials::messages.missing_token'),
            ], 401);
        }

        $hashedToken = hash('sha256', $token);
        $accessToken = AccessToken::where('token', $hashedToken)->first();

        if (!$accessToken || !$accessToken->isValid()) {
            return response()->json([
                'message' => __('client-credentials::messages.invalid_token'),
            ], 401);
        }

        if ($scope && !in_array($scope, $accessToken->scopes ?? [])) {
            return response()->json([
                'message' => __('client-credentials::messages.insufficient_scope'),
            ], 403);
        }

        $client = $accessToken->client;

        $request->merge(['client' => $client, 'accessToken' => $accessToken]);

        return $next($request);
    }
}
