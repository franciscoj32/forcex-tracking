{{-- Pizarra de oficina: avisos de la Gerencia y cumpleanos. Reemplaza los correos masivos internos. --}}
@php
    $canManage = auth()->user()->can('manage-announcements');
    $month = ucfirst(today()->locale('es')->translatedFormat('F'));
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="hidden text-xs text-slate-500 sm:block">Equipo</p>
        <h1>Pizarra de oficina</h1>
    </x-slot>

    <x-slot name="actions">
        @if ($canManage)
            <button type="button" x-data x-on:click="$dispatch('open-dialog', 'anuncio')" class="fx-btn-primary">
                <x-nav-icon name="megaphone" /> Publicar anuncio
            </button>
        @endif
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div>
            @if ($announcements->isEmpty())
                <section class="fx-card px-6 py-12 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-50 text-violet-600"><x-nav-icon name="megaphone" class="h-7 w-7" /></span>
                    <p class="mt-4 font-semibold text-slate-900">La pizarra está limpia</p>
                    <p class="mt-1 text-slate-500">Aquí aparecerán los anuncios de la Gerencia y los cumpleaños del equipo.</p>
                </section>
            @else
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($announcements as $announcement)
                        @include('productivity.board._card', ['announcement' => $announcement, 'compact' => false])
                    @endforeach
                </div>
            @endif

            @if ($expired->isNotEmpty())
                <details class="fx-card group mt-4 overflow-hidden">
                    <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        <span>Vencidos en los últimos 30 días · {{ $expired->count() }}</span>
                        <x-nav-icon name="chevron-right" class="h-4 w-4 transition group-open:rotate-90" />
                    </summary>
                    <ul class="divide-y divide-slate-100 border-t border-slate-200 text-sm">
                        @foreach ($expired as $old)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <span class="min-w-0 truncate text-slate-700">{{ $old->titulo }}</span>
                                <span class="shrink-0 text-xs text-slate-400">hasta el {{ $old->expira_en->format('d/m') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="overflow-hidden rounded-2xl border border-pink-100 bg-white">
                <div class="flex items-center gap-2 border-b border-pink-100 bg-gradient-to-r from-pink-50 to-amber-50 px-4 py-3">
                    <x-nav-icon name="cake" class="h-4 w-4 text-pink-600" />
                    <h2 class="text-sm font-semibold text-slate-900">Cumpleaños de {{ $month }}</h2>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($birthdays as $person)
                        @php $isToday = $person->isBirthdayOn(); @endphp
                        <li @class(['flex items-center justify-between gap-3 px-4 py-2.5', 'bg-pink-50/60' => $isToday])>
                            <span class="min-w-0 truncate {{ $isToday ? 'font-semibold text-pink-700' : 'text-slate-800' }}">{{ $person->name }}{{ $isToday ? ' 🎉' : '' }}</span>
                            <span class="shrink-0 text-xs tabular-nums text-slate-500">{{ $isToday ? 'Hoy' : $person->birthdayLabel() }}</span>
                        </li>
                    @empty
                        <li class="px-4 py-4 text-xs leading-relaxed text-slate-500">Nadie cumple este mes. Cada uno puede agregar su fecha en "Mi perfil"{{ $canManage ? ' y tú en Usuarios' : '' }}.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>

    @if ($canManage)
        <x-dialog name="anuncio" title="Publicar en la pizarra" :show="$errors->any() || request()->boolean('nuevo')" max-width="max-w-lg">
            <form method="POST" action="{{ route('pizarra.store') }}" class="space-y-4 p-5">
                @csrf
                <div>
                    <label class="fx-label" for="anuncio_titulo">Anuncio</label>
                    <input class="fx-input" id="anuncio_titulo" name="titulo" maxlength="160" required value="{{ old('titulo') }}" placeholder="Ej. Reunión general hoy a las 4 PM">
                </div>
                <div>
                    <label class="fx-label" for="anuncio_cuerpo">Detalle <span class="font-normal text-slate-400">(opcional)</span></label>
                    <textarea class="fx-textarea" id="anuncio_cuerpo" name="cuerpo" rows="3" maxlength="2000">{{ old('cuerpo') }}</textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="fx-label" for="anuncio_expira">Mostrar hasta</label>
                        <input class="fx-input" id="anuncio_expira" name="expira" type="date" min="{{ today()->toDateString() }}" value="{{ old('expira', today()->addDays(\App\Models\Announcement::DEFAULT_DAYS)->toDateString()) }}">
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2.5 text-sm text-slate-700">
                        <input type="hidden" name="fijado" value="0">
                        <input type="checkbox" name="fijado" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600/30" @checked(old('fijado'))>
                        Fijar arriba
                    </label>
                </div>
                <p class="text-xs text-slate-500">Todo el equipo verá un aviso en su campanita. No se envían correos.</p>
                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="fx-btn-secondary h-9" x-on:click="open = false">Cancelar</button>
                    <button type="submit" class="fx-btn-primary h-9">Publicar</button>
                </div>
            </form>
        </x-dialog>
    @endif
</x-app-layout>
