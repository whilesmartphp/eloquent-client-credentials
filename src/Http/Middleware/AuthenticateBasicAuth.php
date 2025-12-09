<?php

namespace Whilesmart\EloquentClientCredentials\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateBasicAuth
{
    public function handle(Request $request, Closure $next, ?string $model = null): Response
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Basic ')) {
            return $this->unauthorized(__('client-credentials::messages.missing_credentials'));
        }

        $credentials = base64_decode(substr($authorization, 6));
        
        if (!$credentials || !str_contains($credentials, ':')) {
            return $this->unauthorized(__('client-credentials::messages.invalid_credentials'));
        }

        [$clientId, $clientSecret] = explode(':', $credentials, 2);

        $modelClass = $model ?? config('client-credentials.default_model');
        $client = $modelClass::find($clientId);

        if (!$client) {
            return $this->unauthorized(__('client-credentials::messages.invalid_client'));
        }

        if (method_exists($client, 'verifySecret') && !$client->verifySecret($clientSecret)) {
            return $this->unauthorized(__('client-credentials::messages.invalid_secret'));
        }

        if (isset($client->revoked) && $client->revoked) {
            return response()->json([
                'message' => __('client-credentials::messages.client_revoked'),
            ], 403);
        }

        $request->merge(['client' => $client]);

        return $next($request);
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'message' => $message,
        ], 401)->withHeaders([
            'WWW-Authenticate' => 'Basic realm="Client Credentials"',
        ]);
    }
}
