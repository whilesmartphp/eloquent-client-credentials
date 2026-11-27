<?php

namespace Whilesmart\EloquentClientCredentials\Resolvers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\EloquentClientCredentials\Contracts\AuthorizationClaimsResolver;

class NoAuthorizationClaims implements AuthorizationClaimsResolver
{
    public function resolve(Authenticatable $user, Model $client, array $scopes, array $context): array
    {
        return [];
    }
}
