<?php

namespace Whilesmart\EloquentClientCredentials\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

interface AuthorizationClaimsResolver
{
    /**
     * Claims to add to every access token issued from this approval, including
     * refreshed ones. $context is what the consent screen sent with the approval,
     * such as a chosen workspace. Throw a ValidationException to refuse it.
     *
     * @param  string[]  $scopes
     */
    public function resolve(Authenticatable $user, Model $client, array $scopes, array $context): array;
}
