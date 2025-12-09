<?php

namespace Whilesmart\EloquentClientCredentials\Enums;

enum HookAction: string
{
    case CLIENT_STORE = 'clientStore';
    case CLIENT_UPDATE = 'clientUpdate';
    case CLIENT_DELETE = 'clientDelete';
    case CLIENT_REGENERATE_SECRET = 'clientRegenerateSecret';
    case TOKEN_ISSUE = 'tokenIssue';
    case TOKEN_REVOKE = 'tokenRevoke';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }
}
