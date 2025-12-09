<?php

namespace Whilesmart\EloquentClientCredentials\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClient
{
    public function handle(Request $request, Closure $next, ?string $model = null): Response
    {
        $clientId = $request->header('X-Client-ID');
        $clientSecret = $request->header('X-Client-Secret');

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'message' => __('client-credentials.missing_credentials'),
            ], 401);
        }

        $modelClass = $model ?? config('client-credentials.default_model');
        $client = $modelClass::find($clientId);

        if (!$client) {
            return response()->json([
                'message' => __('client-credentials.invalid_client'),
            ], 401);
        }

        if (method_exists($client, 'verifySecret') && !$client->verifySecret($clientSecret)) {
            return response()->json([
                'message' => __('client-credentials.invalid_secret'),
            ], 401);
        }

        if (isset($client->revoked) && $client->revoked) {
            return response()->json([
                'message' => __('client-credentials::messages.client_revoked'),
            ], 403);
        }

        $request->merge(['client' => $client]);
        $request->setUserResolver(fn () => $client);

        return $next($request);
    }
}
