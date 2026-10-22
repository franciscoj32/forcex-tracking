<?php

namespace App\Http\Controllers;

use App\Models\Provision;
use App\Models\RateConcept;
use App\Models\Shipment;
use App\Models\Supplier;
use App\Support\GroupedList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProvisionController extends Controller
{
    /** Opciones de "Agrupar por". La primera es la predeterminada. */
    public const GROUPINGS = ['proveedor', 'ro', 'ninguno'];

    /** Nombre, detalle y enlace de cada grupo de la pagina. */
    private function labelGroups(LengthAwarePaginator $groups, string $grouping): void
    {
        $keys = $groups->getCollection()->pluck('grupo')->filter()->values();

        if ($grouping === 'proveedor') {
            $names = Supplier::whereIn('id', $keys)->pluck('razon_social', 'id');
            $groups->getCollection()->each(function (object $group) use ($names) {
                $group->label = $group->grupo ? ($names[$group->grupo] ?? 'Proveedor eliminado') : 'Sin proveedor';
                $group->subtitle = null;
                $group->url = null;
                $group->searchTerm = $group->grupo ? $group->label : null;
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
        Gate::authorize('view-provisions');

        $search = trim((string) $request->string('search'));
        $view = in_array($request->query('vista'), ['pendientes', 'pagados', 'todos'], true) ? $request->query('vista') : 'pendientes';

        $base = fn () => Provision::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested
                ->where('descripcion', 'like', "%{$search}%")
                ->orWhere('nro_factura_proveedor', 'like', "%{$search}%")
                ->orWhere('nro_operacion_bancaria', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('razon_social', 'like', "%{$search}%"))
                ->orWhereHas('shipment', fn ($shipment) => $shipment
                    ->where('nro_orden_trabajo', 'like', "%{$search}%")
                    ->orWhere('routing_order', 'like', "%{$search}%"))));

        $applyView = fn ($query, string $key) => match ($key) {
            'pendientes' => $query->where('estado', Provision::STATUS_PENDING),
            'pagados' => $query->where('estado', Provision::STATUS_PAID),
            default => $query,
        };

        $filtered = $applyView($base(), $view);

        // Agrupar: por proveedor (tesoreria: que le debo a quien), por RO o sin agrupar.
        $grouping = in_array($request->query('agrupar'), self::GROUPINGS, true) ? $request->query('agrupar') : 'proveedor';
        $relations = ['shipment:id,nro_orden_trabajo,routing_order,client_id', 'shipment.client:id,razon_social', 'supplier:id,razon_social', 'concept:id,nombre'];
        $loadItems = fn ($query) => $query->with($relations)->latest('fecha')->latest('id')->get();

        $groups = null;
        $provisions = null;

        if ($grouping === 'ninguno') {
            $provisions = (clone $filtered)->with($relations)->latest('fecha')->latest('id')->paginate(20)->withQueryString();
        } else {
            $column = $grouping === 'proveedor' ? 'provisions.supplier_id' : 'provisions.shipment_id';
            $groups = GroupedList::paginate(
                $filtered,
                $column,
                fn ($query, $keys) => GroupedList::whereKeyIn($query, $column, $keys),
                fn (Provision $provision) => $grouping === 'proveedor' ? $provision->supplier_id : $provision->shipment_id,
                'provisions.estado',
                [Provision::STATUS_PENDING],
                'provisions.fecha',
                $loadItems,
            );
            $this->labelGroups($groups, $grouping);
        }

        return view('provisions.index', [
            'search' => $search,
            'view' => $view,
            'grouping' => $grouping,
            'counts' => collect(['pendientes', 'pagados', 'todos'])->mapWithKeys(fn ($key) => [$key => $applyView($base(), $key)->count()]),
            'totals' => (clone $filtered)->selectRaw('moneda, SUM(monto) as total')->groupBy('moneda')->pluck('total', 'moneda')->map(fn ($total) => (float) $total)->all(),
            'provisions' => $provisions,
            'groups' => $groups,
            // Para la ventana "Registrar costo".
            'form' => $request->user()->can('manage-provisions')
                ? self::formData(new Provision(['moneda' => 'USD', 'estado' => Provision::STATUS_PENDING]))
                : null,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage-provisions');

        return view('provisions.create', self::formData(new Provision([
            'shipment_id' => $request->integer('shipment_id') ?: null,
            'moneda' => 'USD',
            'estado' => Provision::STATUS_PENDING,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-provisions');

        $provision = Provision::create($this->validatedData($request));

        // Desde la ventana emergente se vuelve a la misma pagina (listado o ficha).
        if ($request->boolean('_dialog')) {
            return back()->with('status', 'Costo de proveedor registrado correctamente.');
        }

        return redirect()
            ->route('embarques.show', ['shipment' => $provision->shipment_id, 'tab' => 'finanzas'])
            ->with('status', 'Costo de proveedor registrado correctamente.');
    }

    public function show(Provision $provision): RedirectResponse
    {
        return redirect()->route('provisiones.edit', $provision);
    }

    public function edit(Provision $provision): View
    {
        Gate::authorize('manage-provisions');

        return view('provisions.edit', self::formData($provision));
    }

    public function update(Request $request, Provision $provision): RedirectResponse
    {
        Gate::authorize('manage-provisions');

        $provision->update($this->validatedData($request));

        return redirect()
            ->route('embarques.show', ['shipment' => $provision->shipment_id, 'tab' => 'finanzas'])
            ->with('status', 'Costo de proveedor actualizado correctamente.');
    }

    public function destroy(Provision $provision): RedirectResponse
    {
        Gate::authorize('delete-records');

        $provision->delete();

        return redirect()->route('provisiones.index')->with('status', 'Provision eliminada correctamente.');
    }

    /** Datos del formulario de costo (tambien lo usa la ventana emergente de la ficha del embarque). */
    public static function formData(Provision $provision, bool $withShipments = true): array
    {
        return [
            'provision' => $provision,
            'shipments' => $withShipments
                ? Shipment::with('client:id,razon_social')->orderByDesc('created_at')->get(['id', 'nro_orden_trabajo', 'client_id'])
                : collect(),
            'suppliers' => Supplier::query()
                ->where('activo', true)
                ->when($provision->supplier_id, fn ($query) => $query->orWhere('id', $provision->supplier_id))
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'tipo']),
            'concepts' => RateConcept::query()
                ->where('activo', true)
                ->when($provision->rate_concept_id, fn ($query) => $query->orWhere('id', $provision->rate_concept_id))
                ->ordered()
                ->get(['id', 'nombre']),
            'currencies' => Provision::CURRENCIES,
        ];
    }

    /**
     * Un costo pagado debe sustentarse con el N° de operacion bancaria
     * y la fecha de pago.
     */
    private function validatedData(Request $request): array
    {
        $isPaid = $request->input('estado') === Provision::STATUS_PAID;

        $data = $request->validate([
            'shipment_id' => ['required', 'exists:shipments,id'],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'rate_concept_id' => ['required', Rule::exists('rate_concepts', 'id')],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'moneda' => ['required', Rule::in(Provision::CURRENCIES)],
            'nro_factura_proveedor' => ['nullable', 'string', 'max:50'],
            'estado' => ['required', Rule::in([Provision::STATUS_PENDING, Provision::STATUS_PAID])],
            'nro_operacion_bancaria' => [Rule::requiredIf($isPaid), 'nullable', 'string', 'max:50'],
            'fecha_pago' => [Rule::requiredIf($isPaid), 'nullable', 'date'],
            'fecha' => ['required', 'date'],
        ], [
            'nro_operacion_bancaria.required' => 'Un costo pagado necesita el N° de operacion bancaria.',
            'fecha_pago.required' => 'Un costo pagado necesita la fecha de pago.',
        ], [
            'supplier_id' => 'proveedor',
            'rate_concept_id' => 'concepto',
            'nro_factura_proveedor' => 'N° de factura del proveedor',
            'fecha' => 'fecha de registro',
        ]);

        if (! $isPaid) {
            $data['nro_operacion_bancaria'] = null;
            $data['fecha_pago'] = null;
        }

        // La descripcion es opcional: por defecto, el nombre del concepto.
        if (blank($data['descripcion'] ?? null)) {
            $data['descripcion'] = RateConcept::whereKey($data['rate_concept_id'])->value('nombre');
        }

        return $data;
    }
}
