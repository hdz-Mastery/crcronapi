<?php

namespace App\Services;

use App\Mail\WelcomeUserMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Genera una contraseña segura aleatoria
     */
    public function generateSecurePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $special = '!@#$%&*-_+=';
        
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        $allChars = $uppercase . $lowercase . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Crear un nuevo usuario
     */
    public function createUser(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Generar contraseña segura
            $temporaryPassword = $this->generateSecurePassword();
            
            // Crear usuario
            $user = User::create([
                'id' => Str::uuid(),
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($temporaryPassword),
                'is_active' => $data['is_active'] ?? true,
            ]);
            
            // Asignar rol (por defecto VENDEDOR)
            $roleName = $data['role'] ?? 'VENDEDOR';
            $role = Role::where('name', $roleName)->firstOrFail();
            $user->roles()->attach($role->id);
            
            // Enviar correo de bienvenida
            Mail::to($user->email)->send(new WelcomeUserMail($user, $temporaryPassword));
            
            DB::commit();
            
            return [
                'user' => $user->load('roles'),
                'temporary_password' => $temporaryPassword,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar usuario
     */
    public function updateUser(User $user, array $data): User
    {
        DB::beginTransaction();
        
        try {
            // Actualizar datos básicos
            $updateData = [];
            
            if (isset($data['username'])) {
                $updateData['username'] = $data['username'];
            }
            
            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }
            
            if (isset($data['is_active'])) {
                $updateData['is_active'] = $data['is_active'];
            }
            
            if (!empty($updateData)) {
                $user->update($updateData);
            }
            
            // Actualizar rol si se proporciona
            if (isset($data['role'])) {
                $role = Role::where('name', $data['role'])->firstOrFail();
                $user->roles()->sync([$role->id]);
            }
            
            DB::commit();
            
            return $user->fresh(['roles']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Activar/Desactivar usuario
     */
    public function toggleUserStatus(User $user): User
    {
        $user->update(['is_active' => !$user->is_active]);
        
        // Si se desactiva, revocar todas sus sesiones
        if (!$user->is_active) {
            $user->sessions()->update(['revoked' => true]);
        }
        
        return $user->fresh();
    }

    /**
     * Eliminar usuario (soft delete si lo implementas, o hard delete)
     */
    public function deleteUser(User $user): bool
    {
        DB::beginTransaction();
        
        try {
            // Revocar todas las sesiones
            $user->sessions()->update(['revoked' => true]);
            
            // Eliminar usuario
            $user->delete();
            
            DB::commit();
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}