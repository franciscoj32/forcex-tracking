<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'razon_social' => fake()->company().' S.A.C.',
            'ruc' => '20'.fake()->unique()->numerify('#########'),
            'correo' => fake()->companyEmail(),
            'telefono' => fake()->numerify('01#######'),
            'direccion' => fake()->address(),
            'estado' => 'activo',
            'estado_aprobacion' => Client::APPROVAL_APPROVED,
            'condicion_credito' => Client::CREDIT_CASH,
            'dias_credito' => null,
            'moneda_linea_credito' => 'USD',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['estado_aprobacion' => Client::APPROVAL_PENDING]);
    }

    public function withCredit(int $days = 30): static
    {
        return $this->state(fn () => [
            'condicion_credito' => Client::CREDIT_TERM,
            'dias_credito' => $days,
        ]);
    }
}
