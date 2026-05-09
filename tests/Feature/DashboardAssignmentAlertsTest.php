<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentUserAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAssignmentAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_user_sees_assigned_shipments_and_alert_cards(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $client = Client::create([
            'razon_social' => 'Cliente Demo',
            'ruc' => '20999999991',
            'correo' => 'demo@example.com',
            'telefono' => '999999991',
            'direccion' => 'Direccion demo',
            'estado' => 'activo',
        ]);

        $shipment = Shipment::create([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-ALERTA-01',
            'routing_order' => 'RO-ALERTA-01',
            'bl' => 'BL-ALERTA-01',
            'tipo_carga' => 'FCL',
            'origen' => 'China',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima, Peru',
            'naviera' => 'Maersk',
            'consignatario' => 'Cliente Demo',
            'peso' => 1200,
            'volumen' => 24,
            'fecha_etd' => now()->subDays(5)->toDateString(),
            'fecha_eta' => now()->addDay()->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'ETA',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Carga critica',
        ]);

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'estado' => 'ETA',
            'fecha' => now()->subDay(),
            'descripcion' => 'Cambio reciente de estado.',
            'user_id' => $user->id,
        ]);

        ShipmentUserAssignment::create([
            'shipment_id' => $shipment->id,
            'user_id' => $user->id,
            'assignment_type' => ShipmentUserAssignment::TYPE_RESPONSABLE,
            'alerts_enabled' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis cargas vinculadas')
            ->assertSee('Alertas de cambio de estado')
            ->assertSee('Alertas de llegada y ETA')
            ->assertSee('OT-ALERTA-01')
            ->assertSee('Responsable')
            ->assertSee('ETA proxima');
    }
}
