<?php

namespace App\Http\Middleware;

use App\Support\RoleMapper;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$allowed): Response
    {
        $user = $request->user();
        if (! $user || ! $user->employee || ! $user->employee->role) {
            return new JsonResponse(['message' => 'Accès refusé.'], 403);
        }

        $roleKey = RoleMapper::toFrontendKey($user->employee->role->name);
        if (! in_array($roleKey, $allowed, true)) {
            return new JsonResponse(['message' => 'Permission insuffisante.'], 403);
        }

        return $next($request);
    }
}
