{{--
    Seguimiento: una sola linea de tiempo con los hitos, los formularios para
    registrar un hito o escribirle al cliente, y el historial completo plegado.
--}}
@php
    $clientNotes = $timelineEvents->where('tipo', \App\Models\ShipmentEvent::TYPE_CLIENT_NOTE)->sortByDesc('fecha')->values();
    $history = $timelineEvents->sortByDesc('fecha')->values();
    $recipients = $shipment->clientRecipientEmails();
    $trackingStateOptions = collect($trackingStates)->map(fn ($state) => ['value' => $state, 'label' => $state]);
    $logTone = fn (string $status) => match ($status) {
        'enviado' => 'text-slate-500',
        'fallido' => 'font-medium text-rose-700',
        'sin_destinatarios' => 'font-medium text-amber-700',
        default => 'text-slate-500',
    };
@endphp

<div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
    <div class="space-y-4">
        {{-- Recorrido --}}
        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Recorrido</h2>
            <ol class="px-4 py-3">
                @foreach ($trackingStates as $index => $state)
                    @php
                        $event = $timelineByState->get($state);
                        $isDone = $index < $currentStateIndex;
                        $isCurrent = $index === $currentStateIndex;
                    @endphp
                    <li class="relative flex gap-3 pb-4 last:pb-0">
                        @unless ($loop->last)
                            <span @class(['absolute left-[5px] top-4 h-full w-px', 'bg-accent-600' => $isDone, 'bg-slate-200' => ! $isDone])></span>
                        @endunless
                        <span @class([
                            'relative mt-1 h-[11px] w-[11px] shrink-0 rounded-full',
                            'bg-accent-600' => $isDone,
                            'bg-brand-600 ring-4 ring-brand-100' => $isCurrent,
                            'border-2 border-slate-300 bg-white' => ! $isDone && ! $isCurrent,
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                <p @class([
                                    'font-semibold text-slate-900' => $isCurrent,
                                    'text-slate-800' => $isDone,
                                    'text-slate-400' => ! $isDone && ! $isCurrent,
                                ])>
                                    {{ $state }}
                                    @if ($isCurrent)<span class="ml-1 text-xs font-medium text-brand-700">actual</span>@endif
                                </p>
                                @if ($event)
                                    <p class="text-xs tabular-nums text-slate-500">{{ $event->fecha->format('d/m/Y H:i') }}</p>
                                @endif
                            </div>
                            @if ($event?->descripcion)
                                <p class="mt-0.5 text-sm text-slate-600">{{ $event->descripcion }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Notas que ve el cliente --}}
        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Notas para el cliente <span class="font-normal text-slate-500">({{ $clientNotes->count() }})</span></h2>
            @forelse ($clientNotes as $note)
                <div class="border-b border-slate-100 px-4 py-3 last:border-0">
                    <p class="whitespace-pre-line text-slate-800">{{ $note->descripcion }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $note->user->name }} · {{ $note->fecha->format('d/m/Y H:i') }}</p>
                </div>
            @empty
                <p class="px-4 py-3 text-slate-500">Sin notas.</p>
            @endforelse
        </section>

        {{-- Correos y auditoria, plegados --}}
        <details class="fx-card group">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
                <span class="fx-section-title">Correos enviados <span class="font-normal text-slate-500">({{ $shipment->notificationLogs->count() }})</span></span>
                <span class="text-sm text-slate-500 group-open:hidden">Mostrar</span>
                <span class="hidden text-sm text-slate-500 group-open:inline">Ocultar</span>
            </summary>
            <div class="border-t border-slate-200">
                @forelse ($shipment->notificationLogs as $log)
                    <div class="border-b border-slate-100 px-4 py-2.5 last:border-0">
                        <p class="text-slate-800">{{ $log->asunto }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <span class="{{ $logTone($log->estado) }}">{{ str_replace('_', ' ', ucfirst($log->estado)) }}</span>
                            · {{ $log->destinatarios ? implode(', ', $log->destinatarios) : 'sin destinatarios' }}
                            · {{ $log->created_at->format('d/m/Y H:i') }}
                        </p>
                        @if ($log->error)
                            <p class="mt-0.5 text-xs text-rose-700">{{ $log->error }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-3 text-slate-500">Sin correos.</p>
                @endforelse
            </div>
        </details>

        <details class="fx-card group">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3">
                <span class="fx-section-title">Historial de cambios <span class="font-normal text-slate-500">({{ $history->count() }})</span></span>
                <span class="text-sm text-slate-500 group-open:hidden">Mostrar</span>
                <span class="hidden text-sm text-slate-500 group-open:inline">Ocultar</span>
            </summary>
            <div class="border-t border-slate-200">
                @foreach ($history as $event)
                    <div class="border-b border-slate-100 px-4 py-2.5 last:border-0">
                        <p class="text-slate-800">
                            <span class="font-medium">{{ $event->estado }}</span>
                            <span class="text-xs text-slate-500">· {{ $event->typeLabel() }}{{ $event->visible_cliente ? ' · lo ve el cliente' : '' }}</span>
                        </p>
                        @if ($event->descripcion)
                            <p class="text-sm text-slate-600">{{ $event->descripcion }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-500">{{ $event->user->name }} · {{ $event->fecha->format('d/m/Y H:i') }}</p>
                    </div>
                @endforeach
            </div>
        </details>
    </div>

    {{-- Acciones --}}
    <div class="space-y-4 lg:sticky lg:top-20">
        @if ($canTrack)
            <section id="registrar-hito" class="fx-card scroll-mt-20">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Registrar hito</h2>
                <form method="POST" action="{{ route('embarques.tracking.store', $shipment) }}" class="space-y-3 p-4" data-disable-native-autocomplete="true">
                    @csrf
                    <x-form-combobox
                        id="estado"
                        name="estado"
                        label="Estado"
                        :options="$trackingStateOptions"
                        :value="old('estado', $trackingStates[$currentStateIndex + 1] ?? $shipment->estado_actual)"
                        placeholder="Seleccionar estado"
                        search-placeholder="Buscar estado..."
                        empty-text="No hay estados que coincidan."
                        searchable
                        required
                    />
                    <x-form-date-picker
                        id="fecha"
                        name="fecha"
                        label="Fecha y hora"
                        :value="old('fecha', now()->format('Y-m-d H:i:S'))"
                        placeholder="Seleccionar fecha y hora"
                        enable-time
                        required
                    />
                    <div>
                        <label class="fx-label" for="descripcion">Nota interna (el cliente no la ve)</label>
                        <textarea class="fx-textarea" id="descripcion" name="descripcion" rows="2" placeholder="Detalle operativo">{{ old('descripcion') }}</textarea>
                    </div>
                    <button class="fx-btn-primary w-full" type="submit">Guardar hito</button>
                </form>
            </section>
        @endif

        @if ($canCommunicate)
            <section id="nota-cliente" class="fx-card scroll-mt-20">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Nota para el cliente</h2>
                <form method="POST" action="{{ route('embarques.notas-cliente.store', $shipment) }}" class="space-y-3 p-4">
                    @csrf
                    <div>
                        <label class="fx-label" for="nota">Mensaje (se publica en su tracking)</label>
                        <textarea class="fx-textarea" id="nota" name="nota" rows="3" maxlength="2000" required
                            placeholder="Ej. Tu carga pasó el aforo documentario sin observaciones.">{{ old('nota') }}</textarea>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="hidden" name="enviar_correo" value="0">
                        <input type="checkbox" name="enviar_correo" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" @checked($recipients !== []) @disabled($recipients === [])>
                        <span>
                            Enviar también por correo
                            <span class="block text-xs text-slate-500">{{ $recipients !== [] ? implode(', ', $recipients) : 'El cliente no tiene contactos con avisos de carga.' }}</span>
                        </span>
                    </label>
                    <button type="submit" class="fx-btn-primary w-full">Publicar nota</button>
                </form>
            </section>

            {{-- WhatsApp sin costo: abre el WhatsApp del Customer con el mensaje listo --}}
            @php
                $waContacts = ($shipment->client?->contacts ?? collect())
                    ->map(fn ($contact) => [
                        'id' => $contact->id,
                        'name' => $contact->nombre,
                        'first' => \Illuminate\Support\Str::before(trim($contact->nombre), ' '),
                        'phone' => ($normalized = \App\Support\WhatsApp::normalizePhone($contact->telefono)) ? \App\Support\WhatsApp::display($normalized) : null,
                        'tracking' => (bool) $contact->recibe_tracking,
                    ])
                    ->filter(fn ($contact) => $contact['phone'] !== null)
                    ->sortByDesc('tracking')
                    ->values();
                $waTemplates = \App\Support\WhatsApp::templates($shipment, auth()->user());
            @endphp
            <section id="whatsapp" class="fx-card scroll-mt-20"
                x-data="{
                    contacts: @js($waContacts),
                    templates: @js($waTemplates),
                    contact: @js(old('contact_id', $waContacts->first()['id'] ?? 'otro')),
                    template: @js(\App\Support\WhatsApp::defaultTemplate($shipment)),
                    message: @js(old('mensaje', '')),
                    build() {
                        const selected = this.contacts.find((c) => String(c.id) === String(this.contact));
                        const name = selected ? selected.first : '';
                        this.message = this.templates[this.template].text
                            .replaceAll('{nombre}', name)
                            .replace('Hola , ', 'Hola, ');
                    },
                }"
                x-init="if (! message) build()">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">WhatsApp al cliente</h2>
                <form method="POST" action="{{ route('embarques.whatsapp', $shipment) }}" target="_blank" class="space-y-3 p-4">
                    @csrf
                    <div>
                        <label class="fx-label" for="wa_contact">Para</label>
                        <select id="wa_contact" class="fx-select" x-model="contact" x-on:change="build()">
                            <template x-for="c in contacts" :key="c.id">
                                <option :value="c.id" x-text="c.name + ' · ' + c.phone"></option>
                            </template>
                            <option value="otro">Otro número…</option>
                        </select>
                        <input type="hidden" name="contact_id" :value="contact === 'otro' ? '' : contact">
                        @if ($waContacts->isEmpty())
                            <p class="mt-1 text-xs text-amber-700">
                                Ningún contacto del cliente tiene un celular válido.
                                @can('update', $shipment->client)
                                    <a href="{{ route('clientes.show', $shipment->client) }}" class="fx-btn-link-primary">Agregar en la ficha del cliente</a>
                                @endcan
                            </p>
                        @endif
                    </div>
                    <div x-show="contact === 'otro'" x-cloak>
                        <label class="fx-label" for="wa_telefono">Celular</label>
                        <input id="wa_telefono" name="telefono" class="fx-input" inputmode="tel" placeholder="987654321 o +51 987 654 321"
                            value="{{ old('telefono') }}" :required="contact === 'otro'" :disabled="contact !== 'otro'">
                    </div>
                    <div>
                        <label class="fx-label" for="wa_template">Mensaje sugerido</label>
                        <select id="wa_template" class="fx-select" x-model="template" x-on:change="build()">
                            @foreach ($waTemplates as $key => $waTemplate)
                                <option value="{{ $key }}">{{ $waTemplate['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fx-label" for="wa_mensaje">Mensaje (puedes editarlo)</label>
                        <textarea id="wa_mensaje" name="mensaje" class="fx-textarea" rows="7" maxlength="2000" required x-model="message"></textarea>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700">
                        <x-nav-icon name="whatsapp" class="h-4 w-4" />
                        Abrir WhatsApp
                    </button>
                    <p class="text-xs text-slate-500">Se abre tu WhatsApp con el mensaje listo y tú lo envías. No tiene costo. Queda registrado en el historial.</p>
                </form>
            </section>
        @endif

        {{-- La pre-alerta de llegada ya no vive aqui: aparece como aviso destacado en
             "Requiere atencion" y se completa en una ventana emergente. Lo enviado
             queda en "Correos enviados" y en el historial. --}}
    </div>
</div>
