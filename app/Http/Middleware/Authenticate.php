<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * API-first app: never redirect unauthenticated users to a web "login" route.
     * Returning null forces Laravel to respond with 401 for API requests instead
     * of trying route('login') (which is not defined for this project).
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}

