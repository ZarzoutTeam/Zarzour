<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    protected function error(string $message = '', mixed $errors = null, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'errors' => $errors,
            'message' => $message,
        ], $status);
    }
}
