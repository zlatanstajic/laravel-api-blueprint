<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Base Controller
 */
abstract class Controller
{
    /**
     * Send a success response.
     */
    protected function successResponse(
        mixed $data = [],
        string $message = '',
        int $code = Response::HTTP_OK
    ): JsonResponse {
        if (empty($message)) {
            $message = __('messages.default.success');
        }

        return response()->json([
            'data' => $data,
        ], $code);
    }

    /**
     * Keep unexpected failure details out of API responses.
     */
    protected function serverErrorResponse(Throwable $exception): JsonResponse
    {
        Log::error('Unexpected API failure.', [
            'exception_type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        return $this->errorResponse(__('messages.error.internal_server_error'));
    }

    /**
     * Send an error response.
     */
    protected function errorResponse(
        string $message,
        int $code = Response::HTTP_INTERNAL_SERVER_ERROR
    ): JsonResponse {
        return response()->json([
            'error' => $message,
        ], $code);
    }
}
