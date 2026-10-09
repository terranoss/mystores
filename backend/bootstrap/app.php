<?php

// Polyfill mb_split if the mbstring extension is not loaded.
if (!function_exists('mb_split')) {
    function mb_split($pattern, $string, $limit = -1) {
        return preg_split('/' . $pattern . '/u', $string, $limit);
    }
}

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(HandleCors::class);
        $middleware->append(\App\Http\Middleware\CheckInstallation::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\StoreNotActiveException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        });

        $exceptions->render(function (\App\Exceptions\ProductAuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode());
        });
    })->create();
