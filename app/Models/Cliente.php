<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'created_by_user_id',
        'tipo_identificacion',
        'identificacion',
        'nombre',
        'email',
        'telefono',
        'direccion',
        'activo',
        'fecha_ingreso',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_ingreso' => 'datetime',
        ];
    }

    // Constantes para tipos de identificación
    public const TIPO_CEDULA_NACIONAL = 'CEDULA_NACIONAL';
    public const TIPO_DIMEX = 'DIMEX';
    public const TIPO_PASAPORTE = 'PASAPORTE';
    public const TIPO_CEDULA_JURIDICA = 'CEDULA_JURIDICA';

    public static function getTiposIdentificacion(): array
    {
        return [
            self::TIPO_CEDULA_NACIONAL => 'Cédula Nacional',
            self::TIPO_DIMEX => 'DIMEX (Residentes Extranjeros)',
            self::TIPO_PASAPORTE => 'Pasaporte',
            self::TIPO_CEDULA_JURIDICA => 'Cédula Jurídica',
        ];
    }

    /**
     * Relación: Cliente pertenece a un usuario (vendedor/agente)
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope: Clientes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Clientes de un usuario específico
     */
    public function scopeDeUsuario($query, $userId)
    {
        return $query->where('created_by_user_id', $userId);
    }

    /**
     * Scope: Buscar por identificación
     */
    public function scopeBuscarPorIdentificacion($query, $identificacion)
    {
        return $query->where('identificacion', 'like', "%{$identificacion}%");
    }

    /**
     * Scope: Buscar por nombre
     */
    public function scopeBuscarPorNombre($query, $nombre)
    {
        return $query->where('nombre', 'ilike', "%{$nombre}%");
    }

    /**
     * Accessor: Obtener nombre del tipo de identificación
     */
    public function getTipoIdentificacionNombreAttribute(): string
    {
        return self::getTiposIdentificacion()[$this->tipo_identificacion] ?? $this->tipo_identificacion;
    }
}