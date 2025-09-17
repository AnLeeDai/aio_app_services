<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a successful JSON response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function success($data = null, string $message = 'Request successful', int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'status' => 'success',
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return JsonResponse
     */
    protected function error(string $message = 'Request failed', int $statusCode = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error JSON response
     *
     * @param string $message
     * @param mixed $errors
     * @return JsonResponse
     */
    protected function validationError(string $message = 'Validation failed', $errors = null): JsonResponse
    {
        return $this->error($message, 422, $errors);
    }

    /**
     * Return a not found error JSON response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Return an unauthorized error JSON response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Return a forbidden error JSON response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Return a server error JSON response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return $this->error($message, 500);
    }
}