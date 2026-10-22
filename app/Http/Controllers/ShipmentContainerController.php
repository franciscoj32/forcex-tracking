<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShipmentContainer;
use App\Models\ShipmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Contenedores de la RO. Los registra Operaciones (o el Admin).
 */
class ShipmentContainerController extends Controller
{
    public function store(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('manageContainers', $shipment);

        $container = $shipment->containers()->create($this->validated($request, $shipment));
        $this->log($shipment, 'Contenedor '.$container->numero.' ('.$container->tipo.') registrado.');

        return $this->back($shipment, 'Contenedor registrado correctamente.');
    }

    public function update(Request $request, ShipmentContainer $container): RedirectResponse
    {
        $shipment = $container->shipment;
        Gate::authorize('manageContainers', $shipment);

        $container->update($this->validated($request, $shipment, $container));
        $this->log($shipment, 'Contenedor '.$container->numero.' actualizado.');

        return $this->back($shipment, 'Contenedor actualizado correctamente.');
    }

    public function destroy(ShipmentContainer $container): RedirectResponse
    {
        $shipment = $container->shipment;
        Gate::authorize('manageContainers', $shipment);

        $number = $container->numero;
        $container->delete();
        $this->log($shipment, 'Contenedor '.$number.' eliminado.');

        return $this->back($shipment, 'Contenedor eliminado.');
    }

    private function validated(Request $request, Shipment $shipment, ?ShipmentContainer $container = null): array
    {
        $request->merge([
            'numero' => strtoupper(preg_replace('/[\s-]+/', '', (string) $request->input('numero'))),
        ]);

        return $request->validate([
            'numero' => [
                'required',
                'string',
                'size:11',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! ShipmentContainer::isValidNumber((string) $value)) {
                        $fail('El numero de contenedor no es valido (formato ISO 6346, ej. MSCU1234565). Revisa letras y digito de control.');
                    }
                },
                Rule::unique('shipment_containers', 'numero')
                    ->where('shipment_id', $shipment->id)
                    ->ignore($container),
            ],
            'tipo' => ['required', Rule::in(ShipmentContainer::TYPES)],
            'precinto' => ['nullable', 'string', 'max:50'],
            'bultos' => ['nullable', 'integer', 'min:0'],
            'peso_kg' => ['nullable', 'numeric', 'min:0'],
            'volumen_cbm' => ['nullable', 'numeric', 'min:0'],
            ...$this->dateRules($request),
        ], [], [
            'numero' => 'numero de contenedor',
            'peso_kg' => 'peso (kg)',
            'volumen_cbm' => 'volumen (CBM)',
            ...self::DATE_LABELS,
        ]);
    }

    private const DATE_LABELS = [
        'fecha_retiro' => 'fecha de retiro',
        'fecha_devolucion' => 'fecha de devolución del vacío',
    ];

    /** No se devuelve un vacio que no se retiro, ni antes de retirarlo. */
    private function dateRules(Request $request): array
    {
        return [
            'fecha_retiro' => ['nullable', 'date', 'required_with:fecha_devolucion', 'before_or_equal:today'],
            'fecha_devolucion' => array_merge(
                ['nullable', 'date', 'before_or_equal:today'],
                $request->filled('fecha_retiro') ? ['after_or_equal:fecha_retiro'] : []
            ),
        ];
    }

    /**
     * "Aplicar a todos": registra la misma fecha de retiro y/o devolucion en los
     * contenedores que aun no la tienen. No pisa fechas ya registradas.
     */
    public function applyDates(Request $request, Shipment $shipment): RedirectResponse
    {
        Gate::authorize('manageContainers', $shipment);

        $data = $request->validate([
            'fecha_retiro' => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_devolucion' => ['nullable', 'date', 'before_or_equal:today'],
        ], [], self::DATE_LABELS);

        if (blank($data['fecha_retiro'] ?? null) && blank($data['fecha_devolucion'] ?? null)) {
            return $this->back($shipment, 'Indica la fecha de retiro o de devolución.');
        }

        $retiro = filled($data['fecha_retiro'] ?? null) ? Carbon::parse($data['fecha_retiro'])->startOfDay() : null;
        $devolucion = filled($data['fecha_devolucion'] ?? null) ? Carbon::parse($data['fecha_devolucion'])->startOfDay() : null;
        $updated = collect();
        $skipped = 0;

        DB::transaction(function () use ($shipment, $retiro, $devolucion, &$updated, &$skipped) {
            foreach ($shipment->containers as $container) {
                if ($retiro && ! $container->fecha_retiro) {
                    $container->fecha_retiro = $retiro;
                }

                if ($devolucion && ! $container->fecha_devolucion) {
                    // Solo si ya fue retirado y la devolucion no es anterior al retiro.
                    if ($container->fecha_retiro && $devolucion->gte($container->fecha_retiro)) {
                        $container->fecha_devolucion = $devolucion;
                    } else {
                        $skipped++;
                    }
                }

                if ($container->isDirty()) {
                    $container->save();
                    $updated->push($container->numero);
                }
            }

            if ($updated->isNotEmpty()) {
                $this->log($shipment, 'Fechas de contenedor registradas para '.$updated->join(', ')
                    .($retiro ? ' · retiro '.$retiro->format('d/m/Y') : '')
                    .($devolucion ? ' · devolución '.$devolucion->format('d/m/Y') : '').'.');
            }
        });

        $status = $updated->isEmpty()
            ? 'No había contenedores sin esa fecha.'
            : 'Fechas registradas en '.$updated->count().' '.($updated->count() === 1 ? 'contenedor' : 'contenedores').'.';

        if ($skipped > 0) {
            $status .= " {$skipped} sin devolución: falta su retiro o la fecha es anterior al retiro.";
        }

        return $this->back($shipment, $status);
    }

    private function log(Shipment $shipment, string $description): void
    {
        ShipmentEvent::recordAudit($shipment, $description, auth()->id());
    }

    private function back(Shipment $shipment, string $status): RedirectResponse
    {
        return redirect()
            ->to(route('embarques.show', $shipment).'#contenedores')
            ->with('status', $status);
    }
}
