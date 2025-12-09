<?php

namespace Whilesmart\EloquentClientCredentials\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface OwnerResolverInterface
{
    public function resolve(Request $request): ?Model;
}
