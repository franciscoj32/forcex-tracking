<x-guest-layout>
    <h1 class="text-xl font-semibold">Verifica tu correo</h1>
    <p class="mt-1 text-slate-600">Te enviamos un enlace de verificación. Si no lo recibiste, pide otro.</p>

    @if (session('status') == 'verification-link-sent')
        <p class="mt-4 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-slate-700">Te enviamos un nuevo enlace.</p>
    @endif

    <div class="mt-5 flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="fx-btn-primary">Reenviar enlace</button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="fx-btn-link-primary">Cerrar sesión</button>
        </form>
    </div>
</x-guest-layout>
