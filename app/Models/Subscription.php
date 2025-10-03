<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'status',
        'precio_mensual',
        'fecha_inicio',
        'fecha_proximo_pago',
        'fecha_ultimo_pago',
        'fecha_suspension',
        'fecha_cancelacion',
        'meses_pagados',
        'dias_mora',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'fecha_inicio' => 'date',
            'fecha_proximo_pago' => 'date',
            'fecha_ultimo_pago' => 'date',
            'fecha_suspension' => 'date',
            'fecha_cancelacion' => 'date',
            'meses_pagados' => 'integer',
            'dias_mora' => 'integer',
        ];
    }

    // Constantes
    public const STATUS_ACTIVA = 'ACTIVA';
    public const STATUS_SUSPENDIDA = 'SUSPENDIDA';
    public const STATUS_CANCELADA = 'CANCELADA';
    public const STATUS_PENDIENTE_PAGO = 'PENDIENTE_PAGO';

    public const PRECIO_MENSUAL = 15000.00;

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications()
    {
        return $this->hasMany(PaymentNotification::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('status', self::STATUS_ACTIVA);
    }

    public function scopeVencidas($query)
    {
        return $query->where('fecha_proximo_pago', '<', now()->toDateString())
                     ->where('status', '!=', self::STATUS_CANCELADA);
    }

    public function scopePorVencer($query, $dias = 3)
    {
        return $query->whereBetween('fecha_proximo_pago', [
            now()->toDateString(),
            now()->addDays($dias)->toDateString()
        ])->where('status', self::STATUS_ACTIVA);
    }

    // Métodos auxiliares
    public function estaActiva(): bool
    {
        return $this->status === self::STATUS_ACTIVA;
    }

    public function estaVencida(): bool
    {
        return $this->fecha_proximo_pago < now()->startOfDay();
    }

    public function calcularDiasMora(): int
    {
        if (!$this->estaVencida()) {
            return 0;
        }
        
        return now()->startOfDay()->diffInDays($this->fecha_proximo_pago);
    }

    public function actualizarDiasMora(): void
    {
        $this->update(['dias_mora' => $this->calcularDiasMora()]);
    }

    public function suspender(): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDIDA,
            'fecha_suspension' => now(),
        ]);
        
        // Desactivar el usuario
        $this->user->update(['is_active' => false]);
    }

    public function cancelar(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELADA,
            'fecha_cancelacion' => now(),
        ]);
        
        // Desactivar el usuario
        $this->user->update(['is_active' => false]);
    }

    public function reactivar(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVA,
            'fecha_suspension' => null,
            'dias_mora' => 0,
        ]);
        
        // Activar el usuario
        $this->user->update(['is_active' => true]);
    }
}