{{--
    Documentos: lista de control de lo exigido y archivos subidos.
    Los documentos que envia el cliente (Invoice, Packing List, borrador de BL)
    los valida el Customer asignado: aprobado u observado con motivo.
--}}
@php
    $checklist = collect($shipment->documentChecklist());
    $items = $checklist->flatMap(fn (array $category) => collect($category['items'])->map(fn (array $item) => $item + ['category_label' => $category['label']]));
    $uploadUrl = fn (?string $category = null, ?string $type = null) => route('embarques.show', array_filter([
        'shipment' => $shipment, 'tab' => 'documentos', 'subir' => 1, 'categoria' => $category, 'tipo' => $type,
    ]));
    $documents = $shipment->documents->sortByDesc('created_at')->values();
    $canValidate = auth()->user()->can('validateDocuments', $shipment);
    $queue = \App\Support\ShipmentAttention::documentValidationQueue($shipment);
    // Ultima version de cada tipo: las anteriores quedan como historial.
    $latestIds = $documents->unique(fn ($document) => mb_strtolower($document->tipo_documento))->pluck('id')->all();
@endphp

<div class="space-y-4">
    <section class="fx-card">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
            <h2 class="fx-section-title">
                Lista de control
                @if (count($missingDocuments) > 0)
                    <span class="ml-1 text-sm font-medium text-amber-700">faltan {{ count($missingDocuments) }} obligatorios</span>
                @elseif ($queue['pending']->isNotEmpty())
                    <span class="ml-1 text-sm font-medium text-amber-700">{{ $queue['pending']->count() }} por validar</span>
                @elseif ($items->isNotEmpty())
                    <span class="ml-1 text-sm font-normal text-slate-500">obligatorios completos</span>
                @endif
            </h2>
            @if ($canManageDocuments)
                <button type="button" x-on:click="showDocumentModal = true" class="fx-btn-primary">Subir documento</button>
            @endif
        </div>

        @if ($items->isEmpty())
            <p class="px-4 py-4 text-slate-500">En este estado todavía no se exigen documentos.</p>
        @else
            <div class="overflow-x-auto">
                <table class="fx-table">
                    <thead>
                        <tr>
                            <th class="fx-th">Documento</th>
                            <th class="fx-th">Categoría</th>
                            <th class="fx-th">Prioridad</th>
                            <th class="fx-th">Estado</th>
                            <th class="fx-th"><span class="sr-only">Acción</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($items as $item)
                            @php $isRequired = $item['priority'] === \App\Models\Shipment::CHECKLIST_PRIORITY_REQUIRED; @endphp
                            <tr>
                                <td class="fx-td font-medium text-slate-900">{{ $item['label'] }}</td>
                                <td class="fx-td">{{ $item['category_label'] }}</td>
                                <td class="fx-td">{{ $item['priority_label'] }}</td>
                                <td class="fx-td">
                                    @switch(true)
                                        @case($item['validation'] === \App\Models\Document::VALIDATION_OBSERVED)
                                            <span class="fx-badge bg-rose-100 text-rose-700">Observado</span>
                                            @break
                                        @case($item['validation'] === \App\Models\Document::VALIDATION_PENDING)
                                            <span class="fx-badge bg-amber-100 text-amber-800">Por validar</span>
                                            @break
                                        @case($item['validation'] === \App\Models\Document::VALIDATION_APPROVED)
                                            <span class="text-accent-700">Aprobado</span>
                                            @break
                                        @case($item['uploaded'])
                                            <span class="text-slate-700">Subido</span>
                                            @break
                                        @case($isRequired)
                                            <span class="fx-badge bg-amber-100 text-amber-800">Falta</span>
                                            @break
                                        @default
                                            <span class="text-slate-400">Falta</span>
                                    @endswitch
                                </td>
                                <td class="fx-td text-right">
                                    @if ($canManageDocuments && (! $item['uploaded'] || $item['validation'] === \App\Models\Document::VALIDATION_OBSERVED))
                                        <a href="{{ $uploadUrl($item['category'], $item['type']) }}" class="fx-btn-link-primary">
                                            {{ $item['uploaded'] ? 'Subir corrección' : 'Subir' }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="fx-card scroll-mt-20" id="archivos">
        <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">Archivos <span class="font-normal text-slate-500">({{ $documents->count() }})</span></h2>
        @if ($documents->isEmpty())
            <p class="px-4 py-4 text-slate-500">Sin archivos.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($documents as $document)
                    @php $isLatest = in_array($document->id, $latestIds, true); @endphp
                    <li class="px-4 py-3" x-data="{ observing: false }">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p @class(['font-medium', 'text-slate-900' => $isLatest, 'text-slate-500' => ! $isLatest])>
                                    {{ $document->tipo_documento }}
                                    <span class="font-normal text-slate-500">· {{ ucfirst($document->categoria) }}</span>
                                    @unless ($isLatest)
                                        <span class="text-xs font-normal text-slate-400">· versión anterior</span>
                                    @endunless
                                </p>
                                <p class="max-w-md truncate text-sm text-slate-600" title="{{ $document->nombre_original }}">{{ $document->nombre_original }}</p>
                                <p class="text-xs text-slate-500">Subido por {{ $document->user->name }} · {{ $document->created_at->format('d/m/Y H:i') }}</p>

                                @if ($document->needsValidation())
                                    <p class="mt-1 text-sm">
                                        @if ($document->isPendingValidation())
                                            <span class="fx-badge bg-amber-100 text-amber-800">Por validar</span>
                                        @elseif ($document->isApproved())
                                            <span class="text-accent-700">Aprobado</span>
                                            @if ($document->validatedBy)
                                                <span class="text-xs text-slate-500">por {{ $document->validatedBy->name }} · {{ $document->validado_en?->format('d/m/Y H:i') }}</span>
                                            @endif
                                        @else
                                            <span class="fx-badge bg-rose-100 text-rose-700">Observado</span>
                                            <span class="text-slate-700">{{ $document->observacion_validacion }}</span>
                                            @if ($document->validatedBy)
                                                <span class="block text-xs text-slate-500">{{ $document->validatedBy->name }} · {{ $document->validado_en?->format('d/m/Y H:i') }}</span>
                                            @endif
                                        @endif
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <a href="{{ route('documentos.download', $document) }}" class="fx-btn-link-primary">Descargar</a>
                                @if ($canValidate && $isLatest && $document->isPendingValidation())
                                    <form method="POST" action="{{ route('documentos.approve', $document) }}">
                                        @csrf
                                        <button type="submit" class="fx-btn-primary h-9">Aprobar</button>
                                    </form>
                                    <button type="button" class="fx-btn-secondary h-9" x-on:click="observing = ! observing">Observar</button>
                                @endif
                                @if ($canManageDocuments)
                                    <form method="POST" action="{{ route('documentos.destroy', $document) }}" onsubmit="return confirm('¿Eliminar documento?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm font-medium text-rose-700 hover:underline" type="submit">Eliminar</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if ($canValidate && $isLatest && $document->isPendingValidation())
                            <form x-show="observing" x-cloak method="POST" action="{{ route('documentos.observe', $document) }}" class="mt-3 flex flex-col gap-2 rounded-md bg-slate-50 p-3 sm:flex-row sm:items-end">
                                @csrf
                                <div class="flex-1">
                                    <label class="fx-label" for="observacion_{{ $document->id }}">Motivo (se guarda en el historial)</label>
                                    <input class="fx-input" id="observacion_{{ $document->id }}" name="observacion_validacion" maxlength="1000" required
                                           placeholder="Ej. El peso no coincide con el Packing List">
                                </div>
                                <button type="submit" class="fx-btn-danger">Marcar como observado</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
