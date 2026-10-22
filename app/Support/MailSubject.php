<?php

namespace App\Support;

use App\Models\Shipment;
use Illuminate\Support\Str;

/**
 * Asunto estandar de los correos de un embarque.
 *
 *   [Accion] RO | Evento | Cliente | Tipo Origen → Destino | BL
 *
 * - La RO va primero: al buscar o filtrar por RO en Outlook/Gmail aparece
 *   toda la historia del embarque, y se ve aunque el celular corte el asunto.
 * - El evento va segundo: es lo unico que cambia y lo que el usuario necesita
 *   leer en la bandeja sin abrir el correo.
 * - [Accion] solo cuando alguien debe hacer algo ([Validar], [Aprobar]).
 * - Al cliente no se le repite su propio nombre ni se le muestran etiquetas internas.
 */
class MailSubject
{
    private const SEPARATOR = ' | ';

    public static function forShipment(Shipment $shipment, string $event, bool $internal = false, ?string $action = null): string
    {
        $shipment->loadMissing('client');

        $parts = [
            $shipment->routing_order,
            $event,
            $internal ? Str::limit((string) $shipment->client?->razon_social, 30, '…') : null,
            trim("{$shipment->tipo_carga} ".self::route($shipment)),
            filled($shipment->bl) ? "BL {$shipment->bl}" : null,
        ];

        $subject = implode(self::SEPARATOR, array_filter($parts, fn ($part) => filled($part)));

        return ($internal && $action ? "[{$action}] " : '').$subject;
    }

    /** Asunto de correos que no son de un embarque (clientes, alertas). */
    public static function general(string $event, array $details = [], ?string $action = null): string
    {
        $subject = implode(self::SEPARATOR, array_filter([$event, ...$details], fn ($part) => filled($part)));

        return ($action ? "[{$action}] " : '').$subject;
    }

    /**
     * Identificador fijo por embarque. Se envia en In-Reply-To/References para
     * que Outlook, Apple Mail y Thunderbird agrupen los correos del embarque.
     */
    public static function threadId(Shipment $shipment): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return 'embarque-'.$shipment->getKey().'-'.Str::lower(Str::slug((string) $shipment->routing_order)).'@'.$host;
    }

    private static function route(Shipment $shipment): string
    {
        $from = $shipment->puerto_carga ?: $shipment->origen;
        $to = $shipment->puerto_descarga ?: $shipment->destino_final;

        return $from && $to ? "{$from} → {$to}" : '';
    }
}
