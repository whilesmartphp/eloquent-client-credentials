<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Repositories;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\RefreshTokenEntity;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;
use Whilesmart\EloquentClientCredentials\Models\RefreshToken;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(protected GrantContext $context) {}

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity;
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $accessToken = AccessToken::where('token', $refreshTokenEntity->getAccessToken()->getIdentifier())->firstOrFail();

        RefreshToken::create([
            'access_token_id' => $accessToken->getKey(),
            'token' => $refreshTokenEntity->getIdentifier(),
            'expires_at' => $refreshTokenEntity->getExpiryDateTime(),
            'revoked' => false,
        ]);
    }

    /**
     * League revokes the old refresh token after validating the request and before
     * issuing new tokens. Making that revocation conditional lets exactly one of two
     * concurrent refreshes with the same token through.
     */
    public function revokeRefreshToken(string $tokenId): void
    {
        $revoked = RefreshToken::where('token', $tokenId)->where('revoked', false)->update(['revoked' => true]);

        if ($revoked === 0 && $tokenId === $this->context->previousRefreshTokenId) {
            throw OAuthServerException::invalidRefreshToken('Token has been revoked');
        }
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $token = RefreshToken::where('token', $tokenId)->first();

        return ! $token || ! $token->isValid();
    }
}
