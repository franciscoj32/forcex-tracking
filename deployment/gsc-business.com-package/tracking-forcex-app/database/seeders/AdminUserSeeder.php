<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@forcexlogistics.com.pe'],
            [
                'name' => 'Administrador FORCE X',
                'username' => 'admin',
                'password' => 'cambiar123',
                'role' => User::ROLE_ADMIN,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}
