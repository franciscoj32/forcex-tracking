<x-app-layout>
    @php
        $categoryOptions = collect($categories)
            ->map(fn ($category) => ['value' => $category, 'label' => ucfirst($category)]);
        $documentTypeOptions = collect($typeSuggestions)
            ->flatten()
            ->unique()
            ->values()
            ->map(fn ($item) => ['value' => $item, 'label' => $item]);
    @endphp

    <x-slot name="header">
        <h1 class="text-2xl font-semibold text-slate-900">Documentos {{ $shipment->nro_orden_trabajo }}</h1>
        <p class="text-sm text-slate-500">Carga privada y descarga protegida por controlador.</p>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
        @if (auth()->user()->hasRole(['admin', 'operaciones']))
            <div class="fx-card fx-card-body">
                <h2 class="text-lg font-semibold text-slate-900">Subir documento</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Sugerencia operativa: usa la categoria <strong>Endoso</strong> solo para documentos de cesion, BL endosado,
                    carta de endoso o sustento directo del proceso de endoso.
                </p>
                <form method="POST" action="{{ route('embarques.documentos.store', $shipment) }}" enctype="multipart/form-data" class="mt-6 space-y-5" data-disable-native-autocomplete="true">
                    @csrf
                    <div>
                        <x-form-combobox
                            id="categoria"
                            name="categoria"
                            label="Categoria"
                            :options="$categoryOptions"
                            :value="old('categoria', $categories[0] ?? '')"
                            placeholder="Seleccionar categoria"
                            required
                        />
                    </div>
                    <div>
                        <x-form-autocomplete
                            id="tipo_documento"
                            name="tipo_documento"
                            label="Tipo de documento"
                            :options="$documentTypeOptions"
                            :value="old('tipo_documento')"
                            placeholder="Escribe o busca el tipo"
                            search-placeholder="Buscar tipo de documento..."
                            empty-text="No hay tipos sugeridos que coincidan."
                            required
                        />
                    </div>
                    <div>
                        <label class="fx-label" for="archivo">Archivo</label>
                        <input class="fx-input" id="archivo" name="archivo" type="file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <p class="mt-2 text-xs text-slate-500">Formatos permitidos: PDF, JPG, PNG. Maximo 5 MB.</p>
                    </div>
                    <button class="fx-btn-primary" type="submit">Cargar archivo</button>
                </form>
            </div>
        @endif

        <div class="fx-card">
            <div class="fx-card-header"><h2 class="text-lg font-semibold text-slate-900">Archivos cargados</h2></div>
            <div class="overflow-x-auto">
                <table class="fx-table">
                    <thead>
                        <tr>
                            <th class="fx-th">Categoria</th>
                            <th class="fx-th">Tipo</th>
                            <th class="fx-th">Archivo</th>
                            <th class="fx-th">Subido por</th>
                            <th class="fx-th"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($shipment->documents as $document)
                            <tr>
                                <td class="fx-td">
                                    <span class="fx-badge {{ $document->categoria === 'endoso' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($document->categoria) }}
                                    </span>
                                </td>
                                <td class="fx-td">{{ $document->tipo_documento }}</td>
                                <td class="fx-td">{{ $document->nombre_original }}</td>
                                <td class="fx-td">{{ $document->user->name }}</td>
                                <td class="fx-td">
                                    <div class="flex gap-3">
                                        <a href="{{ route('documentos.download', $document) }}" class="text-cyan-700">Descargar</a>
                                        @if (auth()->user()->hasRole(['admin', 'operaciones']))
                                            <form method="POST" action="{{ route('documentos.destroy', $document) }}" onsubmit="return confirm('Eliminar documento?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-rose-600" type="submit">Eliminar</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="fx-td text-center text-slate-500">No hay documentos cargados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
