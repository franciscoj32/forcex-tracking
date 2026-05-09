<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Provision;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentUserAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoShipmentSeeder extends Seeder
{
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

        $clients = collect($this->clientData())
            ->mapWithKeys(function (array $data) {
                $code = $data['code'];
                unset($data['code']);

                $client = Client::updateOrCreate(
                    ['ruc' => $data['ruc']],
                    $data
                );

                return [$code => $client];
            });

        $operators = $this->demoOperators($clients, $admin);

        foreach ($this->shipmentData() as $shipmentData) {
            $client = $clients[$shipmentData['client_code']];
            $owner = $operators[$shipmentData['owner']] ?? $admin;

            $shipment = Shipment::updateOrCreate(
                ['nro_orden_trabajo' => $shipmentData['nro_orden_trabajo']],
                [
                    'client_id' => $client->id,
                    'routing_order' => $shipmentData['routing_order'],
                    'bl' => $shipmentData['bl'],
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
                ]
            );

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
                    'fecha' => $event['fecha'],
                    'descripcion' => $event['descripcion'],
                    'user_id' => $owner->id,
                ]);
            }

            $this->seedDocuments($shipment, $shipmentData, $owner);
            $this->seedProvisions($shipment, $shipmentData);
            $this->seedInvoices($shipment, $shipmentData);
            $this->seedAssignments($shipment, $shipmentData, $operators, $admin);
        }
    }

    private function clientData(): array
    {
        return [
            [
                'code' => 'andean',
                'razon_social' => 'Andean Retail Peru S.A.C.',
                'ruc' => '20600010001',
                'correo' => 'logistica@andeanretail.pe',
                'telefono' => '014010101',
                'direccion' => 'Av. Javier Prado 4200, Santiago de Surco, Lima',
                'estado' => 'activo',
            ],
            [
                'code' => 'minera',
                'razon_social' => 'Minera Pacifico S.A.',
                'ruc' => '20600010002',
                'correo' => 'comex@minerapacifico.pe',
                'telefono' => '014010102',
                'direccion' => 'Av. El Derby 255, Santiago de Surco, Lima',
                'estado' => 'activo',
            ],
            [
                'code' => 'agro',
                'razon_social' => 'Agroexportadora Santa Maria S.A.C.',
                'ruc' => '20600010003',
                'correo' => 'importaciones@agrosantamaria.pe',
                'telefono' => '014010103',
                'direccion' => 'Panamericana Sur Km 45, Lurin, Lima',
                'estado' => 'activo',
            ],
            [
                'code' => 'industrial',
                'razon_social' => 'Industrial Los Andes S.A.C.',
                'ruc' => '20600010004',
                'correo' => 'supply@industriallosandes.pe',
                'telefono' => '014010104',
                'direccion' => 'Av. Argentina 3250, Callao',
                'estado' => 'activo',
            ],
            [
                'code' => 'medic',
                'razon_social' => 'Medic Global Peru S.A.C.',
                'ruc' => '20600010005',
                'correo' => 'compras@medicglobal.pe',
                'telefono' => '014010105',
                'direccion' => 'Av. Canada 1790, La Victoria, Lima',
                'estado' => 'activo',
            ],
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
            ]);
        }
    }

    private function seedProvisions(Shipment $shipment, array $shipmentData): void
    {
        $provisions = [
            [
                'descripcion' => 'Flete internacional '.$shipmentData['naviera'],
                'monto' => round(($shipmentData['peso'] * 0.12) + ($shipmentData['volumen'] * 5.5), 2),
                'estado' => in_array($shipmentData['estado_actual'], ['Arribo a puerto', 'Descarga', 'Ingreso a almacen', 'Retiro de almacen', 'Entregado', 'Finalizado'], true) ? 'pagado' : 'pendiente',
                'fecha' => Carbon::parse($shipmentData['fecha_etd'])->subDay()->toDateString(),
            ],
            [
                'descripcion' => 'Gastos locales y THC en '.$shipmentData['puerto_descarga'],
                'monto' => round(($shipmentData['volumen'] * 3.8) + 120, 2),
                'estado' => in_array($shipmentData['estado_actual'], ['Ingreso a almacen', 'Retiro de almacen', 'Entregado', 'Finalizado'], true) ? 'pagado' : 'pendiente',
                'fecha' => Carbon::parse($shipmentData['fecha_eta'])->toDateString(),
            ],
        ];

        if ($shipmentData['tipo_carga'] === 'AEREO') {
            $provisions[] = [
                'descripcion' => 'Recargo por manipulacion aeroportuaria',
                'monto' => round(($shipmentData['peso'] * 0.18) + 95, 2),
                'estado' => in_array($shipmentData['estado_actual'], ['Retiro de almacen', 'Entregado', 'Finalizado'], true) ? 'pagado' : 'pendiente',
                'fecha' => Carbon::parse($shipmentData['fecha_eta'])->addDay()->toDateString(),
            ];
        } else {
            $provisions[] = [
                'descripcion' => 'Servicio de almacenaje temporal',
                'monto' => round(($shipmentData['volumen'] * 1.75) + 85, 2),
                'estado' => in_array($shipmentData['estado_actual'], ['Entregado', 'Finalizado'], true) ? 'pagado' : 'pendiente',
                'fecha' => Carbon::parse($shipmentData['fecha_eta'])->addDays(2)->toDateString(),
            ];
        }

        foreach ($provisions as $provision) {
            Provision::create([
                'shipment_id' => $shipment->id,
                'descripcion' => $provision['descripcion'],
                'monto' => $provision['monto'],
                'estado' => $provision['estado'],
                'fecha' => $provision['fecha'],
            ]);
        }
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
