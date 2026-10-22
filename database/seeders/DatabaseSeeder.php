<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Los datos de prueba no deben encolar correos reales.
        config(['forcex.notifications' => false]);

        $this->call([
            AdminUserSeeder::class,
            DemoShipmentSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
