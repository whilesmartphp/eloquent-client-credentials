<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Repositories;

use Illuminate\Support\Facades\Cache;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Connections;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\AuthCodeEntity;
use Whilesmart\EloquentClientCredentials\Authorization\GrantContext;

/**
 * League encrypts the client, user, scopes and PKCE challenge into the code it
 * hands out, so the server only keeps whether the code is still unused and the
 * host claims to carry into its tokens, for the code's short lifetime.
 */
class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(protected GrantContext $context) {}

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity;
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $clientId = $authCodeEntity->getClient()->getIdentifier();
        $subjectId = $authCodeEntity->getUserIdentifier();

        Cache::put(static::key($authCodeEntity->getIdentifier()), [
            'client_id' => $clientId,
            'subject_type' => $this->context->subjectType,
            'subject_id' => $subjectId,
            'claims' => $this->context->claims,
            'generation' => Connections::generation($clientId, $this->context->subjectType, $subjectId),
        ], $authCodeEntity->getExpiryDateTime());
    }

    public function revokeAuthCode(string $codeId): void
    {
        Cache::forget(static::key($codeId));
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        return $this->find($codeId) === null;
    }

    public function find(string $codeId): ?array
    {
        $data = Cache::get(static::key($codeId));

        if (! is_array($data)) {
            return null;
        }

        $current = Connections::generation($data['client_id'], $data['subject_type'], $data['subject_id']);

        return $current === $data['generation'] ? $data : null;
    }

    /**
     * League revokes a code only after issuing its tokens, so two concurrent
     * redemptions could both pass the revocation check. The atomic add runs once
     * validation is done and lets exactly one of them through.
     */
    public function consume(string $codeId): bool
    {
        return $this->find($codeId) !== null
            && Cache::add(static::key($codeId).':used', true, (int) config('client-credentials.authorization_code.code_lifetime', 600));
    }

    protected static function key(string $codeId): string
    {
        return "client-credentials:authorization-code:{$codeId}";
    }
}
