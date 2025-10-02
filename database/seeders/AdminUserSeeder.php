<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'ADMINISTRADOR')->first();
        
        $admin = User::create([
            'username' => 'alonsohdz67',
            'email' => 'alonsohdz67@gmail.com',
            'password' => Hash::make('Cangrejo10.'),
            'is_active' => true,
        ]);
        
        $admin->roles()->attach($adminRole->id);
    }
}