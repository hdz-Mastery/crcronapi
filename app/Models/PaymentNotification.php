<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentNotification extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'tipo',
        'mensaje',
        'enviado',
        'fecha_envio',
        'dias_mora',
    ];

    protected function casts(): array
    {
        return [
            'enviado' => 'boolean',
            'fecha_envio' => 'datetime',
            'dias_mora' => 'integer',
        ];
    }

    // Constantes
    public const TIPO_PROXIMO_VENCIMIENTO = 'PROXIMO_VENCIMIENTO';
    public const TIPO_VENCIMIENTO_HOY = 'VENCIMIENTO_HOY';
    public const TIPO_PAGO_VENCIDO = 'PAGO_VENCIDO';
    public const TIPO_SUSPENSION = 'SUSPENSION_CUENTA';
    public const TIPO_PAGO_RECIBIDO = 'PAGO_RECIBIDO';

    // Relaciones
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('enviado', false);
    }
}