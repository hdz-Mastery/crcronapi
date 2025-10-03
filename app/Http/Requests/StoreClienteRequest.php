<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_identificacion' => [
                'required',
                Rule::in([
                    Cliente::TIPO_CEDULA_NACIONAL,
                    Cliente::TIPO_DIMEX,
                    Cliente::TIPO_PASAPORTE,
                    Cliente::TIPO_CEDULA_JURIDICA,
                ]),
            ],
            'identificacion' => 'required|string|max:50|unique:clientes,identificacion',
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:50',
            'direccion' => 'nullable|string',
            'activo' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_identificacion.required' => 'El tipo de identificación es obligatorio',
            'tipo_identificacion.in' => 'El tipo de identificación no es válido',
            'identificacion.required' => 'La identificación es obligatoria',
            'identificacion.unique' => 'Este número de identificación ya está registrado',
            'nombre.required' => 'El nombre es obligatorio',
            'email.email' => 'El formato del email no es válido',
        ];
    }
}