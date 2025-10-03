<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentNotification;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * Crear suscripción para un nuevo usuario
     */
    public function createSubscription(User $user): Subscription
    {
        DB::beginTransaction();

        try {
            $subscription = Subscription::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'status' => Subscription::STATUS_PENDIENTE_PAGO,
                'precio_mensual' => Subscription::PRECIO_MENSUAL,
                'fecha_inicio' => now()->toDateString(),
                'fecha_proximo_pago' => now()->addMonth()->toDateString(),
                'meses_pagados' => 0,
                'dias_mora' => 0,
            ]);

            // Desactivar usuario hasta que pague
            $user->update(['is_active' => false]);

            DB::commit();

            return $subscription;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Registrar un pago y actualizar la suscripción
     */
    public function registrarPago(array $data, User $registradoPor): Payment
    {
        DB::beginTransaction();

        try {
            $subscription = Subscription::findOrFail($data['subscription_id']);

            // Calcular período que cubre el pago
            $periodoInicio = $subscription->fecha_proximo_pago ?? now();
            $periodoFin = Carbon::parse($periodoInicio)->addMonth();

            // Crear el pago
            $payment = Payment::create([
                'id' => Str::uuid(),
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'monto' => $data['monto'],
                'metodo_pago' => $data['metodo_pago'],
                'status' => Payment::STATUS_COMPLETADO,
                'fecha_pago' => $data['fecha_pago'] ?? now()->toDateString(),
                'periodo_inicio' => $periodoInicio,
                'periodo_fin' => $periodoFin,
                'numero_referencia' => $data['numero_referencia'] ?? null,
                'notas' => $data['notas'] ?? null,
                'registrado_por' => $registradoPor->id,
            ]);

            // Actualizar la suscripción
            $subscription->update([
                'status' => Subscription::STATUS_ACTIVA,
                'fecha_ultimo_pago' => $payment->fecha_pago,
                'fecha_proximo_pago' => $periodoFin,
                'meses_pagados' => $subscription->meses_pagados + 1,
                'dias_mora' => 0,
                'fecha_suspension' => null,
            ]);

            // Activar el usuario
            $subscription->user->update(['is_active' => true]);

            // Crear notificación de pago recibido
            $montoFormateado = number_format((float) $payment->monto, 2);
            $fechaFormateada = Carbon::parse($periodoFin)->format('d/m/Y');

            $this->crearNotificacion(
                $subscription,
                PaymentNotification::TIPO_PAGO_RECIBIDO,
                "Pago recibido por ₡{$montoFormateado}. Tu suscripción está activa hasta {$fechaFormateada}"
            );

            DB::commit();

            return $payment->load(['subscription', 'user', 'registradoPor']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Suspender suscripción por falta de pago
     */
    public function suspenderPorMora(Subscription $subscription): void
    {
        DB::beginTransaction();

        try {
            $subscription->suspender();

            $this->crearNotificacion(
                $subscription,
                PaymentNotification::TIPO_SUSPENSION,
                "Tu cuenta ha sido suspendida por falta de pago. Tienes " . $subscription->dias_mora . " días de mora."
            );

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancelar suscripción
     */
    public function cancelarSuscripcion(Subscription $subscription, string $motivo = null): void
    {
        DB::beginTransaction();

        try {
            $subscription->update([
                'notas' => $motivo ? "Cancelada: {$motivo}" : $subscription->notas,
            ]);

            $subscription->cancelar();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reactivar suscripción suspendida (después de pagar)
     */
    public function reactivarSuscripcion(Subscription $subscription): void
    {
        $subscription->reactivar();
    }

    /**
     * Verificar suscripciones vencidas y actualizar estados
     */
    public function verificarSuscripcionesVencidas(): array
    {
        $resultados = [
            'vencidas_hoy' => 0,
            'suspendidas' => 0,
            'notificaciones_enviadas' => 0,
        ];

        // Suscripciones que vencen hoy
        $vencidasHoy = Subscription::activas()
            ->whereDate('fecha_proximo_pago', now()->toDateString())
            ->get();

        foreach ($vencidasHoy as $subscription) {
            $this->crearNotificacion(
                $subscription,
                PaymentNotification::TIPO_VENCIMIENTO_HOY,
                "Tu pago vence hoy. Por favor realiza tu pago de ₡15,000 para mantener tu cuenta activa."
            );
            $resultados['vencidas_hoy']++;
        }

        // Suscripciones vencidas (actualizar días de mora)
        $vencidas = Subscription::vencidas()->get();

        foreach ($vencidas as $subscription) {
            $diasMora = $subscription->calcularDiasMora();
            $subscription->update(['dias_mora' => $diasMora]);

            // Suspender después de 7 días de mora
            if ($diasMora >= 7 && $subscription->status !== Subscription::STATUS_SUSPENDIDA) {
                $this->suspenderPorMora($subscription);
                $resultados['suspendidas']++;
            }
            // Notificar si tiene mora
            elseif ($diasMora > 0 && $diasMora < 7) {
                $this->crearNotificacion(
                    $subscription,
                    PaymentNotification::TIPO_PAGO_VENCIDO,
                    "Tu pago está vencido. Llevas {$diasMora} días de mora. Por favor paga antes de que tu cuenta sea suspendida."
                );
                $resultados['notificaciones_enviadas']++;
            }
        }

        // Suscripciones próximas a vencer (3 días antes)
        $porVencer = Subscription::porVencer(3)->get();

        foreach ($porVencer as $subscription) {
            $diasRestantes = now()->startOfDay()->diffInDays($subscription->fecha_proximo_pago);
            $fechaVencimiento = Carbon::parse($subscription->fecha_proximo_pago)->format('d/m/Y');

            $this->crearNotificacion(
                $subscription,
                PaymentNotification::TIPO_PROXIMO_VENCIMIENTO,
                "Tu pago vence en {$diasRestantes} días (el {$fechaVencimiento}). Por favor realiza tu pago de ₡15,000."
            );
            $resultados['notificaciones_enviadas']++;
        }

        return $resultados;
    }

    /**
     * Crear notificación de pago
     */
    private function crearNotificacion(Subscription $subscription, string $tipo, string $mensaje): void
    {
        PaymentNotification::create([
            'id' => Str::uuid(),
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'dias_mora' => $subscription->dias_mora,
            'enviado' => false,
        ]);
    }

    /**
     * Obtener estadísticas de suscripciones
     */
    public function obtenerEstadisticas(): array
    {
        return [
            'total_suscripciones' => Subscription::count(),
            'activas' => Subscription::where('status', Subscription::STATUS_ACTIVA)->count(),
            'suspendidas' => Subscription::where('status', Subscription::STATUS_SUSPENDIDA)->count(),
            'canceladas' => Subscription::where('status', Subscription::STATUS_CANCELADA)->count(),
            'pendientes_pago' => Subscription::where('status', Subscription::STATUS_PENDIENTE_PAGO)->count(),
            'vencidas' => Subscription::vencidas()->count(),
            'ingreso_mes_actual' => Payment::completados()
                ->delMes()
                ->sum('monto'),
            'pagos_este_mes' => Payment::completados()
                ->delMes()
                ->count(),
            'total_recaudado' => Payment::completados()->sum('monto'),
        ];
    }
}