<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface
{
    use ClientTrait, EntityTrait;

    public function __construct(
        string $identifier,
        string $name,
        array $redirectUris,
        bool $confidential,
        protected array $grantTypes,
    ) {
        $this->setIdentifier($identifier);
        $this->name = $name;
        $this->redirectUri = $redirectUris;
        $this->isConfidential = $confidential;
    }

    public function supportsGrantType(string $grantType): bool
    {
        return in_array($grantType, $this->grantTypes, true);
    }
}
