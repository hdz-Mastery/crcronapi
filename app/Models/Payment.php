<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'monto',
        'metodo_pago',
        'status',
        'fecha_pago',
        'periodo_inicio',
        'periodo_fin',
        'numero_referencia',
        'notas',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
            'periodo_inicio' => 'date',
            'periodo_fin' => 'date',
        ];
    }

    // Constantes
    public const STATUS_COMPLETADO = 'COMPLETADO';
    public const STATUS_PENDIENTE = 'PENDIENTE';
    public const STATUS_RECHAZADO = 'RECHAZADO';

    public const METODO_TRANSFERENCIA = 'TRANSFERENCIA_BANCARIA';
    public const METODO_SINPE = 'SINPE_MOVIL';
    public const METODO_EFECTIVO = 'EFECTIVO';
    public const METODO_TARJETA = 'TARJETA';
    public const METODO_OTRO = 'OTRO';

    // Relaciones
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    // Scopes
    public function scopeCompletados($query)
    {
        return $query->where('status', self::STATUS_COMPLETADO);
    }

    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        
        return $query->whereYear('fecha_pago', $anio)
                     ->whereMonth('fecha_pago', $mes);
    }

    // Métodos estáticos
    public static function getMetodosPago(): array
    {
        return [
            self::METODO_TRANSFERENCIA => 'Transferencia Bancaria',
            self::METODO_SINPE => 'SINPE Móvil',
            self::METODO_EFECTIVO => 'Efectivo',
            self::METODO_TARJETA => 'Tarjeta',
            self::METODO_OTRO => 'Otro',
        ];
    }
}