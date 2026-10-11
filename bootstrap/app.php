<?php

use App\Exceptions\Handler;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every exception goes through App\Exceptions\Handler, which returns the fail() shape for API routes.
        $exceptions->render(function (Throwable $e, $request) {
            $handler = new Handler(app());

            return $handler->render($request, $e);
        });
    })->create();
