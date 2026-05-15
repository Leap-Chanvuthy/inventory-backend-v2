<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = auth()->user();

        if (!$user) {
            return ResponseHelper::error('Unauthorized', 401, 'User must be authenticated to access this resource.');
        }

        $roleKey = strtoupper((string) ($user->role?->key ?? ''));
        if ($roleKey === 'ADMIN') {
            return $next($request);
        }

        $allowedRoles = array_map(fn ($role) => strtoupper((string) $role), $roles);
        if (!in_array($roleKey, $allowedRoles, true)) {
            return ResponseHelper::error('Forbidden', 403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
