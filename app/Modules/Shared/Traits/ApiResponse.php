<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function createdResponse(mixed $data, string $message = 'Created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    protected function notFoundResponse(string $message = 'Not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    protected function errorResponse(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
