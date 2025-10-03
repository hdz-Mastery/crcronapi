<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {
    }

    /**
     * Listar todas las suscripciones (solo admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['user:id,username,email,is_active']);

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por mora
        if ($request->has('con_mora') && $request->boolean('con_mora')) {
            $query->where('dias_mora', '>', 0);
        }

        // Filtro por búsqueda
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Ordenar
        $sortBy = $request->get('sort_by', 'fecha_proximo_pago');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $subscriptions = $query->paginate($perPage);

        return response()->json($subscriptions);
    }

    /**
     * Obtener suscripción del usuario autenticado
     */
    public function miSuscripcion(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription()
            ->with('payments')
            ->first();

        if (!$subscription) {
            return response()->json([
                'message' => 'No tienes una suscripción activa',
                'subscription' => null
            ], 404);
        }

        // Corregir el cálculo de días
        $diasParaVencer = now()->startOfDay()->diffInDays($subscription->fecha_proximo_pago, false);

        return response()->json([
            'subscription' => $subscription,
            'esta_vencida' => $subscription->estaVencida(),
            'dias_para_vencer' => $diasParaVencer,
        ]);
    }

    /**
     * Ver detalles de una suscripción específica
     */
    public function show(Subscription $subscription): JsonResponse
    {
        return response()->json([
            'subscription' => $subscription->load([
                'user:id,username,email',
                'payments' => function ($q) {
                    $q->orderBy('fecha_pago', 'desc');
                }
            ]),
            'estadisticas' => [
                'total_pagado' => $subscription->payments()->completados()->sum('monto'),
                'promedio_pago' => $subscription->payments()->completados()->avg('monto'),
                'ultimo_pago' => $subscription->payments()->completados()->latest('fecha_pago')->first(),
            ]
        ]);
    }

    /**
     * Registrar un pago
     */
    public function registrarPago(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|uuid|exists:subscriptions,id',
            'monto' => 'required|numeric|min:0',
            'metodo_pago' => 'required|in:TRANSFERENCIA_BANCARIA,SINPE_MOVIL,EFECTIVO,TARJETA,OTRO',
            'fecha_pago' => 'nullable|date',
            'numero_referencia' => 'nullable|string|max:100',
            'notas' => 'nullable|string',
        ]);

        try {
            $payment = $this->subscriptionService->registrarPago(
                $request->all(),
                $request->user()
            );

            return response()->json([
                'message' => 'Pago registrado exitosamente',
                'payment' => $payment,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspender suscripción
     */
    public function suspender(Subscription $subscription): JsonResponse
    {
        try {
            $this->subscriptionService->suspenderPorMora($subscription);

            return response()->json([
                'message' => 'Suscripción suspendida exitosamente',
                'subscription' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al suspender la suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reactivar suscripción
     */
    public function reactivar(Subscription $subscription): JsonResponse
    {
        try {
            $this->subscriptionService->reactivarSuscripcion($subscription);

            return response()->json([
                'message' => 'Suscripción reactivada exitosamente',
                'subscription' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al reactivar la suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar suscripción
     */
    public function cancelar(Request $request, Subscription $subscription): JsonResponse
    {
        $request->validate([
            'motivo' => 'nullable|string',
        ]);

        try {
            $this->subscriptionService->cancelarSuscripcion(
                $subscription,
                $request->motivo
            );

            return response()->json([
                'message' => 'Suscripción cancelada exitosamente',
                'subscription' => $subscription->fresh(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cancelar la suscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Historial de pagos de una suscripción
     */
    public function historialPagos(Subscription $subscription): JsonResponse
    {
        $pagos = $subscription->payments()
            ->with('registradoPor:id,username')
            ->orderBy('fecha_pago', 'desc')
            ->get();

        return response()->json([
            'pagos' => $pagos,
            'resumen' => [
                'total_pagos' => $pagos->count(),
                'monto_total' => $pagos->where('status', Payment::STATUS_COMPLETADO)->sum('monto'),
                'metodos_usados' => $pagos->groupBy('metodo_pago')->map->count(),
            ]
        ]);
    }

    /**
     * Estadísticas generales (solo admin)
     */
    public function estadisticas(): JsonResponse
    {
        $stats = $this->subscriptionService->obtenerEstadisticas();

        // Agregar gráficas de ingresos por mes (últimos 12 meses)
        $ingresosPorMes = [];
        for ($i = 11; $i >= 0; $i--) {
            $fecha = now()->subMonths($i);
            $ingresosPorMes[] = [
                'mes' => $fecha->format('M Y'),
                'ingreso' => Payment::completados()
                    ->delMes($fecha->month, $fecha->year)
                    ->sum('monto'),
                'cantidad' => Payment::completados()
                    ->delMes($fecha->month, $fecha->year)
                    ->count(),
            ];
        }

        $stats['ingresos_por_mes'] = $ingresosPorMes;

        return response()->json($stats);
    }

    /**
     * Verificar suscripciones vencidas (ejecutar manualmente o con cron)
     */
    public function verificarVencimientos(): JsonResponse
    {
        try {
            $resultados = $this->subscriptionService->verificarSuscripcionesVencidas();

            return response()->json([
                'message' => 'Verificación completada',
                'resultados' => $resultados,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al verificar vencimientos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener métodos de pago disponibles
     */
    public function metodosPago(): JsonResponse
    {
        return response()->json([
            'metodos' => Payment::getMetodosPago()
        ]);
    }
}