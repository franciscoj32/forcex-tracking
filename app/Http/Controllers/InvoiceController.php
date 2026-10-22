<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Shipment;
use App\Models\User;
use App\Support\GroupedList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /** Opciones de "Agrupar por". La primera es la predeterminada. */
    public const GROUPINGS = ['cliente', 'ro', 'ninguno'];

    /** Nombre, detalle y enlace de cada grupo de la pagina. */
    private function labelGroups(LengthAwarePaginator $groups, string $grouping): void
    {
        $keys = $groups->getCollection()->pluck('grupo')->filter()->values();

        if ($grouping === 'cliente') {
            $names = Client::whereIn('id', $keys)->pluck('razon_social', 'id');
            $groups->getCollection()->each(function (object $group) use ($names) {
                $group->label = $names[$group->grupo] ?? 'Cliente eliminado';
                $group->subtitle = null;
                $group->url = $group->grupo ? route('clientes.show', $group->grupo) : null;
                $group->searchTerm = $names[$group->grupo] ?? null;
            });

            return;
        }

        $shipments = Shipment::with('client:id,razon_social')->whereIn('id', $keys)->get(['id', 'nro_orden_trabajo', 'routing_order', 'client_id'])->keyBy('id');
        $groups->getCollection()->each(function (object $group) use ($shipments) {
            $shipment = $shipments->get($group->grupo);
            $group->label = $shipment ? "{$shipment->nro_orden_trabajo} · {$shipment->routing_order}" : 'RO eliminada';
            $group->subtitle = $shipment?->client?->razon_social;
            $group->url = $shipment ? route('embarques.show', ['shipment' => $shipment, 'tab' => 'finanzas']) : null;
            $group->searchTerm = $shipment?->routing_order;
        });
    }

    public function index(Request $request): View
    {
        Gate::authorize('view-invoices');

        $search = trim((string) $request->string('search'));
        $view = in_array($request->query('vista'), ['por-cobrar', 'pagadas', 'todas'], true) ? $request->query('vista') : 'por-cobrar';

        $base = fn () => Invoice::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested
                ->where('invoices.numero', 'like', "%{$search}%")
                ->orWhereHas('shipment', fn ($shipment) => $shipment
                    ->where('nro_orden_trabajo', 'like', "%{$search}%")
                    ->orWhere('routing_order', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->where('razon_social', 'like', "%{$search}%")))));

        $applyView = fn ($query, string $key) => match ($key) {
            'por-cobrar' => $query->whereIn('invoices.estado', ['pendiente', 'emitida']),
            'pagadas' => $query->where('invoices.estado', 'pagada'),
            default => $query,
        };

        $filtered = $applyView($base(), $view);

        // Agrupar: por cliente (cobranza: quien me debe que), por RO o sin agrupar.
        $grouping = in_array($request->query('agrupar'), self::GROUPINGS, true) ? $request->query('agrupar') : 'cliente';
        $relations = ['shipment:id,nro_orden_trabajo,routing_order,client_id', 'shipment.client:id,razon_social'];

        $groups = null;
        $invoices = null;

        if ($grouping === 'ninguno') {
            $invoices = (clone $filtered)->with($relations)->latest()->latest('id')->paginate(20)->withQueryString();
        } else {
            $byClient = $grouping === 'cliente';
            $groups = GroupedList::paginate(
                $filtered,
                // La factura no guarda el cliente: se toma de su RO (tabla unida con alias).
                $byClient ? 'grupo_ro.client_id' : 'invoices.shipment_id',
                fn ($query, $keys) => $byClient
                    ? $query->whereHas('shipment', fn ($shipment) => $shipment->whereIn('client_id', $keys->filter()->values()->all()))
                    : GroupedList::whereKeyIn($query, 'invoices.shipment_id', $keys),
                fn (Invoice $invoice) => $byClient ? $invoice->shipment?->client_id : $invoice->shipment_id,
                'invoices.estado',
                ['pendiente', 'emitida'],
                'invoices.created_at',
                fn ($query) => $query->with($relations)->latest()->latest('id')->get(),
                $byClient ? fn ($query) => $query->join('shipments as grupo_ro', 'grupo_ro.id', '=', 'invoices.shipment_id') : null,
            );
            $this->labelGroups($groups, $grouping);
        }

        return view('invoices.index', [
            'search' => $search,
            'view' => $view,
            'grouping' => $grouping,
            'counts' => collect(['por-cobrar', 'pagadas', 'todas'])->mapWithKeys(fn ($key) => [$key => $applyView($base(), $key)->count()]),
            'totals' => (clone $filtered)->selectRaw('moneda, SUM(monto) as total')->groupBy('moneda')->pluck('total', 'moneda')->map(fn ($total) => (float) $total)->all(),
            'invoices' => $invoices,
            'groups' => $groups,
            // Para la ventana "Registrar factura".
            'formShipments' => $request->user()->can('manage-invoices')
                ? Shipment::with('client:id,razon_social')->orderByDesc('created_at')->get(['id', 'nro_orden_trabajo', 'client_id'])
                : collect(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage-invoices');

        return view('invoices.create', [
            'shipments' => Shipment::with('client')->orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-invoices');

        Invoice::create($this->validatedData($request));

        // Desde la ventana emergente se vuelve a la misma pagina (listado o ficha).
        if ($request->boolean('_dialog')) {
            return back()->with('status', 'Factura registrada correctamente.');
        }

        return redirect()->route('facturacion.index')->with('status', 'Factura registrada correctamente.');
    }

    public function show(Invoice $invoice): RedirectResponse
    {
        return redirect()->route('facturacion.edit', $invoice);
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('manage-invoices');

        return view('invoices.edit', [
            'invoice' => $invoice,
            'shipments' => Shipment::with('client')->orderByDesc('created_at')->get(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manage-invoices');

        $invoice->update($this->validatedData($request, $invoice));

        return redirect()->route('facturacion.index')->with('status', 'Factura actualizada correctamente.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete-records');

        if ($invoice->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
            Storage::disk('private')->delete($invoice->archivo_pdf);
        }

        $invoice->delete();

        return redirect()->route('facturacion.index')->with('status', 'Factura eliminada correctamente.');
    }

    private function validatedData(Request $request, ?Invoice $invoice = null): array
    {
        $data = $request->validate([
            'shipment_id' => ['required', 'exists:shipments,id'],
            'numero' => ['required', 'string', 'max:100', Rule::unique('invoices', 'numero')->ignore($invoice)],
            'monto' => ['required', 'numeric', 'min:0'],
            'moneda' => ['required', Rule::in(Invoice::CURRENCIES)],
            'estado' => ['required', 'in:pendiente,emitida,pagada'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ]);

        if ($request->hasFile('archivo_pdf')) {
            if ($invoice?->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
                Storage::disk('private')->delete($invoice->archivo_pdf);
            }

            $data['archivo_pdf'] = Storage::disk('private')->putFileAs(
                'facturas/'.now()->format('Y'),
                $request->file('archivo_pdf'),
                now()->format('YmdHis').'_'.Str::random(8).'.pdf'
            );
        } else {
            unset($data['archivo_pdf']);
        }

        return $data;
    }
}
