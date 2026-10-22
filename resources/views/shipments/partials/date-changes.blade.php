{{--
    Cambios de ETD/ETA pendientes de validacion.
    Operaciones ya cambio la fecha interna; el cliente sigue viendo la publicada
    hasta que el Customer asignado (o el Admin) apruebe o descarte.
--}}
@php
    $pendingChanges = $shipment->pendingDateChanges;
    $canReview = auth()->user()->can('reviewDateChanges', $shipment);
@endphp

@if ($pendingChanges->isNotEmpty())
    <section id="cambios-fecha" class="fx-card scroll-mt-20 border-amber-300">
        <h2 class="fx-section-title border-b border-slate-200 px-4 py-3">
            Cambios de fecha por validar
            @unless ($canReview)
                <span class="ml-2 text-sm font-normal text-slate-500">Los valida el Customer asignado</span>
            @endunless
        </h2>

        <div class="divide-y divide-slate-100">
            @foreach ($pendingChanges as $change)
                @php $delay = $change->delayInDays(); @endphp
                <div class="grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:gap-6">
                    <div>
                        <p class="text-slate-900">
                            <span class="font-medium">{{ $change->fieldLabel() }}:</span>
                            <span class="tabular-nums">{{ $change->valor_publicado?->format('d/m/Y') ?? 'sin fecha' }}</span>
                            →
                            <span class="font-semibold tabular-nums">{{ $change->valor_nuevo?->format('d/m/Y') ?? 'por confirmar' }}</span>
                            @if ($delay !== null && $delay !== 0)
                                <span @class(['ml-1 text-sm', 'text-rose-700' => $delay > 0, 'text-slate-500' => $delay < 0])>
                                    ({{ $delay > 0 ? '+' : '' }}{{ $delay }} {{ abs($delay) === 1 ? 'día' : 'días' }})
                                </span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $change->changedBy->name }} · {{ $change->created_at->format('d/m/Y H:i') }}</p>
                        @if ($change->lote)
                            <a href="{{ route('cambios-fecha.lote', $change->lote) }}" class="fx-btn-link-primary mt-1 inline-block text-xs">
                                Se cambió junto con otras RO · Revisar en grupo
                            </a>
                        @endif
                    </div>

                    @if ($canReview)
                        <form method="POST" action="{{ route('cambios-fecha.approve', $change) }}" class="space-y-2">
                            @csrf
                            <label class="fx-label" for="nota_cliente_{{ $change->id }}">Nota para el cliente (opcional, va en el correo)</label>
                            <textarea class="fx-textarea" id="nota_cliente_{{ $change->id }}" name="nota_cliente" rows="2"
                                placeholder="Ej. La naviera reprogramó la salida por congestión en puerto."></textarea>
                            <div class="flex flex-wrap gap-2">
                                <button type="submit" class="fx-btn-primary">Publicar al cliente</button>
                                <button type="submit" formaction="{{ route('cambios-fecha.discard', $change) }}" class="fx-btn-secondary"
                                    onclick="return confirm('¿Descartar este cambio? El cliente seguirá viendo la fecha publicada.')">
                                    Descartar
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
