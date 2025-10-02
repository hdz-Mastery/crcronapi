<?php

namespace App\Http\Middleware;

use App\Models\Session;
use Closure;
use Illuminate\Http\Request;

class ValidateSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token no proporcionado'], 401);
        }

        $session = Session::where('token', hash('sha256', $token))
                          ->with('user')
                          ->first();

        if (!$session || !$session->isValid()) {
            return response()->json(['message' => 'Sesión inválida o expirada'], 401);
        }

        if (!$session->user->is_active) {
            return response()->json(['message' => 'Usuario inactivo'], 403);
        }

        $request->setUserResolver(fn() => $session->user);

        return $next($request);
    }
}