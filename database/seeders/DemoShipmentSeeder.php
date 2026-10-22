<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Provision;
use App\Models\RateConcept;
use App\Models\Shipment;
use App\Models\ShipmentAlertState;
use App\Models\ShipmentRate;
use App\Models\Supplier;
use App\Models\ShipmentContainer;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentEvent;
use App\Models\ShipmentPreAlert;
use App\Models\ShipmentUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoShipmentSeeder extends Seeder
{
    /** Nave y viaje de las RO demo (tres comparten COSCO SHIPPING ARIES y tres MSC ANNA). */
    private const VESSELS = [
        'FX-OT-250001' => ['COSCO SHIPPING ARIES', '045E'],
        'FX-OT-250011' => ['COSCO SHIPPING ARIES', '045E'],
        'FX-OT-250006' => ['COSCO SHIPPING ARIES', '045E'],
        'FX-OT-250003' => ['MSC ANNA', '442W'],
        'FX-OT-250008' => ['MSC ANNA', '442W'],
        'FX-OT-250009' => ['MSC ANNA', '442W'],
        'FX-OT-250002' => ['MAERSK SEMARANG', '318S'],
        'FX-OT-250007' => ['WAN HAI 512', '0189E'],
        'FX-OT-250014' => ['EVER LUCID', '1172-021S'],
        'FX-OT-250004' => ['CMA CGM TAGE', '0MX5ES'],
        'FX-OT-250005' => ['LH8264', null],
        'FX-OT-250010' => ['TK0801', null],
        'FX-OT-250015' => ['CX0096', null],
    ];

    /** @var array<string, Supplier> */
    private array $suppliers = [];

    /** @var array<string, int> codigo => id */
    private array $conceptIds = [];

    private const TRACKING_FLOW = [
        'Creacion Routing Order',
        'Booking Confirmado',
        'ATD',
        'En transito',
        'Transmision Aduanas',
        'ETA',
        'Arribo a puerto',
        'Descarga',
        'Ingreso a almacen',
        'Retiro de almacen',
        'Entregado',
        'Finalizado',
    ];

    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@forcexlogistics.com.pe'],
            [
                'name' => 'Administrador FORCE X',
                'username' => 'admin',
                'password' => 'cambiar123',
                'role' => User::ROLE_ADMIN,
                'estado' => User::ESTADO_ACTIVO,
                'email_verified_at' => now(),
            ]
        );

        $operators = $this->demoOperators(collect(), $admin);

        $this->seedReferenceExchangeRate();
        $this->suppliers = $this->seedSuppliers();
        $this->conceptIds = RateConcept::pluck('id', 'codigo')->all();

        $clients = collect($this->clientData())
            ->mapWithKeys(function (array $data) use ($operators, $admin) {
                $code = $data['code'];
                $contacts = $data['contacts'];
                $registeredBy = $operators[$data['registered_by']] ?? $admin;
                unset($data['code'], $data['contacts'], $data['registered_by']);

                $isApproved = $data['estado_aprobacion'] === Client::APPROVAL_APPROVED;
                $isRejected = $data['estado_aprobacion'] === Client::APPROVAL_REJECTED;

                $client = Client::updateOrCreate(
                    ['ruc' => $data['ruc']],
                    $data + [
                        'registrado_por_id' => $registeredBy->id,
                        'aprobado_por_id' => $isApproved || $isRejected ? $admin->id : null,
                        'aprobado_en' => $isApproved || $isRejected ? now()->subDays(20) : null,
                    ]
                );

                $client->contacts()->delete();

                foreach ($contacts as $index => $contact) {
                    $client->contacts()->create($contact + ['es_principal' => $index === 0]);
                }

                return [$code => $client];
            });

        foreach ($this->shipmentData() as $index => $shipmentData) {
            $client = $clients[$shipmentData['client_code']];
            $owner = $operators[$shipmentData['owner']] ?? $admin;
            $responsibles = $this->responsiblesFor($shipmentData, $operators);
            $isAir = $shipmentData['tipo_carga'] === 'AEREO';

            // Credito heredado del cliente; una RO de alto valor forzada a contado por el Admin.
            $forceCash = $shipmentData['nro_orden_trabajo'] === 'FX-OT-250008';

            $shipment = Shipment::updateOrCreate(
                ['nro_orden_trabajo' => $shipmentData['nro_orden_trabajo']],
                [
                    'client_id' => $client->id,
                    'comercial_id' => $responsibles['comercial']->id,
                    'customer_id' => $responsibles['customer']->id,
                    'operador_id' => $responsibles['operador']?->id,
                    'incoterm' => $isAir ? 'FCA' : ($index % 3 === 0 ? 'CFR' : 'FOB'),
                    'condicion_credito' => $forceCash ? Client::CREDIT_CASH : $client->condicion_credito,
                    'dias_credito' => $forceCash ? null : $client->dias_credito,
                    'credito_forzado' => $forceCash,
                    'routing_order' => $shipmentData['routing_order'],
                    'bl' => $shipmentData['estado_actual'] === 'Creacion Routing Order' ? null : $shipmentData['bl'],
                    'tipo_carga' => $shipmentData['tipo_carga'],
                    'origen' => $shipmentData['origen'],
                    'puerto_carga' => $shipmentData['puerto_carga'],
                    'puerto_descarga' => $shipmentData['puerto_descarga'],
                    'destino_final' => $shipmentData['destino_final'],
                    'naviera' => $shipmentData['naviera'],
                    'consignatario' => $shipmentData['consignatario'],
                    'peso' => $shipmentData['peso'],
                    'volumen' => $shipmentData['volumen'],
                    'fecha_etd' => $shipmentData['fecha_etd'],
                    'fecha_eta' => $shipmentData['fecha_eta'],
                    'fecha_descarga' => $shipmentData['fecha_descarga'],
                    'estado_actual' => $shipmentData['estado_actual'],
                    'estado_endoso' => $shipmentData['estado_endoso'],
                    'fecha_endoso' => $shipmentData['fecha_endoso'],
                    'responsable_endoso_id' => $owner->id,
                    'observacion_endoso' => $shipmentData['observacion_endoso'],
                    'observaciones' => $shipmentData['observaciones'],
                ] + $this->operationalData($shipmentData, $index)
            );

            // Fechas ya validadas: el cliente ve lo mismo que Operaciones.
            ShipmentDateChange::where('shipment_id', $shipment->id)->delete();
            // Plazos de alertas: se parte de cero en cada carga (ver seedAlertExamples).
            // Nave y viaje: grupos para probar la actualizacion masiva.
            [$nave, $viaje] = self::VESSELS[$shipmentData['nro_orden_trabajo']] ?? [null, null];
            $shipment->forceFill([
                'nave' => $nave,
                'viaje' => $viaje,
                'fecha_etd_cliente' => $shipmentData['fecha_etd'],
                'fecha_eta_cliente' => $shipmentData['fecha_eta'],
                'fecha_eta_original' => $shipmentData['fecha_eta'],
                'cut_off_cumplido_en' => null,
                'cut_off_cumplido_por_id' => null,
                'dias_libres_demurrage' => null,
                'dias_libres_detention' => null,
                'dias_libres_almacen' => null,
            ])->saveQuietly();
            ShipmentAlertState::where('shipment_id', $shipment->id)->delete();
            ShipmentPreAlert::where('shipment_id', $shipment->id)->delete();

            $this->seedContainers($shipment, $shipmentData, $index);

            $this->purgePrivateArtifacts($shipment);
            ShipmentEvent::where('shipment_id', $shipment->id)->delete();
            Provision::where('shipment_id', $shipment->id)->delete();
            Invoice::where('shipment_id', $shipment->id)->delete();
            Document::where('shipment_id', $shipment->id)->delete();
            ShipmentUserAssignment::where('shipment_id', $shipment->id)->delete();

            foreach ($this->buildEventsForShipment($shipmentData, $owner->id) as $event) {
                ShipmentEvent::create([
                    'shipment_id' => $shipment->id,
                    'estado' => $event['estado'],
                    'tipo' => ShipmentEvent::TYPE_STATE,
                    'visible_cliente' => true,
                    'fecha' => $event['fecha'],
                    'descripcion' => $event['descripcion'],
                    'user_id' => $owner->id,
                ]);
            }

            $this->seedDocuments($shipment, $shipmentData, $owner);
            // Tipo de cambio congelado al crear la RO (historico, varia por RO).
            $shipment->forceFill(['tipo_cambio' => round(3.70 + ($index % 5) * 0.015, 4)])->saveQuietly();

            $costs = $this->seedProvisions($shipment, $shipmentData);
            $this->seedRates($shipment, $shipmentData, $costs);
            $this->seedInvoices($shipment, $shipmentData);
            $this->seedAssignments($shipment, $shipmentData, $operators, $admin);
        }

        $this->seedPendingDateChanges($operators);
        $this->seedClientNotes();
        $this->seedDelayedExample();
        $this->seedAlertExamples();
        $this->seedPreAlertExamples();
    }

    /**
     * Pre-alerta de llegada, relativa a hoy:
     * - FX-OT-250003: ETA en 2 dias (amarilla), con agencia de aduana y
     *   transportista en sus provisiones: lista para enviar.
     * - FX-OT-250008 (al contado, ETA vencida): retenida por facturas por pagar.
     */
    private function seedPreAlertExamples(): void
    {
        $find = fn (string $ot) => Shipment::where('nro_orden_trabajo', $ot)->first();
        $eta = now()->addDays(2)->toDateString();

        $ready = $find('FX-OT-250003');
        $ready?->forceFill(['fecha_eta' => $eta, 'fecha_eta_cliente' => $eta, 'fecha_eta_original' => $eta])->saveQuietly();

        foreach (array_filter([$ready, $find('FX-OT-250008')]) as $shipment) {
            $sequence = (int) preg_replace('/\D+/', '', $shipment->nro_orden_trabajo);

            foreach ([
                ['Agencia de Aduanas Callao Express', 'CUSTOMS', 'Despacho de importacion', 380.00, 'F010'],
                ['Transportes Lima Cargo', 'LOCAL_TRANSPORT', 'Traslado del puerto al almacen del cliente', 520.00, 'F020'],
            ] as [$supplierName, $concept, $description, $amount, $series]) {
                Provision::create([
                    'shipment_id' => $shipment->id,
                    'supplier_id' => $this->suppliers[$supplierName]->id,
                    'rate_concept_id' => $this->conceptIds[$concept],
                    'descripcion' => $description,
                    'monto' => $amount,
                    'moneda' => 'PEN',
                    'nro_factura_proveedor' => sprintf('%s-%06d', $series, $sequence),
                    'estado' => Provision::STATUS_PENDING,
                    'fecha' => $shipment->fecha_eta->toDateString(),
                ]);
            }
        }

        if ($held = $find('FX-OT-250008')) {
            $held->preAlerts()->create([
                'estado' => ShipmentPreAlert::STATUS_HELD,
                'motivo_retencion' => 'RO al contado: falta pagar la factura F001-'.str_pad((string) preg_replace('/\D+/', '', $held->nro_orden_trabajo), 4, '0', STR_PAD_LEFT).'.',
                'user_id' => $held->customer_id,
            ]);
        }
    }

    /**
     * Una alerta critica de cada tipo y nivel, siempre relativa a hoy:
     * - Cut-off amarillo (36 h) y verde (4 dias), los demas cut-off cumplidos.
     * - Demurrage rojo (vence hoy) y detention amarilla (vence manana).
     * - Almacenaje verde (vence en 3 dias).
     * - ETA retrasada: la crean seedPendingDateChanges (ETA +2/+3/+5 dias).
     * - ETA vencida: la crea seedDelayedExample.
     */
    private function seedAlertExamples(): void
    {
        $find = fn (string $ot) => Shipment::where('nro_orden_trabajo', $ot)->first();
        $today = now()->startOfDay();

        // Cut-off: los que quedaron en el pasado se dan por cumplidos (dato demo realista).
        Shipment::query()
            ->whereNotNull('fecha_cut_off')
            ->where('fecha_cut_off', '<', now())
            ->get()
            ->each(fn (Shipment $s) => $s->forceFill(['cut_off_cumplido_en' => $s->fecha_cut_off, 'cut_off_cumplido_por_id' => $s->operador_id])->saveQuietly());

        $find('FX-OT-250006')?->forceFill(['fecha_cut_off' => now()->addHours(36)->startOfHour(), 'cut_off_cumplido_en' => null, 'cut_off_cumplido_por_id' => null])->saveQuietly();
        $find('FX-OT-250013')?->forceFill(['fecha_cut_off' => $today->copy()->addDays(4)->setTime(18, 0), 'cut_off_cumplido_en' => null, 'cut_off_cumplido_por_id' => null])->saveQuietly();

        // Demurrage: descargado hace 6 dias con 7 libres -> hoy es el dia 7 (vence 23:59).
        if ($demurrage = $find('FX-OT-250007')) {
            $demurrage->forceFill([
                'fecha_descarga' => $today->copy()->subDays(6)->toDateString(),
                'dias_libres_demurrage' => 7,
                'dias_libres_detention' => 14,
            ])->saveQuietly();
            $demurrage->containers()->update(['fecha_retiro' => null, 'fecha_devolucion' => null]);
        }

        // Detention: contenedores retirados hace 12 dias con 14 libres -> vence manana.
        if ($detention = $find('FX-OT-250014')) {
            $detention->forceFill([
                'fecha_descarga' => $today->copy()->subDays(14)->toDateString(),
                'dias_libres_demurrage' => 7,
                'dias_libres_detention' => 14,
            ])->saveQuietly();
            $detention->containers()->update(['fecha_retiro' => $today->copy()->subDays(12)->toDateString(), 'fecha_devolucion' => null]);
        }

        // Almacenaje: ingreso hace 3 dias con 7 libres -> vence en 3 dias.
        $find('FX-OT-250004')?->forceFill([
            'fecha_ingreso_almacen' => $today->copy()->subDays(3)->setTime(9, 0),
            'dias_libres_almacen' => 7,
            'almacen_direccionamiento' => 'Talma',
        ])->saveQuietly();
    }

    /** Una RO con ETA vencida sin arribo, para ver el panel de retrasados y las alertas. */
    private function seedDelayedExample(): void
    {
        $shipment = Shipment::where('nro_orden_trabajo', 'FX-OT-250008')->first();
        $eta = now()->subDays(2)->toDateString();

        $shipment?->forceFill([
            'fecha_eta' => $eta,
            'fecha_eta_cliente' => $eta,
            'ubicacion_actual' => 'En espera de atraque en Chancay',
        ])->saveQuietly();
    }

    /** Notas del Customer visibles en el tracking publico. */
    private function seedClientNotes(): void
    {
        $notes = [
            'FX-OT-250001' => 'Tu contenedor fue embarcado sin observaciones. Te avisaremos cuando la naviera confirme el arribo.',
            'FX-OT-250014' => 'Tu carga ingreso al almacen. Estamos coordinando el levante con la agencia de aduanas.',
            'FX-OT-250005' => 'Tu carga aerea llego a Lima y paso el control documentario.',
        ];

        foreach ($notes as $ot => $note) {
            $shipment = Shipment::where('nro_orden_trabajo', $ot)->first();

            if ($shipment) {
                ShipmentEvent::recordClientNote($shipment, $note, $shipment->customer_id);
            }
        }
    }

    /** Tipo de cambio de referencia que copiaran las RO nuevas. */
    private function seedReferenceExchangeRate(): void
    {
        AppSetting::current()->forceFill([
            'tipo_cambio_referencial' => 3.7450,
            'tipo_cambio_actualizado_en' => now(),
        ])->save();

        AppSetting::flushCache();
    }

    /** @return array<string, Supplier> indexados por razon social */
    private function seedSuppliers(): array
    {
        $suppliers = [
            ['COSCO Shipping Lines Peru', 'naviera', '20511000001'],
            ['Maersk Peru', 'naviera', '20511000002'],
            ['Hapag-Lloyd Peru', 'naviera', '20511000003'],
            ['CMA CGM Peru', 'naviera', '20511000004'],
            ['MSC Peru', 'naviera', '20511000005'],
            ['Evergreen Line Peru', 'naviera', '20511000006'],
            ['Lufthansa Cargo', 'aerolinea', null],
            ['Turkish Cargo', 'aerolinea', null],
            ['Cathay Cargo', 'aerolinea', null],
            ['DP World Callao', 'almacen', '20511000010'],
            ['Neptunia', 'almacen', '20511000011'],
            ['Talma Servicios Aeroportuarios', 'almacen', '20511000012'],
            // Con correo: reciben la Pre-alerta de llegada (dominio .test, nunca llega a un buzon real).
            ['Agencia de Aduanas Callao Express', 'agencia_aduana', '20511000013', 'despachos@aduanascallaoexpress.test'],
            ['Transportes Lima Cargo', 'transportista', '20511000014', 'programacion@limacargo.test'],
        ];

        return collect($suppliers)->mapWithKeys(fn (array $supplier) => [
            $supplier[0] => Supplier::updateOrCreate(
                ['razon_social' => $supplier[0]],
                ['tipo' => $supplier[1], 'ruc' => $supplier[2], 'correo' => $supplier[3] ?? null, 'activo' => true]
            ),
        ])->all();
    }

    /** Proveedor del flete segun la naviera/aerolinea del embarque. */
    private function freightSupplierFor(string $carrier): Supplier
    {
        $map = [
            'COSCO' => 'COSCO Shipping Lines Peru', 'APL' => 'CMA CGM Peru', 'CMA CGM' => 'CMA CGM Peru',
            'Maersk' => 'Maersk Peru', 'Hapag-Lloyd' => 'Hapag-Lloyd Peru', 'MSC' => 'MSC Peru',
            'Evergreen' => 'Evergreen Line Peru', 'Lufthansa Cargo' => 'Lufthansa Cargo',
            'Turkish Cargo' => 'Turkish Cargo', 'Cathay Cargo' => 'Cathay Cargo',
        ];

        return $this->suppliers[$map[$carrier] ?? 'COSCO Shipping Lines Peru'];
    }

    /**
     * Tarifas de venta pactadas: el costo de cada concepto mas un margen
     * comercial, y servicios propios (aduana, transporte, emision de BL).
     */
    private function seedRates(Shipment $shipment, array $shipmentData, array $costs): void
    {
        ShipmentRate::where('shipment_id', $shipment->id)->delete();

        $isAir = $shipmentData['tipo_carga'] === 'AEREO';
        $rates = [];

        foreach ($costs as $cost) {
            $markup = $cost['codigo'] === 'OCEAN_FREIGHT' || $cost['codigo'] === 'AIR_FREIGHT' ? 1.18 : 1.25;
            $rates[] = [$cost['codigo'], round($cost['monto'] * $markup + ($cost['moneda'] === 'USD' ? 120 : 0), 2), $cost['moneda']];
        }

        $rates[] = ['CUSTOMS', 450.00, 'PEN'];
        $rates[] = ['LOCAL_TRANSPORT', $isAir ? 280.00 : 650.00, 'PEN'];

        if (! $isAir) {
            $rates[] = ['BL_FEE', 75.00, 'USD'];
        }

        foreach ($rates as [$code, $amount, $currency]) {
            $shipment->rates()->create([
                'rate_concept_id' => $this->conceptIds[$code],
                'monto' => $amount,
                'moneda' => $currency,
            ]);
        }
    }

    /** Cut-off, agente, "donde esta ahora" y tarja segun el estado de la carga. */
    private function operationalData(array $shipmentData, int $index): array
    {
        $state = $shipmentData['estado_actual'];
        $stateIndex = array_search($state, self::TRACKING_FLOW, true);
        $isAir = $shipmentData['tipo_carga'] === 'AEREO';
        $warehouses = ['Neptunia', 'Ransa Callao', 'DP World Callao', 'Alma Peru'];
        $warehouse = $warehouses[$index % count($warehouses)];
        $agents = ['Shenzhen Global Logistics', 'Santos Cargo Agents', 'Gulf Forwarding LLC', 'Pacific Link Asia', 'EuroFreight GmbH'];
        $etd = Carbon::parse($shipmentData['fecha_etd']);
        $hasArrivedToWarehouse = $stateIndex >= array_search('Ingreso a almacen', self::TRACKING_FLOW, true);
        $tarjaDate = Carbon::parse($shipmentData['fecha_descarga'] ?? $shipmentData['fecha_eta'])->addDay()->setTime(10, 30);

        $location = match (true) {
            in_array($state, ['Creacion Routing Order', 'Booking Confirmado'], true) => 'En origen: '.$shipmentData['puerto_carga'],
            in_array($state, ['ATD', 'En transito'], true) => ($isAir ? 'En vuelo rumbo a ' : 'En navegacion rumbo a ').$shipmentData['puerto_descarga'],
            in_array($state, ['Transmision Aduanas', 'ETA'], true) => 'Proximo a arribar a '.$shipmentData['puerto_descarga'],
            in_array($state, ['Arribo a puerto', 'Descarga'], true) => 'En terminal de '.$shipmentData['puerto_descarga'],
            in_array($state, ['Ingreso a almacen', 'Retiro de almacen'], true) => 'Almacen '.$warehouse.', Callao',
            default => 'Entregado en '.$shipmentData['destino_final'],
        };

        return [
            'agente_origen' => $agents[$index % count($agents)],
            'fecha_cut_off' => $isAir ? null : $etd->copy()->subDays(2)->setTime(18, 0),
            'ubicacion_actual' => $location,
            // Coordenadas de ejemplo para cargas en navegacion (Pacifico Sur).
            'ubicacion_latitud' => $state === 'En transito' && ! $isAir ? -6.5 - ($index % 4) : null,
            'ubicacion_longitud' => $state === 'En transito' && ! $isAir ? -84.2 + ($index % 3) : null,
            'almacen_direccionamiento' => $stateIndex >= array_search('Arribo a puerto', self::TRACKING_FLOW, true) ? $warehouse : null,
            'fecha_tarja' => $hasArrivedToWarehouse ? $tarjaDate : null,
            'bultos_tarjados' => $hasArrivedToWarehouse ? 20 + ($index * 7) : null,
            'peso_tarjado_kg' => $hasArrivedToWarehouse ? $shipmentData['peso'] : null,
            'fecha_ingreso_almacen' => $hasArrivedToWarehouse ? $tarjaDate->copy()->addHours(3) : null,
            'observacion_tarja' => $hasArrivedToWarehouse && $index % 4 === 0 ? 'Un bulto con embalaje humedo, se dejo constancia en el acta.' : null,
        ];
    }

    /** Contenedores con numero ISO 6346 valido (digito de control calculado). */
    private function seedContainers(Shipment $shipment, array $shipmentData, int $index): void
    {
        $shipment->containers()->delete();

        if ($shipmentData['tipo_carga'] !== 'FCL') {
            return;
        }

        $owners = [
            'COSCO' => 'CSNU', 'Maersk' => 'MSKU', 'CMA CGM' => 'CMAU', 'Wan Hai' => 'WHSU', 'Yang Ming' => 'YMLU',
            'APL' => 'APZU', 'ZIM' => 'ZIMU', 'Evergreen' => 'EGHU', 'MSC' => 'MSCU', 'Hapag-Lloyd' => 'HLXU',
        ];
        $owner = $owners[$shipmentData['naviera']] ?? 'FXLU';
        $count = $index % 3 === 0 ? 2 : 1;

        for ($i = 0; $i < $count; $i++) {
            $serial = str_pad((string) (300000 + ($index * 1000) + $i), 6, '0', STR_PAD_LEFT);
            $firstTen = $owner.$serial;

            $shipment->containers()->create([
                'numero' => $firstTen.ShipmentContainer::checkDigit($firstTen),
                'tipo' => $i === 0 ? '40HC' : '20GP',
                'precinto' => 'PR'.str_pad((string) (($index + 1) * 1000 + $i), 6, '0', STR_PAD_LEFT),
                'bultos' => 10 + $index + $i,
                'peso_kg' => round($shipmentData['peso'] / $count, 2),
                'volumen_cbm' => round($shipmentData['volumen'] / $count, 2),
            ]);
        }
    }

    /**
     * Cambios de ETA informados por Operaciones que esperan la validacion
     * del Customer (para probar el flujo de aprobacion).
     */
    private function seedPendingDateChanges(array $users): void
    {
        $changes = [
            'FX-OT-250001' => ['fecha_eta' => 3],   // retraso de 3 dias
            'FX-OT-250011' => ['fecha_eta' => 5],   // retraso de 5 dias
            'FX-OT-250006' => ['fecha_etd' => -1],  // adelanto de 1 dia (Customer: Maria)
            'FX-OT-250009' => ['fecha_eta' => 2],   // retraso de 2 dias (Customer: Diego)
        ];

        foreach ($changes as $ot => $fields) {
            $shipment = Shipment::where('nro_orden_trabajo', $ot)->first();

            if (! $shipment) {
                continue;
            }

            auth()->setUser($shipment->operador ?? $users['carla_operaciones']);

            foreach ($fields as $field => $days) {
                $shipment->{$field} = $shipment->{$field}->copy()->addDays($days);
            }

            $shipment->save();
        }

        auth()->forgetUser();
    }

    private function clientData(): array
    {
        $contacts = fn (string $domain, string $logisticsName, string $billingName) => [
            [
                'nombre' => $logisticsName,
                'cargo' => 'Jefe de Logistica',
                'correo' => 'logistica@'.$domain,
                'telefono' => '987654321',
                'area' => 'logistica',
                'recibe_tracking' => true,
                'recibe_facturacion' => false,
            ],
            [
                'nombre' => $billingName,
                'cargo' => 'Analista de Cuentas por Pagar',
                'correo' => 'facturacion@'.$domain,
                'telefono' => '987654322',
                'area' => 'facturacion',
                'recibe_tracking' => false,
                'recibe_facturacion' => true,
            ],
        ];

        return [
            [
                'code' => 'andean',
                'razon_social' => 'Andean Retail Peru S.A.C.',
                'ruc' => '20600010001',
                'correo' => 'contacto@andeanretail.pe',
                'telefono' => '014010101',
                'direccion' => 'Av. Javier Prado 4200, Santiago de Surco, Lima',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_APPROVED,
                'condicion_credito' => Client::CREDIT_TERM,
                'dias_credito' => 30,
                'linea_credito' => 80000,
                'moneda_linea_credito' => 'USD',
                'registered_by' => 'juan_comercial',
                'contacts' => $contacts('andeanretail.pe', 'Rosa Quispe', 'Martin Salas'),
            ],
            [
                'code' => 'minera',
                'razon_social' => 'Minera Pacifico S.A.',
                'ruc' => '20600010002',
                'correo' => 'contacto@minerapacifico.pe',
                'telefono' => '014010102',
                'direccion' => 'Av. El Derby 255, Santiago de Surco, Lima',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_APPROVED,
                'condicion_credito' => Client::CREDIT_TERM,
                'dias_credito' => 60,
                'linea_credito' => 250000,
                'moneda_linea_credito' => 'USD',
                'registered_by' => 'ana_comercial',
                'contacts' => $contacts('minerapacifico.pe', 'Jorge Huaman', 'Lucia Torres'),
            ],
            [
                'code' => 'agro',
                'razon_social' => 'Agroexportadora Santa Maria S.A.C.',
                'ruc' => '20600010003',
                'correo' => 'contacto@agrosantamaria.pe',
                'telefono' => '014010103',
                'direccion' => 'Panamericana Sur Km 45, Lurin, Lima',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_APPROVED,
                'condicion_credito' => Client::CREDIT_CASH,
                'dias_credito' => null,
                'linea_credito' => null,
                'moneda_linea_credito' => 'USD',
                'registered_by' => 'juan_comercial',
                'contacts' => $contacts('agrosantamaria.pe', 'Carlos Ramos', 'Elena Vargas'),
            ],
            [
                'code' => 'industrial',
                'razon_social' => 'Industrial Los Andes S.A.C.',
                'ruc' => '20600010004',
                'correo' => 'contacto@industriallosandes.pe',
                'telefono' => '014010104',
                'direccion' => 'Av. Argentina 3250, Callao',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_APPROVED,
                'condicion_credito' => Client::CREDIT_TERM,
                'dias_credito' => 45,
                'linea_credito' => 350000,
                'moneda_linea_credito' => 'PEN',
                'registered_by' => 'ana_comercial',
                'contacts' => $contacts('industriallosandes.pe', 'Pedro Castillo', 'Silvia Mendoza'),
            ],
            [
                'code' => 'medic',
                'razon_social' => 'Medic Global Peru S.A.C.',
                'ruc' => '20600010005',
                'correo' => 'contacto@medicglobal.pe',
                'telefono' => '014010105',
                'direccion' => 'Av. Canada 1790, La Victoria, Lima',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_APPROVED,
                'condicion_credito' => Client::CREDIT_CASH,
                'dias_credito' => null,
                'linea_credito' => null,
                'moneda_linea_credito' => 'USD',
                'registered_by' => 'juan_comercial',
                'contacts' => $contacts('medicglobal.pe', 'Andrea Flores', 'Raul Paredes'),
            ],
            [
                'code' => 'textil',
                'razon_social' => 'Textiles Pacifico Sur S.A.C.',
                'ruc' => '20600010006',
                'correo' => 'contacto@textilespacifico.pe',
                'telefono' => '014010106',
                'direccion' => 'Av. Gamarra 850, La Victoria, Lima',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_PENDING,
                'condicion_credito' => Client::CREDIT_TERM,
                'dias_credito' => 30,
                'linea_credito' => 40000,
                'moneda_linea_credito' => 'USD',
                'registered_by' => 'juan_comercial',
                'contacts' => $contacts('textilespacifico.pe', 'Miguel Rojas', 'Carmen Diaz'),
            ],
            [
                'code' => 'norte',
                'razon_social' => 'Importadora Lima Norte E.I.R.L.',
                'ruc' => '20600010007',
                'correo' => 'contacto@limanorte.pe',
                'telefono' => '014010107',
                'direccion' => 'Av. Tupac Amaru 1200, Independencia, Lima',
                'estado' => 'activo',
                'estado_aprobacion' => Client::APPROVAL_REJECTED,
                'observacion_aprobacion' => 'El RUC figura como no habido en SUNAT. Validar datos fiscales.',
                'condicion_credito' => Client::CREDIT_TERM,
                'dias_credito' => 60,
                'linea_credito' => 100000,
                'moneda_linea_credito' => 'USD',
                'registered_by' => 'ana_comercial',
                'contacts' => $contacts('limanorte.pe', 'Victor Leon', 'Patricia Cruz'),
            ],
        ];
    }

    /**
     * Responsables por RO: el comercial que vendio la cuenta, el Customer que
     * la atiende y el operador que ejecuta.
     */
    private function responsiblesFor(array $shipmentData, array $users): array
    {
        $commercialByClient = [
            'andean' => 'juan_comercial',
            'minera' => 'ana_comercial',
            'agro' => 'juan_comercial',
            'industrial' => 'ana_comercial',
            'medic' => 'juan_comercial',
        ];

        $customerByClient = [
            'andean' => 'maria_customer',
            'minera' => 'maria_customer',
            'agro' => 'diego_customer',
            'industrial' => 'diego_customer',
            'medic' => 'diego_customer',
        ];

        $operator = str_ends_with($shipmentData['owner'], '_operaciones')
            ? $users[$shipmentData['owner']]
            : $users['carla_operaciones'];

        return [
            'comercial' => $users[$commercialByClient[$shipmentData['client_code']]],
            'customer' => $users[$customerByClient[$shipmentData['client_code']]],
            // Una RO recien creada todavia no tiene operador asignado.
            'operador' => $shipmentData['estado_actual'] === 'Creacion Routing Order' ? null : $operator,
        ];
    }

    private function shipmentData(): array
    {
        return [
            $this->shipment('FX-OT-250001', 'FX-RO-250001', 'OOLU9054101', 'andean', 'FCL', 'China', 'Shanghai', 'Callao', 'Lima, Peru', 'COSCO', 'Andean Retail Peru S.A.C.', 18250.00, 67.40, '2026-04-04', '2026-05-07', null, 'En transito', 'en_proceso', '2026-04-15', 'Contenedor con linea blanca y pequenos electrodomesticos para campana de invierno.', 'carla_operaciones'),
            $this->shipment('FX-OT-250002', 'FX-RO-250002', 'MSKU4432198', 'industrial', 'FCL', 'Brasil', 'Santos', 'Callao', 'Callao, Peru', 'Maersk', 'Industrial Los Andes S.A.C.', 21400.00, 58.10, '2026-04-12', '2026-05-03', '2026-05-04', 'Descarga', 'pendiente', null, 'Bobinas de acero galvanizado para produccion metalmecanica.', 'luis_operaciones'),
            $this->shipment('FX-OT-250003', 'FX-RO-250003', 'HLCU7719004', 'minera', 'LCL', 'Estados Unidos', 'Houston', 'Chancay', 'Lima, Peru', 'Hapag-Lloyd', 'Minera Pacifico S.A.', 8450.00, 24.80, '2026-04-15', '2026-05-09', null, 'ATD', 'pendiente', null, 'Repuestos criticos para mantenimiento de planta concentradora.', 'carla_operaciones'),
            $this->shipment('FX-OT-250004', 'FX-RO-250004', 'CMAU5528703', 'agro', 'FCL', 'Mexico', 'Manzanillo', 'Callao', 'Ica, Peru', 'CMA CGM', 'Agroexportadora Santa Maria S.A.C.', 19800.00, 61.30, '2026-04-02', '2026-04-28', '2026-05-01', 'Ingreso a almacen', 'completado', '2026-04-20', 'Material de empaque, pallets plasticos y etiquetas para exportacion.', 'juan_comercial'),
            $this->shipment('FX-OT-250005', 'FX-RO-250005', 'EGLV6625107', 'medic', 'AEREO', 'Alemania', 'Frankfurt', 'Callao', 'Lima, Peru', 'Lufthansa Cargo', 'Medic Global Peru S.A.C.', 1280.00, 9.50, '2026-05-01', '2026-05-03', '2026-05-03', 'Retiro de almacen', 'en_proceso', '2026-05-02', 'Equipos medicos y repuestos con prioridad alta.', 'carla_operaciones'),
            $this->shipment('FX-OT-250006', 'FX-RO-250006', 'ONEY3307715', 'andean', 'LCL', 'India', 'Nhava Sheva', 'Chancay', 'Lima, Peru', 'ONE', 'Andean Retail Peru S.A.C.', 6230.00, 19.70, '2026-04-22', '2026-05-24', null, 'Booking Confirmado', 'pendiente', null, 'Textiles y accesorios de temporada para retail multiformato.', 'juan_comercial'),
            $this->shipment('FX-OT-250007', 'FX-RO-250007', 'WHSU5098731', 'industrial', 'FCL', 'Vietnam', 'Ho Chi Minh', 'Callao', 'Arequipa, Peru', 'Wan Hai', 'Industrial Los Andes S.A.C.', 17340.00, 55.60, '2026-03-28', '2026-04-30', '2026-05-02', 'Arribo a puerto', 'pendiente', null, 'Maquinaria liviana, herramientas y componentes industriales.', 'luis_operaciones'),
            $this->shipment('FX-OT-250008', 'FX-RO-250008', 'YMLU7811220', 'minera', 'FCL', 'Corea del Sur', 'Busan', 'Chancay', 'Lima, Peru', 'Yang Ming', 'Minera Pacifico S.A.', 22600.00, 64.90, '2026-04-09', '2026-05-05', null, 'ETA', 'en_proceso', '2026-04-29', 'Piezas sobredimensionadas y valvulas industriales para mina.', 'carla_operaciones'),
            $this->shipment('FX-OT-250009', 'FX-RO-250009', 'MEDU4688305', 'agro', 'LCL', 'Espana', 'Valencia', 'Callao', 'Piura, Peru', 'MSC', 'Agroexportadora Santa Maria S.A.C.', 4970.00, 17.20, '2026-04-18', '2026-05-11', null, 'Transmision Aduanas', 'completado', '2026-05-01', 'Fertilizantes foliares, sensores y kits de riego tecnificado.', 'juan_comercial'),
            $this->shipment('FX-OT-250010', 'FX-RO-250010', 'SUDU9182076', 'medic', 'AEREO', 'Turquia', 'Estambul', 'Callao', 'Lima, Peru', 'Turkish Cargo', 'Medic Global Peru S.A.C.', 920.00, 6.40, '2026-05-02', '2026-05-04', '2026-05-04', 'Entregado', 'completado', '2026-05-03', 'Insumos hospitalarios y material de diagnostico.', 'luis_operaciones'),
            $this->shipment('FX-OT-250011', 'FX-RO-250011', 'APZU3911054', 'andean', 'FCL', 'China', 'Ningbo', 'Chancay', 'Lima, Peru', 'APL', 'Andean Retail Peru S.A.C.', 20460.00, 68.10, '2026-04-11', '2026-05-12', null, 'En transito', 'pendiente', null, 'Muebles RTA, sillas y decoracion para nueva apertura de tiendas.', 'carla_operaciones'),
            $this->shipment('FX-OT-250012', 'FX-RO-250012', 'ZIMU7724419', 'agro', 'FCL', 'Brasil', 'Paranagua', 'Callao', 'Trujillo, Peru', 'ZIM', 'Agroexportadora Santa Maria S.A.C.', 19110.00, 59.80, '2026-03-20', '2026-04-18', '2026-04-21', 'Finalizado', 'completado', '2026-04-10', 'Carga de insumos agricolas ya recepcionada y cerrada.', 'juan_comercial'),
            $this->shipment('FX-OT-250013', 'FX-RO-250013', 'TGBU5512903', 'industrial', 'LCL', 'Singapur', 'Singapore', 'Chancay', 'Lima, Peru', 'PIL', 'Industrial Los Andes S.A.C.', 5520.00, 21.10, '2026-04-27', '2026-05-19', null, 'Creacion Routing Order', 'pendiente', null, 'Componentes electronicos para automatizacion industrial.', 'luis_operaciones'),
            $this->shipment('FX-OT-250014', 'FX-RO-250014', 'FSCU8904512', 'minera', 'FCL', 'Chile', 'San Antonio', 'Callao', 'Lima, Peru', 'Evergreen', 'Minera Pacifico S.A.', 20880.00, 60.50, '2026-04-25', '2026-05-02', '2026-05-03', 'Ingreso a almacen', 'en_proceso', '2026-05-01', 'Reactivos y suministros para operacion minera.', 'carla_operaciones'),
            $this->shipment('FX-OT-250015', 'FX-RO-250015', 'KKFU3301987', 'medic', 'AEREO', 'China', 'Guangzhou', 'Callao', 'Lima, Peru', 'Cathay Cargo', 'Medic Global Peru S.A.C.', 1460.00, 8.30, '2026-04-30', '2026-05-02', '2026-05-02', 'Entregado', 'completado', '2026-05-01', 'Lote de monitores de signos vitales y accesorios clinicos.', 'luis_operaciones'),
        ];
    }

    private function shipment(
        string $ot,
        string $routingOrder,
        string $bl,
        string $clientCode,
        string $tipoCarga,
        string $origen,
        string $puertoCarga,
        string $puertoDescarga,
        string $destinoFinal,
        string $naviera,
        string $consignatario,
        float $peso,
        float $volumen,
        string $fechaEtd,
        string $fechaEta,
        ?string $fechaDescarga,
        string $estadoActual,
        string $estadoEndoso,
        ?string $fechaEndoso,
        string $observaciones,
        string $owner
    ): array {
        // Las fechas de ejemplo se escribieron alrededor del 05/05/2026; se
        // desplazan a la fecha actual para que el dashboard del mes, los
        // retrasos y las alertas tengan sentido el dia que se ejecuta el seeder.
        [$fechaEtd, $fechaEta, $fechaDescarga, $fechaEndoso] = array_map(
            fn (?string $date) => $this->shiftToToday($date),
            [$fechaEtd, $fechaEta, $fechaDescarga, $fechaEndoso]
        );

        return [
            'nro_orden_trabajo' => $ot,
            'routing_order' => $routingOrder,
            'bl' => $bl,
            'client_code' => $clientCode,
            'tipo_carga' => $tipoCarga,
            'origen' => $origen,
            'puerto_carga' => $puertoCarga,
            'puerto_descarga' => $puertoDescarga,
            'destino_final' => $destinoFinal,
            'naviera' => $naviera,
            'consignatario' => $consignatario,
            'peso' => $peso,
            'volumen' => $volumen,
            'fecha_etd' => $fechaEtd,
            'fecha_eta' => $fechaEta,
            'fecha_descarga' => $fechaDescarga,
            'estado_actual' => $estadoActual,
            'estado_endoso' => $estadoEndoso,
            'fecha_endoso' => $fechaEndoso,
            'observacion_endoso' => $fechaEndoso ? 'Endoso revisado por el equipo de operaciones.' : null,
            'observaciones' => $observaciones,
            'owner' => $owner,
        ];
    }

    private function shiftToToday(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $offsetDays = (int) Carbon::parse('2026-05-05')->diffInDays(now()->startOfDay(), false);

        return Carbon::parse($date)->addDays($offsetDays)->toDateString();
    }

    private function demoOperators($clients, User $admin): array
    {
        return [
            'admin' => $admin,
            'carla_operaciones' => User::updateOrCreate(
                ['email' => 'operaciones1@forcex.test'],
                [
                    'name' => 'Carla Operaciones',
                    'username' => 'carla_operaciones',
                    'password' => 'Demo123456',
                    'role' => User::ROLE_OPERACIONES,
                    'estado' => User::ESTADO_ACTIVO,
                    'client_id' => null,
                    'email_verified_at' => now(),
                ]
            ),
            'luis_operaciones' => User::updateOrCreate(
                ['email' => 'operaciones2@forcex.test'],
                [
                    'name' => 'Luis Operaciones',
                    'username' => 'luis_operaciones',
                    'password' => 'Demo123456',
                    'role' => User::ROLE_OPERACIONES,
                    'estado' => User::ESTADO_ACTIVO,
                    'client_id' => null,
                    'email_verified_at' => now(),
                ]
            ),
            'juan_comercial' => User::updateOrCreate(
                ['email' => 'comercial1@forcex.test'],
                [
                    'name' => 'Juan Comercial',
                    'username' => 'juan_comercial',
                    'password' => 'Demo123456',
                    'role' => User::ROLE_COMERCIAL,
                    'estado' => User::ESTADO_ACTIVO,
                    'client_id' => null,
                    'email_verified_at' => now(),
                ]
            ),
            'ana_comercial' => User::updateOrCreate(
                ['email' => 'comercial2@forcex.test'],
                [
                    'name' => 'Ana Comercial',
                    'username' => 'ana_comercial',
                    'password' => 'Demo123456',
                    'role' => User::ROLE_COMERCIAL,
                    'estado' => User::ESTADO_ACTIVO,
                    'client_id' => null,
                    'email_verified_at' => now(),
                ]
            ),
            'maria_customer' => User::updateOrCreate(
                ['email' => 'customer1@forcex.test'],
                [
                    'name' => 'Maria Customer',
                    'username' => 'maria_customer',
                    'password' => 'Demo123456',
                    'role' => User::ROLE_CUSTOMER,
                    'estado' => User::ESTADO_ACTIVO,
                    'client_id' => null,
                    'email_verified_at' => now(),
                ]
            ),
            'diego_customer' => User::updateOrCreate(
                ['email' => 'customer2@forcex.test'],
                [
                    'name' => 'Diego Customer',
                    'username' => 'diego_customer',
                    'password' => 'Demo123456',
                    'role' => User::ROLE_CUSTOMER,
                    'estado' => User::ESTADO_ACTIVO,
                    'client_id' => null,
                    'email_verified_at' => now(),
                ]
            ),
        ];
    }

    private function seedDocuments(Shipment $shipment, array $shipmentData, User $owner): void
    {
        $documents = [
            [
                'categoria' => Document::CATEGORY_OPERATIVO,
                'tipo_documento' => 'BL',
                'nombre_original' => $shipment->bl.'.pdf',
                'contenido' => $this->pdfContent('BL', $shipment, 'Bill of Lading operativo del embarque.'),
            ],
            [
                'categoria' => Document::CATEGORY_OPERATIVO,
                'tipo_documento' => 'Commercial Invoice',
                'nombre_original' => 'commercial-invoice-'.$shipment->nro_orden_trabajo.'.pdf',
                'contenido' => $this->pdfContent('Commercial Invoice', $shipment, 'Sustento comercial de la carga declarada.'),
            ],
        ];

        // Validacion documental: antes de zarpar el Customer aun revisa lo que envio el cliente.
        $beforeDeparture = in_array($shipmentData['estado_actual'], ['Creacion Routing Order', 'Booking Confirmado'], true);
        $documents[1]['validacion'] = $beforeDeparture ? Document::VALIDATION_PENDING : Document::VALIDATION_APPROVED;

        if ($beforeDeparture && $shipment->tipo_carga !== 'AEREO') {
            $documents[] = [
                'categoria' => Document::CATEGORY_OPERATIVO,
                'tipo_documento' => 'Borrador de BL',
                'nombre_original' => 'borrador-bl-'.$shipment->nro_orden_trabajo.'.pdf',
                'contenido' => $this->pdfContent('Borrador de BL', $shipment, 'Borrador enviado por la naviera para revision del cliente.'),
                'validacion' => Document::VALIDATION_PENDING,
            ];
            $documents[] = [
                'categoria' => Document::CATEGORY_OPERATIVO,
                'tipo_documento' => 'Packing List',
                'nombre_original' => 'packing-list-'.$shipment->nro_orden_trabajo.'.pdf',
                'contenido' => $this->pdfContent('Packing List', $shipment, 'Detalle de bultos enviado por el cliente.'),
                'validacion' => Document::VALIDATION_OBSERVED,
                'observacion' => 'El peso bruto no coincide con la Commercial Invoice.',
            ];
        }

        if (in_array($shipmentData['estado_actual'], ['Transmision Aduanas', 'ETA', 'Arribo a puerto', 'Descarga', 'Ingreso a almacen', 'Retiro de almacen', 'Entregado', 'Finalizado'], true)) {
            $documents[] = [
                'categoria' => Document::CATEGORY_ADUANAS,
                'tipo_documento' => 'DAM',
                'nombre_original' => 'dam-'.$shipment->nro_orden_trabajo.'.pdf',
                'contenido' => $this->pdfContent('DAM', $shipment, 'Declaracion aduanera asociada al arribo.'),
            ];
        }

        if ($shipmentData['fecha_endoso']) {
            $documents[] = [
                'categoria' => Document::CATEGORY_ENDOSO,
                'tipo_documento' => 'BL Endosado',
                'nombre_original' => 'bl-endosado-'.$shipment->nro_orden_trabajo.'.pdf',
                'contenido' => $this->pdfContent('BL Endosado', $shipment, 'Documento de endoso revisado por operaciones.'),
            ];
        }

        if (in_array($shipmentData['estado_actual'], ['Entregado', 'Finalizado', 'Ingreso a almacen', 'Retiro de almacen'], true)) {
            $documents[] = [
                'categoria' => Document::CATEGORY_FACTURACION,
                'tipo_documento' => 'Sustento de cobro',
                'nombre_original' => 'sustento-cobro-'.$shipment->nro_orden_trabajo.'.pdf',
                'contenido' => $this->pdfContent('Sustento de cobro', $shipment, 'Consolidado de servicios facturables del embarque.'),
            ];
        }

        foreach ($documents as $index => $documentData) {
            $path = $this->storePrivatePdf(
                'embarques/'.date('Y').'/'.Str::slug($shipment->nro_orden_trabajo),
                $shipment->nro_orden_trabajo.'-'.Str::slug($documentData['tipo_documento']).'-'.($index + 1).'.pdf',
                $documentData['contenido']
            );

            Document::create([
                'shipment_id' => $shipment->id,
                'categoria' => $documentData['categoria'],
                'tipo_documento' => $documentData['tipo_documento'],
                'nombre_original' => $documentData['nombre_original'],
                'nombre_archivo' => basename($path),
                'ruta' => $path,
                'peso' => strlen($documentData['contenido']),
                'mime_type' => 'application/pdf',
                'user_id' => $owner->id,
            ] + $this->validationData($shipment, $documentData));
        }
    }

    /** Estado de validacion de un documento demo (solo para los tipos validables). */
    private function validationData(Shipment $shipment, array $documentData): array
    {
        $status = $documentData['validacion'] ?? null;

        if ($status === null || $status === Document::VALIDATION_PENDING) {
            return $status ? ['estado_validacion' => $status] : [];
        }

        return [
            'estado_validacion' => $status,
            'observacion_validacion' => $documentData['observacion'] ?? null,
            'validado_por_id' => $shipment->customer_id,
            'validado_en' => now()->subDay(),
        ];
    }

    /**
     * Costos de proveedores (flete en USD, gastos locales en PEN).
     *
     * @return array<int, array{codigo: string, monto: float, moneda: string}>
     */
    private function seedProvisions(Shipment $shipment, array $shipmentData): array
    {
        $isAir = $shipmentData['tipo_carga'] === 'AEREO';
        $arrivedStates = ['Arribo a puerto', 'Descarga', 'Ingreso a almacen', 'Retiro de almacen', 'Entregado', 'Finalizado'];
        $warehouseStates = ['Ingreso a almacen', 'Retiro de almacen', 'Entregado', 'Finalizado'];
        $closedStates = ['Entregado', 'Finalizado'];
        $state = $shipmentData['estado_actual'];

        $provisions = [
            [
                'codigo' => $isAir ? 'AIR_FREIGHT' : 'OCEAN_FREIGHT',
                'supplier' => $this->freightSupplierFor($shipmentData['naviera']),
                'descripcion' => 'Flete internacional '.$shipmentData['naviera'],
                'monto' => round(($shipmentData['peso'] * 0.12) + ($shipmentData['volumen'] * 5.5), 2),
                'moneda' => 'USD',
                'pagado' => in_array($state, $arrivedStates, true),
                'fecha' => Carbon::parse($shipmentData['fecha_etd'])->subDay(),
            ],
            [
                'codigo' => 'THC',
                'supplier' => $this->suppliers[$isAir ? 'Talma Servicios Aeroportuarios' : 'DP World Callao'],
                'descripcion' => 'Gastos locales y THC en '.$shipmentData['puerto_descarga'],
                'monto' => round(($shipmentData['volumen'] * 14.2) + 450, 2),
                'moneda' => 'PEN',
                'pagado' => in_array($state, $warehouseStates, true),
                'fecha' => Carbon::parse($shipmentData['fecha_eta']),
            ],
            $isAir
                ? [
                    'codigo' => 'HANDLING',
                    'supplier' => $this->suppliers['Talma Servicios Aeroportuarios'],
                    'descripcion' => 'Recargo por manipulacion aeroportuaria',
                    'monto' => round(($shipmentData['peso'] * 0.65) + 350, 2),
                    'moneda' => 'PEN',
                    'pagado' => in_array($state, $closedStates, true) || $state === 'Retiro de almacen',
                    'fecha' => Carbon::parse($shipmentData['fecha_eta'])->addDay(),
                ]
                : [
                    'codigo' => 'STORAGE',
                    'supplier' => $this->suppliers['Neptunia'],
                    'descripcion' => 'Servicio de almacenaje temporal',
                    'monto' => round(($shipmentData['volumen'] * 6.5) + 320, 2),
                    'moneda' => 'PEN',
                    'pagado' => in_array($state, $closedStates, true),
                    'fecha' => Carbon::parse($shipmentData['fecha_eta'])->addDays(2),
                ],
        ];

        $sequence = (int) preg_replace('/\D+/', '', $shipment->nro_orden_trabajo);

        foreach ($provisions as $index => $provision) {
            Provision::create([
                'shipment_id' => $shipment->id,
                'supplier_id' => $provision['supplier']->id,
                'rate_concept_id' => $this->conceptIds[$provision['codigo']],
                'descripcion' => $provision['descripcion'],
                'monto' => $provision['monto'],
                'moneda' => $provision['moneda'],
                'nro_factura_proveedor' => sprintf('F%03d-%06d', $index + 1, $sequence),
                'estado' => $provision['pagado'] ? Provision::STATUS_PAID : Provision::STATUS_PENDING,
                'nro_operacion_bancaria' => $provision['pagado'] ? sprintf('OP%08d', $sequence * 10 + $index) : null,
                'fecha_pago' => $provision['pagado'] ? $provision['fecha']->copy()->addDays(3)->toDateString() : null,
                'fecha' => $provision['fecha']->toDateString(),
            ]);
        }

        return collect($provisions)
            ->map(fn (array $provision) => [
                'codigo' => $provision['codigo'],
                'monto' => $provision['monto'],
                'moneda' => $provision['moneda'],
            ])
            ->all();
    }

    private function seedInvoices(Shipment $shipment, array $shipmentData): void
    {
        $invoiceBase = preg_replace('/\D+/', '', $shipment->nro_orden_trabajo);
        $status = match ($shipmentData['estado_actual']) {
            'Finalizado', 'Entregado' => 'pagada',
            'Ingreso a almacen', 'Retiro de almacen', 'Descarga', 'Arribo a puerto' => 'emitida',
            default => 'pendiente',
        };

        $invoiceRows = [
            [
                'numero' => 'F001-'.str_pad((string) $invoiceBase, 4, '0', STR_PAD_LEFT),
                'monto' => round(($shipmentData['peso'] * 0.24) + ($shipmentData['volumen'] * 11.5) + 450, 2),
                'estado' => $status,
                'descripcion' => 'Factura principal de forwarding internacional.',
            ],
        ];

        if (in_array($shipmentData['estado_actual'], ['Ingreso a almacen', 'Retiro de almacen', 'Entregado', 'Finalizado'], true)) {
            $invoiceRows[] = [
                'numero' => 'F002-'.str_pad((string) $invoiceBase, 4, '0', STR_PAD_LEFT),
                'monto' => round(($shipmentData['volumen'] * 4.5) + 180, 2),
                'estado' => $status === 'pagada' ? 'pagada' : 'emitida',
                'descripcion' => 'Factura complementaria por servicios locales y almacenaje.',
            ];
        }

        foreach ($invoiceRows as $invoiceData) {
            $pdf = $this->pdfContent('Factura '.$invoiceData['numero'], $shipment, $invoiceData['descripcion']);
            $path = $this->storePrivatePdf(
                'facturas/'.date('Y'),
                $invoiceData['numero'].'-'.Str::slug($shipment->nro_orden_trabajo).'.pdf',
                $pdf
            );

            Invoice::create([
                'shipment_id' => $shipment->id,
                'numero' => $invoiceData['numero'],
                'monto' => $invoiceData['monto'],
                // F001: forwarding internacional en USD. F002: servicios locales en PEN.
                'moneda' => str_starts_with($invoiceData['numero'], 'F001') ? 'USD' : 'PEN',
                'estado' => $invoiceData['estado'],
                'archivo_pdf' => $path,
            ]);
        }
    }

    private function seedAssignments(Shipment $shipment, array $shipmentData, array $operators, User $admin): void
    {
        $assignmentBlueprints = [
            [
                'username' => $shipmentData['owner'],
                'assignment_type' => ShipmentUserAssignment::TYPE_RESPONSABLE,
            ],
        ];

        $extraWatchers = match ($shipmentData['client_code']) {
            'andean' => ['juan_comercial', 'luis_operaciones'],
            'minera' => ['carla_operaciones', 'juan_comercial'],
            'agro' => ['juan_comercial', 'carla_operaciones'],
            'industrial' => ['luis_operaciones', 'carla_operaciones'],
            'medic' => ['luis_operaciones', 'juan_comercial'],
            default => [],
        };

        foreach ($extraWatchers as $username) {
            if ($username === $shipmentData['owner']) {
                continue;
            }

            $assignmentBlueprints[] = [
                'username' => $username,
                'assignment_type' => ShipmentUserAssignment::TYPE_SEGUIMIENTO,
            ];
        }

        if (in_array($shipmentData['estado_actual'], ['ETA', 'Arribo a puerto', 'Descarga', 'Ingreso a almacen'], true)) {
            $assignmentBlueprints[] = [
                'username' => 'admin',
                'assignment_type' => ShipmentUserAssignment::TYPE_SEGUIMIENTO,
            ];
        }

        collect($assignmentBlueprints)
            ->unique(fn (array $assignment) => $assignment['username'])
            ->each(function (array $assignment) use ($shipment, $operators, $admin) {
                $user = $operators[$assignment['username']] ?? ($assignment['username'] === 'admin' ? $admin : null);

                if (! $user) {
                    return;
                }

                ShipmentUserAssignment::create([
                    'shipment_id' => $shipment->id,
                    'user_id' => $user->id,
                    'assignment_type' => $assignment['assignment_type'],
                    'alerts_enabled' => true,
                ]);
            });
    }

    private function purgePrivateArtifacts(Shipment $shipment): void
    {
        $shipment->loadMissing(['documents', 'invoices']);

        foreach ($shipment->documents as $document) {
            if (Storage::disk('private')->exists($document->ruta)) {
                Storage::disk('private')->delete($document->ruta);
            }
        }

        foreach ($shipment->invoices as $invoice) {
            if ($invoice->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
                Storage::disk('private')->delete($invoice->archivo_pdf);
            }
        }
    }

    private function storePrivatePdf(string $directory, string $filename, string $content): string
    {
        $path = $directory.'/'.$filename;
        Storage::disk('private')->put($path, $content);

        return $path;
    }

    private function pdfContent(string $title, Shipment $shipment, string $body): string
    {
        $lines = [
            '%PDF-1.1',
            'FORCEX LOGISTICS',
            $title,
            'Embarque: '.$shipment->nro_orden_trabajo,
            'Cliente: '.$shipment->client->razon_social,
            'BL: '.$shipment->bl,
            'Routing Order: '.$shipment->routing_order,
            'Estado: '.$shipment->estado_actual,
            $body,
            '%%EOF',
        ];

        return implode("\n", $lines);
    }

    private function buildEventsForShipment(array $shipment, int $userId): array
    {
        $currentIndex = array_search($shipment['estado_actual'], self::TRACKING_FLOW, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $etd = Carbon::parse($shipment['fecha_etd']);
        $baseDate = $etd->copy()->subDays(5);
        $events = [];

        foreach (array_slice(self::TRACKING_FLOW, 0, $currentIndex + 1) as $index => $estado) {
            $eventDate = match ($estado) {
                'Creacion Routing Order' => $baseDate->copy(),
                'Booking Confirmado' => $baseDate->copy()->addDays(2),
                'ATD' => $etd->copy(),
                'En transito' => $etd->copy()->addDays(4),
                'Transmision Aduanas' => Carbon::parse($shipment['fecha_eta'])->copy()->subDays(2),
                'ETA' => Carbon::parse($shipment['fecha_eta'])->copy(),
                'Arribo a puerto' => Carbon::parse($shipment['fecha_eta'])->copy()->addDay(),
                'Descarga' => $shipment['fecha_descarga']
                    ? Carbon::parse($shipment['fecha_descarga'])->copy()
                    : Carbon::parse($shipment['fecha_eta'])->copy()->addDays(2),
                'Ingreso a almacen' => ($shipment['fecha_descarga']
                    ? Carbon::parse($shipment['fecha_descarga'])->copy()
                    : Carbon::parse($shipment['fecha_eta'])->copy()->addDays(2))->addDay(),
                'Retiro de almacen' => ($shipment['fecha_descarga']
                    ? Carbon::parse($shipment['fecha_descarga'])->copy()
                    : Carbon::parse($shipment['fecha_eta'])->copy()->addDays(2))->addDays(2),
                'Entregado' => ($shipment['fecha_descarga']
                    ? Carbon::parse($shipment['fecha_descarga'])->copy()
                    : Carbon::parse($shipment['fecha_eta'])->copy()->addDays(2))->addDays(3),
                'Finalizado' => ($shipment['fecha_descarga']
                    ? Carbon::parse($shipment['fecha_descarga'])->copy()
                    : Carbon::parse($shipment['fecha_eta'])->copy()->addDays(2))->addDays(4),
                default => $baseDate->copy()->addDays($index),
            };

            $events[] = [
                'estado' => $estado,
                'fecha' => $eventDate,
                'descripcion' => $this->eventDescription($estado, $shipment),
                'user_id' => $userId,
            ];
        }

        usort($events, fn (array $a, array $b) => $a['fecha']->getTimestamp() <=> $b['fecha']->getTimestamp());

        return $events;
    }

    private function eventDescription(string $estado, array $shipment): string
    {
        return match ($estado) {
            'Creacion Routing Order' => 'Se registro el embarque y se genero la orden de trabajo interna.',
            'Booking Confirmado' => 'El booking fue confirmado con la linea o aerolinea asignada.',
            'ATD' => 'La carga salio desde '.$shipment['puerto_carga'].' rumbo al Peru.',
            'En transito' => 'La carga se encuentra en transito internacional hacia '.$shipment['puerto_descarga'].'.',
            'Transmision Aduanas' => 'Se transmitio documentacion previa para arribo y nacionalizacion.',
            'ETA' => 'Arribo estimado confirmado para '.$shipment['puerto_descarga'].'.',
            'Arribo a puerto' => 'La carga arribo a terminal portuario en Peru.',
            'Descarga' => 'La carga fue descargada en terminal y quedo pendiente de liberacion.',
            'Ingreso a almacen' => 'La mercaderia ingreso a almacen temporal para aforo o coordinacion.',
            'Retiro de almacen' => 'La unidad fue retirada de almacen para entrega final.',
            'Entregado' => 'La carga fue entregada al consignatario sin observaciones mayores.',
            'Finalizado' => 'Operacion cerrada administrativamente y embarque finalizado.',
            default => 'Actualizacion de tracking registrada.',
        };
    }
}
