@csrf

@php
    $rows = collect(old('items', $quotation->relationLoaded('items')
        ? $quotation->items->map(fn ($item) => [
            'rate_concept_id' => (string) $item->rate_concept_id,
            'descripcion' => $item->descripcion,
            'cantidad' => (string) (float) $item->cantidad,
            'precio_unitario' => (string) $item->precio_unitario,
            'moneda' => $item->moneda,
            'afecto_igv' => $item->afecto_igv,
        ])->all()
        : []))
        ->map(fn ($row) => [
            'rate_concept_id' => (string) ($row['rate_concept_id'] ?? ''),
            'descripcion' => (string) ($row['descripcion'] ?? ''),
            'cantidad' => (string) ($row['cantidad'] ?? '1'),
            'precio_unitario' => (string) ($row['precio_unitario'] ?? ''),
            'moneda' => (string) ($row['moneda'] ?? 'USD'),
            'afecto_igv' => filter_var($row['afecto_igv'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ])
        ->values()
        ->all();

    $customer = [];
    foreach (['ruc', 'razon_social', 'direccion', 'contacto_nombre', 'contacto_correo', 'contacto_telefono'] as $field) {
        $customer[$field] = (string) old($field, $quotation->{$field});
    }
    $cargo = old('tipo_carga', $quotation->tipo_carga ?? 'FCL');
@endphp

<div
    class="space-y-4"
    x-data="quotationForm({
        lookupUrl: @js(route('cotizaciones.ruc')),
        igvRate: @js(\App\Models\Quotation::igvRate()),
        concepts: @js($rateConcepts->map(fn ($concept) => ['id' => $concept->id, 'nombre' => $concept->nombre])->values()),
        customer: @js($customer),
        rows: @js($rows),
        linked: @js(filled($customer['ruc']) && \App\Models\Client::query()->where('ruc', $customer['ruc'])->exists()),
    })"
>
    {{-- 1. Cliente o prospecto --}}
    <section class="fx-card">
        <div class="fx-card-header">
            <h2 class="fx-section-title">Cliente</h2>
            <span class="text-xs text-slate-500">Si el RUC ya es cliente, se completan sus datos</span>
        </div>
        <div class="fx-card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="fx-label" for="ruc">RUC (11 dígitos)</label>
                <div class="mt-1 flex gap-2">
                    <input class="fx-input mt-0 tabular-nums" id="ruc" name="ruc" inputmode="numeric" maxlength="11" pattern="\d{11}" required
                        x-model="customer.ruc" x-on:input="if (customer.ruc.replace(/\D+/g, '').length === 11) findRuc()" x-on:blur="findRuc()" x-on:keydown.enter.prevent="findRuc()">
                    <button type="button" class="fx-btn-secondary" x-on:click="findRuc()">Buscar</button>
                </div>
                <p class="mt-1 text-xs" aria-live="polite">
                    <span x-show="lookup.state === 'loading'" x-cloak class="text-slate-500">Buscando…</span>
                    <span x-show="lookup.state === 'found'" x-cloak class="text-green-700">Cliente registrado. Datos completados.</span>
                    <span x-show="lookup.state === 'missing'" x-cloak class="text-amber-700">No está registrado: completa los datos (queda como prospecto).</span>
                    <span x-show="lookup.state === 'invalid'" x-cloak class="text-rose-700">El RUC debe tener 11 dígitos.</span>
                    <span x-show="lookup.state === 'error'" x-cloak class="text-rose-700">No se pudo buscar. Completa los datos a mano.</span>
                </p>
            </div>
            <div class="lg:col-span-2">
                <label class="fx-label" for="razon_social">Razón social</label>
                <input class="fx-input" id="razon_social" name="razon_social" maxlength="255" required x-model="customer.razon_social">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label class="fx-label" for="direccion">Dirección</label>
                <input class="fx-input" id="direccion" name="direccion" maxlength="255" x-model="customer.direccion">
            </div>

            <div class="sm:col-span-2 lg:col-span-3" x-show="lookup.contacts.length > 1" x-cloak>
                <label class="fx-label" for="contacto_ficha">Contacto de la ficha del cliente</label>
                <select class="fx-select" id="contacto_ficha" x-on:change="pickContact($event)">
                    <option value="">Elegir para completar…</option>
                    <template x-for="(contact, index) in lookup.contacts" :key="index">
                        <option :value="index" x-text="contact.nombre + (contact.cargo ? ' · ' + contact.cargo : '')"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="fx-label" for="contacto_nombre">Atención (contacto)</label>
                <input class="fx-input" id="contacto_nombre" name="contacto_nombre" maxlength="255" x-model="customer.contacto_nombre">
            </div>
            <div>
                <label class="fx-label" for="contacto_correo">Correo del contacto</label>
                <input class="fx-input" id="contacto_correo" name="contacto_correo" type="email" maxlength="255" x-model="customer.contacto_correo">
            </div>
            <div>
                <label class="fx-label" for="contacto_telefono">Celular (para WhatsApp)</label>
                <input class="fx-input" id="contacto_telefono" name="contacto_telefono" inputmode="tel" maxlength="30" placeholder="987654321" x-model="customer.contacto_telefono">
            </div>
        </div>
    </section>

    {{-- 2. Servicio y ruta --}}
    <section class="fx-card" x-data="{ cargo: @js($cargo) }">
        <div class="fx-card-header">
            <h2 class="fx-section-title">Servicio y ruta</h2>
        </div>
        <div class="fx-card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="fx-label" for="tipo_carga">Tipo de carga</label>
                <select class="fx-select" id="tipo_carga" name="tipo_carga" x-model="cargo" required>
                    @foreach ($cargoTypes as $type)
                        <option value="{{ $type }}">{{ $type === 'AEREO' ? 'Aéreo' : $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="fx-label" for="incoterm">Incoterm</label>
                <select class="fx-select" id="incoterm" name="incoterm">
                    <option value="">Sin indicar</option>
                    @foreach ($incoterms as $incoterm)
                        <option value="{{ $incoterm }}" @selected(old('incoterm', $quotation->incoterm) === $incoterm)>{{ $incoterm }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="fx-label" for="origen">Origen (país / ciudad)</label>
                <input class="fx-input" id="origen" name="origen" maxlength="255" value="{{ old('origen', $quotation->origen) }}">
            </div>
            <div>
                <label class="fx-label" for="destino">Destino (país / ciudad)</label>
                <input class="fx-input" id="destino" name="destino" maxlength="255" value="{{ old('destino', $quotation->destino) }}">
            </div>
            <div>
                <label class="fx-label" for="puerto_carga" x-text="cargo === 'AEREO' ? 'Aeropuerto de salida' : 'Puerto de carga (POL)'">Puerto de carga (POL)</label>
                <input class="fx-input" id="puerto_carga" name="puerto_carga" maxlength="255" value="{{ old('puerto_carga', $quotation->puerto_carga) }}">
            </div>
            <div>
                <label class="fx-label" for="puerto_descarga" x-text="cargo === 'AEREO' ? 'Aeropuerto de llegada' : 'Puerto de descarga (POD)'">Puerto de descarga (POD)</label>
                <input class="fx-input" id="puerto_descarga" name="puerto_descarga" maxlength="255" value="{{ old('puerto_descarga', $quotation->puerto_descarga) }}">
            </div>
            <div class="sm:col-span-2">
                <label class="fx-label" for="mercancia">Mercancía</label>
                <input class="fx-input" id="mercancia" name="mercancia" maxlength="255" value="{{ old('mercancia', $quotation->mercancia) }}" placeholder="Ej. repuestos automotrices, carga general">
            </div>
            <div x-show="cargo === 'FCL'" x-cloak>
                <label class="fx-label" for="contenedores">Contenedores</label>
                <input class="fx-input" id="contenedores" name="contenedores" maxlength="255" value="{{ old('contenedores', $quotation->contenedores) }}" placeholder="Ej. 1 x 40HC">
            </div>
            <div>
                <label class="fx-label" for="peso">Peso (kg)</label>
                <input class="fx-input" id="peso" name="peso" type="number" min="0" step="0.01" value="{{ old('peso', $quotation->peso) }}">
            </div>
            <div>
                <label class="fx-label" for="volumen">Volumen (m³)</label>
                <input class="fx-input" id="volumen" name="volumen" type="number" min="0" step="0.001" value="{{ old('volumen', $quotation->volumen) }}">
            </div>
            <div>
                <label class="fx-label" for="naviera" x-text="cargo === 'AEREO' ? 'Aerolínea' : 'Naviera'">Naviera</label>
                <input class="fx-input" id="naviera" name="naviera" maxlength="255" value="{{ old('naviera', $quotation->naviera) }}">
            </div>
            <div>
                <label class="fx-label" for="tiempo_transito">Tiempo de tránsito</label>
                <input class="fx-input" id="tiempo_transito" name="tiempo_transito" maxlength="60" value="{{ old('tiempo_transito', $quotation->tiempo_transito) }}" placeholder="Ej. 32 días aprox.">
            </div>
            <div>
                <label class="fx-label" for="frecuencia">Frecuencia de salidas</label>
                <input class="fx-input" id="frecuencia" name="frecuencia" maxlength="60" value="{{ old('frecuencia', $quotation->frecuencia) }}" placeholder="Ej. Semanal">
            </div>
            <div x-show="cargo !== 'AEREO'" x-cloak>
                <label class="fx-label" for="dias_libres">Días libres de contenedor</label>
                <input class="fx-input" id="dias_libres" name="dias_libres" type="number" min="0" max="365" step="1" value="{{ old('dias_libres', $quotation->dias_libres) }}">
            </div>
        </div>
    </section>

    {{-- 3. Tarifas --}}
    <section class="fx-card">
        <div class="fx-card-header flex-wrap">
            <h2 class="fx-section-title">Tarifas</h2>
            <span class="text-xs text-slate-500">Marca "IGV" en los gastos locales; el flete internacional es inafecto. IGV {{ $igvPercent }}%.</span>
        </div>
        <div class="space-y-3 p-4">
            <div class="hidden gap-3 text-xs font-medium text-slate-500 lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(0,2fr)_6rem_8rem_6rem_4rem_7rem_auto]">
                <span>Concepto</span><span>Descripción</span><span>Cantidad</span><span>Precio unit.</span><span>Moneda</span><span class="text-center">IGV</span><span class="text-right">Importe</span><span class="w-16"></span>
            </div>
            <template x-for="(row, index) in rows" :key="index">
                <div class="grid gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,2fr)_6rem_8rem_6rem_4rem_7rem_auto] lg:items-center">
                    <div>
                        <label class="fx-label lg:sr-only" :for="`item_concept_${index}`">Concepto</label>
                        <select class="fx-select lg:mt-0" :id="`item_concept_${index}`" :name="`items[${index}][rate_concept_id]`" x-model="row.rate_concept_id" x-on:change="pickConcept(row)">
                            <option value="">Otro / libre</option>
                            @foreach ($rateConcepts as $concept)
                                <option value="{{ $concept->id }}">{{ $concept->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="fx-label lg:sr-only" :for="`item_desc_${index}`">Descripción</label>
                        <input class="fx-input lg:mt-0" :id="`item_desc_${index}`" :name="`items[${index}][descripcion]`" x-model="row.descripcion" maxlength="255" required placeholder="Ej. Flete marítimo 40HC">
                    </div>
                    <div>
                        <label class="fx-label lg:sr-only" :for="`item_qty_${index}`">Cantidad</label>
                        <input class="fx-input lg:mt-0" :id="`item_qty_${index}`" :name="`items[${index}][cantidad]`" x-model="row.cantidad" type="number" min="0.01" step="0.01" required>
                    </div>
                    <div>
                        <label class="fx-label lg:sr-only" :for="`item_price_${index}`">Precio unitario</label>
                        <input class="fx-input lg:mt-0" :id="`item_price_${index}`" :name="`items[${index}][precio_unitario]`" x-model="row.precio_unitario" type="number" min="0" step="0.01" required>
                    </div>
                    <div>
                        <label class="fx-label lg:sr-only" :for="`item_currency_${index}`">Moneda</label>
                        <select class="fx-select lg:mt-0" :id="`item_currency_${index}`" :name="`items[${index}][moneda]`" x-model="row.moneda">
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency }}">{{ $currency }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 lg:justify-center">
                        <input type="hidden" :name="`items[${index}][afecto_igv]`" :value="row.afecto_igv ? 1 : 0">
                        <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" x-model="row.afecto_igv">
                        <span class="lg:sr-only">Afecto a IGV</span>
                    </label>
                    <p class="text-sm tabular-nums text-slate-900 lg:text-right" x-text="money(lineTotal(row), row.moneda)"></p>
                    <button type="button" class="fx-btn-secondary text-rose-700" x-on:click="remove(index)" aria-label="Quitar tarifa">Quitar</button>
                </div>
            </template>
            <button type="button" class="fx-btn-link-primary" x-on:click="add()">+ Agregar tarifa</button>

            <div class="ml-auto max-w-sm space-y-3 border-t border-slate-200 pt-3 text-sm tabular-nums">
                <template x-for="currency in currencies()" :key="currency">
                    <dl class="space-y-0.5">
                        <div class="flex justify-between text-slate-600"><dt x-text="`Subtotal ${currency}`"></dt><dd x-text="money(totals(currency).subtotal, currency)"></dd></div>
                        <div class="flex justify-between text-slate-600"><dt>IGV {{ $igvPercent }}%</dt><dd x-text="money(totals(currency).igv, currency)"></dd></div>
                        <div class="flex justify-between font-semibold text-slate-900"><dt x-text="`Total ${currency}`"></dt><dd x-text="money(totals(currency).total, currency)"></dd></div>
                    </dl>
                </template>
            </div>
        </div>
    </section>

    {{-- 4. Condiciones --}}
    <section class="fx-card">
        <div class="fx-card-header">
            <h2 class="fx-section-title">Condiciones</h2>
        </div>
        <div class="fx-card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-form-date-picker id="fecha" name="fecha" label="Fecha" :value="old('fecha', $quotation->fecha?->format('Y-m-d'))" required />
            <div>
                <label class="fx-label" for="validez_dias">Validez (días calendario)</label>
                <input class="fx-input" id="validez_dias" name="validez_dias" type="number" min="1" max="180" step="1" value="{{ old('validez_dias', $quotation->validez_dias) }}" required>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label class="fx-label" for="condiciones">Condiciones (vienen de Configuración; puedes ajustarlas para este cliente)</label>
                <textarea class="fx-textarea" id="condiciones" name="condiciones" rows="7" maxlength="5000">{{ old('condiciones', $quotation->condiciones) }}</textarea>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label class="fx-label" for="observaciones">Observaciones (opcional, se imprimen en el PDF)</label>
                <textarea class="fx-textarea" id="observaciones" name="observaciones" rows="3" maxlength="2000">{{ old('observaciones', $quotation->observaciones) }}</textarea>
            </div>
        </div>
    </section>
</div>

<div class="sticky bottom-0 z-10 -mx-4 mt-4 flex items-center gap-2 border-t border-slate-200 bg-white px-4 py-3 sm:-mx-6 sm:px-6">
    <button class="fx-btn-primary" type="submit">{{ $quotation->exists ? 'Guardar cambios' : 'Guardar cotización' }}</button>
    <a href="{{ $quotation->exists ? route('cotizaciones.show', $quotation) : route('cotizaciones.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
