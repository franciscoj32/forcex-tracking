<?php

namespace App\Http\Controllers;

use App\Models\Provision;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProvisionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]), 403);

        $search = trim((string) $request->string('search'));

        return view('provisions.index', [
            'search' => $search,
            'shipments' => Shipment::query()
                ->with([
                    'client',
                    'provisions' => fn ($query) => $query->latest('fecha'),
                ])
                ->withCount('provisions')
                ->withSum('provisions', 'monto')
                ->withMax('provisions', 'fecha')
                ->whereHas('provisions')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($nestedQuery) use ($search) {
                        $nestedQuery
                            ->where('nro_orden_trabajo', 'like', "%{$search}%")
                            ->orWhereHas('provisions', function ($provisionQuery) use ($search) {
                                $provisionQuery->where('descripcion', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderByDesc('provisions_max_fecha')
                ->paginate(8)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        return view('provisions.create', [
            'shipments' => Shipment::with('client')->orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        Provision::create($this->validatedData($request));

        return redirect()->route('provisiones.index')->with('status', 'Provision registrada correctamente.');
    }

    public function show(Provision $provision): RedirectResponse
    {
        return redirect()->route('provisiones.edit', $provision);
    }

    public function edit(Provision $provision): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        return view('provisions.edit', [
            'provision' => $provision,
            'shipments' => Shipment::with('client')->orderByDesc('created_at')->get(),
        ]);
    }

    public function update(Request $request, Provision $provision): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        $provision->update($this->validatedData($request));

        return redirect()->route('provisiones.index')->with('status', 'Provision actualizada correctamente.');
    }

    public function destroy(Provision $provision): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $provision->delete();

        return redirect()->route('provisiones.index')->with('status', 'Provision eliminada correctamente.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'shipment_id' => ['required', 'exists:shipments,id'],
            'descripcion' => ['required', 'string'],
            'monto' => ['required', 'numeric', 'min:0'],
            'estado' => ['required', 'in:pendiente,pagado'],
            'fecha' => ['required', 'date'],
        ]);
    }
}
