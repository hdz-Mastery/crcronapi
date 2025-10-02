<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $userRoles = $user->roles->pluck('name')->toArray();
        
        if (!array_intersect($roles, $userRoles)) {
            return response()->json(['message' => 'No tienes permisos para esta acción'], 403);
        }

        return $next($request);
    }
}