<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_event_cannot_repeat_the_current_state(): void
    {
        $user = $this->createOperator();
        $shipment = $this->createShipment([
            'estado_actual' => 'En transito',
        ]);

        $response = $this->actingAs($user)
            ->from(route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']))
            ->post(route('embarques.tracking.store', $shipment), [
                'estado' => 'En transito',
                'fecha' => now()->toDateTimeString(),
                'descripcion' => 'Intento duplicado.',
            ]);

        $response->assertRedirect(route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']));
        $response->assertSessionHasErrors('estado');
        $this->assertDatabaseMissing('shipment_events', [
            'shipment_id' => $shipment->id,
            'descripcion' => 'Intento duplicado.',
        ]);
    }

    public function test_tracking_event_cannot_go_back_to_a_previous_state(): void
    {
        $user = $this->createOperator();
        $shipment = $this->createShipment([
            'estado_actual' => 'Arribo a puerto',
        ]);

        $response = $this->actingAs($user)
            ->from(route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']))
            ->post(route('embarques.tracking.store', $shipment), [
                'estado' => 'En transito',
                'fecha' => now()->toDateTimeString(),
                'descripcion' => 'Intento de retroceso.',
            ]);

        $response->assertRedirect(route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']));
        $response->assertSessionHasErrors('estado');
        $shipment->refresh();

        $this->assertSame('Arribo a puerto', $shipment->estado_actual);
        $this->assertDatabaseMissing('shipment_events', [
            'shipment_id' => $shipment->id,
            'descripcion' => 'Intento de retroceso.',
        ]);
    }

    public function test_tracking_event_can_advance_and_updates_current_state(): void
    {
        $user = $this->createOperator();
        $shipment = $this->createShipment([
            'estado_actual' => 'En transito',
        ]);

        $response = $this->actingAs($user)
            ->post(route('embarques.tracking.store', $shipment), [
                'estado' => 'ETA',
                'fecha' => now()->toDateTimeString(),
                'descripcion' => 'Carga próxima a arribar.',
            ]);

        $response->assertRedirect(route('embarques.show', [
            'shipment' => $shipment,
            'tab' => 'tracking',
        ]));

        $shipment->refresh();

        $this->assertSame('ETA', $shipment->estado_actual);
        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->id,
            'estado' => 'ETA',
            'descripcion' => 'Carga próxima a arribar.',
            'user_id' => $user->id,
        ]);
    }

    public function test_edit_shipment_cannot_move_current_state_backwards(): void
    {
        $user = $this->createOperator();
        $shipment = $this->createShipment([
            'estado_actual' => 'Arribo a puerto',
        ]);

        $response = $this->actingAs($user)
            ->from(route('embarques.edit', $shipment))
            ->put(route('embarques.update', $shipment), $this->shipmentPayload($shipment, [
                'estado_actual' => 'En transito',
            ]));

        $response->assertRedirect(route('embarques.edit', $shipment));
        $response->assertSessionHasErrors('estado_actual');

        $shipment->refresh();
        $this->assertSame('Arribo a puerto', $shipment->estado_actual);
    }

    private function createOperator(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);
    }

    private function createShipment(array $overrides = []): Shipment
    {
        $client = Client::create([
            'razon_social' => 'Cliente Flujo SAC',
            'ruc' => fake()->unique()->numerify('20#########'),
            'correo' => 'flujo@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Flujo 123',
            'estado' => 'activo',
        ]);

        $shipment = Shipment::create(array_merge([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-'.fake()->unique()->numerify('####'),
            'routing_order' => 'RO-'.fake()->unique()->numerify('####'),
            'bl' => 'BL-'.fake()->unique()->numerify('####'),
            'tipo_carga' => 'FCL',
            'origen' => 'Shanghai',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima',
            'naviera' => 'Maersk',
            'consignatario' => 'Cliente Flujo',
            'peso' => 1000,
            'volumen' => 25,
            'fecha_etd' => now()->subDays(2)->toDateString(),
            'fecha_eta' => now()->addDays(10)->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Carga de prueba',
        ], $overrides));

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'estado' => $shipment->estado_actual,
            'fecha' => now()->subHour(),
            'descripcion' => 'Estado inicial de prueba.',
            'user_id' => User::factory()->create([
                'role' => User::ROLE_OPERACIONES,
            ])->id,
        ]);

        return $shipment;
    }

    private function shipmentPayload(Shipment $shipment, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $shipment->client_id,
            'nro_orden_trabajo' => $shipment->nro_orden_trabajo,
            'routing_order' => $shipment->routing_order,
            'bl' => $shipment->bl,
            'tipo_carga' => $shipment->tipo_carga,
            'origen' => $shipment->origen,
            'puerto_carga' => $shipment->puerto_carga,
            'puerto_descarga' => $shipment->puerto_descarga,
            'destino_final' => $shipment->destino_final,
            'naviera' => $shipment->naviera,
            'consignatario' => $shipment->consignatario,
            'peso' => $shipment->peso,
            'volumen' => $shipment->volumen,
            'fecha_etd' => optional($shipment->fecha_etd)->format('Y-m-d'),
            'fecha_eta' => optional($shipment->fecha_eta)->format('Y-m-d'),
            'fecha_descarga' => optional($shipment->fecha_descarga)->format('Y-m-d'),
            'estado_actual' => $shipment->estado_actual,
            'estado_endoso' => $shipment->estado_endoso,
            'fecha_endoso' => optional($shipment->fecha_endoso)->format('Y-m-d'),
            'responsable_endoso_id' => $shipment->responsable_endoso_id,
            'observacion_endoso' => $shipment->observacion_endoso,
            'observaciones' => $shipment->observaciones,
        ], $overrides);
    }
}

