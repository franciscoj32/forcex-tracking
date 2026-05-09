<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\AppSetting::current()->program_name }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=forcex">
        <link rel="shortcut icon" href="{{ asset('favicon.svg') }}?v=forcex">
        <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}?v=forcex">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-['IBM_Plex_Sans']">
        @php
            $user = auth()->user();
            $appSettings = \App\Models\AppSetting::current();
            $menu = [
                'core' => [
                    ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => ['dashboard'], 'icon' => 'dashboard', 'can' => true],
                ],
                'gestion' => [
                    ['label' => 'Clientes', 'route' => 'clientes.index', 'active' => ['clientes.*'], 'icon' => 'clients', 'can' => $user->hasRole(['admin', 'operaciones', 'comercial'])],
                    ['label' => 'Embarques', 'route' => 'embarques.index', 'active' => ['embarques.index', 'embarques.create', 'embarques.show', 'embarques.edit'], 'icon' => 'shipments', 'can' => true],
                    ['label' => 'Provisiones', 'route' => 'provisiones.index', 'active' => ['provisiones.*'], 'icon' => 'provisions', 'can' => $user->hasRole(['admin', 'operaciones', 'comercial'])],
                    ['label' => 'Facturación', 'route' => 'facturacion.index', 'active' => ['facturacion.*'], 'icon' => 'billing', 'can' => $user->hasRole(['admin', 'operaciones', 'comercial'])],
                    ['label' => 'Reportes', 'route' => 'reportes.index', 'active' => ['reportes.*'], 'icon' => 'reports', 'can' => $user->hasRole(['admin', 'operaciones', 'comercial'])],
                ],
                'seguridad' => [
                    ['label' => 'Usuarios', 'route' => 'usuarios.index', 'active' => ['usuarios.*'], 'icon' => 'users', 'can' => $user->hasRole(['admin'])],
                    ['label' => 'Roles', 'route' => 'roles.index', 'active' => ['roles.*'], 'icon' => 'roles', 'can' => $user->hasRole(['admin'])],
                    ['label' => 'Configuracion', 'route' => 'configuracion.edit', 'active' => ['configuracion.*'], 'icon' => 'settings', 'can' => $user->hasRole(['admin'])],
                ],
                'cuenta' => [
                    ['label' => 'Perfil', 'route' => 'profile.edit', 'active' => ['profile.edit'], 'icon' => 'profile', 'can' => true],
                ],
            ];
        @endphp

        <div class="min-h-screen bg-slate-100 text-slate-900">
            <div class="flex min-h-screen">
                <aside class="hidden w-72 shrink-0 bg-slate-950 text-slate-200 lg:block">
                    <div class="flex h-full flex-col px-6 py-8">
                        <x-application-logo />

                        <nav class="mt-8 space-y-8">
                            @foreach ($menu as $section => $items)
                                @php $visibleItems = collect($items)->where('can', true); @endphp
                                @if ($visibleItems->isNotEmpty())
                                    <div>
                                        <p class="px-3 text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">{{ $section }}</p>
                                        <div class="mt-3 space-y-1">
                                            @foreach ($visibleItems as $item)
                                                @php
                                                    $isActive = collect($item['active'] ?? [$item['route']])
                                                        ->contains(fn ($pattern) => request()->routeIs($pattern));
                                                @endphp
                                                <a href="{{ route($item['route']) }}"
                                                   class="group {{ $isActive ? 'bg-cyan-500/15 text-cyan-300' : 'text-slate-300 hover:bg-white/5 hover:text-white' }} flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $isActive ? 'bg-cyan-400/10 text-white' : 'bg-white/5 text-white group-hover:bg-white/10 group-hover:text-white' }}">
                                                        @switch($item['icon'] ?? 'default')
                                                            @case('dashboard')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M3 12.5 12 4l9 8.5"/>
                                                                    <path d="M5 10.5V20h14v-9.5"/>
                                                                    <path d="M9.5 20v-5h5v5"/>
                                                                </svg>
                                                                @break
                                                            @case('clients')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M16 19a4 4 0 0 0-8 0"/>
                                                                    <circle cx="12" cy="11" r="3"/>
                                                                    <path d="M5 19a3 3 0 0 1 3-3"/>
                                                                    <path d="M19 19a3 3 0 0 0-3-3"/>
                                                                    <path d="M7.5 10a2.5 2.5 0 1 0-2.5-2.5"/>
                                                                    <path d="M16.5 10A2.5 2.5 0 1 1 19 7.5"/>
                                                                </svg>
                                                                @break
                                                            @case('shipments')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M3 7h11v8H3z"/>
                                                                    <path d="M14 10h3l3 3v2h-6z"/>
                                                                    <circle cx="7.5" cy="17.5" r="1.5"/>
                                                                    <circle cx="17.5" cy="17.5" r="1.5"/>
                                                                </svg>
                                                                @break
                                                            @case('provisions')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M6 4h9l3 3v13H6z"/>
                                                                    <path d="M15 4v4h4"/>
                                                                    <path d="M9 12h6"/>
                                                                    <path d="M9 16h4"/>
                                                                </svg>
                                                                @break
                                                            @case('billing')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <rect x="4" y="5" width="16" height="14" rx="2"/>
                                                                    <path d="M4 10h16"/>
                                                                    <path d="M8 15h3"/>
                                                                </svg>
                                                                @break
                                                            @case('reports')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M4 19h16"/>
                                                                    <path d="M7 16V9"/>
                                                                    <path d="M12 16V5"/>
                                                                    <path d="M17 16v-7"/>
                                                                </svg>
                                                                @break
                                                            @case('users')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="M17 19a5 5 0 0 0-10 0"/>
                                                                    <circle cx="12" cy="10" r="3"/>
                                                                    <path d="M19 8h2"/>
                                                                    <path d="M20 7v2"/>
                                                                </svg>
                                                                @break
                                                            @case('roles')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <path d="m12 3 7 4v5c0 4.2-2.7 8.1-7 9-4.3-.9-7-4.8-7-9V7z"/>
                                                                    <path d="m9.5 12 1.8 1.8 3.2-3.6"/>
                                                                </svg>
                                                                @break
                                                            @case('settings')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <circle cx="12" cy="12" r="3"/>
                                                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                                                                </svg>
                                                                @break
                                                            @case('profile')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <circle cx="12" cy="8" r="3"/>
                                                                    <path d="M6 19a6 6 0 0 1 12 0"/>
                                                                </svg>
                                                                @break
                                                            @case('password')
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <rect x="4" y="11" width="16" height="9" rx="2"/>
                                                                    <path d="M8 11V8a4 4 0 1 1 8 0v3"/>
                                                                    <circle cx="12" cy="15.5" r="1"/>
                                                                </svg>
                                                                @break
                                                            @default
                                                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                    <circle cx="12" cy="12" r="8"/>
                                                                </svg>
                                                        @endswitch
                                                    </span>
                                                    <span>{{ $item['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </nav>

                    </div>
                </aside>

                <div class="flex min-w-0 flex-1 flex-col">
                    <header class="border-b border-slate-200 bg-white/90 px-4 py-4 backdrop-blur lg:px-8">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ $appSettings->company_name }}</p>
                                @isset($header)
                                    <div class="mt-1">{{ $header }}</div>
                                @else
                                    <h1 class="text-2xl font-semibold text-slate-900">Panel de control</h1>
                                @endisset
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('profile.edit') }}" class="fx-btn-secondary">Mi perfil</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="fx-btn-primary">Cerrar sesion</button>
                                </form>
                            </div>
                        </div>
                    </header>

                    <main class="flex-1 px-4 py-6 lg:px-8">
                        @if (($pendingMigrations ?? 0) > 0)
                            <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                                <p class="font-semibold">Hay {{ $pendingMigrations }} migración(es) pendiente(s).</p>
                                <p class="mt-1">Ejecuta `php artisan migrate` para alinear la base de datos con el código actual.</p>
                            </div>
                        @endif

                        @if (session('status'))
                            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                                {{ session('status') }}
                            </div>
                        @endif

                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
