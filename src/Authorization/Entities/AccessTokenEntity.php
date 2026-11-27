<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Entities;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use Whilesmart\EloquentClientCredentials\Authorization\Keys;

class AccessTokenEntity implements AccessTokenEntityInterface
{
    use EntityTrait, TokenEntityTrait;

    public const RESERVED_CLAIMS = ['iss', 'aud', 'sub', 'exp', 'nbf', 'iat', 'jti', 'scope', 'client_id'];

    protected CryptKeyInterface $privateKey;

    protected array $claims = [];

    public function setPrivateKey(CryptKeyInterface $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function setClaims(array $claims): void
    {
        $this->claims = array_diff_key($claims, array_flip(self::RESERVED_CLAIMS));
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public function toString(): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($this->privateKey->getKeyContents(), $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText('unused', 'unused'),
        );

        $now = new DateTimeImmutable;

        $builder = $configuration->builder()
            ->withHeader('kid', Keys::keyId())
            ->issuedBy(static::issuer())
            ->permittedFor(static::audience())
            ->identifiedBy($this->getIdentifier())
            ->relatedTo($this->getUserIdentifier() ?? $this->getClient()->getIdentifier())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($this->getExpiryDateTime())
            ->withClaim('client_id', $this->getClient()->getIdentifier())
            ->withClaim('scope', implode(' ', array_map(
                fn (ScopeEntityInterface $scope) => $scope->getIdentifier(),
                $this->getScopes(),
            )));

        foreach ($this->claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }

    public static function issuer(): string
    {
        return rtrim(config('client-credentials.authorization_code.issuer') ?: config('app.url'), '/');
    }

    public static function audience(): string
    {
        return config('client-credentials.authorization_code.audience') ?: static::issuer();
    }
}
