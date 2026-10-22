<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Proveedores: navieras, aerolineas, agentes, almacenes, transportistas, aduanas. */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('view-provisions');

        $search = trim((string) $request->query('q', ''));

        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->withCount('provisions')
                ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested
                    ->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")))
                ->orderByDesc('activo')
                ->orderBy('razon_social')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'types' => Supplier::TYPE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-suppliers');

        Supplier::create($this->validated($request));

        return redirect()->route('proveedores.index')->with('status', 'Proveedor registrado correctamente.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('manage-suppliers');

        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('proveedores.index')->with('status', 'Proveedor actualizado correctamente.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20', Rule::unique('suppliers', 'ruc')->ignore($supplier)],
            'tipo' => ['required', Rule::in(Supplier::TYPES)],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'activo' => ['nullable', 'boolean'],
        ], [], ['ruc' => 'RUC o identificacion tributaria']);

        $data['activo'] = $request->boolean('activo', $supplier?->activo ?? true);

        return $data;
    }
}
