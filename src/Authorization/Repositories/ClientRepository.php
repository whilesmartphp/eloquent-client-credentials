<?php

namespace Whilesmart\EloquentClientCredentials\Authorization\Repositories;

use Illuminate\Database\Eloquent\Model;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Whilesmart\EloquentClientCredentials\Authorization\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface
{
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = $this->find($clientIdentifier);

        if (! $client || ! $client->allowsGrant('authorization_code')) {
            return null;
        }

        return new ClientEntity(
            (string) $client->getKey(),
            $client->name,
            $client->redirect_uris ?? [],
            (bool) $client->confidential,
            (array) ($client->grant_types ?? []),
        );
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $client = $this->find($clientIdentifier);

        if (! $client || ! $grantType || ! $client->allowsGrant($grantType)) {
            return false;
        }

        if ($client->confidential && ($clientSecret === null || ! $client->verifySecret($clientSecret))) {
            return false;
        }

        if (method_exists($client, 'markUsed')) {
            $client->markUsed();
        }

        return true;
    }

    public function find(string $clientIdentifier): ?Model
    {
        $model = config('client-credentials.default_model');

        $client = $model::find($clientIdentifier);

        if (! $client || $client->revoked) {
            return null;
        }

        return $client;
    }
}
