<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\UserEntity;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;
use Whilesmart\EloquentClientCredentials\Authorization\PendingAuthorization;
use Whilesmart\EloquentClientCredentials\Authorization\Psr;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\ClientRepository;
use Whilesmart\EloquentClientCredentials\Authorization\ServerFactory;
use Whilesmart\EloquentClientCredentials\Contracts\AuthorizationClaimsResolver;

class AuthorizationRequestController
{
    public function show(string $id): JsonResponse
    {
        $pending = PendingAuthorization::find($id);
        $client = $pending ? (new ClientRepository)->find($pending->data['client_id']) : null;

        if (! $client) {
            return $this->notFound();
        }

        $descriptions = config('client-credentials.authorization_code.scopes', []);

        return response()->json([
            'data' => [
                'id' => $pending->id,
                'client' => [
                    'id' => (string) $client->getKey(),
                    'name' => $client->name,
                ],
                'redirect_host' => parse_url($pending->data['display_redirect_uri'] ?? '', PHP_URL_HOST),
                'scopes' => array_map(fn (string $scope) => [
                    'id' => $scope,
                    'description' => $descriptions[$scope] ?? $scope,
                ], $pending->data['scopes']),
                'expires_at' => date(DATE_ATOM, $pending->data['expires_at']),
            ],
        ]);
    }

    public function approve(
        Request $request,
        string $id,
        ServerFactory $factory,
        GrantContext $context,
        AuthorizationClaimsResolver $claims,
    ): JsonResponse {
        $pending = PendingAuthorization::find($id);
        $client = $pending ? (new ClientRepository)->find($pending->data['client_id']) : null;
        $authorizationRequest = $pending?->toLeagueRequest();

        if (! $client || ! $authorizationRequest) {
            return $this->notFound();
        }

        $user = $request->user();
        $context->claims = $claims->resolve($user, $client, $pending->data['scopes'], (array) $request->input('context', []));
        $context->subjectType = $user->getMorphClass();

        if (! $pending->consume()) {
            return $this->notFound();
        }

        $authorizationRequest->setUser(new UserEntity((string) $user->getAuthIdentifier()));
        $authorizationRequest->setAuthorizationApproved(true);

        $response = $factory->make()->completeAuthorizationRequest($authorizationRequest, Psr::newResponse());

        return response()->json(['data' => ['redirect_to' => $response->getHeaderLine('Location')]]);
    }

    public function deny(Request $request, string $id, ServerFactory $factory): JsonResponse
    {
        $pending = PendingAuthorization::find($id);
        $authorizationRequest = $pending?->toLeagueRequest();

        if (! $authorizationRequest || ! $pending->consume()) {
            return $this->notFound();
        }

        $authorizationRequest->setUser(new UserEntity((string) $request->user()->getAuthIdentifier()));
        $authorizationRequest->setAuthorizationApproved(false);

        try {
            $factory->make()->completeAuthorizationRequest($authorizationRequest, Psr::newResponse());
        } catch (OAuthServerException $exception) {
            $response = $exception->generateHttpResponse(Psr::newResponse());

            return response()->json(['data' => ['redirect_to' => $response->getHeaderLine('Location')]]);
        }

        return $this->notFound();
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'message' => __('client-credentials::messages.authorization_request_not_found'),
        ], 404);
    }
}
