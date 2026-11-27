<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Grants;

use League\OAuth2\Server\Grant\RefreshTokenGrant as LeagueRefreshTokenGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;

class RefreshTokenGrant extends LeagueRefreshTokenGrant
{
    public function __construct(RefreshTokenRepositoryInterface $refreshTokenRepository, protected GrantContext $context)
    {
        parent::__construct($refreshTokenRepository);
    }

    protected function validateOldRefreshToken(ServerRequestInterface $request, string $clientId): array
    {
        $refreshToken = parent::validateOldRefreshToken($request, $clientId);

        $this->context->previousAccessTokenId = $refreshToken['access_token_id'];
        $this->context->previousRefreshTokenId = $refreshToken['refresh_token_id'];

        return $refreshToken;
    }
}
