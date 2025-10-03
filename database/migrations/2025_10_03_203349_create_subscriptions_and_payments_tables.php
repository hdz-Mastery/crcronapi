<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de suscripciones
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique(); // Un usuario solo puede tener una suscripción activa
            
            // Información de la suscripción
            $table->enum('status', [
                'ACTIVA',
                'SUSPENDIDA',
                'CANCELADA',
                'PENDIENTE_PAGO'
            ])->default('PENDIENTE_PAGO');
            
            $table->decimal('precio_mensual', 10, 2)->default(15000.00); // ₡15,000
            
            // Fechas importantes
            $table->date('fecha_inicio'); // Cuándo inició la suscripción
            $table->date('fecha_proximo_pago'); // Cuándo debe pagar el siguiente mes
            $table->date('fecha_ultimo_pago')->nullable(); // Última vez que pagó
            $table->date('fecha_suspension')->nullable(); // Si se suspendió, cuándo fue
            $table->date('fecha_cancelacion')->nullable(); // Si se canceló, cuándo fue
            
            // Estadísticas
            $table->integer('meses_pagados')->default(0); // Total de meses que ha pagado
            $table->integer('dias_mora')->default(0); // Días de atraso en el pago
            
            // Notas administrativas
            $table->text('notas')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            // Índices
            $table->index('status');
            $table->index('fecha_proximo_pago');
            $table->index('fecha_inicio');
        });

        // 2. Tabla de pagos (historial)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->uuid('user_id'); // Redundante pero útil para queries
            
            // Información del pago
            $table->decimal('monto', 10, 2);
            $table->enum('metodo_pago', [
                'TRANSFERENCIA_BANCARIA',
                'SINPE_MOVIL',
                'EFECTIVO',
                'TARJETA',
                'OTRO'
            ]);
            
            $table->enum('status', [
                'COMPLETADO',
                'PENDIENTE',
                'RECHAZADO'
            ])->default('COMPLETADO');
            
            // Fechas
            $table->date('fecha_pago'); // Fecha en que se registró el pago
            $table->date('periodo_inicio'); // Período que cubre: inicio
            $table->date('periodo_fin'); // Período que cubre: fin
            
            // Detalles adicionales
            $table->string('numero_referencia', 100)->nullable(); // Número de transacción
            $table->text('notas')->nullable();
            
            // Auditoría
            $table->uuid('registrado_por')->nullable(); // Qué admin registró el pago
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('subscription_id')
                  ->references('id')
                  ->on('subscriptions')
                  ->onDelete('cascade');
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->foreign('registrado_por')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            
            // Índices
            $table->index('subscription_id');
            $table->index('user_id');
            $table->index('fecha_pago');
            $table->index('status');
            $table->index(['periodo_inicio', 'periodo_fin']);
        });

        // 3. Tabla de notificaciones de pago
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->uuid('user_id');
            
            $table->enum('tipo', [
                'PROXIMO_VENCIMIENTO', // 3 días antes
                'VENCIMIENTO_HOY',
                'PAGO_VENCIDO',
                'SUSPENSION_CUENTA',
                'PAGO_RECIBIDO'
            ]);
            
            $table->text('mensaje');
            $table->boolean('enviado')->default(false);
            $table->timestamp('fecha_envio')->nullable();
            $table->integer('dias_mora')->default(0);
            
            $table->timestamps();
            
            $table->foreign('subscription_id')
                  ->references('id')
                  ->on('subscriptions')
                  ->onDelete('cascade');
            
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->index(['subscription_id', 'tipo']);
            $table->index('enviado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
    }
};