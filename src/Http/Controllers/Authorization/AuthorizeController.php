<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AccessTokenEntity;
use Whilesmart\EloquentClientCredentials\Authorization\PendingAuthorization;
use Whilesmart\EloquentClientCredentials\Authorization\Psr;
use Whilesmart\EloquentClientCredentials\Authorization\ServerFactory;

class AuthorizeController
{
    public function __invoke(Request $request, ServerFactory $factory): Response
    {
        $resource = $request->query('resource');

        if (is_string($resource) && rtrim($resource, '/') !== rtrim(AccessTokenEntity::audience(), '/')) {
            return response()->json([
                'error' => 'invalid_target',
                'error_description' => 'The requested resource is not served by this authorization server.',
            ], 400);
        }

        try {
            $authorizationRequest = $factory->make()->validateAuthorizationRequest(Psr::request($request));
        } catch (OAuthServerException $exception) {
            return Psr::response($exception->generateHttpResponse(Psr::newResponse()));
        }

        $consentUrl = config('client-credentials.authorization_code.consent_url');

        if (! $consentUrl) {
            throw new RuntimeException('Set client-credentials.authorization_code.consent_url.');
        }

        $pending = PendingAuthorization::store($authorizationRequest);
        $separator = str_contains($consentUrl, '?') ? '&' : '?';

        return redirect()->away($consentUrl.$separator.http_build_query(['authorization_request' => $pending->id]));
    }
}
