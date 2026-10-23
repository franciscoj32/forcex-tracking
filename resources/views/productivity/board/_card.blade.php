{{-- Tarjeta de la pizarra. $announcement, $compact (Inicio) --}}
@php
    $birthday = $announcement->isBirthday();
    $canManage = auth()->user()->can('manage-announcements');
@endphp
<article id="anuncio-{{ $announcement->id }}" @class([
    'relative flex flex-col overflow-hidden rounded-2xl border p-4 transition',
    'border-pink-200 bg-gradient-to-br from-pink-50 via-white to-amber-50' => $birthday,
    'border-brand-200 bg-white shadow-sm ring-1 ring-brand-100' => ! $birthday && $announcement->fijado,
    'border-slate-200 bg-white' => ! $birthday && ! $announcement->fijado,
    'scroll-mt-20' => true,
])>
    <div class="flex items-start gap-3">
        <span @class([
            'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
            'bg-white text-xl shadow-sm' => $birthday,
            'bg-violet-100 text-violet-700' => ! $birthday,
        ])>
            @if ($birthday)
                <span aria-hidden="true">🎂</span>
            @else
                <x-nav-icon name="megaphone" class="h-5 w-5" />
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <h3 class="font-semibold leading-snug text-slate-900">{{ $announcement->titulo }}</h3>
            @if ($announcement->cuerpo)
                <p @class(['mt-1 whitespace-pre-line text-sm leading-relaxed text-slate-600', 'line-clamp-3' => $compact ?? false])>{{ $announcement->cuerpo }}</p>
            @endif
        </div>
        @if ($announcement->fijado)
            <span class="text-brand-600" title="Fijado"><x-nav-icon name="pin" class="h-4 w-4" /></span>
        @endif
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 pt-2.5 text-xs text-slate-500">
        <span>{{ $birthday ? 'Cumpleaños del equipo' : ($announcement->author?->name ?? 'Gerencia') }}</span>
        <span>{{ $announcement->publicado_en->locale('es')->diffForHumans() }}</span>
        @if (! $birthday && $announcement->expira_en)
            <span>hasta el {{ $announcement->expira_en->format('d/m') }}</span>
        @endif

        @if ($canManage && ! ($compact ?? false))
            <span class="ml-auto flex items-center gap-1">
                @unless ($birthday)
                    <form method="POST" action="{{ route('pizarra.fijar', $announcement) }}">
                        @csrf
                        <button type="submit" class="rounded-md px-2 py-1 font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-800">{{ $announcement->fijado ? 'Desfijar' : 'Fijar' }}</button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('pizarra.destroy', $announcement) }}" x-data x-on:submit="if (! confirm('¿Retirar este anuncio de la pizarra?')) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md px-2 py-1 font-medium text-rose-600 hover:bg-rose-50">Retirar</button>
                </form>
            </span>
        @endif
    </div>
</article>
