<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body>
        @php
            $user = auth()->user();
            $isAdmin = $user->isAdmin();
            // Grupos del menu. Un grupo sin titulo se muestra sin encabezado.
            $menu = [
                ['title' => null, 'items' => [
                    ['label' => 'Inicio', 'route' => 'dashboard', 'active' => ['dashboard'], 'icon' => 'home', 'can' => true],
                    ['label' => 'Embarques', 'route' => 'embarques.index', 'active' => ['embarques.*'], 'icon' => 'shipments', 'can' => true],
                    ['label' => 'Clientes', 'route' => 'clientes.index', 'active' => ['clientes.*'], 'icon' => 'clients', 'can' => $user->can('viewAny', \App\Models\Client::class)],
                    ['label' => 'Cotizaciones', 'route' => 'cotizaciones.index', 'active' => ['cotizaciones.*'], 'icon' => 'quotation', 'can' => $user->can('viewAny', \App\Models\Quotation::class)],
                ]],
                // Modulo opcional de Productividad (solo si esta encendido en Configuracion).
                ['title' => 'Equipo', 'items' => [
                    ['label' => 'Tareas', 'route' => 'tareas.index', 'active' => ['tareas.*'], 'icon' => 'tasks', 'can' => $productivity !== null],
                    ['label' => 'Pizarra', 'route' => 'pizarra.index', 'active' => ['pizarra.*'], 'icon' => 'megaphone', 'can' => $productivity !== null],
                ]],
                ['title' => 'Finanzas', 'items' => [
                    ['label' => 'Provisiones', 'route' => 'provisiones.index', 'active' => ['provisiones.*'], 'icon' => 'provisions', 'can' => $user->can('view-provisions')],
                    ['label' => 'Facturación', 'route' => 'facturacion.index', 'active' => ['facturacion.*'], 'icon' => 'billing', 'can' => $user->can('view-invoices')],
                    ['label' => 'Proveedores', 'route' => 'proveedores.index', 'active' => ['proveedores.*'], 'icon' => 'suppliers', 'can' => $user->can('view-provisions')],
                    ['label' => 'Conceptos de tarifa', 'route' => 'conceptos.index', 'active' => ['conceptos.*'], 'icon' => 'concepts', 'can' => $user->can('manage-rate-concepts')],
                ]],
                ['title' => null, 'items' => [
                    ['label' => 'Reportes', 'route' => 'reportes.index', 'active' => ['reportes.*'], 'icon' => 'reports', 'can' => $user->can('view-reports')],
                ]],
                ['title' => 'Administración', 'items' => [
                    ['label' => 'Usuarios', 'route' => 'usuarios.index', 'active' => ['usuarios.*'], 'icon' => 'users', 'can' => $isAdmin],
                    ['label' => 'Roles', 'route' => 'roles.index', 'active' => ['roles.*'], 'icon' => 'roles', 'can' => $isAdmin],
                    ['label' => 'Configuración', 'route' => 'configuracion.edit', 'active' => ['configuracion.*'], 'icon' => 'settings', 'can' => $isAdmin],
                ]],
            ];
        @endphp

        <div x-data="{ menuOpen: false }" x-on:keydown.escape.window="menuOpen = false" class="min-h-screen lg:flex">
            {{-- Fondo oscuro del menu en celular --}}
            <div x-cloak x-show="menuOpen" x-transition.opacity x-on:click="menuOpen = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

            {{-- Menu lateral: fijo en escritorio, desplegable en celular --}}
            <aside
                class="fx-sidebar fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                :class="{ '!translate-x-0': menuOpen }"
                aria-label="Menú principal"
            >
                <div class="fx-sidebar-divider flex h-14 shrink-0 items-center justify-between border-b px-4">
                    <a href="{{ route('dashboard') }}" class="min-w-0"><x-application-logo dark /></a>
                    <button type="button" class="rounded-md p-1.5 text-brand-200 hover:bg-white/10 hover:text-white lg:hidden" x-on:click="menuOpen = false" aria-label="Cerrar menú">
                        <x-nav-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <form method="GET" action="{{ route('embarques.index') }}" class="fx-sidebar-divider border-b p-3 xl:hidden" role="search">
                    <label for="mobile-search" class="sr-only">Buscar embarque</label>
                    <input id="mobile-search" type="search" name="q" placeholder="Buscar OT, RO, BL o código" class="fx-input mt-0">
                </form>

                <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-4">
                    @foreach ($menu as $group)
                        @php $visibleItems = collect($group['items'])->where('can', true); @endphp
                        @if ($visibleItems->isNotEmpty())
                            <div class="space-y-0.5">
                                @if ($group['title'])
                                    <p class="fx-nav-group-title">{{ $group['title'] }}</p>
                                @endif
                                @foreach ($visibleItems as $item)
                                    @php $isActive = collect($item['active'])->contains(fn ($pattern) => request()->routeIs($pattern)); @endphp
                                    <a href="{{ route($item['route']) }}" @class(['fx-nav-link', 'fx-nav-link-active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                                        <x-nav-icon :name="$item['icon']" class="fx-nav-icon" />
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </nav>

                {{-- Usuario: nombre y rol arriba; Mi perfil y Cerrar sesion, uno debajo del otro --}}
                @php
                    $initials = collect(preg_split('/\s+/', trim($user->name)))
                        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
                @endphp
                <div class="fx-sidebar-divider shrink-0 border-t p-3">
                    <div class="flex items-center gap-3 px-3 pb-2 pt-1">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-semibold text-white" aria-hidden="true">{{ $initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                            <p class="truncate text-xs text-brand-200">{{ \App\Models\User::roleLabel($user->role) }}</p>
                        </div>
                    </div>
                    <div class="space-y-0.5">
                        <a href="{{ route('profile.edit') }}" @class(['fx-nav-link', 'fx-nav-link-active' => request()->routeIs('profile.*')]) @if (request()->routeIs('profile.*')) aria-current="page" @endif>
                            <x-nav-icon name="user" class="fx-nav-icon" />
                            <span>Mi perfil</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="fx-nav-link w-full">
                                <x-nav-icon name="logout" class="fx-nav-icon" />
                                <span>Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white">
                    <div class="flex min-h-14 items-center gap-3 px-4 py-2 sm:px-6">
                        <button type="button" class="-ml-1 rounded-md p-1.5 text-slate-600 hover:bg-slate-100 lg:hidden" x-on:click="menuOpen = true" aria-label="Abrir menú">
                            <x-nav-icon name="menu" class="h-5 w-5" />
                        </button>

                        <div class="min-w-0 flex-1 [&_h1]:truncate [&_h1]:text-lg [&_h1]:font-semibold [&_h1]:sm:text-xl">
                            @isset($header)
                                {{ $header }}
                            @else
                                <h1>{{ \App\Models\AppSetting::current()->program_name }}</h1>
                            @endisset
                        </div>

                        @isset($actions)
                            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
                        @endisset

                        @if ($productivity)
                            @include('productivity.partials.bell')
                        @endif

                        <form method="GET" action="{{ route('embarques.index') }}" @class(['relative w-72 shrink-0', 'hidden', 'xl:block' => ! request()->routeIs('embarques.index')]) role="search">
                            <label for="global-search" class="sr-only">Buscar embarque</label>
                            <x-nav-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input id="global-search" type="search" name="q" placeholder="Buscar OT, RO, BL o código" class="fx-input mt-0 h-9 pl-9">
                        </form>
                    </div>
                </header>

                <main class="w-full max-w-[1600px] flex-1 px-4 py-5 sm:px-6">
                    @if ($isAdmin && ($pendingMigrations ?? 0) > 0)
                        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Hay {{ $pendingMigrations }} migración(es) pendiente(s). Ejecuta <code class="font-mono">php artisan migrate</code>.
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-md border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                            <p class="font-medium">Revisa estos datos:</p>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>

            @if ($productivity)
                @include('productivity.partials.post-it')
                @if ($productivity['birthday'])
                    @include('productivity.partials.birthday')
                @endif
            @endif

            {{-- Aviso temporal de "guardado" --}}
            @php
                $statusMessage = match (session('status')) {
                    'profile-updated' => 'Perfil actualizado.',
                    'password-updated' => 'Contraseña actualizada.',
                    'verification-link-sent' => 'Te enviamos un nuevo enlace de verificación.',
                    default => is_string(session('status')) ? session('status') : null,
                };
            @endphp
            @if ($statusMessage)
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 5000)"
                    x-show="show"
                    x-transition.opacity.duration.200ms
                    role="status"
                    class="fixed inset-x-4 bottom-4 z-50 flex items-start gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-lg sm:inset-x-auto sm:right-6 sm:max-w-sm"
                >
                    <span class="mt-1 fx-status-dot bg-accent-600"></span>
                    <p class="flex-1">{{ $statusMessage }}</p>
                    <button type="button" x-on:click="show = false" class="text-slate-400 hover:text-slate-700" aria-label="Cerrar aviso">
                        <x-nav-icon name="close" />
                    </button>
                </div>
            @endif
        </div>
    </body>
</html>
