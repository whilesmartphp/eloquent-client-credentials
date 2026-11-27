<?php

namespace Whilesmart\EloquentClientCredentials\Models;

use Illuminate\Database\Eloquent\Model;

class AuthorizationCode extends Model
{
    protected $table = 'client_authorization_codes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'client_id',
        'user_type',
        'user_id',
        'scopes',
        'claims',
        'revoked',
        'expires_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'claims' => 'array',
        'revoked' => 'boolean',
        'expires_at' => 'datetime',
    ];
}
