<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente')->id;

        return [
            'tipo_identificacion' => [
                'sometimes',
                Rule::in([
                    Cliente::TIPO_CEDULA_NACIONAL,
                    Cliente::TIPO_DIMEX,
                    Cliente::TIPO_PASAPORTE,
                    Cliente::TIPO_CEDULA_JURIDICA,
                ]),
            ],
            'identificacion' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('clientes', 'identificacion')->ignore($clienteId),
            ],
            'nombre' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:50',
            'direccion' => 'nullable|string',
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_identificacion.in' => 'El tipo de identificación no es válido',
            'identificacion.unique' => 'Este número de identificación ya está registrado',
            'email.email' => 'El formato del email no es válido',
        ];
    }
}