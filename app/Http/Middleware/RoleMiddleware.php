<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: middleware('role:admin') or middleware('role:admin,staff')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is suspended'], 403);
        }

        if (! empty($roles) && ! in_array($user->role, $roles)) {
            return response()->json([
                'message'  => 'Access denied. Insufficient permissions.',
                'required' => $roles,
                'yours'    => $user->role,
            ], 403);
        }

        return $next($request);
    }
}