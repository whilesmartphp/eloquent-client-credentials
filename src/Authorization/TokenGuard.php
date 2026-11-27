<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;
use Throwable;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AccessTokenEntity;
use Whilesmart\EloquentClientCredentials\Models\AccessToken;

/**
 * Leaves the matching AccessToken on the request as `oauth_access_token`, so
 * routes can read its scopes and claims.
 */
class TokenGuard
{
    public function __invoke(Request $request): ?Authenticatable
    {
        $bearer = $request->bearerToken();

        if (! $bearer || substr_count($bearer, '.') !== 2) {
            return null;
        }

        $publicKey = InMemory::plainText(Keys::publicKey());
        $configuration = Configuration::forAsymmetricSigner(new Sha256, $publicKey, $publicKey);

        try {
            $token = $configuration->parser()->parse($bearer);
        } catch (Throwable) {
            return null;
        }

        $valid = $token instanceof UnencryptedToken && $configuration->validator()->validate(
            $token,
            new SignedWith(new Sha256, $publicKey),
            new StrictValidAt(new class implements ClockInterface
            {
                public function now(): DateTimeImmutable
                {
                    return now()->toDateTimeImmutable();
                }
            }),
            new IssuedBy(AccessTokenEntity::issuer()),
            new PermittedFor(AccessTokenEntity::audience()),
        );

        if (! $valid) {
            return null;
        }

        $record = AccessToken::query()
            ->where('token', (string) $token->claims()->get('jti'))
            ->whereNotNull('subject_id')
            ->valid()
            ->first();

        $client = $record?->client;
        $subject = $record?->subject;

        if (! $client || $client->revoked || ! $subject instanceof Authenticatable) {
            return null;
        }

        $request->attributes->set('oauth_access_token', $record);

        return $subject;
    }
}
