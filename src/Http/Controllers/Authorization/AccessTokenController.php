<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\EloquentClientCredentials\Authorization\Psr;
use Whilesmart\EloquentClientCredentials\Authorization\ServerFactory;

class AccessTokenController
{
    public function __invoke(Request $request, ServerFactory $factory): Response
    {
        try {
            $response = $factory->make()->respondToAccessTokenRequest(Psr::request($request), Psr::newResponse());
        } catch (OAuthServerException $exception) {
            $response = $exception->generateHttpResponse(Psr::newResponse());
        }

        return Psr::response($response);
    }
}
