<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            $this->applyCorsHeaders($request, $response);

            return $response;
        }

        $response = $next($request);
        $this->applyCorsHeaders($request, $response);

        return $response;
    }

    private function applyCorsHeaders(Request $request, Response $response): void
    {
        $origin = $this->allowedOrigin($request);
        if ($origin === null) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    private function allowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (! is_string($origin) || $origin === '') {
            return null;
        }

        $allowed = config('cors.allowed_origins', []);
        if (in_array($origin, $allowed, true)) {
            return $origin;
        }

        foreach (config('cors.allowed_origins_patterns', []) as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $origin)) {
                return $origin;
            }
        }

        return null;
    }
}
