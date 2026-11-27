<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Repositories;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\ScopeEntity;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;

class ScopeRepository implements ScopeRepositoryInterface
{
    public function __construct(protected GrantContext $context) {}

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if (! array_key_exists($identifier, config('client-credentials.authorization_code.scopes', []))) {
            return null;
        }

        return new ScopeEntity($identifier);
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        if ($authCodeId !== null) {
            $this->context->authorizationCodeId = $authCodeId;
        }

        return $scopes;
    }
}
