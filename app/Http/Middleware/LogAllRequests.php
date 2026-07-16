<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogAllRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            Log::info('api.request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'origin' => $request->header('Origin'),
            ]);
        }

        $response = $next($request);

        if ($request->is('api/*') && $response->getStatusCode() >= 400) {
            Log::warning('api.response', [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
