<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

/**
 * League calls the repositories without the request that started the grant, so
 * the user type and host claims travel to them through this request-scoped object.
 */
class GrantContext
{
    public ?string $userType = null;

    public array $claims = [];

    public ?string $authorizationCodeId = null;

    public ?string $previousAccessTokenId = null;

    public ?string $previousRefreshTokenId = null;
}
