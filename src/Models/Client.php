<?php

namespace Whilesmart\EloquentClientCredentials\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Whilesmart\EloquentClientCredentials\Traits\HasClientCredentials;

class Client extends Model
{
    use HasUuids, HasClientCredentials, Sluggable;

    protected $table = 'clients';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'secret',
        'owner_type',
        'owner_id',
        'revoked',
    ];

    protected $casts = [
        'revoked' => 'boolean',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
                'onUpdate' => true,
            ],
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function tokens(): MorphMany
    {
        return $this->morphMany(AccessToken::class, 'client');
    }

    public function scopeOwnedBy($query, $owner)
    {
        return $query->where('owner_type', get_class($owner))
            ->where('owner_id', $owner->id);
    }

    public function scopeActive($query)
    {
        return $query->where('revoked', false);
    }
}
