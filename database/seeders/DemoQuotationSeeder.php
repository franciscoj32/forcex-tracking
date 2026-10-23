<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Quotation;
use App\Models\RateConcept;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Database\Seeder;

/**
 * Dos cotizaciones de prueba del Comercial Juan: una a un cliente registrado
 * (enviada) y otra a un prospecto que aun no es cliente (borrador).
 * Si ya existen cotizaciones, no hace nada (no duplica al volver a sembrar).
 */
class DemoQuotationSeeder extends Seeder
{
    public function run(QuotationService $service): void
    {
        $comercial = User::where('username', 'juan_comercial')->first();

        if (! $comercial || Quotation::query()->exists()) {
            return;
        }

        $concepts = RateConcept::pluck('id', 'codigo');
        $client = Client::with('contacts')->where('ruc', '20600010001')->first();

        if ($client) {
            $contact = $client->contacts->first();
            $quotation = $service->save($service->newQuotation(), [
                'client_id' => $client->id,
                'ruc' => $client->ruc,
                'razon_social' => $client->razon_social,
                'direccion' => $client->direccion,
                'contacto_nombre' => $contact?->nombre,
                'contacto_correo' => $contact?->correo,
                'contacto_telefono' => $contact?->telefono,
                'tipo_carga' => 'FCL',
                'incoterm' => 'FOB',
                'origen' => 'China',
                'puerto_carga' => 'Ningbo',
                'puerto_descarga' => 'Callao',
                'destino' => 'Lima, Perú',
                'mercancia' => 'Muebles y artículos de decoración',
                'contenedores' => '1 x 40HC',
                'naviera' => 'COSCO',
                'tiempo_transito' => '35 días aprox.',
                'frecuencia' => 'Semanal',
                'dias_libres' => 14,
                'fecha' => today()->subDays(2),
            ], [
                ['rate_concept_id' => $concepts['OCEAN_FREIGHT'] ?? null, 'descripcion' => 'Flete marítimo Ningbo - Callao 40HC', 'cantidad' => 1, 'precio_unitario' => 2850, 'moneda' => 'USD', 'afecto_igv' => false],
                ['rate_concept_id' => $concepts['THC'] ?? null, 'descripcion' => 'THC destino', 'cantidad' => 1, 'precio_unitario' => 280, 'moneda' => 'USD', 'afecto_igv' => true],
                ['rate_concept_id' => $concepts['BL_FEE'] ?? null, 'descripcion' => 'Emisión de BL', 'cantidad' => 1, 'precio_unitario' => 75, 'moneda' => 'USD', 'afecto_igv' => true],
                ['rate_concept_id' => $concepts['CUSTOMS'] ?? null, 'descripcion' => 'Despacho de aduana', 'cantidad' => 1, 'precio_unitario' => 650, 'moneda' => 'PEN', 'afecto_igv' => true],
            ], $comercial);

            $service->markSent($quotation, $comercial, Quotation::CHANNEL_EMAIL);
        }

        $service->save($service->newQuotation(), [
            'ruc' => '20612345678',
            'razon_social' => 'Importaciones Nova Peru S.A.C.',
            'direccion' => 'Av. Argentina 2450, Callao',
            'contacto_nombre' => 'Lucia Mendoza',
            'contacto_correo' => 'lmendoza@novaperu.example',
            'contacto_telefono' => '987654321',
            'tipo_carga' => 'LCL',
            'incoterm' => 'EXW',
            'origen' => 'Estados Unidos',
            'puerto_carga' => 'Miami',
            'puerto_descarga' => 'Callao',
            'destino' => 'Lima, Perú',
            'mercancia' => 'Repuestos automotrices',
            'peso' => 1850,
            'volumen' => 4.2,
            'tiempo_transito' => '12 días aprox.',
            'frecuencia' => 'Semanal',
            'fecha' => today(),
        ], [
            ['rate_concept_id' => $concepts['OCEAN_FREIGHT'] ?? null, 'descripcion' => 'Flete marítimo LCL (por W/M)', 'cantidad' => 4.2, 'precio_unitario' => 65, 'moneda' => 'USD', 'afecto_igv' => false],
            ['rate_concept_id' => $concepts['HANDLING'] ?? null, 'descripcion' => 'Handling', 'cantidad' => 1, 'precio_unitario' => 60, 'moneda' => 'USD', 'afecto_igv' => true],
            ['rate_concept_id' => $concepts['LOCAL_TRANSPORT'] ?? null, 'descripcion' => 'Transporte local Callao - Lima', 'cantidad' => 1, 'precio_unitario' => 380, 'moneda' => 'PEN', 'afecto_igv' => true],
        ], $comercial);
    }
}
