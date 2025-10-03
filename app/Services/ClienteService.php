<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClienteService
{
    /**
     * Crear un nuevo cliente
     */
    public function createCliente(array $data, string $userId): Cliente
    {
        DB::beginTransaction();
        
        try {
            $cliente = Cliente::create([
                'id' => Str::uuid(),
                'created_by_user_id' => $userId,
                'tipo_identificacion' => $data['tipo_identificacion'],
                'identificacion' => $data['identificacion'],
                'nombre' => $data['nombre'],
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'activo' => $data['activo'] ?? true,
                'fecha_ingreso' => now(),
            ]);
            
            DB::commit();
            
            return $cliente->load('createdBy');
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar cliente
     */
    public function updateCliente(Cliente $cliente, array $data): Cliente
    {
        DB::beginTransaction();
        
        try {
            $updateData = [];
            
            $allowedFields = [
                'tipo_identificacion',
                'identificacion',
                'nombre',
                'email',
                'telefono',
                'direccion',
                'activo'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (!empty($updateData)) {
                $cliente->update($updateData);
            }
            
            DB::commit();
            
            return $cliente->fresh(['createdBy']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Activar/Desactivar cliente
     */
    public function toggleClienteStatus(Cliente $cliente): Cliente
    {
        $cliente->update(['activo' => !$cliente->activo]);
        
        return $cliente->fresh();
    }

    /**
     * Eliminar cliente (soft delete)
     */
    public function deleteCliente(Cliente $cliente): bool
    {
        DB::beginTransaction();
        
        try {
            // Aquí puedes agregar lógica adicional antes de eliminar
            // Por ejemplo, verificar si tiene pólizas activas
            
            $cliente->delete();
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}