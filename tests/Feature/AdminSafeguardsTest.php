<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_role_requires_an_associated_client(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $response = $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Cliente sin empresa',
            'username' => 'cliente_sin_empresa',
            'email' => 'cliente@example.com',
            'password' => 'password123',
            'role' => User::ROLE_CLIENTE,
            'estado' => User::ESTADO_ACTIVO,
            'client_id' => '',
        ]);

        $response->assertSessionHasErrors('client_id');
        $this->assertDatabaseMissing('users', [
            'email' => 'cliente@example.com',
        ]);
    }

    public function test_internal_roles_are_saved_without_a_client_assignment(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $client = Client::create([
            'razon_social' => 'ACME Import SAC',
            'ruc' => '20123456789',
            'correo' => 'acme@example.com',
            'telefono' => '999999999',
            'direccion' => 'Av. Principal 123',
            'estado' => 'activo',
        ]);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'name' => 'Operador',
            'username' => 'operador_fx',
            'email' => 'operador@example.com',
            'password' => 'password123',
            'role' => User::ROLE_OPERACIONES,
            'estado' => User::ESTADO_ACTIVO,
            'client_id' => (string) $client->id,
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'operador@example.com',
            'role' => User::ROLE_OPERACIONES,
            'client_id' => null,
        ]);
    }

    public function test_deleting_a_shipment_removes_private_document_and_invoice_files(): void
    {
        Storage::fake('private');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $client = Client::create([
            'razon_social' => 'Carga Andina SAC',
            'ruc' => '20987654321',
            'correo' => 'carga@example.com',
            'telefono' => '988888888',
            'direccion' => 'Jr. Comercio 456',
            'estado' => 'activo',
        ]);
        $shipment = Shipment::create([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-1001',
            'routing_order' => 'RO-1001',
            'bl' => 'BL-1001',
            'tipo_carga' => 'FCL',
            'origen' => 'Shanghai',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima',
            'naviera' => 'Maersk',
            'consignatario' => 'Carga Andina',
            'peso' => 1250.50,
            'volumen' => 32.10,
            'fecha_etd' => now()->subDays(5)->toDateString(),
            'fecha_eta' => now()->addDays(7)->toDateString(),
            'fecha_descarga' => null,
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
            'fecha_endoso' => null,
            'responsable_endoso_id' => null,
            'observacion_endoso' => null,
            'observaciones' => 'Embarque de prueba',
        ]);

        $documentPath = 'embarques/2026/ot-1001/documento.pdf';
        $invoicePath = 'facturas/2026/factura.pdf';

        Storage::disk('private')->put($documentPath, 'documento');
        Storage::disk('private')->put($invoicePath, 'factura');

        Document::create([
            'shipment_id' => $shipment->id,
            'categoria' => Document::CATEGORY_OPERATIVO,
            'tipo_documento' => 'BL',
            'nombre_original' => 'bl.pdf',
            'nombre_archivo' => 'documento.pdf',
            'ruta' => $documentPath,
            'peso' => 100,
            'mime_type' => 'application/pdf',
            'user_id' => $admin->id,
        ]);

        Invoice::create([
            'shipment_id' => $shipment->id,
            'numero' => 'F001-0001',
            'monto' => 1500,
            'estado' => 'emitida',
            'archivo_pdf' => $invoicePath,
        ]);

        $this->actingAs($admin)
            ->delete(route('embarques.destroy', $shipment))
            ->assertRedirect(route('embarques.index'));

        Storage::disk('private')->assertMissing($documentPath);
        Storage::disk('private')->assertMissing($invoicePath);
        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
        $this->assertDatabaseMissing('documents', ['shipment_id' => $shipment->id]);
        $this->assertDatabaseMissing('invoices', ['shipment_id' => $shipment->id]);
    }

    public function test_deleting_an_internal_user_preserves_tracking_history_and_documents(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $operator = User::factory()->create([
            'role' => User::ROLE_OPERACIONES,
        ]);
        $client = Client::create([
            'razon_social' => 'Historia Segura SAC',
            'ruc' => '20555555553',
            'correo' => 'historia@example.com',
            'telefono' => '900000003',
            'direccion' => 'Av. Historia 789',
            'estado' => 'activo',
        ]);
        $shipment = Shipment::create([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-HIST-1',
            'routing_order' => 'RO-HIST-1',
            'bl' => 'BL-HIST-1',
            'tipo_carga' => 'FCL',
            'origen' => 'Shanghai',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima',
            'naviera' => 'Maersk',
            'consignatario' => 'Historia Segura',
            'peso' => 1500,
            'volumen' => 30,
            'fecha_etd' => now()->subDays(2)->toDateString(),
            'fecha_eta' => now()->addDays(8)->toDateString(),
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
        ]);

        $document = Document::create([
            'shipment_id' => $shipment->id,
            'categoria' => Document::CATEGORY_OPERATIVO,
            'tipo_documento' => 'BL',
            'nombre_original' => 'bl-hist.pdf',
            'nombre_archivo' => 'bl-hist.pdf',
            'ruta' => 'embarques/2026/ot-hist-1/bl-hist.pdf',
            'peso' => 100,
            'mime_type' => 'application/pdf',
            'user_id' => $operator->id,
        ]);

        $event = ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'estado' => 'En transito',
            'fecha' => now(),
            'descripcion' => 'Evento histórico',
            'user_id' => $operator->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('usuarios.destroy', $operator))
            ->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseMissing('users', ['id' => $operator->id]);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('shipment_events', [
            'id' => $event->id,
            'user_id' => null,
        ]);
    }
}
