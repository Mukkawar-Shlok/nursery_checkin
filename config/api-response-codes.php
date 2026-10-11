<?php

use App\Constants\APIResponseCodes;

return [
    // Success Codes Responses
    APIResponseCodes::SUCCESS => [
        'title' => 'Success',
        'message' => 'The request was successful.',
        'http_code' => 200,
        'icon' => 'check-circle-outline',
    ],
    APIResponseCodes::CREATED => [
        'title' => 'Created',
        'message' => 'The resource was created successfully.',
        'http_code' => 201,
        'icon' => 'check-circle-outline',
    ],

    // General Error Codes Responses
    APIResponseCodes::BAD_REQUEST => [
        'title' => 'Bad Request',
        'message' => 'The request was invalid. Please check the request and try again.',
        'http_code' => 400,
        'icon' => 'error',
    ],
    APIResponseCodes::NOT_FOUND => [
        'title' => 'Not Found',
        'message' => 'The requested resource was not found. Please check the URL and try again.',
        'http_code' => 404,
        'icon' => 'error',
    ],
    APIResponseCodes::CONFLICT => [
        'title' => 'Conflict',
        'message' => 'The request conflicts with the current state of the resource.',
        'http_code' => 409,
        'icon' => 'error',
    ],
    APIResponseCodes::INTERNAL_SERVER_ERROR => [
        'title' => 'Internal Server Error',
        'message' => 'Something went wrong. Please try again later.',
        'http_code' => 500,
        'icon' => 'error',
    ],

    // API Error Codes Responses
    APIResponseCodes::VALIDATION_FAILED => [
        'title' => 'Validation Failed',
        'message' => 'The given data was invalid.',
        'http_code' => 422,
        'icon' => 'error',
    ],
];
