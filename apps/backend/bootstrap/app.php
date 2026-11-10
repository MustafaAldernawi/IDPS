<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $error, Request $request) {
            if ($request->is('api/*')) {
                $conflict = in_array((string) $error->getCode(), ['23000', '23505'], true);

                return response()->json(['error' => ['code' => $conflict ? 'CONFLICT' : 'DATABASE_UNAVAILABLE', 'message' => $conflict ? 'Concurrent change or conflicting record' : 'Database operation unavailable', 'request_id' => (string) Str::uuid()]], $conflict ? 409 : 503);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
