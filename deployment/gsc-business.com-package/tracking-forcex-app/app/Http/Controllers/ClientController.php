<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]), 403);

        return view('clients.index', [
            'clients' => Client::query()
                ->withCount([
                    'shipments',
                    'shipments as shipments_active_count' => fn ($query) => $query->where('estado_actual', '!=', 'Finalizado'),
                    'shipments as shipments_finished_count' => fn ($query) => $query->where('estado_actual', 'Finalizado'),
                ])
                ->orderBy('razon_social')
                ->paginate(12),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        Client::create($this->validatedData($request));

        return redirect()->route('clientes.index')->with('status', 'Cliente creado correctamente.');
    }

    public function show(Client $client): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL]), 403);

        $client->loadCount([
            'shipments',
            'shipments as shipments_active_count' => fn ($query) => $query->where('estado_actual', '!=', Shipment::TRACKING_STATES[array_key_last(Shipment::TRACKING_STATES)]),
            'shipments as shipments_finished_count' => fn ($query) => $query->where('estado_actual', Shipment::TRACKING_STATES[array_key_last(Shipment::TRACKING_STATES)]),
            'shipments as shipments_in_transit_count' => fn ($query) => $query->whereIn('estado_actual', ['ATD', 'En transito', 'Transmision Aduanas', 'ETA']),
            'shipments as shipments_pending_endorsement_count' => fn ($query) => $query->where('estado_endoso', '!=', 'completado'),
        ]);

        $client->load([
            'shipments' => fn ($query) => $query->latest()->take(20),
        ]);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $client->update($this->validatedData($request, $client));

        return redirect()->route('clientes.show', $client)->with('status', 'Cliente actualizado correctamente.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($client->users()->exists() || $client->shipments()->exists(), 422, 'No se puede eliminar un cliente con usuarios o embarques asociados.');

        $client->delete();

        return redirect()->route('clientes.index')->with('status', 'Cliente eliminado correctamente.');
    }

    private function validatedData(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc' => ['required', 'string', 'max:20', Rule::unique('clients', 'ruc')->ignore($client)],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:activo,inactivo'],
        ]);
    }
}
