<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Repositories;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AuthCodeEntity;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;
use Whilesmart\EloquentClientCredentials\Models\AuthorizationCode;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(protected GrantContext $context) {}

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity;
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        AuthorizationCode::create([
            'id' => $authCodeEntity->getIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'user_type' => $this->context->userType,
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'scopes' => array_map(fn ($scope) => $scope->getIdentifier(), $authCodeEntity->getScopes()),
            'claims' => $this->context->claims,
            'revoked' => false,
            'expires_at' => $authCodeEntity->getExpiryDateTime(),
        ]);
    }

    public function revokeAuthCode(string $codeId): void
    {
        AuthorizationCode::whereKey($codeId)->update(['revoked' => true]);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        return ! AuthorizationCode::whereKey($codeId)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * League revokes a code only after issuing its tokens, so two concurrent
     * redemptions could both pass the revocation check. This conditional update
     * runs once validation is done and lets exactly one of them through.
     */
    public function consume(string $codeId): bool
    {
        return AuthorizationCode::whereKey($codeId)
            ->where('revoked', false)
            ->update(['revoked' => true]) === 1;
    }
}
