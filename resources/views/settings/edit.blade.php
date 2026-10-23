<x-app-layout>
    <x-slot name="header">
        <h1>Configuración</h1>
    </x-slot>

    <form method="POST" action="{{ route('configuracion.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-5">
        @csrf
        @method('PUT')

        {{-- Modulos opcionales: solo la cuenta de Desarrollo los enciende o apaga --}}
        @can('manage-modules')
            <section class="fx-card border-brand-200">
                <div class="fx-card-header">
                    <h2 class="fx-section-title">Módulos</h2>
                    <span class="text-xs text-slate-500">Solo visible para Desarrollo</span>
                </div>
                <div class="fx-card-body space-y-3">
                    <input type="hidden" name="modulo_productividad" value="0">
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:bg-slate-50">
                        <input type="checkbox" name="modulo_productividad" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-600/30"
                            @checked(old('modulo_productividad', $settings->modulo_productividad))>
                        <span>
                            <span class="block font-medium text-slate-900">Productividad y Cultura</span>
                            <span class="block text-sm text-slate-600">Tareas con lista y calendario, post-it flotante, pizarra de oficina, avisos en la campanita y cumpleaños. Apagado, no aparece en ninguna pantalla ni hace consultas.</span>
                        </span>
                    </label>
                </div>
            </section>
        @endcan

        {{-- Marca (nombre, logo, sigla y colores): solo la cuenta de Desarrollo --}}
        @can('manage-branding')
        <section class="fx-card">
            <div class="fx-card-header">
                <h2 class="fx-section-title">Empresa</h2>
            </div>
            <div class="fx-card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="fx-label" for="company_name">Nombre de la empresa</label>
                    <input class="fx-input" id="company_name" name="company_name" value="{{ old('company_name', $settings->company_name) }}" required>
                </div>
                <div>
                    <label class="fx-label" for="program_name">Nombre del sistema (pestaña del navegador)</label>
                    <input class="fx-input" id="program_name" name="program_name" value="{{ old('program_name', $settings->program_name) }}" required>
                </div>
                <div>
                    <label class="fx-label" for="brand_abbr">Sigla (se usa si no hay logo)</label>
                    <input class="fx-input" id="brand_abbr" name="brand_abbr" maxlength="20" value="{{ old('brand_abbr', $settings->brand_abbr) }}" required>
                </div>
                <div>
                    <label class="fx-label" for="logo_image">Logo (PNG, JPG, WEBP o SVG, máx. 2 MB)</label>
                    <input class="fx-input" id="logo_image" name="logo_image" type="file" accept=".png,.jpg,.jpeg,.webp,.svg">
                    @if ($settings->logoUrl())
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ $settings->logoUrl() }}" alt="Logo actual" class="h-12 max-w-[160px] rounded border border-slate-200 bg-white object-contain p-1">
                            <span class="text-xs text-slate-500">Logo actual. Se usa en el menú, el login, los correos y los PDF.</span>
                        </div>
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30">
                            Quitar logo y usar la sigla
                        </label>
                    @endif
                </div>
            </div>
        </section>

        @endcan

        <section class="fx-card">
            <div class="fx-card-header">
                <h2 class="fx-section-title">Datos de la empresa para documentos</h2>
                <span class="text-xs text-slate-500">Se imprimen en el PDF de las cotizaciones</span>
            </div>
            <div class="fx-card-body grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="fx-label" for="empresa_ruc">RUC</label>
                    <input class="fx-input tabular-nums" id="empresa_ruc" name="empresa_ruc" inputmode="numeric" maxlength="11" pattern="\d{11}" value="{{ old('empresa_ruc', $settings->empresa_ruc) }}">
                </div>
                <div>
                    <label class="fx-label" for="empresa_telefono">Teléfono</label>
                    <input class="fx-input" id="empresa_telefono" name="empresa_telefono" maxlength="60" value="{{ old('empresa_telefono', $settings->empresa_telefono) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="fx-label" for="empresa_direccion">Dirección</label>
                    <input class="fx-input" id="empresa_direccion" name="empresa_direccion" value="{{ old('empresa_direccion', $settings->empresa_direccion) }}">
                </div>
                <div>
                    <label class="fx-label" for="empresa_correo">Correo</label>
                    <input class="fx-input" id="empresa_correo" name="empresa_correo" type="email" value="{{ old('empresa_correo', $settings->empresa_correo) }}">
                </div>
                <div>
                    <label class="fx-label" for="empresa_web">Página web</label>
                    <input class="fx-input" id="empresa_web" name="empresa_web" placeholder="www.empresa.com" value="{{ old('empresa_web', $settings->empresa_web) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="fx-label" for="cuentas_bancarias">Cuentas bancarias (una por línea)</label>
                    <textarea class="fx-textarea" id="cuentas_bancarias" name="cuentas_bancarias" rows="4" maxlength="2000"
                        placeholder="BCP Dólares: 193-1234567-1-00 · CCI 002-193-001234567100-10&#10;BCP Soles: 193-7654321-0-11 · CCI 002-193-007654321011-15">{{ old('cuentas_bancarias', $settings->cuentas_bancarias) }}</textarea>
                </div>
            </div>
        </section>

        <section class="fx-card">
            <div class="fx-card-header">
                <h2 class="fx-section-title">Cotizaciones</h2>
                <span class="text-xs text-slate-500">Valores con los que empieza cada cotización nueva</span>
            </div>
            <div class="fx-card-body space-y-4">
                <div>
                    <label class="fx-label" for="cotizacion_validez_dias">Validez</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input class="fx-input mt-0 w-24" id="cotizacion_validez_dias" name="cotizacion_validez_dias" type="number" min="1" max="180" step="1"
                            value="{{ old('cotizacion_validez_dias', $settings->cotizacion_validez_dias ?? 7) }}" required>
                        <span class="text-sm text-slate-500">días calendario</span>
                    </div>
                </div>
                <div>
                    <label class="fx-label" for="cotizacion_condiciones">Condiciones (el Comercial puede ajustarlas en cada cotización)</label>
                    <textarea class="fx-textarea" id="cotizacion_condiciones" name="cotizacion_condiciones" rows="8" maxlength="5000">{{ old('cotizacion_condiciones', $settings->cotizacion_condiciones) }}</textarea>
                </div>
            </div>
        </section>

        @can('manage-branding')
        <section class="fx-card" x-data="{ primary: @js(old('color_primario', $settings->color_primario)), accent: @js(old('color_acento', $settings->color_acento)) }">
            <div class="fx-card-header">
                <h2 class="fx-section-title">Colores de marca</h2>
            </div>
            <div class="fx-card-body grid gap-4 sm:grid-cols-2">
                @foreach (['color_primario' => ['Color principal (menú y botones)', 'primary'], 'color_acento' => ['Color de acento (avance y confirmaciones)', 'accent']] as $field => [$label, $model])
                    <div>
                        <label class="fx-label" for="{{ $field }}">{{ $label }}</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input type="color" class="h-10 w-12 shrink-0 cursor-pointer rounded-md border border-slate-300 bg-white p-1" x-model="{{ $model }}" aria-label="{{ $label }}">
                            <input class="fx-input mt-0 font-mono uppercase" id="{{ $field }}" name="{{ $field }}" x-model="{{ $model }}" maxlength="7" pattern="#[0-9a-fA-F]{6}" required>
                        </div>
                    </div>
                @endforeach

                <div class="sm:col-span-2 flex flex-wrap items-center gap-3 rounded-md border border-slate-200 bg-slate-50 p-3">
                    <span class="text-sm text-slate-500">Vista previa:</span>
                    <span class="inline-flex h-10 items-center rounded-md px-4 text-sm font-medium text-white" :style="`background:${primary}`">Botón principal</span>
                    <span class="inline-flex h-1.5 w-40 overflow-hidden rounded-full bg-slate-200"><span class="w-2/3" :style="`background:${accent}`"></span></span>
                </div>
            </div>
        </section>

        @endcan

        <section class="fx-card">
            <div class="fx-card-header">
                <h2 class="fx-section-title">Tipo de cambio de referencia</h2>
                <span class="text-xs text-slate-500">Actualizado: {{ $settings->tipo_cambio_actualizado_en?->format('d/m/Y H:i') ?? 'nunca' }}</span>
            </div>
            <div class="fx-card-body">
                <label class="fx-label" for="tipo_cambio_referencial">S/ por US$ (cada RO nueva copia este valor y lo congela)</label>
                <input class="fx-input max-w-xs" id="tipo_cambio_referencial" name="tipo_cambio_referencial" type="number" min="1" max="10" step="0.0001"
                    value="{{ old('tipo_cambio_referencial', $settings->tipo_cambio_referencial) }}" placeholder="3.7450">
            </div>
        </section>

        <section class="fx-card">
            <div class="fx-card-header">
                <h2 class="fx-section-title">Alertas críticas</h2>
                <span class="text-xs text-slate-500">Cut-off, ETA, demurrage, detention, almacenaje y pre-alerta de llegada</span>
            </div>
            <div class="fx-card-body space-y-4">
                <p class="text-sm text-slate-600">El semáforo cuenta el tiempo que falta para cada plazo. Con más tiempo que la alerta verde, el plazo no aparece en el Inicio.</p>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        'alerta_dias_preventiva' => ['Verde (preventiva) desde', 'días antes', 'bg-green-600', 1, 30],
                        'alerta_horas_advertencia' => ['Amarilla (advertencia) desde', 'horas antes', 'bg-amber-500', 2, 720],
                        'alerta_horas_critica' => ['Roja (crítica) desde', 'horas antes', 'bg-rose-600', 1, 240],
                    ] as $field => [$fieldLabel, $unit, $dot, $min, $max])
                        <div>
                            <label class="fx-label flex items-center gap-2" for="{{ $field }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $dot }}"></span>{{ $fieldLabel }}
                            </label>
                            <div class="mt-1 flex items-center gap-2">
                                <input class="fx-input mt-0 w-24" id="{{ $field }}" name="{{ $field }}" type="number" min="{{ $min }}" max="{{ $max }}" step="1"
                                    value="{{ old($field, $settings->{$field}) }}" required>
                                <span class="text-sm text-slate-500">{{ $unit }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="fx-label" for="alerta_horas_escalamiento">Si una roja no se atiende, avisar al Administrador después de</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input class="fx-input mt-0 w-24" id="alerta_horas_escalamiento" name="alerta_horas_escalamiento" type="number" min="0" max="72" step="1"
                                value="{{ old('alerta_horas_escalamiento', $settings->alerta_horas_escalamiento) }}" required>
                            <span class="text-sm text-slate-500">horas (0 = de inmediato)</span>
                        </div>
                    </div>
                    <div>
                        <label class="fx-label" for="alerta_dias_retraso_eta">La ETA es crítica si se retrasa frente a la original</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input class="fx-input mt-0 w-24" id="alerta_dias_retraso_eta" name="alerta_dias_retraso_eta" type="number" min="1" max="30" step="1"
                                value="{{ old('alerta_dias_retraso_eta', $settings->alerta_dias_retraso_eta) }}" required>
                            <span class="text-sm text-slate-500">días o más</span>
                        </div>
                    </div>
                    <div>
                        <label class="fx-label" for="alerta_horas_prealerta">Pedir la pre-alerta de llegada al Customer</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input class="fx-input mt-0 w-24" id="alerta_horas_prealerta" name="alerta_horas_prealerta" type="number" min="12" max="240" step="1"
                                value="{{ old('alerta_horas_prealerta', $settings->alerta_horas_prealerta) }}" required>
                            <span class="text-sm text-slate-500">horas antes de la ETA (pasa a roja con la roja de arriba)</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div>
            <button class="fx-btn-primary" type="submit">Guardar cambios</button>
        </div>
    </form>
</x-app-layout>
