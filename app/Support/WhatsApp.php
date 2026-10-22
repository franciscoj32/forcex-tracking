<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * WhatsApp sin costo: arma un enlace https://wa.me/<numero>?text=<mensaje> que
 * abre el WhatsApp del Customer con el mensaje listo. El Customer lo envia
 * desde su propio WhatsApp; no pasa por la API de Meta ni tiene costo.
 */
final class WhatsApp
{
    /**
     * Celular en formato internacional, solo digitos (ej. 51987654321).
     * null si no parece un celular: los fijos no tienen WhatsApp.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }

        $country = (string) config('forcex.whatsapp_country_code', '51');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (! str_starts_with($raw, '+')) {
            // Peru: celular de 9 digitos que empieza con 9.
            if (strlen($digits) === 9 && $digits[0] === '9') {
                $digits = $country.$digits;
            } elseif (! (str_starts_with($digits, $country) && strlen($digits) === strlen($country) + 9)) {
                return null;
            }
        }

        // Peru con codigo de pais: 51 + 9XXXXXXXX. Otros paises: 10 a 15 digitos.
        if (str_starts_with($digits, '51')) {
            return preg_match('/^519\d{8}$/', $digits) ? $digits : null;
        }

        return preg_match('/^[1-9]\d{9,14}$/', $digits) ? $digits : null;
    }

    /** "+51 987 654 321" para mostrar. */
    public static function display(string $normalized): string
    {
        if (preg_match('/^51(9\d{2})(\d{3})(\d{3})$/', $normalized, $m)) {
            return "+51 {$m[1]} {$m[2]} {$m[3]}";
        }

        return '+'.$normalized;
    }

    public static function url(string $normalized, string $message): string
    {
        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($message);
    }

    /**
     * Mensajes sugeridos. {nombre} se reemplaza por el nombre del contacto.
     *
     * @return array<string, array{label: string, text: string}>
     */
    public static function templates(Shipment $shipment, User $sender): array
    {
        $company = AppSetting::current()->company_name;
        $greeting = 'Hola {nombre}, te saluda '.Str::before($sender->name, ' ')." de {$company}.";
        $ro = "RO {$shipment->routing_order}";
        $route = trim(($shipment->puerto_carga ?: $shipment->origen).' → '.($shipment->puerto_descarga ?: $shipment->destino_final), ' →');
        $tracking = $shipment->tracking_code
            ? "\n\nSigue tu carga en línea con el código {$shipment->tracking_code}: ".route('tracking.public', ['code' => $shipment->tracking_code])
            : '';

        $templates = [
            'estado' => [
                'label' => 'Estado de la carga',
                'text' => "{$greeting}\n\nTu carga {$ro} ({$route}): ".Shipment::clientStateText($shipment->estado_actual)
                    .($shipment->fecha_eta_cliente ? "\nFecha estimada de llegada: {$shipment->fecha_eta_cliente->format('d/m/Y')}." : '')
                    .$tracking,
            ],
        ];

        if ($shipment->fecha_eta_cliente) {
            $templates['fecha'] = [
                'label' => 'Fecha estimada de llegada',
                'text' => "{$greeting}\n\nTe confirmamos que la fecha estimada de llegada de tu carga {$ro} es el {$shipment->fecha_eta_cliente->format('d/m/Y')}."
                    .$tracking,
            ];
        }

        // Mismo aviso que el correo C (tarja / ingreso a almacen).
        if ($shipment->hasTarja()) {
            $date = ($shipment->fecha_ingreso_almacen ?? $shipment->fecha_tarja)?->format('d/m/Y');
            $templates['almacen'] = [
                'label' => 'Carga en almacén',
                'text' => "{$greeting}\n\nTu carga {$ro} ya arribó y se encuentra en el almacén"
                    .($shipment->almacen_direccionamiento ? " {$shipment->almacen_direccionamiento}" : '')
                    .($date ? " desde el {$date}" : '').'.'
                    .($shipment->bultos_tarjados !== null ? " Bultos recibidos: {$shipment->bultos_tarjados}." : '')
                    ."\n\nTe escribiremos para coordinar el retiro."
                    .$tracking,
            ];
        }

        $templates['libre'] = [
            'label' => 'Mensaje libre',
            'text' => "{$greeting}\n\nSobre tu carga {$ro}: ",
        ];

        return $templates;
    }

    /** Plantilla sugerida segun el momento de la carga. */
    public static function defaultTemplate(Shipment $shipment): string
    {
        return $shipment->hasTarja() && ! $shipment->hasLeftWarehouse() ? 'almacen' : 'estado';
    }
}
