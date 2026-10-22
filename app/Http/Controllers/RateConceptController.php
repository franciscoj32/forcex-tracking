<?php

namespace App\Http\Controllers;

use App\Models\RateConcept;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catalogo de conceptos de tarifas y costos. Lo administra el Admin.
 * Un concepto en uso no se elimina: se desactiva para conservar el historial.
 */
class RateConceptController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-rate-concepts');

        return view('rate-concepts.index', [
            'concepts' => RateConcept::query()
                ->withCount(['rates', 'provisions'])
                ->ordered()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-rate-concepts');

        $data = $this->validated($request);
        $data['codigo'] = $this->uniqueCode($data['nombre']);
        $data['orden'] ??= (int) RateConcept::max('orden') + 10;

        RateConcept::create($data);

        return redirect()->route('conceptos.index')->with('status', 'Concepto agregado al catalogo.');
    }

    public function update(Request $request, RateConcept $concept): RedirectResponse
    {
        Gate::authorize('manage-rate-concepts');

        $concept->update($this->validated($request, $concept));

        return redirect()->route('conceptos.index')->with('status', 'Concepto actualizado.');
    }

    private function validated(Request $request, ?RateConcept $concept = null): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('rate_concepts', 'nombre')->ignore($concept)],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = $request->boolean('activo', $concept?->activo ?? true);

        return $data;
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::upper(Str::limit(Str::slug($name, '_'), 24, ''));
        $code = $base;
        $suffix = 2;

        while (RateConcept::where('codigo', $code)->exists()) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }
}
