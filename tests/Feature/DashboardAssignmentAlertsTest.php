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

    public function test_internal_user_sees_pending_actions_of_assigned_shipments(): void
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

        // FCL sin contenedores y con ETA vencida: dos pendientes para Operaciones.
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
            'fecha_etd' => now()->subDays(20)->toDateString(),
            'fecha_eta' => now()->subDays(2)->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Carga critica',
        ]);

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'estado' => 'En transito',
            'fecha' => now()->subDays(5),
            'descripcion' => 'Zarpo.',
            'user_id' => $user->id,
        ]);

        // Vinculado como responsable con alertas (no es el operador asignado).
        ShipmentUserAssignment::create([
            'shipment_id' => $shipment->id,
            'user_id' => $user->id,
            'assignment_type' => ShipmentUserAssignment::TYPE_RESPONSABLE,
            'alerts_enabled' => true,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Requiere tu acción')
            ->assertSee('OT-ALERTA-01')
            ->assertSee('Cliente Demo')
            ->assertSee('ETA vencida hace 2 días')
            ->assertSee('FCL sin contenedores registrados.');

        // Un usuario no vinculado no ve ese embarque en su bandeja.
        $other = User::factory()->create(['role' => User::ROLE_OPERACIONES, 'estado' => User::ESTADO_ACTIVO]);

        $this->actingAs($other)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('OT-ALERTA-01')
            ->assertSee('Sin pendientes');
    }
}
