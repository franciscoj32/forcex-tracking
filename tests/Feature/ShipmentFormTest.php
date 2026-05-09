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
            'bl',
            'tipo_carga',
            'origen',
            'puerto_carga',
            'puerto_descarga',
            'destino_final',
            'estado_actual',
            'estado_endoso',
        ]);

        $this->actingAs($user)
            ->get(route('embarques.create'))
            ->assertOk()
            ->assertSee('Nuevo embarque')
            ->assertSee('Cliente')
            ->assertSee('Estado actual');
    }

    public function test_create_shipment_form_rejects_duplicate_bl_for_the_same_client(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $client = Client::create([
            'razon_social' => 'Cliente Uno SAC',
            'ruc' => '20555555551',
            'correo' => 'cliente1@example.com',
            'telefono' => '900000001',
            'direccion' => 'Av. Uno 123',
            'estado' => 'activo',
        ]);

        Shipment::create([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-BASE-1',
            'routing_order' => 'RO-BASE-1',
            'bl' => 'BL-BASE-1',
            'tipo_carga' => 'FCL',
            'origen' => 'Shanghai',
            'puerto_carga' => 'Shanghai',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima',
            'naviera' => 'Maersk',
            'consignatario' => 'Cliente Uno',
            'peso' => 1000,
            'volumen' => 20,
            'fecha_etd' => now()->toDateString(),
            'fecha_eta' => now()->addDays(5)->toDateString(),
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
        ]);

        $response = $this->actingAs($user)
            ->from(route('embarques.create'))
            ->post(route('embarques.store'), [
                'client_id' => $client->id,
                'nro_orden_trabajo' => 'OT-BASE-2',
                'routing_order' => 'RO-BASE-2',
                'bl' => 'BL-BASE-1',
                'tipo_carga' => 'FCL',
                'origen' => 'Ningbo',
                'puerto_carga' => 'Ningbo',
                'puerto_descarga' => 'Callao',
                'destino_final' => 'Lima',
                'naviera' => 'CMA CGM',
                'consignatario' => 'Cliente Uno',
                'peso' => 900,
                'volumen' => 18,
                'fecha_etd' => now()->toDateString(),
                'fecha_eta' => now()->addDays(7)->toDateString(),
                'estado_actual' => 'En transito',
                'estado_endoso' => 'pendiente',
            ]);

        $response->assertRedirect(route('embarques.create'));
        $response->assertSessionHasErrors('bl');
    }

    public function test_create_shipment_form_rejects_duplicate_routing_order_for_the_same_client(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $client = Client::create([
            'razon_social' => 'Cliente Dos SAC',
            'ruc' => '20555555552',
            'correo' => 'cliente2@example.com',
            'telefono' => '900000002',
            'direccion' => 'Av. Dos 456',
            'estado' => 'activo',
        ]);

        Shipment::create([
            'client_id' => $client->id,
            'nro_orden_trabajo' => 'OT-BASE-3',
            'routing_order' => 'RO-BASE-3',
            'bl' => 'BL-BASE-3',
            'tipo_carga' => 'LCL',
            'origen' => 'Shenzhen',
            'puerto_carga' => 'Shenzhen',
            'puerto_descarga' => 'Callao',
            'destino_final' => 'Lima',
            'naviera' => 'Evergreen',
            'consignatario' => 'Cliente Dos',
            'peso' => 800,
            'volumen' => 15,
            'fecha_etd' => now()->toDateString(),
            'fecha_eta' => now()->addDays(6)->toDateString(),
            'estado_actual' => 'En transito',
            'estado_endoso' => 'pendiente',
        ]);

        $response = $this->actingAs($user)
            ->from(route('embarques.create'))
            ->post(route('embarques.store'), [
                'client_id' => $client->id,
                'nro_orden_trabajo' => 'OT-BASE-4',
                'routing_order' => 'RO-BASE-3',
                'bl' => 'BL-BASE-4',
                'tipo_carga' => 'LCL',
                'origen' => 'Busan',
                'puerto_carga' => 'Busan',
                'puerto_descarga' => 'Callao',
                'destino_final' => 'Lima',
                'naviera' => 'HMM',
                'consignatario' => 'Cliente Dos',
                'peso' => 780,
                'volumen' => 14,
                'fecha_etd' => now()->toDateString(),
                'fecha_eta' => now()->addDays(8)->toDateString(),
                'estado_actual' => 'En transito',
                'estado_endoso' => 'pendiente',
            ]);

        $response->assertRedirect(route('embarques.create'));
        $response->assertSessionHasErrors('routing_order');
    }
}
