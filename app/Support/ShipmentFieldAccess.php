<?php

namespace App\Support;

use App\Models\Shipment;
use App\Models\User;

/**
 * Matriz central de permisos por campo del embarque (RO).
 *
 * Es la unica fuente de verdad: la usan el controlador (para validar y
 * descartar campos no permitidos) y el formulario (para bloquearlos).
 * Asi el servidor nunca acepta un campo que la vista mostro bloqueado,
 * aunque alguien manipule el formulario.
 */
final class ShipmentFieldAccess
{
    /** Lo que se pacta con el cliente: lo define el Comercial al crear la RO. */
    public const COMERCIAL = [
        'client_id',
        'nro_orden_trabajo',
        'routing_order',
        'tipo_carga',
        'incoterm',
        'origen',
        'destino_final',
        'puerto_carga',
        'puerto_descarga',
        'consignatario',
        'customer_id',
        // Tarifas de venta pactadas (tabla shipment_rates).
        'rates',
    ];

    /** Datos tecnicos reales: duenio Operaciones. */
    public const OPERATIVO = [
        'bl',
        'naviera',
        // Buque y viaje: agrupan las RO para la actualizacion masiva
        'nave',
        'viaje',
        'agente_origen',
        'puerto_carga',
        'puerto_descarga',
        'peso',
        'volumen',
        'fecha_cut_off',
        'fecha_etd',
        'fecha_eta',
        'fecha_descarga',
        // Dias libres pactados en el booking (alertas de demurrage y detention)
        'dias_libres_demurrage',
        'dias_libres_detention',
        'estado_endoso',
        'fecha_endoso',
        'responsable_endoso_id',
        'observacion_endoso',
        // "Donde esta ahora"
        'ubicacion_actual',
        'ubicacion_latitud',
        'ubicacion_longitud',
        // Tarja y almacen de direccionamiento
        'almacen_direccionamiento',
        'fecha_tarja',
        'bultos_tarjados',
        'peso_tarjado_kg',
        'fecha_ingreso_almacen',
        'dias_libres_almacen',
        'observacion_tarja',
    ];

    /** Avance del flujo de tracking. */
    public const TRACKING = ['estado_actual'];

    /** El Customer coordina: decide que operador ejecuta. */
    public const COORDINACION = ['operador_id'];

    /** Solo el Admin puede forzar el credito del embarque. */
    public const CREDITO = ['condicion_credito', 'dias_credito'];

    /** Reasignar el comercial (afecta el ranking): solo Admin. */
    public const SUPERVISION = ['comercial_id'];

    /**
     * Tipo de cambio congelado de la RO: se copia del valor de referencia al
     * crearla y solo el Admin puede ajustarlo.
     */
    public const FINANZAS = ['tipo_cambio'];

    /** Notas internas de la ficha. */
    public const NOTAS = ['observaciones'];

    /** Usuarios vinculados para seguimiento y alertas. */
    public const SEGUIMIENTO = ['responsable_users', 'tracking_users'];

    /** Todos los campos editables del formulario. */
    public static function all(): array
    {
        return self::merge(
            self::COMERCIAL,
            self::OPERATIVO,
            self::TRACKING,
            self::COORDINACION,
            self::CREDITO,
            self::SUPERVISION,
            self::FINANZAS,
            self::NOTAS,
            self::SEGUIMIENTO,
        );
    }

    /** Campos que el usuario puede llenar al crear una RO. */
    public static function editableOnCreate(User $user): array
    {
        return match ($user->permissionRole()) {
            // Al crear, el estado siempre arranca en "Creacion Routing Order".
            User::ROLE_ADMIN => array_values(array_diff(self::all(), self::TRACKING)),
            User::ROLE_COMERCIAL => self::merge(self::COMERCIAL, self::NOTAS),
            default => [],
        };
    }

    /** Campos que el usuario puede modificar en una RO existente. */
    public static function editableOnUpdate(User $user, Shipment $shipment): array
    {
        return match ($user->permissionRole()) {
            User::ROLE_ADMIN => self::all(),

            // El Comercial corrige solo sus propias RO y solo mientras siguen
            // en "Creacion Routing Order". Despues, solo el Admin.
            User::ROLE_COMERCIAL => $shipment->comercial_id === $user->id && $shipment->isInCreationStage()
                ? self::merge(self::COMERCIAL, self::NOTAS)
                : [],

            User::ROLE_OPERACIONES => self::merge(self::OPERATIVO, self::TRACKING, self::NOTAS, self::SEGUIMIENTO),

            User::ROLE_CUSTOMER => self::merge(self::TRACKING, self::COORDINACION, self::NOTAS, self::SEGUIMIENTO),

            default => [],
        };
    }

    public static function canEdit(User $user, Shipment $shipment, string $field): bool
    {
        $fields = $shipment->exists
            ? self::editableOnUpdate($user, $shipment)
            : self::editableOnCreate($user);

        return in_array($field, $fields, true);
    }

    private static function merge(array ...$groups): array
    {
        return array_values(array_unique(array_merge(...$groups)));
    }
}
