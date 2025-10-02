<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Registrar intento fallido inicialmente
        $this->recordLoginAttempt($user?->id, $request->email, $request->ip(), false);

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está inactiva. Contacta al administrador.'],
            ]);
        }

        // Intento exitoso
        $this->recordLoginAttempt($user->id, $request->email, $request->ip(), true);

        // Crear sesión
        $token = Str::random(80);
        $session = Session::create([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'token' => hash('sha256', $token),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addDays(7),
        ]);

        // Actualizar último login
        $user->update(['last_login' => now()]);

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
            'token' => $token,
            'expires_at' => $session->expires_at,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            $session = Session::where('token', hash('sha256', $token))->first();
            if ($session) {
                $session->revoke();
            }
        }

        return response()->json(['message' => 'Sesión cerrada exitosamente']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'username' => $request->user()->username,
                'email' => $request->user()->email,
                'is_active' => $request->user()->is_active,
                'roles' => $request->user()->roles->pluck('name'),
                'last_login' => $request->user()->last_login,
            ],
        ]);
    }

    public function refreshToken(Request $request)
    {
        $oldToken = $request->bearerToken();
        $oldSession = Session::where('token', hash('sha256', $oldToken))->first();

        if (!$oldSession || !$oldSession->isValid()) {
            return response()->json(['message' => 'Sesión inválida'], 401);
        }

        // Revocar sesión anterior
        $oldSession->revoke();

        // Crear nueva sesión
        $newToken = Str::random(80);
        $newSession = Session::create([
            'id' => Str::uuid(),
            'user_id' => $oldSession->user_id,
            'token' => hash('sha256', $newToken),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'message' => 'Token renovado exitosamente',
            'token' => $newToken,
            'expires_at' => $newSession->expires_at,
        ]);
    }

    public function revokeSessions(Request $request)
    {
        $user = $request->user();
        
        Session::where('user_id', $user->id)
               ->where('revoked', false)
               ->update(['revoked' => true]);

        return response()->json(['message' => 'Todas las sesiones han sido revocadas']);
    }

    private function recordLoginAttempt($userId, $email, $ip, $success)
    {
        LoginAttempt::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ip,
            'success' => $success,
            'attempted_at' => now(), // ← AGREGAR ESTO
        ]);
    }
}