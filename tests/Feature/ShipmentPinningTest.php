<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentPinningTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pin_a_shipment_and_it_appears_first_for_that_user_only(): void
    {
        $userJuan = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
            'estado' => User::ESTADO_ACTIVO,
        ]);
        $userMaria = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $olderShipment = $this->createShipment([
            'nro_orden_trabajo' => 'OT-OLDER',
            'bl' => 'BL-OLDER',
        ]);
        $newerShipment = $this->createShipment([
            'nro_orden_trabajo' => 'OT-NEWER',
            'bl' => 'BL-NEWER',
        ]);

        $olderShipment->forceFill(['updated_at' => now()->subHour()])->save();
        $newerShipment->forceFill(['updated_at' => now()])->save();

        $this->actingAs($userJuan)
            ->post(route('embarques.pin', $olderShipment))
            ->assertRedirect();

        $this->assertDatabaseHas('user_shipment_preferences', [
            'user_id' => $userJuan->id,
            'shipment_id' => $olderShipment->id,
            'is_pinned' => true,
        ]);

        $this->actingAs($userJuan)
            ->get(route('embarques.index'))
            ->assertOk()
            ->assertSeeInOrder(['OT-OLDER', 'OT-NEWER'])
            ->assertSee('Fijado');

        $this->actingAs($userMaria)
            ->get(route('embarques.index'))
            ->assertOk()
            ->assertSeeInOrder(['OT-NEWER', 'OT-OLDER'])
            ->assertDontSee('Fijado');
    }

    public function test_user_can_filter_shipments_by_ot_bl_and_routing_order(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $this->createShipment([
            'nro_orden_trabajo' => 'OT-FILTRO',
            'bl' => 'BL-1111',
        ]);
        $otherShipment = $this->createShipment([
            'nro_orden_trabajo' => 'OT-OTRO',
            'bl' => 'BL-2222',
        ]);

        $this->actingAs($user)
            ->get(route('embarques.index', ['q' => 'BL-1111']))
            ->assertOk()
            ->assertSee('OT-FILTRO')
            ->assertDontSee($otherShipment->nro_orden_trabajo);

        $this->actingAs($user)
            ->get(route('embarques.index', ['q' => 'RO-1']))
            ->assertOk()
            ->assertSee('OT-FILTRO')
            ->assertDontSee($otherShipment->nro_orden_trabajo);
    }

    public function test_internal_user_can_export_filtered_shipments_for_a_client(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
            'estado' => User::ESTADO_ACTIVO,
        ]);

        $targetShipment = $this->createShipment([
            'nro_orden_trabajo' => 'OT-EXPORT-OK',
            'estado_actual' => 'Finalizado',
        ]);

        $otherShipment = $this->createShipment([
            'nro_orden_trabajo' => 'OT-EXPORT-NO',
            'estado_actual' => 'En transito',
        ]);

        $response = $this->actingAs($user)->get(route('embarques.export', [
            'client_id' => $targetShipment->client_id,
            'lifecycle' => 'finished',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $response->assertSee('OT-EXPORT-OK');
        $response->assertDontSee('OT-EXPORT-NO');
        $this->assertNotSame($targetShipment->client_id, $otherShipment->client_id);
    }

    private function createShipment(array $overrides = []): Shipment
    {
        $sequence = Shipment::count() + 1;

        $client = Client::create([
            'razon_social' => 'Cliente '.$sequence,
            'ruc' => '20'.str_pad((string) $sequence, 9, '0', STR_PAD_LEFT),
            'correo' => 'cliente'.$sequence.'@example.com',
            'telefono' => '99999999'.$sequence,
            'direccion' => 'Direccion '.$sequence,
            'estado' => 'activo',
        ]);

        return Shipment::create(array_merge([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-'.$sequence,
            'routing_order' => 'RO-'.$sequence,
            'bl' => 'BL-'.$sequence,
            'tipo_carga' => 'FCL',
            'origen' => 'China',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Peru',
            'naviera' => 'Maersk',
            'consignatario' => 'Consignatario '.$sequence,
            'peso' => 1000,
            'volumen' => 25,
            'fecha_etd' => now()->subDays(2)->toDateString(),
            'fecha_eta' => now()->addDays(7)->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Observacion '.$sequence,
        ], $overrides));
    }
}
