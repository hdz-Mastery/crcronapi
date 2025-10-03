<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relación con el usuario que creó el cliente (vendedor/agente)
            $table->uuid('created_by_user_id');
            
            // Datos de identificación
            $table->enum('tipo_identificacion', [
                'CEDULA_NACIONAL',
                'DIMEX',
                'PASAPORTE',
                'CEDULA_JURIDICA'
            ])->comment('Tipo de documento de identidad');
            $table->string('identificacion', 50)->unique()->comment('Número de documento');
            
            // Datos personales/comerciales
            $table->string('nombre', 255)->comment('Nombre completo o razón social');
            $table->string('email', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->text('direccion')->nullable();
            
            // Estado y auditoría
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->timestamps();
            $table->softDeletes(); // Para mantener histórico
            
            // Foreign keys
            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict'); // No permitir eliminar usuario si tiene clientes
            
            // Índices para búsquedas
            $table->index('created_by_user_id');
            $table->index('identificacion');
            $table->index('tipo_identificacion');
            $table->index('nombre');
            $table->index('activo');
            $table->index('fecha_ingreso');
            
            // Índice compuesto para búsquedas por vendedor
            $table->index(['created_by_user_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};