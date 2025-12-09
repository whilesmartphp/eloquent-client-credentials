<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Whilesmart\EloquentClientCredentials\Enums\HookAction;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\RefreshToken;
use Whilesmart\EloquentClientCredentials\Traits\HasMiddlewareHooks;

class TokenController extends ApiController
{
    use HasMiddlewareHooks;

    public function issue(Request $request): JsonResponse
    {
        $request = $this->runBeforeHooks($request, HookAction::TOKEN_ISSUE);

        $grantType = $request->input('grant_type');

        if ($grantType === 'refresh_token') {
            return $this->handleRefreshToken($request);
        }

        $request->validate([
            'grant_type' => 'required|in:client_credentials,refresh_token',
            'client_id' => 'required|string',
            'client_secret' => 'required|string',
            'scope' => 'sometimes|string',
        ]);

        $model = config('client-credentials.default_model');
        $client = $model::find($request->client_id);

        if (!$client || !$client->verifySecret($request->client_secret)) {
            return $this->failure(__('client-credentials::messages.invalid_credentials'), 401);
        }

        if ($client->revoked) {
            return $this->failure(__('client-credentials::messages.client_revoked'), 403);
        }

        $scopes = $request->scope ? explode(' ', $request->scope) : [];
        $tokenLifetime = config('client-credentials.oauth.token_lifetime', 3600);

        $plainToken = Str::random(80);

        $accessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'scopes' => $scopes,
            'expires_at' => now()->addSeconds($tokenLifetime),
            'revoked' => false,
        ]);

        $responseData = [
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenLifetime,
            'scope' => implode(' ', $scopes),
        ];

        if (config('client-credentials.oauth.refresh_tokens_enabled', false)) {
            $plainRefreshToken = Str::random(80);
            $refreshTokenLifetime = config('client-credentials.oauth.refresh_token_lifetime', 86400 * 30);

            RefreshToken::create([
                'access_token_id' => $accessToken->id,
                'token' => hash('sha256', $plainRefreshToken),
                'expires_at' => now()->addSeconds($refreshTokenLifetime),
                'revoked' => false,
            ]);

            $responseData['refresh_token'] = $plainRefreshToken;
        }

        $response = response()->json($responseData);

        return $this->runAfterHooks($request, $response, HookAction::TOKEN_ISSUE);
    }

    protected function handleRefreshToken(Request $request): JsonResponse
    {
        if (!config('client-credentials.oauth.refresh_tokens_enabled', false)) {
            return $this->failure(__('client-credentials::messages.refresh_tokens_disabled'), 400);
        }

        $request->validate([
            'grant_type' => 'required|in:refresh_token',
            'refresh_token' => 'required|string',
        ]);

        $hashedRefreshToken = hash('sha256', $request->refresh_token);
        $refreshToken = RefreshToken::where('token', $hashedRefreshToken)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return $this->failure(__('client-credentials::messages.invalid_refresh_token'), 401);
        }

        $oldAccessToken = $refreshToken->accessToken;
        $client = $oldAccessToken->client;

        if (!$client || $client->revoked) {
            return $this->failure(__('client-credentials::messages.client_revoked'), 403);
        }

        $refreshToken->revoke();
        $oldAccessToken->revoke();

        $scopes = $oldAccessToken->scopes ?? [];
        $tokenLifetime = config('client-credentials.oauth.token_lifetime', 3600);
        $refreshTokenLifetime = config('client-credentials.oauth.refresh_token_lifetime', 86400 * 30);

        $plainToken = Str::random(80);
        $plainRefreshToken = Str::random(80);

        $newAccessToken = AccessToken::create([
            'client_type' => get_class($client),
            'client_id' => $client->id,
            'token' => hash('sha256', $plainToken),
            'scopes' => $scopes,
            'expires_at' => now()->addSeconds($tokenLifetime),
            'revoked' => false,
        ]);

        RefreshToken::create([
            'access_token_id' => $newAccessToken->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addSeconds($refreshTokenLifetime),
            'revoked' => false,
        ]);

        $response = response()->json([
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenLifetime,
            'refresh_token' => $plainRefreshToken,
            'scope' => implode(' ', $scopes),
        ]);

        return $this->runAfterHooks($request, $response, HookAction::TOKEN_ISSUE);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request = $this->runBeforeHooks($request, HookAction::TOKEN_REVOKE);

        $token = $request->bearerToken();

        if (!$token) {
            return $this->failure(__('client-credentials::messages.missing_token'), 401);
        }

        $hashedToken = hash('sha256', $token);
        $accessToken = AccessToken::where('token', $hashedToken)->first();

        if (!$accessToken) {
            return $this->failure(__('client-credentials::messages.invalid_token'), 401);
        }

        $accessToken->revoke();

        if (config('client-credentials.oauth.refresh_tokens_enabled', false)) {
            RefreshToken::where('access_token_id', $accessToken->id)->update(['revoked' => true]);
        }

        $response = $this->success(null, __('client-credentials::messages.token_revoked'));

        return $this->runAfterHooks($request, $response, HookAction::TOKEN_REVOKE);
    }
}
