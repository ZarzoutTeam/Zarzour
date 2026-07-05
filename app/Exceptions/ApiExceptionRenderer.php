<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionRenderer
{
    public function render(Throwable $e, Request $request): JsonResponse
    {
        [$message, $errors, $status] = match (true) {
            $e instanceof ValidationException => [
                __('validation.failed_message'),
                $e->errors(),
                422,
            ],
            $e instanceof AuthenticationException => [
                __('auth.failed'),
                null,
                401,
            ],
            $e instanceof AuthorizationException => [
                __('auth.forbidden'),
                null,
                403,
            ],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                __('http.not_found'),
                null,
                404,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getMessage() ?: __('http.error'),
                null,
                $e->getStatusCode(),
            ],
            default => [
                app()->hasDebugModeEnabled() ? $e->getMessage() : __('http.server_error'),
                app()->hasDebugModeEnabled() ? [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->take(10)->toArray(),
                ] : null,
                500,
            ],
        };

        return response()->json([
            'success' => false,
            'errors' => $errors,
            'message' => $message,
        ], $status);
    }
}
