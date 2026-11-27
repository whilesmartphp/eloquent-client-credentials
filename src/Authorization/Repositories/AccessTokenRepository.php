<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Repositories;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AccessTokenEntity;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(protected GrantContext $context) {}

    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): AccessTokenEntityInterface {
        $this->resolveGrantOrigin();

        $token = new AccessTokenEntity;
        $token->setClient($clientEntity);
        $token->setClaims($this->context->claims);

        foreach ($scopes as $scope) {
            $token->addScope($scope);
        }

        if ($userIdentifier !== null) {
            $token->setUserIdentifier($userIdentifier);
        }

        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        if ($this->context->authorizationCodeId !== null) {
            if (! (new AuthCodeRepository($this->context))->consume($this->context->authorizationCodeId)) {
                throw OAuthServerException::invalidGrant('Authorization code has been revoked');
            }

            $this->context->authorizationCodeId = null;
        }

        $model = config('client-credentials.default_model');

        AccessToken::create([
            'client_type' => $model,
            'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
            'token' => $accessTokenEntity->getIdentifier(),
            'scopes' => array_map(fn ($scope) => $scope->getIdentifier(), $accessTokenEntity->getScopes()),
            'expires_at' => $accessTokenEntity->getExpiryDateTime(),
            'revoked' => false,
            'user_type' => $this->context->userType,
            'user_id' => $accessTokenEntity->getUserIdentifier(),
            'claims' => $this->context->claims,
        ]);
    }

    public function revokeAccessToken(string $tokenId): void
    {
        AccessToken::where('token', $tokenId)->update(['revoked' => true]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return ! AccessToken::where('token', $tokenId)->valid()->exists();
    }

    protected function resolveGrantOrigin(): void
    {
        $origin = match (true) {
            $this->context->authorizationCodeId !== null => (new AuthCodeRepository($this->context))->find($this->context->authorizationCodeId),
            $this->context->previousAccessTokenId !== null => AccessToken::where('token', $this->context->previousAccessTokenId)->first()?->only(['user_type', 'claims']),
            default => null,
        };

        if ($origin) {
            $this->context->userType = $origin['user_type'];
            $this->context->claims = $origin['claims'] ?? [];
        }
    }
}
