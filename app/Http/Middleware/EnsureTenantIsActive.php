<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->role === 'platform_admin') {
            return $next($request);
        }

       $tenant = $user->tenant()->first();

        if (! $tenant || $tenant->status !== 'active') {
            return response()->json([
                'message' => 'Your company is not active.',
            ], 403);
        }

        return $next($request);
    }
}
