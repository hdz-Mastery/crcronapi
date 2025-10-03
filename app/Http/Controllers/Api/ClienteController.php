<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function __construct(
        private ClienteService $clienteService
    ) {}

    /**
     * Listar clientes con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Cliente::with('createdBy:id,username,email');
        
        // Si es vendedor, solo ver sus propios clientes
        if (!$user->isAdmin()) {
            $query->where('created_by_user_id', $user->id);
        }
        
        // Filtro por búsqueda general
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                  ->orWhere('identificacion', 'like', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }
        
        // Filtro por tipo de identificación
        if ($request->has('tipo_identificacion')) {
            $query->where('tipo_identificacion', $request->tipo_identificacion);
        }
        
        // Filtro por estado
        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }
        
        // Filtro por vendedor (solo para admins)
        if ($request->has('created_by') && $user->isAdmin()) {
            $query->where('created_by_user_id', $request->created_by);
        }
        
        // Filtro por rango de fechas
        if ($request->has('fecha_desde')) {
            $query->where('fecha_ingreso', '>=', $request->fecha_desde);
        }
        
        if ($request->has('fecha_hasta')) {
            $query->where('fecha_ingreso', '<=', $request->fecha_hasta);
        }
        
        // Ordenar
        $sortBy = $request->get('sort_by', 'fecha_ingreso');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginación
        $perPage = $request->get('per_page', 15);
        $clientes = $query->paginate($perPage);
        
        return response()->json($clientes);
    }

    /**
     * Crear nuevo cliente
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        try {
            $cliente = $this->clienteService->createCliente(
                $request->validated(),
                $request->user()->id
            );
            
            return response()->json([
                'message' => 'Cliente creado exitosamente',
                'cliente' => $cliente,
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un cliente específico
     */
    public function show(Request $request, Cliente $cliente): JsonResponse
    {
        $user = $request->user();
        
        // Verificar que el vendedor solo pueda ver sus propios clientes
        if (!$user->isAdmin() && $cliente->created_by_user_id !== $user->id) {
            return response()->json([
                'message' => 'No tienes permiso para ver este cliente'
            ], 403);
        }
        
        return response()->json([
            'cliente' => $cliente->load('createdBy:id,username,email')
        ]);
    }

    /**
     * Actualizar cliente
     */
    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar que el vendedor solo pueda actualizar sus propios clientes
            if (!$user->isAdmin() && $cliente->created_by_user_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permiso para actualizar este cliente'
                ], 403);
            }
            
            $updatedCliente = $this->clienteService->updateCliente(
                $cliente,
                $request->validated()
            );
            
            return response()->json([
                'message' => 'Cliente actualizado exitosamente',
                'cliente' => $updatedCliente,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar/Desactivar cliente
     */
    public function toggleActive(Request $request, Cliente $cliente): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Verificar permisos
            if (!$user->isAdmin() && $cliente->created_by_user_id !== $user->id) {
                return response()->json([
                    'message' => 'No tienes permiso para cambiar el estado de este cliente'
                ], 403);
            }
            
            $updatedCliente = $this->clienteService->toggleClienteStatus($cliente);
            
            $status = $updatedCliente->activo ? 'activado' : 'desactivado';
            
            return response()->json([
                'message' => "Cliente {$status} exitosamente",
                'cliente' => $updatedCliente,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el estado del cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar cliente
     */
    public function destroy(Request $request, Cliente $cliente): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Solo admins pueden eliminar clientes
            if (!$user->isAdmin()) {
                return response()->json([
                    'message' => 'No tienes permiso para eliminar clientes'
                ], 403);
            }
            
            $this->clienteService->deleteCliente($cliente);
            
            return response()->json([
                'message' => 'Cliente eliminado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el cliente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de clientes
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Cliente::query();
        
        // Si es vendedor, solo sus clientes
        if (!$user->isAdmin()) {
            $query->where('created_by_user_id', $user->id);
        }
        
        $stats = [
            'total' => (clone $query)->count(),
            'activos' => (clone $query)->where('activo', true)->count(),
            'inactivos' => (clone $query)->where('activo', false)->count(),
            'por_tipo_identificacion' => (clone $query)
                ->select('tipo_identificacion', DB::raw('count(*) as total'))
                ->groupBy('tipo_identificacion')
                ->get(),
            'recientes' => (clone $query)
                ->with('createdBy:id,username')
                ->orderBy('fecha_ingreso', 'desc')
                ->limit(5)
                ->get(),
            'este_mes' => (clone $query)
                ->whereYear('fecha_ingreso', now()->year)
                ->whereMonth('fecha_ingreso', now()->month)
                ->count(),
        ];
        
        // Si es admin, agregar stats por vendedor
        if ($user->isAdmin()) {
            $stats['por_vendedor'] = Cliente::select('created_by_user_id', DB::raw('count(*) as total'))
                ->with('createdBy:id,username')
                ->groupBy('created_by_user_id')
                ->get()
                ->map(function($item) {
                    return [
                        'vendedor' => $item->createdBy->username,
                        'total' => $item->total,
                    ];
                });
        }
        
        return response()->json($stats);
    }

    /**
     * Obtener tipos de identificación disponibles
     */
    public function tiposIdentificacion(): JsonResponse
    {
        return response()->json([
            'tipos' => Cliente::getTiposIdentificacion()
        ]);
    }
}