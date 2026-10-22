<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['pageTitle' => 'Seguimiento de carga'])
        <link rel="stylesheet" href="{{ asset('flags/flag-icons/css/flag-icons.min.css') }}">
    </head>
    <body>
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-14 w-full max-w-4xl items-center px-4 sm:px-6">
                <x-application-logo />
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 sm:py-8">
            {{ $slot }}
        </main>

        <footer class="mx-auto w-full max-w-4xl px-4 pb-8 text-xs text-slate-400 sm:px-6">
            {{ \App\Models\AppSetting::current()->company_name }} · Seguimiento de carga
        </footer>
    </body>
</html>
