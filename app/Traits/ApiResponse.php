<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     *
     * @param  string  $message
     * @param  mixed  $data
     * @param  int  $statusCode
     * @param  array<string, mixed>  $meta
     * @return JsonResponse
     */
    protected function ok(
        string $message = 'Success',
        mixed $data = null,
        int $statusCode = Response::HTTP_OK,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a standardized 201 Created JSON response.
     *
     * @param  string  $message
     * @param  mixed  $data
     * @return JsonResponse
     */
    protected function created(
        string $message = 'Created successfully',
        mixed $data = null
    ): JsonResponse {
        return $this->ok($message, $data, Response::HTTP_CREATED);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @param  mixed  $errors
     * @return JsonResponse
     */
    protected function error(
        string $message = 'Error',
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
