<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_shipment_form_renders_again_after_validation_errors(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($user)
            ->from(route('embarques.create'))
            ->post(route('embarques.store'), []);

        $response->assertRedirect(route('embarques.create'));
        $response->assertSessionHasErrors([
            'client_id',
            'nro_orden_trabajo',
            'routing_order',
            'tipo_carga',
            'incoterm',
            'origen',
            'puerto_carga',
            'puerto_descarga',
            'destino_final',
            'customer_id',
            'estado_endoso',
        ]);
        // La RO nace en el primer estado: no se elige al crear.
        $response->assertSessionDoesntHaveErrors(['estado_actual', 'bl']);

        $this->actingAs($user)
            ->get(route('embarques.create'))
            ->assertOk()
            ->assertSee('Nuevo embarque')
            ->assertSee('Cliente')
            ->assertSee('Customer Service');
    }

    public function test_create_shipment_form_rejects_duplicate_bl_for_the_same_client(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $client = Client::factory()->create(['ruc' => '20555555551']);

        Shipment::create($this->shipmentAttributes($client, [
            'nro_orden_trabajo' => 'OT-BASE-1',
            'routing_order' => 'RO-BASE-1',
            'bl' => 'BL-BASE-1',
        ]));

        $response = $this->actingAs($user)
            ->from(route('embarques.create'))
            ->post(route('embarques.store'), $this->payload($client, $customer, [
                'nro_orden_trabajo' => 'OT-BASE-2',
                'routing_order' => 'RO-BASE-2',
                'bl' => 'BL-BASE-1',
            ]));

        $response->assertRedirect(route('embarques.create'));
        $response->assertSessionHasErrors('bl');
        $response->assertSessionDoesntHaveErrors('client_id');
    }

    public function test_create_shipment_form_rejects_duplicate_routing_order_for_the_same_client(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $client = Client::factory()->create(['ruc' => '20555555552']);

        Shipment::create($this->shipmentAttributes($client, [
            'nro_orden_trabajo' => 'OT-BASE-3',
            'routing_order' => 'RO-BASE-3',
            'bl' => 'BL-BASE-3',
        ]));

        $response = $this->actingAs($user)
            ->from(route('embarques.create'))
            ->post(route('embarques.store'), $this->payload($client, $customer, [
                'nro_orden_trabajo' => 'OT-BASE-4',
                'routing_order' => 'RO-BASE-3',
                'bl' => 'BL-BASE-4',
            ]));

        $response->assertRedirect(route('embarques.create'));
        $response->assertSessionHasErrors('routing_order');
    }

    private function shipmentAttributes(Client $client, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'tipo_carga' => 'FCL',
            'incoterm' => 'FOB',
            'origen' => 'China',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Peru',
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
        ], $overrides);
    }

    private function payload(Client $client, User $customer, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $client->id,
            'customer_id' => $customer->id,
            'tipo_carga' => 'FCL',
            'incoterm' => 'FOB',
            'origen' => 'China',
            'puerto_carga' => 'Ningbo',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Peru',
            'estado_endoso' => 'pendiente',
        ], $overrides);
    }
}
