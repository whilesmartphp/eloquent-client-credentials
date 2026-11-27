<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers\Authorization;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\Response;
use Whilesmart\EloquentClientCredentials\Authorization\Connections;
use Whilesmart\EloquentClientCredentials\Authorization\Psr;
use Whilesmart\EloquentClientCredentials\Authorization\ServerFactory;

class AccessTokenController
{
    public function __invoke(Request $request, ServerFactory $factory): Response
    {
        $clientId = (string) ($request->input('client_id') ?? $request->getUser() ?? '');

        try {
            $response = Connections::exclusively($clientId, fn () => $factory->make()
                ->respondToAccessTokenRequest(Psr::request($request), Psr::newResponse()));
        } catch (OAuthServerException $exception) {
            $response = $exception->generateHttpResponse(Psr::newResponse());
        } catch (LockTimeoutException) {
            $response = (new OAuthServerException('The server is busy, try again.', 0, 'temporarily_unavailable', 503))
                ->generateHttpResponse(Psr::newResponse());
        }

        return Psr::response($response);
    }
}
