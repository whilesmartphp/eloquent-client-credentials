<?php

namespace Whilesmart\EloquentClientCredentials\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait HasClientCredentials
{
    protected ?string $tempPlainSecret = null;

    public static function bootHasClientCredentials(): void
    {
        static::creating(function ($model) {
            if (empty($model->secret)) {
                $model->setSecret(Str::random(40));
            }
        });
    }

    public function initializeHasClientCredentials(): void
    {
        $this->hidden = array_merge($this->hidden ?? [], ['secret']);
    }

    public function setSecret(string $plainSecret): static
    {
        $this->attributes['secret'] = Hash::make($plainSecret);
        $this->tempPlainSecret = $plainSecret;

        return $this;
    }

    public function getPlainSecretAttribute(): ?string
    {
        return $this->tempPlainSecret;
    }

    public function regenerateSecret(): string
    {
        $plainSecret = Str::random(40);
        $this->setSecret($plainSecret);
        $this->save();

        return $plainSecret;
    }

    public function verifySecret(string $secret): bool
    {
        return Hash::check($secret, $this->secret);
    }

    public function getClientId(): string
    {
        return $this->getKey();
    }
}
