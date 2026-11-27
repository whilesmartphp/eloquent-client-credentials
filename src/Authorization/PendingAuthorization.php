<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\ScopeEntity;
use Whilesmart\EloquentClientCredentials\Authorization\Repositories\ClientRepository;

/**
 * Stored as plain data rather than League objects, which a cache limited by
 * `cache.serializable_classes` would refuse to unserialize.
 */
class PendingAuthorization
{
    public function __construct(public readonly string $id, public readonly array $data) {}

    public static function store(AuthorizationRequestInterface $request): self
    {
        $id = Str::random(48);

        $data = [
            'client_id' => $request->getClient()->getIdentifier(),
            'redirect_uri' => $request->getRedirectUri(),
            'state' => $request->getState(),
            'scopes' => array_map(fn ($scope) => $scope->getIdentifier(), $request->getScopes()),
            'code_challenge' => $request->getCodeChallenge(),
            'code_challenge_method' => $request->getCodeChallengeMethod(),
            'expires_at' => now()->addSeconds(static::lifetime())->getTimestamp(),
        ];

        Cache::put(static::key($id), $data, static::lifetime());

        return new self($id, $data);
    }

    public static function find(string $id): ?self
    {
        $data = Cache::get(static::key($id));

        return is_array($data) ? new self($id, $data) : null;
    }

    public function consume(): bool
    {
        if (! Cache::add(static::key($this->id).':answered', true, static::lifetime())) {
            return false;
        }

        return Cache::pull(static::key($this->id)) !== null;
    }

    public function toLeagueRequest(): ?AuthorizationRequest
    {
        $client = (new ClientRepository)->getClientEntity($this->data['client_id']);

        if (! $client) {
            return null;
        }

        $request = new AuthorizationRequest;
        $request->setGrantTypeId('authorization_code');
        $request->setClient($client);
        $request->setRedirectUri($this->data['redirect_uri']);
        $request->setScopes(array_map(fn ($scope) => new ScopeEntity($scope), $this->data['scopes']));
        $request->setCodeChallenge($this->data['code_challenge']);
        $request->setCodeChallengeMethod($this->data['code_challenge_method']);

        if ($this->data['state'] !== null) {
            $request->setState($this->data['state']);
        }

        return $request;
    }

    protected static function key(string $id): string
    {
        return "client-credentials:authorization-request:{$id}";
    }

    protected static function lifetime(): int
    {
        return (int) config('client-credentials.authorization_code.request_lifetime', 600);
    }
}
