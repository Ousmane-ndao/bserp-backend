<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->use([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Garde les en-têtes CORS sur les réponses API même en cas d’erreur (évite « blocked by CORS »).
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return $response;
            }
            $origin = $request->headers->get('Origin');
            if (!$origin) {
                return $response;
            }
            $allowed = array_filter(array_map('trim', explode(',', (string) env(
                'CORS_ALLOWED_ORIGINS',
                'https://bserp.vercel.app,http://localhost:8080,http://127.0.0.1:8080,http://localhost:5173',
            ))));
            $ok = in_array($origin, $allowed, true)
                || (bool) preg_match('#^https://[a-z0-9-]+\.vercel\.app$#', $origin);
            if ($ok) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Vary', 'Origin');
            }

            return $response;
        });
    })->create();
