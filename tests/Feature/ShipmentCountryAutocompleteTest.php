<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentCountryAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_form_exposes_country_autocomplete_options(): void
    {
        // La RO la registra el Comercial.
        $user = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $this->actingAs($user)
            ->get(route('embarques.create'))
            ->assertOk()
            ->assertSee('country-options', false)
            ->assertSee('Pais de origen')
            ->assertSee('Pais de llegada')
            ->assertSee('Singapur')
            ->assertSee('Perú')
            ->assertSee('México');
    }
}
