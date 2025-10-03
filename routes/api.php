<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\ValidateSession;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware(ValidateSession::class)->group(function () {
    // Autenticación
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/revoke-sessions', [AuthController::class, 'revokeSessions']);

    // Gestión de usuarios (solo ADMINISTRADOR)
    Route::middleware([CheckRole::class . ':ADMINISTRADOR'])->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::patch('/{user}/toggle-active', [UserController::class, 'toggleActive']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });

    // Gestión de clientes (ADMINISTRADOR y VENDEDOR)
    Route::prefix('clientes')->group(function () {
        Route::get('/', [ClienteController::class, 'index']);
        Route::post('/', [ClienteController::class, 'store']);
        Route::get('/stats', [ClienteController::class, 'stats']);
        Route::get('/tipos-identificacion', [ClienteController::class, 'tiposIdentificacion']);
        Route::get('/{cliente}', [ClienteController::class, 'show']);
        Route::put('/{cliente}', [ClienteController::class, 'update']);
        Route::patch('/{cliente}/toggle-active', [ClienteController::class, 'toggleActive']);

        // Solo admins pueden eliminar
        Route::delete('/{cliente}', [ClienteController::class, 'destroy'])
            ->middleware(CheckRole::class . ':ADMINISTRADOR');
    });
});