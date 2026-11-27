<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Grants;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant as LeagueAuthCodeGrant;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OAuth 2.1 requires PKCE on every authorization request and rules out the plain
 * method, which League accepts and also assumes when no method is sent.
 */
class AuthCodeGrant extends LeagueAuthCodeGrant
{
    public function validateAuthorizationRequest(ServerRequestInterface $request): AuthorizationRequestInterface
    {
        $authorizationRequest = parent::validateAuthorizationRequest($request);

        if ($authorizationRequest->getCodeChallenge() === null || $authorizationRequest->getCodeChallengeMethod() !== 'S256') {
            throw OAuthServerException::invalidRequest('code_challenge_method', 'A code challenge using S256 is required.');
        }

        return $authorizationRequest;
    }
}
