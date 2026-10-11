<?php

namespace App\Traits;

use App\Constants\APIResponseCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

/**
 * Trait ApiResponse
 *
 * Provides a standardized JSON response structure for API endpoints.
 */
trait ApiResponse
{
    /**
     * Return a success JSON response.
     */
    protected function success(string $successCode = APIResponseCodes::SUCCESS, mixed $data = null, ?string $message = null): JsonResponse
    {
        $success = Config::get('api-response-codes.'.$successCode);
        $httpCode = $success['http_code'];

        return response()->json([
            'success' => true,
            'code' => $successCode,
            'data' => $data,
            'title' => $success['title'],
            'message' => $message ?? $success['message'],
            'icon' => $success['icon'],
            'status_code' => $httpCode,
        ], $httpCode);
    }

    /**
     * Return a failure JSON response.
     */
    protected function fail(string $errorCode = APIResponseCodes::INTERNAL_SERVER_ERROR, mixed $errors = null, ?string $message = null): JsonResponse
    {
        $error = Config::get('api-response-codes.'.$errorCode);
        $httpCode = $error['http_code'];

        return response()->json([
            'success' => false,
            'code' => $errorCode,
            'title' => $error['title'],
            'message' => $message ?? $error['message'],
            'errors' => $errors,
            'icon' => $error['icon'],
            'status_code' => $httpCode,
        ], $httpCode);
    }
}
