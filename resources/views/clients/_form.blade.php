@csrf

@php
    $isAdmin = auth()->user()->isAdmin();
    $creditCondition = old('condicion_credito', $client->condicion_credito ?? \App\Models\Client::CREDIT_CASH);
@endphp

<div class="space-y-4">
    <section class="fx-card">
        <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Datos fiscales</h2>
        <div class="grid gap-4 p-4 sm:grid-cols-2">
            <div>
                <label class="fx-label" for="razon_social">Razón social</label>
                <input class="fx-input" id="razon_social" name="razon_social" value="{{ old('razon_social', $client->razon_social) }}" required>
            </div>
            <div>
                <label class="fx-label" for="ruc">RUC (11 dígitos)</label>
                <input class="fx-input tabular-nums" id="ruc" name="ruc" inputmode="numeric" maxlength="11" pattern="\d{11}" value="{{ old('ruc', $client->ruc) }}" required>
            </div>
            <div class="sm:col-span-2">
                <label class="fx-label" for="direccion">Dirección fiscal</label>
                <input class="fx-input" id="direccion" name="direccion" value="{{ old('direccion', $client->direccion) }}" required>
            </div>
            <div>
                <label class="fx-label" for="correo">Correo general</label>
                <input class="fx-input" id="correo" name="correo" type="email" value="{{ old('correo', $client->correo) }}">
            </div>
            <div>
                <label class="fx-label" for="telefono">Teléfono general</label>
                <input class="fx-input" id="telefono" name="telefono" value="{{ old('telefono', $client->telefono) }}">
            </div>
            <div>
                <label class="fx-label" for="estado">Estado</label>
                <select class="fx-select" id="estado" name="estado">
                    @foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('estado', $client->estado ?? 'activo') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="fx-card" x-data="{ condition: @js($creditCondition) }">
        <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">
            Crédito
            @unless ($isAdmin)
                <span class="ml-1 text-sm font-normal text-slate-500">propuesta, la valida el Administrador</span>
            @endunless
        </h2>
        <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="fx-label" for="condicion_credito">Condición</label>
                <select class="fx-select" id="condicion_credito" name="condicion_credito" x-model="condition" required>
                    <option value="contado">Pago contado</option>
                    <option value="credito">Crédito</option>
                </select>
            </div>
            <div x-show="condition === 'credito'" x-cloak>
                <label class="fx-label" for="dias_credito">Días de crédito</label>
                <select class="fx-select" id="dias_credito" name="dias_credito">
                    <option value="">Seleccionar</option>
                    @foreach (\App\Models\Client::CREDIT_DAYS_OPTIONS as $days)
                        <option value="{{ $days }}" @selected((int) old('dias_credito', $client->dias_credito) === $days)>{{ $days }} días</option>
                    @endforeach
                </select>
            </div>
            <div x-show="condition === 'credito'" x-cloak>
                <label class="fx-label" for="linea_credito">Línea de crédito</label>
                <input class="fx-input" id="linea_credito" name="linea_credito" type="number" step="0.01" min="0" value="{{ old('linea_credito', $client->linea_credito) }}">
            </div>
            <div>
                <label class="fx-label" for="moneda_linea_credito">Moneda</label>
                <select class="fx-select" id="moneda_linea_credito" name="moneda_linea_credito">
                    @foreach (\App\Models\Client::CURRENCIES as $currency)
                        <option value="{{ $currency }}" @selected(old('moneda_linea_credito', $client->moneda_linea_credito ?? 'USD') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    @unless ($client->exists)
        <section class="fx-card">
            <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Contacto principal</h2>
            <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="fx-label" for="contacto_nombre">Nombre</label>
                    <input class="fx-input" id="contacto_nombre" name="contacto[nombre]" value="{{ old('contacto.nombre') }}" required>
                </div>
                <div>
                    <label class="fx-label" for="contacto_correo">Correo</label>
                    <input class="fx-input" id="contacto_correo" name="contacto[correo]" type="email" value="{{ old('contacto.correo') }}" required>
                </div>
                <div>
                    <label class="fx-label" for="contacto_telefono">Celular (para WhatsApp)</label>
                    <input class="fx-input" id="contacto_telefono" name="contacto[telefono]" value="{{ old('contacto.telefono') }}">
                </div>
                <div>
                    <label class="fx-label" for="contacto_cargo">Cargo</label>
                    <input class="fx-input" id="contacto_cargo" name="contacto[cargo]" value="{{ old('contacto.cargo') }}">
                </div>
                <div>
                    <label class="fx-label" for="contacto_area">Área</label>
                    <select class="fx-select" id="contacto_area" name="contacto[area]">
                        @foreach (\App\Models\ClientContact::AREA_LABELS as $area => $areaLabel)
                            <option value="{{ $area }}" @selected(old('contacto.area', 'logistica') === $area)>{{ $areaLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col justify-end gap-2 text-sm text-slate-700">
                    <input type="hidden" name="contacto[recibe_tracking]" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="contacto[recibe_tracking]" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" @checked(old('contacto.recibe_tracking', '1') === '1')>
                        Recibe avisos de la carga
                    </label>
                    <input type="hidden" name="contacto[recibe_facturacion]" value="0">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="contacto[recibe_facturacion]" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" @checked(old('contacto.recibe_facturacion') === '1')>
                        Recibe facturación
                    </label>
                </div>
            </div>
        </section>
    @endunless
</div>

<div class="sticky bottom-0 z-10 -mx-4 mt-4 flex items-center gap-2 border-t border-slate-200 bg-white px-4 py-3 sm:-mx-6 sm:px-6">
    <button class="fx-btn-primary" type="submit">
        {{ $client->exists ? 'Guardar cambios' : ($isAdmin ? 'Registrar y aprobar' : 'Registrar para aprobación') }}
    </button>
    <a href="{{ $client->exists ? route('clientes.show', $client) : route('clientes.index') }}" class="fx-btn-secondary">Cancelar</a>
</div>
