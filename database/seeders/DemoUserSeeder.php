<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::whereIn('ruc', [
            '20600010001',
            '20600010002',
            '20600010003',
            '20600010004',
            '20600010005',
        ])->get()->keyBy('ruc');

        $users = [
            [
                'email' => 'operaciones1@forcex.test',
                'name' => 'Carla Operaciones',
                'username' => 'carla_operaciones',
                'password' => 'Demo123456',
                'role' => User::ROLE_OPERACIONES,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
            ],
            [
                'email' => 'operaciones2@forcex.test',
                'name' => 'Luis Operaciones',
                'username' => 'luis_operaciones',
                'password' => 'Demo123456',
                'role' => User::ROLE_OPERACIONES,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
            ],
            [
                'email' => 'comercial1@forcex.test',
                'name' => 'Juan Comercial',
                'username' => 'juan_comercial',
                'password' => 'Demo123456',
                'role' => User::ROLE_COMERCIAL,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
            ],
            [
                'email' => 'comercial2@forcex.test',
                'name' => 'Ana Comercial',
                'username' => 'ana_comercial',
                'password' => 'Demo123456',
                'role' => User::ROLE_COMERCIAL,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
            ],
            [
                'email' => 'customer1@forcex.test',
                'name' => 'Maria Customer',
                'username' => 'maria_customer',
                'password' => 'Demo123456',
                'role' => User::ROLE_CUSTOMER,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
            ],
            [
                'email' => 'customer2@forcex.test',
                'name' => 'Diego Customer',
                'username' => 'diego_customer',
                'password' => 'Demo123456',
                'role' => User::ROLE_CUSTOMER,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => null,
            ],
            // Rol cliente: existe en BD, sin acceso al backoffice (acceso_portal = false).
            [
                'email' => 'cliente.andean@forcex.test',
                'name' => 'Paola Cliente Andean',
                'username' => 'paola_andean',
                'password' => 'Demo123456',
                'role' => User::ROLE_CLIENTE,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => $clients['20600010001']?->id,
            ],
            [
                'email' => 'cliente.minera@forcex.test',
                'name' => 'Marco Cliente Minera',
                'username' => 'marco_minera',
                'password' => 'Demo123456',
                'role' => User::ROLE_CLIENTE,
                'estado' => User::ESTADO_ACTIVO,
                'client_id' => $clients['20600010002']?->id,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrNew(['email' => $userData['email']]);
            $user->forceFill([
                'name' => $userData['name'],
                'username' => $userData['username'],
                'password' => Hash::make($userData['password']),
                'role' => $userData['role'],
                'estado' => $userData['estado'],
                'client_id' => $userData['client_id'],
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
