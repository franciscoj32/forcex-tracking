<?php

namespace App\Http\Controllers;

use App\Models\ShipmentDateChange;
use App\Services\ShipmentDateChangeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Validacion de cambios de ETD/ETA por el Customer Service.
 */
class ShipmentDateChangeController extends Controller
{
    public function __construct(private readonly ShipmentDateChangeService $service)
    {
    }

    public function approve(Request $request, ShipmentDateChange $change): RedirectResponse
    {
        Gate::authorize('reviewDateChanges', $change->shipment);

        $data = $request->validate([
            'nota_cliente' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(
            fn () => $this->service->approve($change, $request->user(), $data['nota_cliente'] ?? null),
            $change,
            $change->fieldLabel().' validado y publicado al cliente.'
        );
    }

    public function discard(Request $request, ShipmentDateChange $change): RedirectResponse
    {
        Gate::authorize('reviewDateChanges', $change->shipment);

        $data = $request->validate([
            'nota_cliente' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->run(
            fn () => $this->service->discard($change, $request->user(), $data['nota_cliente'] ?? null),
            $change,
            'Cambio de '.$change->fieldLabel().' descartado. El cliente sigue viendo la fecha anterior.'
        );
    }

    /** Cambios hechos juntos en una actualizacion masiva (lote). */
    public function batchShow(Request $request, string $lote): View
    {
        abort_unless($request->user()->isInternal(), 403);

        $changes = ShipmentDateChange::query()
            ->where('lote', $lote)
            ->with(['shipment.client:id,razon_social', 'shipment.customerService:id,name', 'changedBy:id,name', 'reviewedBy:id,name'])
            ->orderBy('id')
            ->get();

        abort_if($changes->isEmpty(), 404);

        $user = $request->user();

        return view('shipments.date-change-batch', [
            'lote' => $lote,
            'changes' => $changes,
            'reviewableIds' => $changes
                ->filter(fn (ShipmentDateChange $change) => $change->isPending() && $user->can('reviewDateChanges', $change->shipment))
                ->pluck('id')
                ->all(),
            'result' => session('batch_result'),
        ]);
    }

    /** Aprobar o descartar varios cambios del lote a la vez, con una sola nota. */
    public function batchReview(Request $request, string $lote): RedirectResponse
    {
        $data = $request->validate([
            'change_ids' => ['required', 'array', 'min:1'],
            'change_ids.*' => ['integer'],
            'accion' => ['required', Rule::in(['aprobar', 'descartar'])],
            'nota_cliente' => ['nullable', 'string', 'max:1000'],
        ], ['change_ids.required' => 'Selecciona al menos un cambio.']);

        $user = $request->user();
        $done = [];
        $skipped = [];

        $changes = ShipmentDateChange::query()
            ->where('lote', $lote)
            ->whereIn('id', $data['change_ids'])
            ->with('shipment')
            ->orderBy('id')
            ->get();

        foreach ($changes as $change) {
            $ro = $change->shipment->routing_order;

            if (! $user->can('reviewDateChanges', $change->shipment)) {
                $skipped[] = [$ro, 'La valida el Customer asignado a esta RO.'];

                continue;
            }

            try {
                $data['accion'] === 'aprobar'
                    ? $this->service->approve($change, $user, $data['nota_cliente'] ?? null)
                    : $this->service->discard($change, $user, $data['nota_cliente'] ?? null);
                $done[] = $ro;
            } catch (InvalidArgumentException) {
                $skipped[] = [$ro, 'Ya fue revisado o lo reemplazó un cambio más reciente.'];
            }
        }

        $message = $done === []
            ? 'No se revisó ningún cambio.'
            : ($data['accion'] === 'aprobar'
                ? count($done).' cambio(s) validados y publicados. Cada cliente recibe su aviso.'
                : count($done).' cambio(s) descartados. Los clientes siguen viendo la fecha anterior.');

        return redirect()
            ->route('cambios-fecha.lote', $lote)
            ->with('status', $message)
            ->with('batch_result', ['done' => $done, 'skipped' => $skipped]);
    }

    private function run(callable $action, ShipmentDateChange $change, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['cambio_fecha' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
