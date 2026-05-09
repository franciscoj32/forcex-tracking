<div class="space-y-5">
    <section class="rounded-3xl border border-slate-200 bg-white p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Centro documental del embarque</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">{{ $documentCount }} archivos</span>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">Cobertura {{ $documentCompletionDone }}/{{ max($documentCompletion, 1) }}</span>
                <span class="rounded-full border {{ $pendingDocuments->isEmpty() ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }} px-3 py-2 text-sm font-semibold">{{ $pendingDocuments->count() }} pendientes</span>
                @if ($canOperate)
                    <button type="button" x-on:click="showDocumentModal = true" class="inline-flex items-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700">
                        Agregar Documento
                    </button>
                @endif
            </div>
        </div>
    </section>

    <div class="space-y-3">
        @foreach ($documentBlueprints as $blueprint)
            <section x-data="{ open: false }" class="rounded-3xl border border-slate-200 bg-white px-5 py-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-base font-semibold text-slate-900">{{ $blueprint['label'] }}</h4>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $blueprint['badge'] }}">{{ $blueprint['count'] }} archivos</span>
                            @if (collect($blueprint['missing_recommended'])->isNotEmpty())
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ collect($blueprint['missing_recommended'])->count() }} recomendados</span>
                            @endif
                            @if (collect($blueprint['missing_required'])->isNotEmpty())
                                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">{{ collect($blueprint['missing_required'])->count() }} obligatorios</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $blueprint['description'] }}</p>
                        @if (collect($blueprint['missing_required'])->isNotEmpty())
                            <p class="mt-1 text-sm text-rose-700">Pendiente: {{ collect($blueprint['missing_required'])->pluck('label')->join(', ') }}.</p>
                        @elseif (collect($blueprint['missing_recommended'])->isNotEmpty())
                            <p class="mt-1 text-sm text-amber-700">Recomendados: {{ collect($blueprint['missing_recommended'])->pluck('label')->take(2)->join(', ') }}{{ collect($blueprint['missing_recommended'])->count() > 2 ? '...' : '' }}.</p>
                        @endif
                    </div>
                    <button
                        type="button"
                        x-on:click="open = !open"
                        class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:border-cyan-300 hover:text-cyan-700"
                        x-text="open ? 'Ocultar detalle' : 'Ver detalle'"
                    ></button>
                </div>

                <div x-show="open" x-cloak class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                    @forelse ($blueprint['documents'] as $document)
                        <article class="rounded-2xl border border-slate-200 px-4 py-3">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-semibold text-slate-900">{{ $document->tipo_documento }}</p>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $blueprint['badge'] }}">{{ ucfirst($document->categoria) }}</span>
                                    </div>
                                    <p class="mt-1 truncate text-sm text-slate-600">{{ $document->nombre_original }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Subido por {{ $document->user->name }} · {{ $document->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('documentos.download', $document) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-700">Descargar</a>
                                    @if ($canOperate)
                                        <form method="POST" action="{{ route('documentos.destroy', $document) }}" onsubmit="return confirm('¿Eliminar documento?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50" type="submit">Eliminar</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                            No hay documentos cargados en esta categoría todavía.
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
