<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RegistrationController
{
    protected const LOOPBACK_HOSTS = ['127.0.0.1', '[::1]', 'localhost'];

    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'redirect_uris' => ['required', 'array', 'min:1'],
            'redirect_uris.*' => ['required', 'string', 'max:2000'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'token_endpoint_auth_method' => ['nullable', 'in:none,client_secret_post,client_secret_basic'],
            'grant_types' => ['nullable', 'array'],
            'grant_types.*' => ['in:authorization_code,refresh_token'],
            'response_types' => ['nullable', 'array'],
            'response_types.*' => ['in:code'],
        ]);

        if ($validator->fails()) {
            return $this->error('invalid_client_metadata', $validator->errors()->first());
        }

        foreach ($request->input('redirect_uris') as $uri) {
            if (! $this->permitted($uri)) {
                return $this->error('invalid_redirect_uri', "The redirect URI {$uri} is not permitted.");
            }
        }

        $authMethod = $request->input('token_endpoint_auth_method', 'none');
        $confidential = $authMethod !== 'none';
        $grantTypes = $request->input('grant_types') ?: ['authorization_code', 'refresh_token'];

        $model = config('client-credentials.default_model');
        $client = $model::create([
            'name' => $request->input('client_name') ?: parse_url($request->input('redirect_uris')[0], PHP_URL_HOST) ?: 'OAuth client',
            'redirect_uris' => array_values($request->input('redirect_uris')),
            'grant_types' => array_values($grantTypes),
            'confidential' => $confidential,
        ]);

        $response = [
            'client_id' => (string) $client->getKey(),
            'client_id_issued_at' => $client->created_at->getTimestamp(),
            'client_name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'grant_types' => $client->grant_types,
            'response_types' => ['code'],
            'token_endpoint_auth_method' => $authMethod,
        ];

        if ($confidential) {
            $response['client_secret'] = $client->plainSecret;
            $response['client_secret_expires_at'] = 0;
        }

        return response()->json($response, 201);
    }

    protected function permitted(string $uri): bool
    {
        $parts = parse_url($uri);

        if (! $parts || ! isset($parts['scheme'], $parts['host']) || isset($parts['fragment'])) {
            return false;
        }

        $config = config('client-credentials.authorization_code.registration');

        if ($parts['scheme'] === 'http' && in_array($parts['host'], static::LOOPBACK_HOSTS, true)) {
            return (bool) ($config['loopback'] ?? true);
        }

        return in_array($uri, $config['redirect_uris'] ?? [], true);
    }

    protected function error(string $code, string $description): JsonResponse
    {
        return response()->json(['error' => $code, 'error_description' => $description], 400);
    }
}
