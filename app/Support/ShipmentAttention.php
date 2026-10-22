<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Shipment;
use App\Models\User;

/**
 * Pendientes de un embarque que requieren una decision o una accion.
 *
 * Se muestran en el bloque "Requiere atencion" de la ficha y alimentan la
 * bandeja "Requiere tu accion" del Inicio. Cada pendiente trae, si el usuario
 * puede resolverlo, el enlace a la accion.
 *
 * Nivel: 'danger' (vencido o bloqueante) o 'warning' (por hacer pronto).
 */
class ShipmentAttention
{
    /**
     * @param  array<int, string>|null  $only  solo estos pendientes (el Inicio pide los del rol;
     *                                         asi no se arma la lista de documentos si no se usa)
     * @return array<int, array{key:string, level:string, text:string, action_label:?string, action_url:?string}>
     */
    public static function for(Shipment $shipment, User $user, ?array $only = null): array
    {
        if ($shipment->estado_actual === 'Finalizado') {
            return [];
        }

        $wants = fn (string $key) => $only === null || in_array($key, $only, true);
        $items = [];
        $show = fn (string $tab, string $anchor = '') => route('embarques.show', ['shipment' => $shipment, 'tab' => $tab]).($anchor !== '' ? "#{$anchor}" : '');
        $canEditField = fn (string $field) => $user->can('update', $shipment) && ShipmentFieldAccess::canEdit($user, $shipment, $field);
        $editUrl = route('embarques.edit', $shipment);
        $beforeArrival = in_array($shipment->estado_actual, Shipment::statesBeforeArrival(), true);

        // Los plazos (ETA vencida o retrasada, cut-off, demurrage, detention,
        // almacenaje) son alertas automaticas: ver App\Services\Alerts.

        // Cambios de fecha que el cliente todavia no ve
        $canReview = $user->can('reviewDateChanges', $shipment);
        foreach ($wants('cambio-fecha') ? $shipment->pendingDateChanges : [] as $change) {
            $items[] = self::item('cambio-fecha', 'warning',
                "{$change->changedBy->name} cambió la {$change->fieldLabel()} a ".($change->valor_nuevo?->format('d/m/Y') ?? 'por confirmar')
                    .'. El cliente aún ve '.($change->valor_publicado?->format('d/m/Y') ?? 'sin fecha').'.',
                $canReview ? 'Revisar cambio' : null,
                $canReview ? $show('datos', 'cambios-fecha') : null)
                // Cambios de una actualizacion masiva: el Inicio los junta en un solo pendiente.
                + ['lote' => $change->lote, 'change' => $change];
        }

        // FCL sin contenedores
        if ($wants('contenedores') && $shipment->isMissingContainers()) {
            $canManage = $user->can('manageContainers', $shipment);
            $items[] = self::item('contenedores', $beforeArrival ? 'warning' : 'danger', 'FCL sin contenedores registrados.',
                $canManage ? 'Agregar contenedor' : null,
                $canManage ? $show('datos', 'contenedores') : null);
        }

        // Arribo sin tarja
        if ($wants('tarja') && in_array($shipment->estado_actual, ['Arribo a puerto', 'Descarga', 'Ingreso a almacen'], true) && ! $shipment->hasTarja()) {
            $items[] = self::item('tarja', 'warning', 'La carga arribó y falta registrar la tarja.',
                $canEditField('fecha_tarja') ? 'Registrar tarja' : null,
                $canEditField('fecha_tarja') ? $editUrl : null);
        }

        // Sin operador asignado
        if ($wants('operador') && ! $shipment->operador_id) {
            $items[] = self::item('operador', 'warning', 'Falta asignar el operador.',
                $canEditField('operador_id') ? 'Asignar' : null,
                $canEditField('operador_id') ? $editUrl : null);
        }

        // Documentos obligatorios que nadie subio todavia
        $missing = $wants('documentos') ? self::missingRequiredDocuments($shipment) : [];
        if ($missing !== []) {
            $items[] = self::item('documentos', 'warning',
                'Faltan documentos obligatorios: '.implode(', ', $missing).'.',
                'Ver documentos',
                $show('documentos'));
        }

        // Validacion documental del Customer
        $queue = $wants('documentos-validar') || $wants('documento-observado')
            ? self::documentValidationQueue($shipment)
            : ['pending' => collect(), 'observed' => collect()];
        if ($wants('documentos-validar') && $queue['pending']->isNotEmpty()) {
            $canValidate = $user->can('validateDocuments', $shipment);
            $items[] = self::item('documentos-validar', 'warning',
                'Por validar: '.$queue['pending']->pluck('tipo_documento')->join(', ').'.',
                $canValidate ? 'Validar' : null,
                $canValidate ? $show('documentos', 'archivos') : null);
        }
        foreach ($wants('documento-observado') ? $queue['observed'] : [] as $document) {
            $canUpload = $user->can('manageDocuments', $shipment);
            $items[] = self::item('documento-observado', 'danger',
                "{$document->tipo_documento} observado: ".rtrim((string) $document->observacion_validacion, " .").'. Falta la versión corregida.',
                $canUpload ? 'Subir corrección' : null,
                $canUpload ? route('embarques.show', ['shipment' => $shipment, 'tab' => 'documentos', 'subir' => 1, 'categoria' => $document->categoria, 'tipo' => $document->tipo_documento]) : null);
        }

        // Primero lo vencido
        usort($items, fn (array $a, array $b) => ($a['level'] === 'danger' ? 0 : 1) <=> ($b['level'] === 'danger' ? 0 : 1));

        return $items;
    }

    /**
     * Obligatorios que todavia no se subieron (los subidos que esperan
     * validacion se informan aparte).
     *
     * @return array<int, string>
     */
    public static function missingRequiredDocuments(Shipment $shipment): array
    {
        return collect($shipment->documentChecklist())
            ->flatMap(fn (array $category) => collect($category['missing_required'])
                ->reject(fn (array $item) => $item['uploaded'] ?? false)
                ->pluck('label'))
            ->values()
            ->all();
    }

    /**
     * Ultima version de cada tipo validable: las que esperan al Customer y
     * las observadas (hay que pedir una version corregida).
     *
     * @return array{pending: \Illuminate\Support\Collection, observed: \Illuminate\Support\Collection}
     */
    public static function documentValidationQueue(Shipment $shipment): array
    {
        $documents = $shipment->relationLoaded('documents') ? $shipment->documents : $shipment->documents()->get();

        $latest = $documents
            ->filter(fn (Document $document) => $document->needsValidation())
            ->sortByDesc(fn (Document $document) => [$document->created_at?->getTimestamp() ?? 0, $document->id])
            ->unique(fn (Document $document) => mb_strtolower($document->tipo_documento))
            ->values();

        return [
            'pending' => $latest->filter(fn (Document $document) => $document->isPendingValidation())->values(),
            'observed' => $latest->filter(fn (Document $document) => $document->isObserved())->values(),
        ];
    }

    private static function item(string $key, string $level, string $text, ?string $actionLabel, ?string $actionUrl): array
    {
        return [
            'key' => $key,
            'level' => $level,
            'text' => $text,
            'action_label' => $actionUrl ? $actionLabel : null,
            'action_url' => $actionUrl,
        ];
    }
}
