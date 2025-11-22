<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
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

        if (!$user){
            return ResponseHelper::error('Unauthorized', 401, 'User must be authenticated to access this resource.');
        }

        if ($user -> role === UserRoleEnum::ADMIN){
            return $next($request);
        }

        // Convert allowed roles to Enum values
        $allowedRoles = array_map(function ($role) {
            return UserRoleEnum::from($role);
        }, $roles);
        if (!in_array($user->role, $allowedRoles)) {
            return ResponseHelper::error('Forbidden', 403, 'You do not have permission to access this resource.');
        }

        return $next($request);

    }
}
