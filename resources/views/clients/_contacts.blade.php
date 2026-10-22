{{-- Contactos del cliente (1 a N). Se incluye en clients.show --}}
@php
    $canManageContacts = auth()->user()->can('manageContacts', $client);
@endphp

<section class="fx-card scroll-mt-20" id="contactos" x-data="{ adding: {{ $errors->hasAny(['nombre', 'correo', 'area']) ? 'true' : 'false' }} }">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <h2 class="fx-section-title">Contactos <span class="font-normal text-slate-500">({{ $client->contacts->count() }})</span></h2>
        @if ($canManageContacts)
            <button type="button" class="fx-btn-link-primary" x-on:click="adding = ! adding" x-text="adding ? 'Cancelar' : 'Agregar contacto'">Agregar contacto</button>
        @endif
    </div>

    @if ($canManageContacts)
        <form x-show="adding" x-cloak method="POST" action="{{ route('clientes.contactos.store', $client) }}" class="border-b border-slate-200 bg-slate-50 p-4">
            @csrf
            @include('clients._contact_fields', ['contact' => new \App\Models\ClientContact(['area' => 'logistica', 'recibe_tracking' => true]), 'prefixId' => 'new_contact'])
            <button type="submit" class="fx-btn-primary mt-4">Guardar contacto</button>
        </form>
    @endif

    <ul class="divide-y divide-slate-100">
        @forelse ($client->contacts as $contact)
            <li class="px-4 py-3" x-data="{ editing: false }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">
                            {{ $contact->nombre }}
                            @if ($contact->es_principal)<span class="ml-1 text-xs font-normal text-slate-500">principal</span>@endif
                        </p>
                        <p class="text-sm text-slate-500">{{ collect([$contact->cargo, $contact->areaLabel()])->filter()->join(' · ') }}</p>
                        <p class="text-sm text-slate-700">{{ $contact->correo }}{{ $contact->telefono ? ' · '.$contact->telefono : '' }}</p>
                        @php
                            $receives = collect([
                                $contact->recibe_tracking ? 'avisos de carga' : null,
                                $contact->recibe_facturacion ? 'facturación' : null,
                            ])->filter();
                        @endphp
                        <p class="mt-0.5 text-xs {{ $receives->isEmpty() ? 'text-slate-400' : 'text-accent-700' }}">
                            {{ $receives->isEmpty() ? 'No recibe correos' : 'Recibe '.$receives->join(' y ') }}
                        </p>
                    </div>
                    @if ($canManageContacts)
                        <div class="flex gap-3">
                            <button type="button" class="fx-btn-link-primary" x-on:click="editing = ! editing" x-text="editing ? 'Cerrar' : 'Editar'">Editar</button>
                            <form method="POST" action="{{ route('contactos.destroy', $contact) }}" onsubmit="return confirm('¿Eliminar este contacto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-rose-700 hover:underline">Eliminar</button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($canManageContacts)
                    <form x-show="editing" x-cloak method="POST" action="{{ route('contactos.update', $contact) }}" class="mt-3 rounded-md bg-slate-50 p-4">
                        @csrf
                        @method('PUT')
                        @include('clients._contact_fields', ['contact' => $contact, 'prefixId' => 'contact_'.$contact->id])
                        <button type="submit" class="fx-btn-primary mt-4">Guardar contacto</button>
                    </form>
                @endif
            </li>
        @empty
            <li class="px-4 py-4 text-slate-500">Sin contactos.</li>
        @endforelse
    </ul>
</section>
