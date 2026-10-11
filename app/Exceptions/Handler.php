<?php

namespace App\Exceptions;

use App\Constants\APIResponseCodes;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        if ($this->isApiRequest($request)) {
            return $this->handleApiException($e);
        }

        return parent::render($request, $e);
    }

    /**
     * Determine if request is for API routes.
     */
    protected function isApiRequest(Request $request): bool
    {
        return $request->is('rooms/*', 'check-ins/*');
    }

    /**
     * Handle API exceptions and convert to standardized JSON response.
     */
    protected function handleApiException(Throwable $e)
    {
        if ($e instanceof ValidationException) {
            $this->logValidationFailure($e);
            $message = collect($e->errors())->flatten()[0];

            return $this->fail(APIResponseCodes::VALIDATION_FAILED, $e->errors(), $message);
        }

        // findOrFail() / route model binding misses arrive here as NotFoundHttpException.
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->fail(APIResponseCodes::NOT_FOUND, ['model' => $e->getMessage()]);
        }

        if ($e instanceof ConflictHttpException) {
            return $this->fail(APIResponseCodes::CONFLICT, [], $e->getMessage());
        }

        if ($e instanceof BadRequestHttpException) {
            return $this->fail(APIResponseCodes::BAD_REQUEST, [], $e->getMessage());
        }

        // Fallback for all other exceptions. Only expose a safe message in production.
        $error = app()->environment('production')
            ? ['message' => 'An unexpected error occurred']
            : ['exception' => $e::class, 'message' => $e->getMessage()];

        Log::error('internal_server_error', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => request()->url(),
            'method' => request()->method(),
        ]);

        return $this->fail(APIResponseCodes::INTERNAL_SERVER_ERROR, $error);
    }

    /**
     * Log validation failure with required context.
     */
    protected function logValidationFailure(ValidationException $e): void
    {
        Log::warning('validation_failed', [
            'errors' => $e->errors(),
            'url' => request()->fullUrl(),
        ]);
    }
}
