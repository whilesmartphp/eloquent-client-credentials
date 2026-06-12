<?php

namespace Whilesmart\EloquentClientCredentials\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a primary key whose type follows the `client-credentials.uuids`
 * config: a generated UUID string when enabled, or a normal auto-incrementing
 * integer when disabled. Replaces a hard dependency on Laravel's HasUuids so
 * the package can match an application that uses integer keys.
 */
trait HasConfigurableIds
{
    public static function bootHasConfigurableIds(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();

            if (static::usesUuids() && empty($model->{$key})) {
                $model->{$key} = (string) Str::orderedUuid();
            }
        });
    }

    public static function usesUuids(): bool
    {
        return (bool) config('client-credentials.uuids', true);
    }

    public function getIncrementing(): bool
    {
        return ! static::usesUuids();
    }

    public function getKeyType(): string
    {
        return static::usesUuids() ? 'string' : 'int';
    }
}
