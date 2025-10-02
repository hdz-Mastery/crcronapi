<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id' => Str::uuid(),
                'name' => 'ADMINISTRADOR',
                'description' => 'Administrador con acceso total al sistema',
            ],
            [
                'id' => Str::uuid(),
                'name' => 'VENDEDOR',
                'description' => 'Vendedor/Agente de pólizas con acceso limitado',
            ],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}