<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_update_registers_audit_event_with_editor_name(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $client = Client::create([
            'razon_social' => 'Cliente Demo',
            'ruc' => '20111111111',
            'correo' => 'cliente@example.com',
            'telefono' => '999111111',
            'direccion' => 'Direccion demo',
            'estado' => 'activo',
        ]);

        $shipment = Shipment::create([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-AUDIT-01',
            'routing_order' => 'RO-AUDIT-01',
            'bl' => 'BL-AUDIT-01',
            'tipo_carga' => 'FCL',
            'origen' => 'China',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Peru',
            'naviera' => 'Maersk',
            'consignatario' => 'Cliente Demo',
            'peso' => 1000,
            'volumen' => 10,
            'fecha_etd' => now()->subDays(10)->toDateString(),
            'fecha_eta' => now()->addDays(5)->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'Booking Confirmado',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Observacion inicial',
        ]);

        $this->actingAs($user)->put(route('embarques.update', $shipment), [
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-AUDIT-01',
            'routing_order' => 'RO-AUDIT-01',
            'bl' => 'BL-AUDIT-01',
            'tipo_carga' => 'FCL',
            'origen' => 'China',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Peru',
            'naviera' => 'Maersk',
            'consignatario' => 'Cliente Demo',
            'peso' => 1000,
            'volumen' => 10,
            'fecha_etd' => now()->subDays(10)->toDateString(),
            'fecha_eta' => now()->addDays(2)->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'Booking Confirmado',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Observacion ajustada',
            'responsable_users' => [$user->id],
            'tracking_users' => [],
        ])->assertRedirect(route('embarques.show', $shipment));

        $shipment->refresh();
        $shipment->load('events.user');

        $this->assertTrue($shipment->events->contains(
            fn ($event) => str_contains((string) $event->descripcion, 'Ficha actualizada:')
                && str_contains((string) $event->descripcion, 'usuarios vinculados')
                && $event->user?->is($user)
        ));
    }
}
