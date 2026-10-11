<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$error = fn (int $status, string $code, string $message, array $extra = []) => response()->json(
    ['error' => ['code' => $code, 'message' => $message, ...$extra]],
    $status,
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: ResolveTenant::class);
        // The tenant must be known before {room} / {checkIn} are resolved.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($error): void {
        $exceptions->shouldRenderJsonWhen(fn () => true);

        $exceptions->render(fn (DomainException $e) => $error($e->status, $e->errorCode, $e->getMessage(), $e->extra));

        // ModelNotFoundException arrives here already converted to NotFoundHttpException.
        $exceptions->render(fn (NotFoundHttpException $e) => $error(404, 'not_found', 'Record not found.'));

        $exceptions->render(fn (ValidationException $e) => $error(422, 'validation_failed', $e->getMessage(), ['fields' => $e->errors()]));
    })->create();
