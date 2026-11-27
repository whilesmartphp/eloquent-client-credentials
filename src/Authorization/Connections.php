<?php

namespace Whilesmart\EloquentClientCredentials\Authorization;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Disconnecting an app must also stop codes the user approved but the app has
 * not redeemed, and those cannot be looked up by user and client in the cache.
 * Each disconnect advances a counter instead, and a code only works while the
 * counter still has the value it had when the code was issued.
 */
class Connections
{
    public static function generation(string $clientId, ?string $subjectType, ?string $subjectId): int
    {
        return (int) Cache::get(static::key($clientId, $subjectType, $subjectId), 0);
    }

    /**
     * The counter only has to outlive the codes issued before it advanced. Once
     * it expires it reads as 0 again, which no code still alive can carry, since
     * any code issued under 0 expired before the counter did.
     */
    public static function advance(string $clientId, string $subjectType, string $subjectId): void
    {
        $key = static::key($clientId, $subjectType, $subjectId);

        Cache::add($key, 0, (int) config('client-credentials.authorization_code.code_lifetime', 600) * 2);
        Cache::increment($key);
    }

    /**
     * Disconnecting and issuing tokens for a client run one at a time, so a token
     * issued during a disconnect is either revoked by it or refused after it.
     */
    public static function exclusively(string $clientId, Closure $callback): mixed
    {
        return Cache::lock('client-credentials:client-lock:'.sha1($clientId), 10)->block((int) config('client-credentials.authorization_code.lock_wait', 5), $callback);
    }

    protected static function key(string $clientId, ?string $subjectType, ?string $subjectId): string
    {
        return 'client-credentials:connection-generation:'.sha1(json_encode([$clientId, $subjectType, $subjectId]));
    }
}
