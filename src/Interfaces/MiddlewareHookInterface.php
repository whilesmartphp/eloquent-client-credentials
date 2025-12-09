<?php

namespace Whilesmart\EloquentClientCredentials\Interfaces;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface MiddlewareHookInterface
{
    public function before(Request $request, string $action): ?Request;

    public function after(Request $request, JsonResponse $response, string $action): JsonResponse;
}
