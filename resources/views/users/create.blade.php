<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Nuevo usuario</h1>
        <p class="text-sm text-slate-500">Alta manual para sistema privado.</p>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="fx-card fx-card-body">
            <form method="POST" action="{{ route('usuarios.store') }}" data-disable-native-autocomplete="true">
                @include('users._form')
            </form>
        </div>

        <aside class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-100/70">
                <h2 class="text-lg font-semibold text-slate-900">Antes de asignar un rol</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Usa el rol como permiso base. La asignacion a embarques debe servir para visibilidad, alertas y responsabilidad operativa, no para abrir mas permisos.
                </p>
                <a href="{{ route('roles.index') }}" class="mt-4 inline-flex items-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-700">
                    Ver matriz de roles
                </a>
            </section>

            <section class="rounded-3xl border border-amber-200 bg-amber-50/80 px-5 py-5">
                <h2 class="text-lg font-semibold text-slate-900">Criterio recomendado</h2>
                <div class="mt-3 space-y-3 text-sm leading-6 text-slate-700">
                    <p><strong>Admin</strong>: controla usuarios, reglas y supervisa toda la operacion.</p>
                    <p><strong>Operaciones</strong>: crea y edita embarques, estados, documentos y control operativo.</p>
                    <p><strong>Comercial</strong>: consulta, seguimiento y apoyo al cliente, sin asumir endoso ni control operativo total.</p>
                </div>
            </section>
        </aside>
    </div>
</x-app-layout>
