<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head')
    </head>
    <body class="bg-brand-950">
        {{-- Fondo: foto de un terminal de contenedores bajo una capa del color de la
             empresa, para que el cuadro blanco resalte y combine con cualquier marca. --}}
        <div class="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10">
            <img
                src="{{ asset('images/login-fondo.jpg') }}"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 -z-20 h-full w-full object-cover"
            >
            <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-950/90 via-brand-900/75 to-brand-800/50"></div>

            <div class="mb-6">
                <x-application-logo dark large />
            </div>

            <main class="w-full max-w-sm rounded-lg bg-white p-6 shadow-2xl shadow-black/40 sm:p-8">
                {{ $slot }}
            </main>

            <p class="mt-6 text-center text-sm text-white/70">Seguimiento de importaciones y exportaciones</p>
        </div>
    </body>
</html>
