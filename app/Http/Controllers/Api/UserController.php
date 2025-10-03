<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Listar usuarios con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles');
        
        // Filtro por búsqueda
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filtro por rol
        if ($request->has('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('name', $request->role);
            });
        }
        
        // Filtro por estado
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Ordenar
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginación
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);
        
        return response()->json($users);
    }

    /**
     * Crear nuevo usuario
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $result = $this->userService->createUser($request->validated());
            
            return response()->json([
                'message' => 'Usuario creado exitosamente. Se ha enviado un correo con las credenciales de acceso.',
                'user' => $result['user'],
                // Solo mostrar la contraseña en desarrollo para verificar
                // En producción, elimina esta línea
                'temporary_password' => config('app.debug') ? $result['temporary_password'] : '***',
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un usuario específico
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load(['roles', 'sessions' => function($q) {
                $q->where('revoked', false)
                  ->where('expires_at', '>', now())
                  ->orderBy('created_at', 'desc');
            }])
        ]);
    }

    /**
     * Actualizar usuario
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userService->updateUser($user, $request->validated());
            
            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => $updatedUser,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar/Desactivar usuario
     */
    public function toggleActive(User $user): JsonResponse
    {
        try {
            $updatedUser = $this->userService->toggleUserStatus($user);
            
            $status = $updatedUser->is_active ? 'activado' : 'desactivado';
            
            return response()->json([
                'message' => "Usuario {$status} exitosamente",
                'user' => $updatedUser,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el estado del usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar usuario
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            // Prevenir que un admin se elimine a sí mismo
            if ($user->id === auth()->id()) {
                return response()->json([
                    'message' => 'No puedes eliminar tu propia cuenta'
                ], 403);
            }
            
            $this->userService->deleteUser($user);
            
            return response()->json([
                'message' => 'Usuario eliminado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}