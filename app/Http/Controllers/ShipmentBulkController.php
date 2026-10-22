<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\ShipmentDateChangeService;
use App\Support\ShipmentFieldAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Actualizacion masiva de las RO que viajan en la misma nave y viaje:
 * cambiar ETD/ETA o registrar un hito a varias RO a la vez.
 *
 * Cada RO se revisa con sus reglas de siempre (permisos por campo, el hito
 * solo avanza, la ETA no queda antes de la ETD). Las que no cumplen se omiten
 * y se informa el motivo. El cut-off no se cambia en masa.
 */
class ShipmentBulkController extends Controller
{
    public const MAX_SHIPMENTS = 50;

    public function __construct(private readonly ShipmentDateChangeService $dateChanges)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('bulk-update-shipments');

        $user = $request->user();
        $search = Str::squish((string) $request->query('q', ''));
        $nave = $this->normalized($request->query('nave'));
        $viaje = $this->normalized($request->query('viaje'));
        $active = fn () => Shipment::query()->whereIn('estado_actual', Shipment::activeStates());

        $groups = $active()
            ->whereNotNull('nave')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('nave', 'like', "%{$search}%")
                ->orWhere('viaje', 'like', "%{$search}%")
                ->orWhere('naviera', 'like', "%{$search}%")))
            ->selectRaw('nave, viaje, COUNT(*) as total, MIN(fecha_eta) as eta_desde, MAX(fecha_eta) as eta_hasta, MIN(naviera) as naviera')
            ->groupBy('nave', 'viaje')
            ->orderBy('nave')
            ->orderBy('viaje')
            ->limit(200)
            ->toBase()
            ->get();

        $shipments = $nave === null ? collect() : $active()
            ->where('nave', $nave)
            ->when($viaje !== null, fn ($query) => $query->where('viaje', $viaje), fn ($query) => $query->whereNull('viaje'))
            ->with(['client:id,razon_social', 'customerService:id,name'])
            ->orderBy('routing_order')
            ->get();

        return view('shipments.bulk', [
            'groups' => $groups,
            'search' => $search,
            'nave' => $nave,
            'viaje' => $viaje,
            'shipments' => $shipments,
            'withoutVessel' => $active()->whereNull('nave')->count(),
            'canChangeDates' => $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]),
            'states' => array_slice(Shipment::TRACKING_STATES, 1),
            'result' => session('bulk_result'),
        ]);
    }

    /** Misma ETD o ETA para todas las RO elegidas. El Customer las valida juntas. */
    public function updateDates(Request $request): RedirectResponse
    {
        Gate::authorize('bulk-update-shipments');

        $data = $request->validate($this->selectionRules() + [
            'campo' => ['required', Rule::in(array_keys(ShipmentDateChange::FIELDS))],
            'fecha' => ['required', 'date'],
        ], $this->selectionMessages(), ['campo' => 'fecha a cambiar', 'fecha' => 'nueva fecha']);

        $user = $request->user();
        $field = $data['campo'];
        $label = ShipmentDateChange::FIELD_LABELS[$field];
        $date = Carbon::parse($data['fecha'])->startOfDay();
        [$shipments, $skipped] = $this->selected($data['shipment_ids']);
        $updated = [];
        $context = $this->vesselContext($shipments, count($data['shipment_ids']));

        DB::transaction(function () use ($shipments, $user, $field, $label, $date, $context, &$updated, &$skipped) {
            $this->dateChanges->batch((string) Str::uuid(), function () use ($shipments, $user, $field, $label, $date, $context, &$updated, &$skipped) {
                foreach ($shipments as $shipment) {
                    $reason = $this->dateSkipReason($user, $shipment, $field, $label, $date);

                    if ($reason !== null) {
                        $skipped[] = [$shipment->routing_order, $reason];

                        continue;
                    }

                    $previous = $shipment->{$field}?->format('d/m/Y') ?? 'sin fecha';
                    $shipment->{$field} = $date->toDateString();
                    $shipment->save();

                    ShipmentEvent::recordAudit($shipment, "Actualización masiva {$context}: {$label} {$previous} → {$date->format('d/m/Y')}.", $user->id);
                    $updated[] = $shipment->routing_order;
                }
            });
        });

        $message = $updated === []
            ? "No se cambió la {$label} de ninguna RO."
            : "{$label} actualizada en ".count($updated).' RO. El Customer las valida juntas antes de que el cliente las vea.';

        return $this->backToGroup($request, $message, $updated, $skipped);
    }

    /** Mismo hito, fecha y nota para todas las RO elegidas. */
    public function registerMilestone(Request $request): RedirectResponse
    {
        Gate::authorize('bulk-update-shipments');

        $data = $request->validate($this->selectionRules() + [
            'estado' => ['required', Rule::in(Shipment::TRACKING_STATES)],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ], $this->selectionMessages(), ['estado' => 'hito', 'fecha' => 'fecha del hito', 'descripcion' => 'nota interna']);

        $user = $request->user();
        [$shipments, $skipped] = $this->selected($data['shipment_ids']);
        $registered = [];
        $note = trim('Registrado en actualización masiva '.$this->vesselContext($shipments, count($data['shipment_ids'])).'. '.($data['descripcion'] ?? ''));

        DB::transaction(function () use ($shipments, $user, $data, $note, &$registered, &$skipped) {
            foreach ($shipments as $shipment) {
                if (! $user->can('registerTracking', $shipment)) {
                    $skipped[] = [$shipment->routing_order, 'No tienes permiso para registrar hitos en esta RO.'];

                    continue;
                }

                if (! $shipment->canAdvanceToTrackingState($data['estado'])) {
                    $skipped[] = [$shipment->routing_order, "Ya está en \"{$shipment->estado_actual}\": el hito solo puede avanzar."];

                    continue;
                }

                // Igual que en la ficha: el cliente ve el hito con su texto estandar.
                ShipmentEvent::recordState($shipment, $data['estado'], $data['fecha'], $note, $user->id);
                $shipment->update(['estado_actual' => $data['estado']]);
                $registered[] = $shipment->routing_order;
            }
        });

        $message = $registered === []
            ? "No se registró \"{$data['estado']}\" en ninguna RO."
            : "\"{$data['estado']}\" registrado en ".count($registered).' RO.';

        return $this->backToGroup($request, $message, $registered, $skipped);
    }

    private function dateSkipReason(User $user, Shipment $shipment, string $field, string $label, Carbon $date): ?string
    {
        if (! $user->can('update', $shipment) || ! ShipmentFieldAccess::canEdit($user, $shipment, $field)) {
            return "No tienes permiso para cambiar la {$label} de esta RO.";
        }

        if ($shipment->{$field}?->isSameDay($date)) {
            return "Ya tenía esa {$label}.";
        }

        if ($field === 'fecha_eta' && $shipment->fecha_etd && $date->lt($shipment->fecha_etd)) {
            return 'La ETA quedaría antes que la ETD ('.$shipment->fecha_etd->format('d/m/Y').').';
        }

        if ($field === 'fecha_etd' && $shipment->fecha_eta && $date->gt($shipment->fecha_eta)) {
            return 'La ETD quedaría después de la ETA ('.$shipment->fecha_eta->format('d/m/Y').'). Cambia primero la ETA.';
        }

        return null;
    }

    /**
     * RO activas elegidas, y las que no se encontraron (finalizadas o borradas).
     *
     * @return array{0: Collection<int, Shipment>, 1: array<int, array{0:string, 1:string}>}
     */
    private function selected(array $ids): array
    {
        $shipments = Shipment::query()
            ->whereIn('id', $ids)
            ->whereIn('estado_actual', Shipment::activeStates())
            ->orderBy('routing_order')
            ->get();

        $missing = count($ids) - $shipments->count();
        $skipped = $missing > 0 ? [["{$missing} RO", 'Ya no están activas (finalizadas o eliminadas).']] : [];

        return [$shipments, $skipped];
    }

    /** "(5 RO · MSC ANNA viaje 442W)" para el historial de cada RO. */
    private function vesselContext(Collection $shipments, int $count): string
    {
        $first = $shipments->first();
        $vessel = $first?->nave ? trim($first->nave.($first->viaje ? " viaje {$first->viaje}" : '')) : null;

        return '('.$count.' RO'.($vessel ? " · {$vessel}" : '').')';
    }

    private function selectionRules(): array
    {
        return [
            'shipment_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_SHIPMENTS],
            'shipment_ids.*' => ['integer', 'distinct'],
        ];
    }

    private function selectionMessages(): array
    {
        return [
            'shipment_ids.required' => 'Selecciona al menos una RO.',
            'shipment_ids.max' => 'Puedes actualizar hasta '.self::MAX_SHIPMENTS.' RO a la vez.',
        ];
    }

    private function backToGroup(Request $request, string $message, array $done, array $skipped): RedirectResponse
    {
        return redirect()
            ->route('embarques.masivo', array_filter(['nave' => $request->input('nave'), 'viaje' => $request->input('viaje')]))
            ->with('status', $message)
            ->with('bulk_result', ['done' => $done, 'skipped' => $skipped]);
    }

    private function normalized(mixed $value): ?string
    {
        $value = Str::upper(Str::squish((string) $value));

        return $value !== '' ? $value : null;
    }
}
