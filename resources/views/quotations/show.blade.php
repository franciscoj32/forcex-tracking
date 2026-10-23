@php
    $row = 'flex items-baseline justify-between gap-4 py-1.5';
    $dt = 'shrink-0 text-slate-500';
    $dd = 'min-w-0 text-right text-slate-900 break-words';
    $air = $quotation->tipo_carga === 'AEREO';
    $service = array_filter([
        'Tipo de carga' => $quotation->cargoLabel(),
        'Incoterm' => $quotation->incoterm,
        'Origen' => $quotation->origen,
        $air ? 'Aeropuerto de salida' : 'Puerto de carga' => $quotation->puerto_carga,
        $air ? 'Aeropuerto de llegada' : 'Puerto de descarga' => $quotation->puerto_descarga,
        'Destino' => $quotation->destino,
        'Mercancía' => $quotation->mercancia,
        'Contenedores' => $quotation->contenedores,
        'Peso' => $quotation->peso !== null ? number_format((float) $quotation->peso, 2).' kg' : null,
        'Volumen' => $quotation->volumen !== null ? rtrim(rtrim(number_format((float) $quotation->volumen, 3), '0'), '.').' m³' : null,
        $air ? 'Aerolínea' : 'Naviera' => $quotation->naviera,
        'Tiempo de tránsito' => $quotation->tiempo_transito,
        'Frecuencia' => $quotation->frecuencia,
        'Días libres' => $quotation->dias_libres !== null ? $quotation->dias_libres.' días' : null,
    ], fn ($value) => filled($value));
    $igvPercent = rtrim(rtrim(number_format(\App\Models\Quotation::igvRate() * 100, 2), '0'), '.');
    $dialog = session('dialog');
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block"><a href="{{ route('cotizaciones.index') }}" class="hover:underline">Cotizaciones</a> / {{ $quotation->razon_social }}</p>
        <h1>Cotización {{ $quotation->numero }}</h1>
    </x-slot>

    <x-slot name="actions">
        <a href="{{ route('cotizaciones.pdf', $quotation) }}" target="_blank" rel="noopener" class="fx-btn-secondary">Ver PDF</a>
        @can('update', $quotation)
            <a href="{{ route('cotizaciones.edit', $quotation) }}" class="fx-btn-secondary hidden sm:inline-flex">Editar</a>
        @endcan
        @if ($canSend)
            <button type="button" x-data x-on:click="$dispatch('open-dialog', 'correo')" class="fx-btn-secondary">Correo</button>
            <button type="button" x-data x-on:click="$dispatch('open-dialog', 'whatsapp')" class="inline-flex h-10 items-center gap-2 rounded-md bg-green-600 px-4 text-sm font-medium text-white hover:bg-green-700">
                <x-nav-icon name="whatsapp" />
                WhatsApp
            </button>
        @endif
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-2">
                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">
                        Cliente
                        @unless ($quotation->client_id)
                            <span class="fx-badge ml-1 bg-slate-100 text-slate-600">Prospecto</span>
                        @endunless
                    </h2>
                    <dl class="divide-y divide-slate-100 px-4 py-1">
                        <div class="{{ $row }}">
                            <dt class="{{ $dt }}">Razón social</dt>
                            <dd class="{{ $dd }}">
                                @if ($quotation->client)
                                    <a href="{{ route('clientes.show', $quotation->client) }}" class="hover:underline">{{ $quotation->razon_social }}</a>
                                @else
                                    {{ $quotation->razon_social }}
                                @endif
                            </dd>
                        </div>
                        <div class="{{ $row }}"><dt class="{{ $dt }}">RUC</dt><dd class="{{ $dd }} tabular-nums">{{ $quotation->ruc }}</dd></div>
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Dirección</dt><dd class="{{ $dd }}">{{ $quotation->direccion ?: '—' }}</dd></div>
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Atención</dt><dd class="{{ $dd }}">{{ $quotation->contacto_nombre ?: '—' }}</dd></div>
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Correo</dt><dd class="{{ $dd }}">{{ $quotation->contacto_correo ?: '—' }}</dd></div>
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Celular</dt><dd class="{{ $dd }}">{{ $quotation->contacto_telefono ?: '—' }}</dd></div>
                    </dl>
                </section>

                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Servicio y ruta</h2>
                    <dl class="divide-y divide-slate-100 px-4 py-1">
                        @foreach ($service as $label => $value)
                            <div class="{{ $row }}"><dt class="{{ $dt }}">{{ $label }}</dt><dd class="{{ $dd }}">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            </div>

            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Tarifas</h2>
                <div class="overflow-x-auto">
                    <table class="fx-table">
                        <thead>
                            <tr>
                                <th class="fx-th">Descripción</th>
                                <th class="fx-th text-right">Cantidad</th>
                                <th class="fx-th text-right">Precio unit.</th>
                                <th class="fx-th text-center">IGV</th>
                                <th class="fx-th text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($quotation->items as $item)
                                <tr>
                                    <td class="fx-td">{{ $item->descripcion }}</td>
                                    <td class="fx-td text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $item->cantidad, 2), '0'), '.') }}</td>
                                    <td class="fx-td text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format((float) $item->precio_unitario, $item->moneda) }}</td>
                                    <td class="fx-td text-center text-slate-600">{{ $item->afecto_igv ? 'Afecto' : 'Inafecto' }}</td>
                                    <td class="fx-td text-right tabular-nums whitespace-nowrap">{{ \App\Support\Money::format($item->total(), $item->moneda) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap justify-end gap-6 border-t border-slate-200 px-4 py-3 text-sm tabular-nums">
                    @foreach ($totals as $currency => $amounts)
                        <dl class="w-56 space-y-0.5">
                            <div class="flex justify-between text-slate-600"><dt>Subtotal {{ $currency }}</dt><dd>{{ \App\Support\Money::format($amounts['subtotal'], $currency) }}</dd></div>
                            <div class="flex justify-between text-slate-600"><dt>IGV {{ $igvPercent }}%</dt><dd>{{ \App\Support\Money::format($amounts['igv'], $currency) }}</dd></div>
                            <div class="flex justify-between font-semibold text-slate-900"><dt>Total {{ $currency }}</dt><dd>{{ \App\Support\Money::format($amounts['total'], $currency) }}</dd></div>
                        </dl>
                    @endforeach
                </div>
            </section>

            @if (filled($quotation->condiciones) || filled($quotation->observaciones))
                <section class="fx-card">
                    <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Condiciones</h2>
                    <div class="space-y-3 p-4 text-sm text-slate-700">
                        @if (filled($quotation->condiciones))
                            <p class="whitespace-pre-line">{{ $quotation->condiciones }}</p>
                        @endif
                        @if (filled($quotation->observaciones))
                            <p class="whitespace-pre-line"><span class="font-medium text-slate-900">Observaciones:</span> {{ $quotation->observaciones }}</p>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        {{-- Estado --}}
        <aside class="space-y-4">
            <section class="fx-card">
                <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Estado</h2>
                <div class="space-y-3 p-4 text-sm">
                    <p><span class="fx-badge {{ $quotation->statusBadge() }}">{{ $quotation->statusLabel() }}</span></p>
                    <dl class="divide-y divide-slate-100">
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Fecha</dt><dd class="{{ $dd }}">{{ $quotation->fecha->format('d/m/Y') }}</dd></div>
                        <div class="{{ $row }}">
                            <dt class="{{ $dt }}">Válida hasta</dt>
                            <dd @class([$dd, 'font-medium text-rose-700' => $quotation->isExpired()])>{{ $quotation->validUntil()->format('d/m/Y') }}</dd>
                        </div>
                        <div class="{{ $row }}"><dt class="{{ $dt }}">Comercial</dt><dd class="{{ $dd }}">{{ $quotation->comercial?->name ?? '—' }}</dd></div>
                        @if ($quotation->enviada_en)
                            <div class="{{ $row }}">
                                <dt class="{{ $dt }}">Último envío</dt>
                                <dd class="{{ $dd }}">
                                    {{ $quotation->enviada_en->format('d/m/Y H:i') }}
                                    <span class="block text-xs text-slate-500">{{ $quotation->ultimo_canal === 'whatsapp' ? 'WhatsApp' : 'Correo' }}{{ $quotation->sentBy ? ' · '.$quotation->sentBy->name : '' }}</span>
                                </dd>
                            </div>
                        @endif
                        @if ($quotation->respondida_en)
                            <div class="{{ $row }}"><dt class="{{ $dt }}">Respuesta</dt><dd class="{{ $dd }}">{{ $quotation->respondida_en->format('d/m/Y') }}</dd></div>
                        @endif
                    </dl>

                    @if ($canSend)
                        <div class="space-y-2 border-t border-slate-200 pt-3">
                            @if ($quotation->isClosed())
                                <form method="POST" action="{{ route('cotizaciones.estado', $quotation) }}">
                                    @csrf
                                    <input type="hidden" name="estado" value="enviada">
                                    <button type="submit" class="fx-btn-secondary w-full">Volver a "enviada"</button>
                                </form>
                                <p class="text-xs text-slate-500">Úsalo si se marcó por error. Mientras esté {{ mb_strtolower($quotation->statusLabel()) }} no se puede editar.</p>
                            @else
                                <p class="text-xs text-slate-500">¿El cliente respondió?</p>
                                <div class="grid grid-cols-2 gap-2">
                                    <form method="POST" action="{{ route('cotizaciones.estado', $quotation) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="aceptada">
                                        <button type="submit" class="fx-btn-primary w-full">Aceptada</button>
                                    </form>
                                    <form method="POST" action="{{ route('cotizaciones.estado', $quotation) }}">
                                        @csrf
                                        <input type="hidden" name="estado" value="rechazada">
                                        <button type="submit" class="fx-btn-secondary w-full text-rose-700">Rechazada</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            @can('delete', $quotation)
                <form method="POST" action="{{ route('cotizaciones.destroy', $quotation) }}" x-data x-on:submit="if (! confirm('¿Eliminar la cotización {{ $quotation->numero }}? No se puede deshacer.')) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="fx-btn-link-primary text-rose-700">Eliminar cotización</button>
                </form>
            @endcan
        </aside>
    </div>

    @if ($canSend)
        {{-- Correo con el PDF adjunto --}}
        <x-dialog name="correo" title="Enviar por correo" :show="$dialog === 'correo'">
            <form method="POST" action="{{ route('cotizaciones.correo', $quotation) }}" class="space-y-4 p-5">
                @csrf
                @unless ($mailEnabled)
                    <p class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">Los correos automáticos están desactivados en este servidor. Descarga el PDF y envíalo desde tu correo.</p>
                @endunless
                <fieldset>
                    <legend class="fx-label">Para</legend>
                    <div class="mt-1 space-y-1.5">
                        @forelse ($emailContacts as $index => $contact)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="correos[]" value="{{ $contact['correo'] }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30"
                                    @checked(in_array($contact['correo'], old('correos', $index === 0 ? [$contact['correo']] : []), true))>
                                <span>{{ $contact['nombre'] }} <span class="text-slate-500">· {{ $contact['correo'] }}</span></span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500">La cotización no tiene correo de contacto. Escríbelo abajo.</p>
                        @endforelse
                    </div>
                </fieldset>
                <div>
                    <label class="fx-label" for="otros_correos">Otros correos (separados por coma)</label>
                    <input class="fx-input" id="otros_correos" name="otros_correos" value="{{ old('otros_correos') }}" placeholder="compras@cliente.com, gerencia@cliente.com">
                </div>
                <div>
                    <label class="fx-label" for="nota">Mensaje (opcional)</label>
                    <textarea class="fx-textarea" id="nota" name="nota" rows="4" maxlength="2000" placeholder="Estimados, adjuntamos la cotización solicitada…">{{ old('nota') }}</textarea>
                </div>
                <p class="text-xs text-slate-500">Se adjunta el PDF {{ $quotation->pdfFileName() }}. Si el cliente responde, el correo te llega a ti ({{ $quotation->comercial?->email }}).</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="fx-btn-secondary" x-on:click="open = false">Cancelar</button>
                    <button type="submit" class="fx-btn-primary" @disabled(! $mailEnabled)>Enviar con PDF adjunto</button>
                </div>
            </form>
        </x-dialog>

        {{-- WhatsApp sin costo con enlace al PDF --}}
        <x-dialog name="whatsapp" title="Enviar por WhatsApp" :show="$dialog === 'whatsapp'">
            <form method="POST" action="{{ route('cotizaciones.whatsapp', $quotation) }}" target="_blank" class="space-y-4 p-5"
                x-data="{
                    contacts: @js($phoneContacts),
                    template: @js($whatsappMessage),
                    contact: @js(old('telefono') ? 'otro' : ($phoneContacts->isNotEmpty() ? '0' : 'otro')),
                    phone: @js(old('telefono', '')),
                    message: @js(old('mensaje', '')),
                    build() {
                        const selected = this.contacts[this.contact];
                        this.message = this.template.replaceAll('{nombre}', selected ? selected.first : '').replace('Hola , ', 'Hola, ');
                    },
                }"
                x-init="if (! message) build()"
                x-on:submit="setTimeout(() => window.location.reload(), 1500)">
                @csrf
                <div>
                    <label class="fx-label" for="wa_contact">Para</label>
                    <select id="wa_contact" class="fx-select" x-model="contact" x-on:change="build()">
                        <template x-for="(c, index) in contacts" :key="index">
                            <option :value="String(index)" x-text="c.name + ' · ' + c.display"></option>
                        </template>
                        <option value="otro">Otro número…</option>
                    </select>
                    <input type="hidden" name="telefono" :value="contact === 'otro' ? phone : contacts[contact].phone">
                </div>
                <div x-show="contact === 'otro'" x-cloak>
                    <label class="fx-label" for="wa_telefono">Celular</label>
                    <input id="wa_telefono" class="fx-input" inputmode="tel" placeholder="987654321 o +51 987 654 321" x-model="phone" :required="contact === 'otro'">
                </div>
                <div>
                    <label class="fx-label" for="wa_mensaje">Mensaje (puedes editarlo)</label>
                    <textarea id="wa_mensaje" name="mensaje" class="fx-textarea" rows="8" maxlength="2000" required x-model="message"></textarea>
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700">
                    <x-nav-icon name="whatsapp" class="h-4 w-4" />
                    Abrir WhatsApp
                </button>
                <p class="text-xs text-slate-500">Se abre tu WhatsApp con el mensaje y el enlace al PDF; tú lo envías. El enlace funciona {{ (int) config('forcex.quotation_link_days', 30) }} días sin iniciar sesión.</p>
            </form>
        </x-dialog>
    @endif
</x-app-layout>
