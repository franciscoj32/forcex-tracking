@csrf

@php
    $clientOptions = collect($clients)
        ->map(fn ($client) => ['value' => (string) $client->id, 'label' => $client->razon_social]);
    $typeOptions = collect($types)
        ->map(fn ($type) => ['value' => $type, 'label' => $type]);
    $trackingStateOptions = collect($trackingStates)
        ->map(fn ($state) => ['value' => $state, 'label' => $state]);
    $endorsementStatusOptions = collect($endorsementStatuses)
        ->map(fn ($status) => ['value' => $status, 'label' => ucfirst(str_replace('_', ' ', $status))]);
    $countryOptions = collect($countries)
        ->map(fn ($country) => ['value' => $country, 'label' => $country]);
    $endorsementUserOptions = collect($endorsementUsers)
        ->map(fn ($endorsementUser) => [
            'value' => (string) $endorsementUser->id,
            'label' => $endorsementUser->name.' - @'.$endorsementUser->username,
        ]);
    $assignmentUserOptions = collect($assignmentUsers ?? [])
        ->map(fn ($assignmentUser) => [
            'value' => (string) $assignmentUser->id,
            'label' => $assignmentUser->name,
            'subtitle' => $assignmentUser->email.' - @'.$assignmentUser->username,
            'search' => strtolower($assignmentUser->name.' '.$assignmentUser->email.' '.$assignmentUser->username.' '.$assignmentUser->role),
        ]);
    $selectedResponsibleUsers = collect(old(
        'responsable_users',
        ($shipment->relationLoaded('userAssignments') ? $shipment->userAssignments : collect())
            ->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_RESPONSABLE)
            ->pluck('user_id')
            ->all()
    ))->map(fn ($id) => (string) $id)->all();
    $selectedTrackingUsers = collect(old(
        'tracking_users',
        ($shipment->relationLoaded('userAssignments') ? $shipment->userAssignments : collect())
            ->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_SEGUIMIENTO)
            ->pluck('user_id')
            ->all()
    ))->map(fn ($id) => (string) $id)->all();
@endphp

<div id="country-options" class="hidden">{{ $countryOptions->pluck('label')->join(' | ') }}</div>

<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    <div>
        <x-form-combobox
            id="client_id"
            name="client_id"
            label="Cliente"
            :options="$clientOptions"
            :value="old('client_id', $shipment->client_id ?? '')"
            placeholder="Seleccionar cliente"
            search-placeholder="Buscar cliente..."
            empty-text="No hay clientes que coincidan."
            searchable
            required
        />
    </div>
    <div>
        <label class="fx-label" for="nro_orden_trabajo">Nro. orden trabajo</label>
        <input class="fx-input" id="nro_orden_trabajo" name="nro_orden_trabajo" value="{{ old('nro_orden_trabajo', $shipment->nro_orden_trabajo ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="routing_order">Routing order</label>
        <input class="fx-input" id="routing_order" name="routing_order" value="{{ old('routing_order', $shipment->routing_order ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="bl">BL</label>
        <input class="fx-input" id="bl" name="bl" value="{{ old('bl', $shipment->bl ?? '') }}" required>
    </div>
    <div>
        <x-form-combobox
            id="tipo_carga"
            name="tipo_carga"
            label="Tipo de carga"
            :options="$typeOptions"
            :value="old('tipo_carga', $shipment->tipo_carga ?? '')"
            placeholder="Seleccionar tipo"
            required
        />
    </div>
    <div>
        <x-form-combobox
            id="estado_actual"
            name="estado_actual"
            label="Estado actual"
            :options="$trackingStateOptions"
            :value="old('estado_actual', $shipment->estado_actual ?? $trackingStates[0])"
            placeholder="Seleccionar estado"
            search-placeholder="Buscar estado..."
            empty-text="No hay estados que coincidan."
            searchable
            required
        />
    </div>
    <div>
        <x-form-combobox
            id="estado_endoso"
            name="estado_endoso"
            label="Estado de endoso"
            :options="$endorsementStatusOptions"
            :value="old('estado_endoso', $shipment->estado_endoso ?? 'pendiente')"
            placeholder="Seleccionar endoso"
            required
        />
    </div>
    <div>
        <x-form-autocomplete
            id="origen"
            name="origen"
            label="Pais de origen"
            :options="$countryOptions"
            :value="old('origen', $shipment->origen ?? '')"
            placeholder="Empieza a escribir el pais"
            search-placeholder="Buscar pais..."
            empty-text="No hay paises que coincidan."
            required
        />
    </div>
    <div>
        <label class="fx-label" for="puerto_carga">Puerto de carga</label>
        <input class="fx-input" id="puerto_carga" name="puerto_carga" value="{{ old('puerto_carga', $shipment->puerto_carga ?? '') }}" required>
    </div>
    <div>
        <label class="fx-label" for="puerto_descarga">Puerto de descarga</label>
        <input class="fx-input" id="puerto_descarga" name="puerto_descarga" value="{{ old('puerto_descarga', $shipment->puerto_descarga ?? '') }}" required>
    </div>
    <div>
        <x-form-autocomplete
            id="destino_final"
            name="destino_final"
            label="Pais de llegada"
            :options="$countryOptions"
            :value="old('destino_final', $shipment->destino_final ?? '')"
            placeholder="Empieza a escribir el pais"
            search-placeholder="Buscar pais..."
            empty-text="No hay paises que coincidan."
            required
        />
    </div>
    <div>
        <label class="fx-label" for="naviera">Naviera</label>
        <input class="fx-input" id="naviera" name="naviera" value="{{ old('naviera', $shipment->naviera ?? '') }}">
    </div>
    <div>
        <label class="fx-label" for="consignatario">Consignatario</label>
        <input class="fx-input" id="consignatario" name="consignatario" value="{{ old('consignatario', $shipment->consignatario ?? '') }}">
    </div>
    <div>
        <label class="fx-label" for="peso">Peso</label>
        <input class="fx-input" id="peso" name="peso" type="number" step="0.01" value="{{ old('peso', $shipment->peso ?? '') }}">
    </div>
    <div>
        <label class="fx-label" for="volumen">Volumen</label>
        <input class="fx-input" id="volumen" name="volumen" type="number" step="0.01" value="{{ old('volumen', $shipment->volumen ?? '') }}">
    </div>
    <div>
        <x-form-date-picker
            id="fecha_etd"
            name="fecha_etd"
            label="ETD"
            :value="old('fecha_etd', optional($shipment->fecha_etd ?? null)->format('Y-m-d'))"
            placeholder="Seleccionar ETD"
        />
    </div>
    <div>
        <x-form-date-picker
            id="fecha_eta"
            name="fecha_eta"
            label="ETA"
            :value="old('fecha_eta', optional($shipment->fecha_eta ?? null)->format('Y-m-d'))"
            placeholder="Seleccionar ETA"
        />
    </div>
    <div>
        <x-form-date-picker
            id="fecha_descarga"
            name="fecha_descarga"
            label="Fecha descarga"
            :value="old('fecha_descarga', optional($shipment->fecha_descarga ?? null)->format('Y-m-d'))"
            placeholder="Seleccionar descarga"
        />
    </div>
    <div>
        <x-form-date-picker
            id="fecha_endoso"
            name="fecha_endoso"
            label="Fecha de endoso"
            :value="old('fecha_endoso', optional($shipment->fecha_endoso ?? null)->format('Y-m-d'))"
            placeholder="Seleccionar endoso"
        />
    </div>
    <div class="md:col-span-2 xl:col-span-1">
        <x-form-combobox
            id="responsable_endoso_id"
            name="responsable_endoso_id"
            label="Responsable de endoso"
            :options="$endorsementUserOptions"
            :value="old('responsable_endoso_id', $shipment->responsable_endoso_id ?? '')"
            placeholder="Seleccionar responsable"
            all-label="Sin asignar"
            search-placeholder="Buscar responsable..."
            empty-text="No hay responsables que coincidan."
            searchable
        />
    </div>
    <div class="md:col-span-2 xl:col-span-1">
        <x-form-multiselect-tags
            id="responsable_users"
            name="responsable_users"
            label="Usuarios responsables"
            :options="$assignmentUserOptions"
            :values="$selectedResponsibleUsers"
            placeholder="Buscar por correo o nombre"
            search-placeholder="Correo, nombre o usuario..."
            empty-text="No hay usuarios que coincidan."
        />
    </div>
    <div class="md:col-span-2 xl:col-span-1">
        <x-form-multiselect-tags
            id="tracking_users"
            name="tracking_users"
            label="Usuarios en seguimiento"
            :options="$assignmentUserOptions"
            :values="$selectedTrackingUsers"
            placeholder="Buscar por correo o nombre"
            search-placeholder="Correo, nombre o usuario..."
            empty-text="No hay usuarios que coincidan."
        />
    </div>
    <div class="md:col-span-2 xl:col-span-3">
        <label class="fx-label" for="observaciones">Observaciones</label>
        <textarea class="fx-textarea" id="observaciones" name="observaciones" rows="4">{{ old('observaciones', $shipment->observaciones ?? '') }}</textarea>
    </div>
    <div class="md:col-span-2 xl:col-span-3">
        <label class="fx-label" for="observacion_endoso">Observacion de endoso</label>
        <textarea class="fx-textarea" id="observacion_endoso" name="observacion_endoso" rows="4">{{ old('observacion_endoso', $shipment->observacion_endoso ?? '') }}</textarea>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button class="fx-btn-primary" type="submit">Guardar</button>
    <a href="{{ route('embarques.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
