<?php

namespace Whilesmart\EloquentClientCredentials\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Whilesmart\EloquentClientCredentials\Concerns\HasConfigurableIds;

class RefreshToken extends Model
{
    use HasConfigurableIds;

    protected $table = 'client_refresh_tokens';

    protected $fillable = [
        'access_token_id',
        'token',
        'expires_at',
        'revoked',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AccessToken::class, 'access_token_id');
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
}
