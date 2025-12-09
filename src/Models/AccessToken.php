<?php

namespace Whilesmart\EloquentClientCredentials\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccessToken extends Model
{
    use HasUuids;

    protected $table = 'client_access_tokens';

    protected $fillable = [
        'client_type',
        'client_id',
        'token',
        'scopes',
        'expires_at',
        'revoked',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->revoked && ! $this->isExpired();
    }

    public function revoke(): bool
    {
        return $this->update(['revoked' => true]);
    }

    public function scopeValid($query)
    {
        return $query->where('revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
