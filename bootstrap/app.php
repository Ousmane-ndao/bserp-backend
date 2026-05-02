<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(HandleCors::class);
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'Unauthenticated.'], 401);
            }

            return null;
        });

        // Safety net: ensure API error responses still carry CORS headers.
        $exceptions->respond(function ($response) {
            $request = request();
            if (! $request || ! $request->is('api/*')) {
                return $response;
            }

            $origin = (string) $request->headers->get('Origin', '');
            $allowedOrigins = (array) config('cors.allowed_origins', []);
            $isAllowedOrigin = in_array($origin, $allowedOrigins, true);

            if ($origin !== '' && $isAllowedOrigin && ! $response->headers->has('Access-Control-Allow-Origin')) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin', false);
            }

            return $response;
        });
    })->create();
