<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Configuracion general</h1>
        <p class="text-sm text-slate-500">Edita la identidad visible del sistema para sidebar, login y titulo del programa.</p>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="fx-card fx-card-body">
            <form method="POST" action="{{ route('configuracion.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="fx-label" for="program_name">Titulo del programa</label>
                        <input class="fx-input" id="program_name" name="program_name" value="{{ old('program_name', $settings->program_name) }}" required>
                    </div>
                    <div>
                        <label class="fx-label" for="company_name">Nombre de empresa</label>
                        <input class="fx-input" id="company_name" name="company_name" value="{{ old('company_name', $settings->company_name) }}" required>
                    </div>
                    <div>
                        <label class="fx-label" for="brand_abbr">Sigla o logo texto</label>
                        <input class="fx-input" id="brand_abbr" name="brand_abbr" maxlength="20" value="{{ old('brand_abbr', $settings->brand_abbr) }}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="fx-label" for="logo_image">Logo imagen opcional</label>
                        <input class="fx-input" id="logo_image" name="logo_image" type="file" accept=".png,.jpg,.jpeg,.webp,.svg">
                        <p class="mt-2 text-xs text-slate-500">PNG, JPG, WEBP o SVG. Se ajustara automaticamente al espacio actual del logo.</p>
                        @if ($settings->logoUrl())
                            <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                Quitar imagen y volver al logo en texto
                            </label>
                        @endif
                    </div>
                    <div>
                        <label class="fx-label" for="brand_eyebrow">Linea superior de marca</label>
                        <input class="fx-input" id="brand_eyebrow" name="brand_eyebrow" value="{{ old('brand_eyebrow', $settings->brand_eyebrow) }}" required>
                    </div>
                    <div>
                        <label class="fx-label" for="brand_title">Titulo de marca</label>
                        <input class="fx-input" id="brand_title" name="brand_title" value="{{ old('brand_title', $settings->brand_title) }}" required>
                    </div>
                    <div>
                        <label class="fx-label" for="login_eyebrow">Etiqueta del login</label>
                        <input class="fx-input" id="login_eyebrow" name="login_eyebrow" value="{{ old('login_eyebrow', $settings->login_eyebrow) }}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="fx-label" for="login_title">Titulo del login</label>
                        <input class="fx-input" id="login_title" name="login_title" value="{{ old('login_title', $settings->login_title) }}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="fx-label" for="login_text">Texto de apoyo del login</label>
                        <textarea class="fx-textarea" id="login_text" name="login_text" rows="4" required>{{ old('login_text', $settings->login_text) }}</textarea>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button class="fx-btn-primary" type="submit">Guardar cambios</button>
                </div>
            </form>
        </div>

        <aside class="space-y-6">
            <section class="rounded-[30px] border border-slate-200 bg-white px-5 py-5 shadow-sm shadow-slate-100/70">
                <h2 class="text-lg font-semibold text-slate-900">Vista previa</h2>
                <div class="mt-4 rounded-[28px] bg-slate-950 px-5 py-5 text-slate-100">
                    @if ($settings->logoUrl())
                        <img src="{{ $settings->logoUrl() }}" alt="Logo actual" class="h-12 max-w-[220px] object-contain">
                    @else
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-500 text-lg font-black text-slate-950 shadow-lg shadow-cyan-500/30">
                                {{ $settings->brand_abbr }}
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">{{ $settings->brand_eyebrow }}</p>
                                <p class="text-2xl font-semibold text-white">{{ $settings->brand_title }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-5 rounded-[28px] border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] text-cyan-700">{{ $settings->login_eyebrow }}</p>
                    <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $settings->login_title }}</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $settings->login_text }}</p>
                </div>
            </section>
        </aside>
    </div>
</x-app-layout>
