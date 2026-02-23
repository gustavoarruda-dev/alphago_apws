<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
trait HandlesApiResponses
{
    protected function success(mixed $data, string $message, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    protected function error(string $message, mixed $data = null, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
}
