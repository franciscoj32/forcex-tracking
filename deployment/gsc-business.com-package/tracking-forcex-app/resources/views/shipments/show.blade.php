@php
    $currentStateIndex = array_search($shipment->estado_actual, $trackingStates, true);
    $currentStateIndex = $currentStateIndex === false ? 0 : $currentStateIndex;
    $timelineByState = $timelineEvents->groupBy('estado')->map(fn ($events) => $events->last());
    $completedStateIndexes = collect(range(0, $currentStateIndex))->reverse()->values();
    $currentEvent = $timelineByState->get($shipment->estado_actual);
    $nextState = $trackingStates[$currentStateIndex + 1] ?? null;
    $lastEventAt = optional($timelineEvents->last()?->fecha)->format('d/m/Y H:i') ?: 'Sin movimientos';
    $canOperate = auth()->user()->hasRole(['admin', 'operaciones']);
    $canViewTrackingCode = $shipment->canRevealTrackingCodeTo(auth()->user());
    $initialTab = $activeTab ?? request('tab', 'datos');
    if ($errors->any()) {
        if ($errors->has('categoria') || $errors->has('tipo_documento') || $errors->has('archivo')) {
            $initialTab = 'documentos';
        } elseif ($errors->has('estado') || $errors->has('fecha') || $errors->has('descripcion')) {
            $initialTab = 'tracking';
        }
    }
    $documentFormHasErrors = $errors->has('categoria') || $errors->has('tipo_documento') || $errors->has('archivo');
    $documentToastMessage = $initialTab === 'documentos' && is_string(session('status')) && str_contains(session('status'), 'Documento')
        ? session('status')
        : null;
    $activeTabClasses = 'rounded-2xl bg-cyan-600 px-5 py-3 text-base font-semibold text-white shadow-sm shadow-cyan-200/80 ring-2 ring-cyan-100';
    $inactiveTabClasses = 'rounded-2xl px-5 py-3 text-base font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900';
    $responsibleAssignments = collect();
    $trackingAssignments = collect();
    $trackingStateOptions = collect();
    $documentCategoryOptions = collect();
    $documentTypeOptions = collect();
    $documentBlueprints = collect();
    $pendingDocuments = collect();
    $documentCompletion = 0;
    $documentCompletionDone = 0;

    if ($initialTab === 'datos') {
        $responsibleAssignments = $shipment->userAssignments->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_RESPONSABLE)->values();
        $trackingAssignments = $shipment->userAssignments->where('assignment_type', \App\Models\ShipmentUserAssignment::TYPE_SEGUIMIENTO)->values();
    }

    if ($initialTab === 'tracking' && $canOperate) {
        $trackingStateOptions = collect($trackingStates)
            ->map(fn ($state) => ['value' => $state, 'label' => $state]);
    }

    if ($initialTab === 'documentos') {
        $documentCategoryOptions = collect($documentCategories)
            ->map(fn ($category) => ['value' => $category, 'label' => ucfirst($category)]);
        $documentTypeOptions = collect($documentTypeSuggestions)
            ->flatten()
            ->unique()
            ->values()
            ->map(fn ($item) => ['value' => $item, 'label' => $item]);
        $documentBlueprints = collect($shipment->documentChecklist())->values();
        $pendingDocuments = $documentBlueprints
            ->flatMap(fn (array $blueprint) => collect($blueprint['missing_items'])->map(fn (array $item) => [
                'category' => $blueprint['key'],
                'category_label' => $blueprint['label'],
                'document' => $item['label'],
                'hint' => $item['hint'],
                'priority' => $item['priority'],
                'priority_label' => $item['priority_label'],
                'tone' => $blueprint['badge'],
            ]))
            ->sortBy(fn (array $item) => $item['priority'] === \App\Models\Shipment::CHECKLIST_PRIORITY_REQUIRED ? 0 : 1)
            ->values();
        $documentCompletion = $documentBlueprints->filter(fn (array $blueprint) => collect($blueprint['required_items'])->isNotEmpty())->count();
        $documentCompletionDone = $documentBlueprints
            ->filter(fn (array $blueprint) => collect($blueprint['required_items'])->isNotEmpty() && $blueprint['required_completion'])
            ->count();
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Detalle de embarque {{ $shipment->nro_orden_trabajo }}</h1>
    </x-slot>

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            Revisa los datos del formulario antes de continuar.
        </div>
    @endif

    <div
        x-data="{
            showLogbook: false,
            showSummary: false,
            showDocumentModal: {{ $documentFormHasErrors ? 'true' : 'false' }},
            showDocumentToast: {{ $documentToastMessage ? 'true' : 'false' }}
        }"
        x-init="if (showDocumentToast) { setTimeout(() => showDocumentToast = false, 2800) }"
        class="grid gap-6 2xl:grid-cols-[1.35fr_0.65fr]"
    >
        <div class="space-y-6">
            <section class="overflow-hidden rounded-[28px] bg-gradient-to-r from-slate-900 via-sky-900 to-cyan-700 px-6 py-7 text-white shadow-lg">
                <div class="flex min-h-[180px] flex-col justify-center gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-3xl font-semibold">Embarque {{ $shipment->nro_orden_trabajo }}</h2>
                            <p class="mt-2 max-w-3xl text-sm text-cyan-50/90">
                                Cliente: {{ $shipment->client->razon_social }} · BL {{ $shipment->bl }} · Routing Order {{ $shipment->routing_order }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3 text-sm">
                            <span class="rounded-full bg-white/15 px-4 py-2 font-semibold text-white">Estado actual: {{ $shipment->estado_actual }}</span>
                            <span class="rounded-full bg-emerald-400/15 px-4 py-2 font-semibold text-emerald-100">ETA {{ optional($shipment->fecha_eta)->format('d/m/Y') ?: 'sin fecha' }}</span>
                            <span class="rounded-full bg-white/15 px-4 py-2 font-semibold text-white">{{ $shipment->tipo_carga }} · {{ $shipment->origen }} / {{ $shipment->puerto_descarga }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col items-start justify-center gap-3 lg:ml-auto lg:items-end">
                        <div class="flex w-full flex-wrap items-start justify-end gap-2 self-end lg:w-auto">
                            @if ($canOperate)
                                <a href="{{ route('embarques.edit', $shipment) }}" class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-cyan-50">Editar embarque</a>
                            @endif
                            <a href="{{ route('embarques.index') }}" class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10">Volver</a>
                        </div>
                        @if ($canViewTrackingCode)
                            <div class="w-full rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-left lg:w-[278px]">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100/80">Código de seguimiento público</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    <p id="tracking-code-value" class="font-mono text-lg font-semibold text-white">{{ $shipment->tracking_code }}</p>
                                    <button
                                        type="button"
                                        id="copy-tracking-code"
                                        class="rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-cyan-50"
                                    >
                                        Copiar código
                                    </button>
                                </div>
                                <p id="copy-tracking-feedback" class="mt-2 text-xs text-cyan-100/80"></p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="fx-card overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="inline-flex flex-wrap gap-2 rounded-2xl bg-white p-2 shadow-sm ring-1 ring-slate-200">
                        <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'datos']) }}" class="relative transition-all duration-200 ease-out {{ $initialTab === 'datos' ? $activeTabClasses : $inactiveTabClasses }}">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 6h16"></path>
                                    <path d="M4 12h16"></path>
                                    <path d="M4 18h10"></path>
                                </svg>
                                <span>Datos</span>
                            </span>
                            @if ($initialTab === 'datos')
                                <span class="absolute inset-x-4 -bottom-1 h-1 rounded-full bg-cyan-300"></span>
                            @endif
                        </a>
                        <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']) }}" class="relative transition-all duration-200 ease-out {{ $initialTab === 'tracking' ? $activeTabClasses : $inactiveTabClasses }}">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14"></path>
                                    <path d="m12 5 7 7-7 7"></path>
                                </svg>
                                <span>Estado de embarque</span>
                            </span>
                            @if ($initialTab === 'tracking')
                                <span class="absolute inset-x-4 -bottom-1 h-1 rounded-full bg-cyan-300"></span>
                            @endif
                        </a>
                        <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'documentos']) }}" class="relative transition-all duration-200 ease-out {{ $initialTab === 'documentos' ? $activeTabClasses : $inactiveTabClasses }}">
                            <span class="inline-flex items-center gap-2">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M7 3h7l5 5v13H7z"></path>
                                    <path d="M14 3v5h5"></path>
                                    <path d="M10 13h6"></path>
                                    <path d="M10 17h4"></path>
                                </svg>
                                <span>Documentos</span>
                            </span>
                            @if ($initialTab === 'documentos')
                                <span class="absolute inset-x-4 -bottom-1 h-1 rounded-full bg-cyan-300"></span>
                            @endif
                        </a>
                    </div>
                </div>

                <div class="p-6">
                    @if ($initialTab === 'datos')
                        @include('shipments.tabs.datos')
                    @elseif ($initialTab === 'tracking')
                        @include('shipments.tabs.tracking')
                    @elseif ($initialTab === 'documentos')
                        @include('shipments.tabs.documentos')
                    @endif
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="fx-card fx-card-body">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900">Bitácora cronológica</h2>
                    <button
                        type="button"
                        x-on:click="showLogbook = !showLogbook"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-semibold text-slate-700 transition hover:border-cyan-300 hover:text-cyan-700"
                        :aria-expanded="showLogbook.toString()"
                        aria-label="Mostrar u ocultar bitácora cronológica"
                    >
                        <span x-text="showLogbook ? '−' : '+'"></span>
                    </button>
                </div>
                <div x-show="showLogbook" x-cloak x-transition.opacity.duration.200ms class="mt-4 space-y-3">
                    @forelse ($visibleLogbookEvents as $event)
                        <div class="rounded-2xl border border-slate-200 px-4 py-4">
                            <div class="flex flex-col gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $event->estado }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ $event->descripcion ?: 'Sin descripción adicional.' }}</p>
                                </div>
                                <div class="text-sm text-slate-500">
                                    {{ $event->fecha->format('d/m/Y H:i') }} · {{ $event->user->name }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No hay eventos registrados todavía.</p>
                    @endforelse
                    @if ($hiddenLogbookCount > 0)
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4">
                            <p class="text-sm font-medium text-slate-700">Hay {{ $hiddenLogbookCount }} movimiento(s) más en la bitácora.</p>
                            <a href="{{ route('embarques.show', ['shipment' => $shipment, 'tab' => 'tracking']) }}" class="mt-2 inline-flex text-sm font-semibold text-cyan-700 transition hover:text-cyan-800">
                                Ver seguimiento completo
                            </a>
                        </div>
                    @endif
                </div>
            </section>

            <section class="fx-card fx-card-body">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900">Resumen operativo</h2>
                    <button
                        type="button"
                        x-on:click="showSummary = !showSummary"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-semibold text-slate-700 transition hover:border-cyan-300 hover:text-cyan-700"
                        :aria-expanded="showSummary.toString()"
                        aria-label="Mostrar u ocultar resumen operativo"
                    >
                        <span x-text="showSummary ? '−' : '+'"></span>
                    </button>
                </div>
                <div x-show="showSummary" x-cloak x-transition.opacity.duration.200ms class="mt-4 space-y-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Estado actual</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $shipment->estado_actual }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Endoso</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $shipment->estado_endoso)) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Último movimiento</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $lastEventAt }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm text-slate-500">Documentos</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $documentCount }} archivos</p>
                    </div>
                </div>
            </section>

            @if ($endorsementDocumentsPreview->isNotEmpty())
                <section class="fx-card fx-card-body">
                    <h2 class="text-lg font-semibold text-slate-900">Endoso</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($endorsementDocumentsPreview as $document)
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $document->tipo_documento }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ $document->nombre_original }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>

        @if ($canOperate && $initialTab === 'documentos')
            <div
                x-init="$nextTick(() => document.body.appendChild($el))"
                x-show="showDocumentModal"
                x-cloak
                x-transition.opacity
                class="fixed inset-0 z-[999] flex items-center justify-center p-6"
                style="position: fixed; inset: 0;"
            >
                <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" x-on:click="showDocumentModal = false"></div>
                <section
                    class="relative z-10 rounded-[28px] bg-white shadow-2xl"
                    style="width:min(42rem, calc(100vw - 3rem));"
                >
                    <div class="flex items-center justify-between border-b border-slate-200 px-8 py-6">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">Agregar documento</h3>
                        </div>
                        <button type="button" x-on:click="showDocumentModal = false" class="text-2xl leading-none text-slate-400 transition hover:text-slate-700">×</button>
                    </div>
                    <form method="POST" action="{{ route('embarques.documentos.store', $shipment) }}" enctype="multipart/form-data" class="space-y-6 px-8 py-7" data-disable-native-autocomplete="true">
                        @csrf
                        <div>
                            <x-form-combobox
                                id="categoria"
                                name="categoria"
                                label="Categoría"
                                :options="$documentCategoryOptions"
                                :value="old('categoria', $pendingDocuments->first()['category'] ?? ($documentCategories[0] ?? ''))"
                                placeholder="Seleccionar categoría"
                                required
                            />
                        </div>
                        <div>
                            <x-form-autocomplete
                                id="tipo_documento"
                                name="tipo_documento"
                                label="Tipo de documento"
                                :options="$documentTypeOptions"
                                :value="old('tipo_documento')"
                                placeholder="Escribe o busca el tipo"
                                search-placeholder="Buscar tipo de documento..."
                                empty-text="No hay tipos sugeridos que coincidan."
                                required
                            />
                        </div>
                        <div>
                            <label class="fx-label" for="archivo">Archivo</label>
                            <input class="fx-input" id="archivo" name="archivo" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                            <p class="mt-2 text-xs text-slate-500">PDF, JPG o PNG · hasta 5 MB por archivo.</p>
                        </div>
                        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-2 sm:flex-row sm:justify-end">
                            <button type="button" x-on:click="showDocumentModal = false" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancelar</button>
                            <button class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700" type="submit">Guardar documento</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif

        @if ($initialTab === 'documentos' && $documentToastMessage)
            <div
                x-show="showDocumentToast"
                x-cloak
                x-transition.opacity.duration.250ms
                class="fixed bottom-6 right-6 z-50 max-w-sm rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-lg"
            >
                <p class="text-sm font-semibold text-emerald-700">{{ $documentToastMessage }}</p>
            </div>
        @endif
    </div>

    @if ($canViewTrackingCode)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const copyButton = document.getElementById('copy-tracking-code');
                const trackingValue = document.getElementById('tracking-code-value');
                const feedback = document.getElementById('copy-tracking-feedback');

                if (!copyButton || !trackingValue || !feedback) {
                    return;
                }

                copyButton.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(trackingValue.textContent.trim());
                        feedback.textContent = 'Código copiado al portapapeles.';
                        feedback.className = 'mt-2 text-xs text-emerald-600';
                    } catch (error) {
                        feedback.textContent = 'No se pudo copiar automáticamente.';
                        feedback.className = 'mt-2 text-xs text-rose-600';
                    }
                });
            });
        </script>
    @endif
</x-app-layout>



