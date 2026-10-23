{{-- Pizarra en el Inicio: los anuncios vigentes mas importantes. --}}
@php $canManage = auth()->user()->can('manage-announcements'); @endphp
@if ($board->isNotEmpty() || $canManage)
    <section aria-label="Pizarra de oficina">
        <div class="mb-2 flex items-center justify-between gap-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-violet-100 text-violet-700"><x-nav-icon name="megaphone" class="h-3.5 w-3.5" /></span>
                Pizarra
            </h2>
            <div class="flex items-center gap-3 text-sm">
                @if ($canManage)
                    <a href="{{ route('pizarra.index', ['nuevo' => 1]) }}" class="fx-btn-link-primary">Publicar</a>
                @endif
                <a href="{{ route('pizarra.index') }}" class="fx-btn-link-primary">Ver todo</a>
            </div>
        </div>
        @if ($board->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-500">Sin anuncios. Publica uno para todo el equipo: reuniones, feriados o novedades.</p>
        @else
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($board as $announcement)
                    @include('productivity.board._card', ['announcement' => $announcement, 'compact' => true])
                @endforeach
            </div>
        @endif
    </section>
@endif
