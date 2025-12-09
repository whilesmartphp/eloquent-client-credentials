<?php

namespace Whilesmart\EloquentClientCredentials\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Whilesmart\EloquentClientCredentials\Contracts\OwnerResolverInterface;

class DefaultOwnerResolver implements OwnerResolverInterface
{
    public function resolve(Request $request): ?Model
    {
        return $request->user();
    }
}
