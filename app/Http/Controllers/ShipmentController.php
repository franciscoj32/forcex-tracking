<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Client;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\ShipmentUserAssignment;
use App\Models\UserShipmentPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $search = trim((string) request('q', ''));
        $clientId = auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
            ? request('client_id')
            : null;
        $lifecycle = (string) request('lifecycle', 'all');

        $query = $this->buildIndexQuery($user, $search, $clientId, $lifecycle)
            ->orderByDesc('is_pinned_for_user')
            ->latest('shipments.updated_at');

        return view('shipments.index', [
            'shipments' => $query->paginate(12)->withQueryString(),
            'search' => $search,
            'selectedClientId' => (string) ($clientId ?? ''),
            'selectedLifecycle' => $lifecycle,
            'clients' => $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                ? Client::orderBy('razon_social')->get(['id', 'razon_social'])
                : collect(),
        ]);
    }

    public function export(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $clientId = $user->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
            ? $request->query('client_id')
            : null;
        $lifecycle = (string) $request->query('lifecycle', 'all');

        $shipments = $this->buildIndexQuery($user, $search, $clientId, $lifecycle)
            ->with('endorsementResponsible')
            ->orderByDesc('is_pinned_for_user')
            ->latest('shipments.updated_at')
            ->get();

        $filters = [
            'Busqueda' => $search !== '' ? $search : 'Todas',
            'Cliente' => blank($clientId)
                ? 'Todos'
                : Client::query()->whereKey($clientId)->value('razon_social'),
            'Ciclo' => match ($lifecycle) {
                'active' => 'Activos',
                'finished' => 'Finalizados',
                default => 'Todos',
            },
        ];

        $html = view('shipments.export', [
            'shipments' => $shipments,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->render();

        $filename = 'embarques_'.now()->format('Ymd_His').'.xls';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        return view('shipments.create', [
            'shipment' => new Shipment(),
            'clients' => Client::orderBy('razon_social')->get(),
            'endorsementUsers' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                ->where('estado', User::ESTADO_ACTIVO)
                ->orderBy('name')
                ->get(),
            'assignmentUsers' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                ->where('estado', User::ESTADO_ACTIVO)
                ->orderBy('name')
                ->get(),
            'countries' => Shipment::countryOptions(),
            'trackingStates' => Shipment::TRACKING_STATES,
            'endorsementStatuses' => Shipment::ENDORSEMENT_STATUSES,
            'types' => Shipment::TIPOS_CARGA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        $shipment = Shipment::create($this->validatedData($request));
        $this->syncAssignments($shipment, $request);

        ShipmentEvent::create([
            'shipment_id' => $shipment->id,
            'estado' => $shipment->estado_actual,
            'fecha' => now(),
            'descripcion' => 'Embarque creado en el sistema.',
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('embarques.show', $shipment)->with('status', 'Embarque creado correctamente.');
    }

    public function show(Shipment $shipment): View
    {
        $this->authorizeAccess($shipment);

        $activeTab = request('tab', 'datos');

        if (! in_array($activeTab, ['datos', 'tracking', 'documentos'], true)) {
            $activeTab = 'datos';
        }

        $shipment->load('client');

        $timelineEvents = $shipment->events()
            ->with('user:id,name')
            ->orderBy('fecha')
            ->get([
                'id',
                'shipment_id',
                'estado',
                'fecha',
                'descripcion',
                'user_id',
            ]);
        $logbookEvents = $timelineEvents->sortByDesc('fecha')->values();
        $visibleLogbookEvents = $logbookEvents->take(12)->values();
        $hiddenLogbookCount = max($logbookEvents->count() - $visibleLogbookEvents->count(), 0);

        $documentCount = $shipment->documents()->count();
        $endorsementDocumentsPreview = $shipment->documents()
            ->where('categoria', Document::CATEGORY_ENDOSO)
            ->latest('created_at')
            ->limit(4)
            ->get([
                'id',
                'shipment_id',
                'categoria',
                'tipo_documento',
                'nombre_original',
                'created_at',
            ]);
        $endorsementDocumentsCount = $shipment->documents()
            ->where('categoria', Document::CATEGORY_ENDOSO)
            ->count();

        if ($activeTab === 'datos') {
            $shipment->loadMissing([
                'endorsementResponsible:id,name',
                'userAssignments.user:id,name',
                'provisions',
                'invoices',
            ]);
        }

        if ($activeTab === 'documentos') {
            $shipment->loadMissing(['documents.user:id,name']);
        }

        return view('shipments.show', [
            'shipment' => $shipment,
            'activeTab' => $activeTab,
            'timelineEvents' => $timelineEvents,
            'visibleLogbookEvents' => $visibleLogbookEvents,
            'hiddenLogbookCount' => $hiddenLogbookCount,
            'documentCount' => $documentCount,
            'endorsementDocumentsPreview' => $endorsementDocumentsPreview,
            'endorsementDocumentsCount' => $endorsementDocumentsCount,
            'trackingStates' => Shipment::TRACKING_STATES,
            'endorsementStatuses' => Shipment::ENDORSEMENT_STATUSES,
            'documentCategories' => Document::CATEGORIES,
            'documentTypeSuggestions' => Document::TYPE_SUGGESTIONS,
        ]);
    }

    public function edit(Shipment $shipment): View
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);
        $shipment->loadMissing('userAssignments');

        return view('shipments.edit', [
            'shipment' => $shipment,
            'clients' => Client::orderBy('razon_social')->get(),
            'endorsementUsers' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                ->where('estado', User::ESTADO_ACTIVO)
                ->orderBy('name')
                ->get(),
            'assignmentUsers' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                ->where('estado', User::ESTADO_ACTIVO)
                ->orderBy('name')
                ->get(),
            'countries' => Shipment::countryOptions(),
            'trackingStates' => Shipment::TRACKING_STATES,
            'endorsementStatuses' => Shipment::ENDORSEMENT_STATUSES,
            'types' => Shipment::TIPOS_CARGA,
        ]);
    }

    public function update(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole([User::ROLE_ADMIN, User::ROLE_OPERACIONES]), 403);

        $validated = $this->validatedData($request, $shipment);
        $previousState = $shipment->estado_actual;
        $shipment->loadMissing('userAssignments');
        $fieldChanges = $this->resolveShipmentFieldChanges($shipment, $validated);
        $hadAssignmentChanges = $this->shipmentAssignmentsWillChange($shipment, $request);

        $shipment->update($validated);
        $this->syncAssignments($shipment, $request);

        if ($previousState !== $shipment->estado_actual) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'estado' => $shipment->estado_actual,
                'fecha' => now(),
                'descripcion' => 'Estado actualizado desde la ficha del embarque.',
                'user_id' => auth()->id(),
            ]);
        }

        $auditDescription = $this->buildShipmentAuditDescription($fieldChanges, $hadAssignmentChanges);

        if ($auditDescription !== null) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'estado' => $shipment->estado_actual,
                'fecha' => now(),
                'descripcion' => $auditDescription,
                'user_id' => auth()->id(),
            ]);
        }

        return redirect()->route('embarques.show', $shipment)->with('status', 'Embarque actualizado correctamente.');
    }

    public function destroy(Shipment $shipment): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $shipment->load(['documents', 'invoices']);

        foreach ($shipment->documents as $document) {
            if (Storage::disk('private')->exists($document->ruta)) {
                Storage::disk('private')->delete($document->ruta);
            }
        }

        foreach ($shipment->invoices as $invoice) {
            if ($invoice->archivo_pdf && Storage::disk('private')->exists($invoice->archivo_pdf)) {
                Storage::disk('private')->delete($invoice->archivo_pdf);
            }
        }

        $shipment->delete();

        return redirect()->route('embarques.index')->with('status', 'Embarque eliminado correctamente.');
    }

    public function togglePin(Shipment $shipment): RedirectResponse
    {
        $this->authorizeAccess($shipment);

        $preference = UserShipmentPreference::firstOrNew([
            'user_id' => auth()->id(),
            'shipment_id' => $shipment->id,
        ]);

        $preference->is_pinned = ! ($preference->exists ? $preference->is_pinned : false);
        $preference->save();

        return back()->with(
            'status',
            $preference->is_pinned
                ? 'Embarque destacado y fijado en la parte superior.'
                : 'Embarque quitado de destacados.'
        );
    }

    private function validatedData(Request $request, ?Shipment $shipment = null): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'nro_orden_trabajo' => ['required', 'string', 'max:100', Rule::unique('shipments', 'nro_orden_trabajo')->ignore($shipment)],
            'routing_order' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shipments', 'routing_order')
                    ->where(fn ($query) => $query->where('client_id', $request->integer('client_id')))
                    ->ignore($shipment),
            ],
            'bl' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shipments', 'bl')
                    ->where(fn ($query) => $query->where('client_id', $request->integer('client_id')))
                    ->ignore($shipment),
            ],
            'tipo_carga' => ['required', Rule::in(Shipment::TIPOS_CARGA)],
            'origen' => ['required', 'string', 'max:255'],
            'puerto_carga' => ['required', 'string', 'max:255'],
            'puerto_descarga' => ['required', 'string', 'max:255'],
            'destino_final' => ['required', 'string', 'max:255'],
            'naviera' => ['nullable', 'string', 'max:255'],
            'consignatario' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'volumen' => ['nullable', 'numeric', 'min:0'],
            'fecha_etd' => ['nullable', 'date'],
            'fecha_eta' => ['nullable', 'date'],
            'fecha_descarga' => ['nullable', 'date'],
            'estado_actual' => [
                'required',
                Rule::in(Shipment::TRACKING_STATES),
                function (string $attribute, mixed $value, \Closure $fail) use ($shipment) {
                    if (! $shipment || ! is_string($value) || $value === $shipment->estado_actual) {
                        return;
                    }

                    if (! $shipment->canAdvanceToTrackingState($value)) {
                        $fail('El estado actual solo puede avanzar en el flujo del embarque.');
                    }
                },
            ],
            'estado_endoso' => ['required', Rule::in(Shipment::ENDORSEMENT_STATUSES)],
            'fecha_endoso' => ['nullable', 'date'],
            'responsable_endoso_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query
                        ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                        ->where('estado', User::ESTADO_ACTIVO);
                }),
            ],
            'observacion_endoso' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'responsable_users' => ['nullable', 'array'],
            'responsable_users.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query
                        ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                        ->where('estado', User::ESTADO_ACTIVO);
                }),
            ],
            'tracking_users' => ['nullable', 'array'],
            'tracking_users.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query
                        ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OPERACIONES, User::ROLE_COMERCIAL])
                        ->where('estado', User::ESTADO_ACTIVO);
                }),
            ],
        ]);
    }

    private function authorizeAccess(Shipment $shipment): void
    {
        $user = auth()->user();

        if ($user->role === User::ROLE_CLIENTE && $shipment->client_id !== $user->client_id) {
            abort(403);
        }
    }

    private function buildIndexQuery(User $user, string $search, mixed $clientId, string $lifecycle)
    {
        $preferences = UserShipmentPreference::query()
            ->select(['shipment_id', 'is_pinned'])
            ->where('user_id', $user->id);

        $query = Shipment::query()
            ->with('client')
            ->leftJoinSub($preferences, 'shipment_preferences', function ($join) {
                $join->on('shipments.id', '=', 'shipment_preferences.shipment_id');
            })
            ->select('shipments.*')
            ->selectRaw('COALESCE(shipment_preferences.is_pinned, 0) as is_pinned_for_user');

        if ($user->role === User::ROLE_CLIENTE) {
            $query->where('client_id', $user->client_id);
        } elseif (filled($clientId)) {
            $query->where('client_id', $clientId);
        }

        if ($search !== '') {
            $query->where(function ($shipmentQuery) use ($search) {
                $shipmentQuery
                    ->where('nro_orden_trabajo', 'like', "%{$search}%")
                    ->orWhere('bl', 'like', "%{$search}%")
                    ->orWhere('routing_order', 'like', "%{$search}%");

                if (Shipment::hasTrackingCodeColumn()) {
                    $shipmentQuery->orWhere('tracking_code', 'like', "%{$search}%");
                }
            });
        }

        if ($lifecycle === 'active') {
            $query->where('estado_actual', '!=', 'Finalizado');
        } elseif ($lifecycle === 'finished') {
            $query->where('estado_actual', 'Finalizado');
        }

        return $query;
    }

    private function syncAssignments(Shipment $shipment, Request $request): void
    {
        $responsableIds = collect($request->input('responsable_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $trackingIds = collect($request->input('tracking_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $responsableIds->contains($id))
            ->unique()
            ->values();

        $responsablePayload = $responsableIds
            ->mapWithKeys(fn (int $id) => [
                $id => [
                    'assignment_type' => ShipmentUserAssignment::TYPE_RESPONSABLE,
                    'alerts_enabled' => true,
                ],
            ])
            ->all();

        $trackingPayload = $trackingIds
            ->mapWithKeys(fn (int $id) => [
                $id => [
                    'assignment_type' => ShipmentUserAssignment::TYPE_SEGUIMIENTO,
                    'alerts_enabled' => true,
                ],
            ])
            ->all();

        $syncPayload = $responsablePayload + $trackingPayload;

        $shipment->assignedUsers()->sync($syncPayload);
    }

    private function resolveShipmentFieldChanges(Shipment $shipment, array $validated): array
    {
        $probe = $shipment->replicate();
        $probe->exists = true;
        $probe->fill($validated);

        return collect(array_keys($probe->getDirty()))
            ->reject(fn (string $field) => $field === 'updated_at')
            ->values()
            ->all();
    }

    private function shipmentAssignmentsWillChange(Shipment $shipment, Request $request): bool
    {
        $requestedResponsableIds = collect($request->input('responsable_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $currentAssignments = $shipment->userAssignments
            ->map(fn (ShipmentUserAssignment $assignment) => $assignment->assignment_type.':'.$assignment->user_id)
            ->sort()
            ->values()
            ->all();

        $requestedResponsables = $requestedResponsableIds
            ->map(fn (int $id) => ShipmentUserAssignment::TYPE_RESPONSABLE.':'.$id);

        $requestedTracking = collect($request->input('tracking_users', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $requestedResponsableIds->contains($id))
            ->unique()
            ->map(fn (int $id) => ShipmentUserAssignment::TYPE_SEGUIMIENTO.':'.$id);

        $requestedAssignments = $requestedResponsables
            ->concat($requestedTracking)
            ->sort()
            ->values()
            ->all();

        return $currentAssignments !== $requestedAssignments;
    }

    private function buildShipmentAuditDescription(array $fieldChanges, bool $hadAssignmentChanges): ?string
    {
        $labels = collect($fieldChanges)
            ->map(fn (string $field) => $this->shipmentFieldLabel($field))
            ->filter()
            ->values();

        if ($hadAssignmentChanges) {
            $labels->push('usuarios vinculados');
        }

        $labels = $labels->unique()->values();

        if ($labels->isEmpty()) {
            return null;
        }

        return 'Ficha actualizada: '.$labels->join(', ').'.';
    }

    private function shipmentFieldLabel(string $field): ?string
    {
        return [
            'client_id' => 'cliente',
            'nro_orden_trabajo' => 'OT',
            'routing_order' => 'routing order',
            'bl' => 'BL',
            'tipo_carga' => 'tipo de carga',
            'origen' => 'pais de origen',
            'puerto_carga' => 'puerto de carga',
            'puerto_descarga' => 'puerto de descarga',
            'destino_final' => 'pais de llegada',
            'naviera' => 'naviera',
            'consignatario' => 'consignatario',
            'peso' => 'peso',
            'volumen' => 'volumen',
            'fecha_etd' => 'ETD',
            'fecha_eta' => 'ETA',
            'fecha_descarga' => 'fecha de descarga',
            'estado_actual' => 'estado actual',
            'estado_endoso' => 'estado de endoso',
            'fecha_endoso' => 'fecha de endoso',
            'responsable_endoso_id' => 'responsable de endoso',
            'observacion_endoso' => 'observacion de endoso',
            'observaciones' => 'observaciones',
        ][$field] ?? null;
    }
}
