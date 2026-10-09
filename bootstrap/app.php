<?php

use App\Http\Middleware\AddSecurityHeaders;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global, so that responses rejected early (401, 429) carry the headers too.
        $middleware->append(AddSecurityHeaders::class);

        // API-only service: there is no login page to send guests to.
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $isApi($request) || $request->expectsJson(),
        );

        // Avoid leaking model class names and ids through "No query results" messages.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? response()->json(['message' => 'Resource not found.'], 404)
                : null;
        });

        // Two writers racing past validation still end up at the unique index.
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) use ($isApi) {
            return $isApi($request)
                ? response()->json(['message' => 'The resource already exists.'], 409)
                : null;
        });
    })->create();
