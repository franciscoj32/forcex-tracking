<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_code_is_generated_automatically_for_new_shipments(): void
    {
        $shipment = $this->createShipment();

        $this->assertNotNull($shipment->tracking_code);
        $this->assertMatchesRegularExpression('/^FX-[A-Z0-9]{8}$/', $shipment->tracking_code);
    }

    public function test_public_tracking_page_shows_shipment_status_by_tracking_code(): void
    {
        $shipment = $this->createShipment();
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);

        // Hito: el cliente ve el texto estandar, no la descripcion interna.
        ShipmentEvent::recordState($shipment, 'En transito', now(), 'Detalle interno: transbordo en Balboa.', $operator->id);
        // Bitacora interna: nunca visible.
        ShipmentEvent::recordAudit($shipment, 'Ficha actualizada: ETA.', $operator->id);
        // Nota del Customer: visible.
        ShipmentEvent::recordClientNote($shipment, 'Carga rumbo a Callao.', $operator->id);

        $response = $this->get(route('tracking.public', ['code' => strtolower($shipment->tracking_code)]));

        $response->assertOk();
        $response->assertSee($shipment->tracking_code);
        $response->assertSee($shipment->nro_orden_trabajo);
        $response->assertSee('En transito');
        $response->assertSee(Shipment::clientStateText('En transito'));
        $response->assertSee('Carga rumbo a Callao.');
        $response->assertDontSee('Detalle interno: transbordo en Balboa.');
        $response->assertDontSee('Ficha actualizada');
        $response->assertDontSee('Facturacion');
        $response->assertDontSee('Repositorio documental');
    }

    public function test_shipment_exposes_icons_from_central_tracking_configuration(): void
    {
        $maritimeShipment = $this->createShipment([
            'tipo_carga' => 'FCL',
            'estado_actual' => 'ATD',
        ]);
        $airShipment = $this->createShipment([
            'tipo_carga' => 'AEREO',
            'estado_actual' => 'Arribo a puerto',
            'nro_orden_trabajo' => 'OT-AEREO',
            'routing_order' => 'RO-AEREO',
            'bl' => 'BL-AEREO',
        ]);

        $this->assertSame('ship', $maritimeShipment->heroTransportIcon());
        $this->assertSame('ship', $maritimeShipment->routeTransportIcon());
        $this->assertSame('ship', $maritimeShipment->eventStateIcon('ATD'));

        $this->assertSame('plane', $airShipment->heroTransportIcon());
        $this->assertSame('plane-landing', $airShipment->routeTransportIcon());
        $this->assertSame('plane-landing', $airShipment->eventStateIcon('Arribo a puerto'));
    }

    public function test_shipment_resolves_country_flags_from_central_configuration(): void
    {
        $shipment = $this->createShipment([
            'origen' => 'China',
            'destino_final' => 'Peru',
        ]);

        $this->assertSame('cn', $shipment->originCountryCode());
        $this->assertSame('pe', $shipment->destinationCountryCode());
        $this->assertSame('us', Shipment::countryCodeFor('Los Angeles, United States'));
        $this->assertSame('sg', Shipment::countryCodeFor('Singapur'));
        $this->assertSame('sg', Shipment::countryCodeFor('Singapore'));
        $this->assertNull(Shipment::countryCodeFor('Zona Franca'));
    }

    public function test_shipment_resolves_flags_from_country_fields_and_not_from_ports_or_cities_alone(): void
    {
        $shipment = $this->createShipment([
            'origen' => 'Shanghai',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima',
        ]);

        $this->assertNull($shipment->originCountryCode());
        $this->assertNull($shipment->destinationCountryCode());
        $this->assertNull(Shipment::countryCodeForCountryField('Shanghai'));
        $this->assertNull(Shipment::countryCodeForCountryField('Callao'));
        $this->assertNull(Shipment::countryCodeForCountryField('Lima'));
        $this->assertSame('pe', Shipment::countryCodeForCountryField('Lima, Peru'));
        $this->assertSame('cn', Shipment::countryCodeForCountryField('China'));
    }

    public function test_shipment_builds_document_checklist_with_required_and_recommended_items(): void
    {
        $shipment = $this->createShipment([
            'estado_actual' => 'Ingreso a almacen',
            'estado_endoso' => 'en_proceso',
        ]);

        Document::create([
            'shipment_id' => $shipment->id,
            'categoria' => Document::CATEGORY_OPERATIVO,
            'tipo_documento' => 'BL',
            'nombre_original' => 'bl.pdf',
            'nombre_archivo' => 'bl.pdf',
            'ruta' => 'embarques/demo/bl.pdf',
            'peso' => 1024,
            'mime_type' => 'application/pdf',
            'user_id' => User::factory()->create(['role' => User::ROLE_OPERACIONES])->id,
        ]);

        $checklist = collect($shipment->fresh()->documentChecklist())->keyBy('key');

        $operativo = $checklist->get(Document::CATEGORY_OPERATIVO);
        $this->assertSame('Operativo', $operativo['label']);
        $this->assertContains('Commercial Invoice', collect($operativo['missing_required'])->pluck('label')->all());
        $this->assertContains('Packing List', collect($operativo['missing_recommended'])->pluck('label')->all());

        $endoso = $checklist->get(Document::CATEGORY_ENDOSO);
        $this->assertContains('BL Endosado', collect($endoso['missing_required'])->pluck('label')->all());
        $this->assertContains('Carta de Endoso', collect($endoso['missing_recommended'])->pluck('label')->all());

        $facturacion = $checklist->get(Document::CATEGORY_FACTURACION);
        $this->assertContains('Sustento de cobro', collect($facturacion['missing_required'])->pluck('label')->all());
        $this->assertSame('Obligatorio', $facturacion['missing_required'][0]['priority_label']);
    }

    public function test_internal_roles_can_see_tracking_code_in_internal_view(): void
    {
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);
        $shipment = $this->createShipment([
            'responsable_endoso_id' => $operator->id,
        ]);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $commercial = User::factory()->create([
            'role' => User::ROLE_COMERCIAL,
        ]);

        $this->actingAs($admin)
            ->get(route('embarques.show', $shipment))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Copiar c')
            ->assertSee('digo');

        $this->actingAs($operator)
            ->get(route('embarques.show', $shipment))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Copiar c')
            ->assertSee('digo');

        $this->actingAs($commercial)
            ->get(route('embarques.show', $shipment))
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Copiar c')
            ->assertSee('digo');
    }

    public function test_internal_roles_find_shipments_by_tracking_code_in_index(): void
    {
        // El codigo ya no ocupa una columna del listado (se ve en la ficha),
        // pero se puede buscar por el.
        $shipment = $this->createShipment();
        $this->createShipment(['nro_orden_trabajo' => 'OT-OTRO-CODIGO', 'routing_order' => 'RO-OTRO', 'bl' => 'BL-OTRO']);

        foreach ([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('embarques.index', ['q' => $shipment->tracking_code]))
                ->assertOk()
                ->assertSee($shipment->nro_orden_trabajo)
                ->assertDontSee('OT-OTRO-CODIGO');
        }
    }

    private function createShipment(array $overrides = []): Shipment
    {
        $client = Client::create([
            'razon_social' => 'Cliente Tracking SAC',
            'ruc' => fake()->unique()->numerify('20#########'),
            'correo' => 'tracking@example.com',
            'telefono' => '987654321',
            'direccion' => 'Av. Tracking 123',
            'estado' => 'activo',
        ]);

        return Shipment::create(array_merge([
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
            'consignatario' => 'Cliente Tracking',
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
    }
}
