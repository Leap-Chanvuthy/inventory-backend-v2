<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissionKeys): Response
    {
        $user = auth()->user();

        if (!$user) {
            return ResponseHelper::error('Unauthorized', 401, 'User must be authenticated to access this resource.');
        }

        $keys = array_values(array_filter(array_map('strval', $permissionKeys)));
        if ($keys === []) {
            return ResponseHelper::error('Forbidden', 403, 'No permission key was provided for this route.');
        }

        if (!$user->hasAnyPermission($keys)) {
            return ResponseHelper::error('Forbidden', 403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}

