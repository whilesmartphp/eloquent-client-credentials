<?php

namespace Whilesmart\EloquentClientCredentials\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    protected function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200
    ): JsonResponse {
        $response = ['data' => $data];

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    protected function failure(string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json(['message' => $message], $statusCode);
    }
}
