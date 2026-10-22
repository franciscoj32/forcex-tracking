<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Shipment;
use App\Services\ShipmentNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Client::class);

        $approval = (string) $request->query('aprobacion', '');

        return view('clients.index', [
            'clients' => Client::query()
                ->with('registeredBy:id,name')
                ->withCount([
                    'shipments',
                    'shipments as shipments_active_count' => fn ($query) => $query->where('estado_actual', '!=', 'Finalizado'),
                    'shipments as shipments_finished_count' => fn ($query) => $query->where('estado_actual', 'Finalizado'),
                ])
                ->when(in_array($approval, Client::APPROVAL_STATUSES, true), fn ($query) => $query->where('estado_aprobacion', $approval))
                ->orderByRaw("CASE WHEN estado_aprobacion = 'pendiente' THEN 0 ELSE 1 END")
                ->orderBy('razon_social')
                ->paginate(12)
                ->withQueryString(),
            'selectedApproval' => $approval,
            'approvalCounts' => Client::query()
                ->selectRaw('estado_aprobacion, COUNT(*) as total')
                ->groupBy('estado_aprobacion')
                ->pluck('total', 'estado_aprobacion')
                ->map(fn ($total) => (int) $total)
                ->all(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.create', ['client' => new Client()]);
    }

    public function store(Request $request, ShipmentNotifier $notifier): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $user = $request->user();
        $data = $this->validatedData($request, null, self::contactRules('contacto.'));
        $contactData = $data['contacto'];
        unset($data['contacto']);

        $client = DB::transaction(function () use ($data, $contactData, $user) {
            $client = new Client($data);
            $client->registrado_por_id = $user->id;

            // El Admin valida al registrar; el Comercial deja la ficha pendiente.
            if ($user->isAdmin()) {
                $client->estado_aprobacion = Client::APPROVAL_APPROVED;
                $client->aprobado_por_id = $user->id;
                $client->aprobado_en = now();
            } else {
                $client->estado_aprobacion = Client::APPROVAL_PENDING;
            }

            $client->save();

            $client->contacts()->create($this->normalizeContact($contactData) + ['es_principal' => true]);

            return $client;
        });

        if ($client->isPendingApproval()) {
            $notifier->clientPendingApproval($client);
        }

        $message = $client->isApproved()
            ? 'Cliente creado y aprobado correctamente.'
            : 'Cliente registrado. Queda pendiente de aprobacion del Administrador antes de emitir RO.';

        return redirect()->route('clientes.show', $client)->with('status', $message);
    }

    public function show(Client $client): View
    {
        Gate::authorize('view', $client);

        $lastState = Shipment::TRACKING_STATES[array_key_last(Shipment::TRACKING_STATES)];

        $client->loadCount([
            'shipments',
            'shipments as shipments_active_count' => fn ($query) => $query->where('estado_actual', '!=', $lastState),
            'shipments as shipments_finished_count' => fn ($query) => $query->where('estado_actual', $lastState),
            'shipments as shipments_delayed_count' => fn ($query) => $query->delayed(),
            'shipments as shipments_year_count' => fn ($query) => $query->whereYear('created_at', now()->year),
        ]);

        $client->load([
            'contacts',
            'registeredBy:id,name',
            'approvedBy:id,name',
            'shipments' => fn ($query) => $query->latest()->take(20),
        ]);

        return view('clients.show', [
            'client' => $client,
            'contactAreas' => ClientContact::AREA_LABELS,
        ]);
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->fill($this->validatedData($request, $client));

        // Si el Comercial corrige una ficha rechazada, vuelve a revision.
        $resubmitted = ! $request->user()->isAdmin() && $client->isRejected();

        if ($resubmitted) {
            $client->estado_aprobacion = Client::APPROVAL_PENDING;
        }

        $client->save();

        if ($resubmitted) {
            app(ShipmentNotifier::class)->clientPendingApproval($client);
        }

        return redirect()->route('clientes.show', $client)->with('status', 'Cliente actualizado correctamente.');
    }

    public function approve(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('approve', $client);

        $data = $request->validate([
            'observacion_aprobacion' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if($client->contacts()->doesntExist(), 422, 'El cliente necesita al menos un contacto antes de aprobarse.');

        $client->forceFill([
            'estado_aprobacion' => Client::APPROVAL_APPROVED,
            'aprobado_por_id' => $request->user()->id,
            'aprobado_en' => now(),
            'observacion_aprobacion' => $data['observacion_aprobacion'] ?? null,
        ])->save();

        app(ShipmentNotifier::class)->clientReviewed($client);

        return redirect()->route('clientes.show', $client)->with('status', 'Cliente aprobado. Ya se le pueden emitir RO.');
    }

    public function reject(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('approve', $client);

        $data = $request->validate([
            'observacion_aprobacion' => ['required', 'string', 'max:2000'],
        ], [
            'observacion_aprobacion.required' => 'Indica el motivo del rechazo para que el Comercial pueda corregirlo.',
        ]);

        abort_if($client->shipments()->exists(), 422, 'No se puede rechazar un cliente que ya tiene embarques.');

        $client->forceFill([
            'estado_aprobacion' => Client::APPROVAL_REJECTED,
            'aprobado_por_id' => $request->user()->id,
            'aprobado_en' => now(),
            'observacion_aprobacion' => $data['observacion_aprobacion'],
        ])->save();

        app(ShipmentNotifier::class)->clientReviewed($client);

        return redirect()->route('clientes.show', $client)->with('status', 'Cliente rechazado. El Comercial vera el motivo en la ficha.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);
        abort_if($client->users()->exists() || $client->shipments()->exists(), 422, 'No se puede eliminar un cliente con usuarios o embarques asociados.');

        $client->delete();

        return redirect()->route('clientes.index')->with('status', 'Cliente eliminado correctamente.');
    }

    /** Reglas de un contacto. Se reutilizan en ClientContactController. */
    public static function contactRules(string $prefix = ''): array
    {
        return [
            $prefix.'nombre' => ['required', 'string', 'max:255'],
            $prefix.'cargo' => ['nullable', 'string', 'max:255'],
            $prefix.'correo' => ['required', 'email', 'max:255'],
            $prefix.'telefono' => ['nullable', 'string', 'max:50'],
            $prefix.'area' => ['required', Rule::in(ClientContact::AREAS)],
            $prefix.'recibe_tracking' => ['nullable', 'boolean'],
            $prefix.'recibe_facturacion' => ['nullable', 'boolean'],
        ];
    }

    public static function normalizeContact(array $data): array
    {
        return [
            'nombre' => $data['nombre'],
            'cargo' => $data['cargo'] ?? null,
            'correo' => strtolower(trim($data['correo'])),
            'telefono' => $data['telefono'] ?? null,
            'area' => $data['area'],
            'recibe_tracking' => (bool) ($data['recibe_tracking'] ?? false),
            'recibe_facturacion' => (bool) ($data['recibe_facturacion'] ?? false),
        ];
    }

    private function validatedData(Request $request, ?Client $client = null, array $extraRules = []): array
    {
        $data = $request->validate($extraRules + [
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc' => ['required', 'digits:11', Rule::unique('clients', 'ruc')->ignore($client)],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'direccion' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'in:activo,inactivo'],
            'condicion_credito' => ['required', Rule::in(Client::CREDIT_CONDITIONS)],
            'dias_credito' => ['nullable', 'required_if:condicion_credito,'.Client::CREDIT_TERM, 'integer', 'min:1', 'max:365'],
            'linea_credito' => ['nullable', 'numeric', 'min:0'],
            'moneda_linea_credito' => ['required', Rule::in(Client::CURRENCIES)],
        ], [
            'ruc.digits' => 'El RUC debe tener 11 digitos.',
            'dias_credito.required_if' => 'Indica los dias de credito.',
        ], [
            'contacto.nombre' => 'nombre del contacto',
            'contacto.correo' => 'correo del contacto',
            'contacto.area' => 'area del contacto',
        ]);

        if ($data['condicion_credito'] === Client::CREDIT_CASH) {
            $data['dias_credito'] = null;
            $data['linea_credito'] = null;
        }

        return $data;
    }
}
