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
        if (empty($this->secret)) {
            return false;
        }

        return Hash::check($secret, $this->secret);
    }

    public function allowsGrant(string $grantType): bool
    {
        return in_array($grantType, (array) ($this->getAttribute('grant_types') ?? []), true);
    }

    /**
     * Clients created before grant types existed have none recorded and keep
     * working; a client registered for other grants cannot use these credentials.
     */
    public function allowsClientCredentials(): bool
    {
        return $this->getAttribute('grant_types') === null || $this->allowsGrant('client_credentials');
    }

    public function getClientId(): string
    {
        return $this->getKey();
    }
}
