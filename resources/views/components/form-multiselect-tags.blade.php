@props([
    'id',
    'name',
    'label' => null,
    'options' => [],
    'values' => [],
    'placeholder' => 'Seleccionar usuarios',
    'searchPlaceholder' => 'Buscar...',
    'emptyText' => 'No hay opciones disponibles.',
])

@php
    $normalizedOptions = collect($options)
        ->map(function ($option) {
            if (is_array($option)) {
                return [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                    'subtitle' => (string) ($option['subtitle'] ?? ''),
                    'search' => strtolower((string) ($option['search'] ?? (($option['label'] ?? '').' '.($option['subtitle'] ?? '')))),
                ];
            }

            return [
                'value' => (string) data_get($option, 'value', ''),
                'label' => (string) data_get($option, 'label', ''),
                'subtitle' => (string) data_get($option, 'subtitle', ''),
                'search' => strtolower((string) data_get($option, 'search', data_get($option, 'label', '').' '.data_get($option, 'subtitle', ''))),
            ];
        })
        ->values();

    $normalizedValues = collect($values)->map(fn ($value) => (string) $value)->values();
@endphp

<div
    x-data="{
        open: false,
        query: '',
        options: {{ Illuminate\Support\Js::from($normalizedOptions) }},
        selected: {{ Illuminate\Support\Js::from($normalizedValues) }},
        placeholder: {{ Illuminate\Support\Js::from($placeholder) }},
        searchPlaceholder: {{ Illuminate\Support\Js::from($searchPlaceholder) }},
        emptyText: {{ Illuminate\Support\Js::from($emptyText) }},
        get selectedOptions() {
            return this.options.filter((option) => this.selected.includes(String(option.value)));
        },
        get filteredOptions() {
            const term = this.query.trim().toLowerCase();
            if (term === '') {
                return this.options;
            }

            return this.options.filter((option) => option.search.includes(term));
        },
        get buttonLabel() {
            return this.selected.length === 0
                ? this.placeholder
                : `${this.selected.length} seleccionado(s)`;
        },
        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.$nextTick(() => this.$refs.searchInput?.focus());
            } else {
                this.query = '';
            }
        },
        closePanel() {
            this.open = false;
            this.query = '';
        },
        isSelected(value) {
            return this.selected.includes(String(value));
        },
        toggleOption(value) {
            const normalized = String(value);

            if (this.isSelected(normalized)) {
                this.selected = this.selected.filter((item) => item !== normalized);
                return;
            }

            this.selected = [...this.selected, normalized];
        },
        removeOption(value) {
            const normalized = String(value);
            this.selected = this.selected.filter((item) => item !== normalized);
        },
    }"
    x-on:click.outside="closePanel()"
    x-on:keydown.escape.prevent.stop="closePanel()"
    {{ $attributes->class('space-y-1') }}
>
    @if ($label)
        <label class="fx-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="fx-combobox">
        <template x-for="value in selected" :key="value">
            <input type="hidden" name="{{ $name }}[]" :value="value">
        </template>

        <button type="button" class="fx-combobox-trigger min-h-[3.5rem] items-start py-3" :data-open="open" x-on:click="toggle()">
            <div class="min-w-0 flex-1 space-y-2 text-left">
                <div x-show="selectedOptions.length > 0" class="flex flex-wrap gap-2">
                    <template x-for="option in selectedOptions" :key="option.value">
                        <span class="inline-flex max-w-full items-center gap-2 rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700 ring-1 ring-cyan-100">
                            <span class="truncate" x-text="option.label"></span>
                            <button
                                type="button"
                                class="shrink-0 text-cyan-500 transition hover:text-cyan-700"
                                x-on:click.stop="removeOption(option.value)"
                                aria-label="Quitar usuario"
                            >
                                ×
                            </button>
                        </span>
                    </template>
                </div>
                <span class="fx-combobox-trigger-label" x-show="selectedOptions.length === 0" x-text="buttonLabel"></span>
            </div>
            <svg class="mt-1 h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m7 15 5 5 5-5"></path>
                <path d="m7 9 5-5 5 5"></path>
            </svg>
        </button>

        <div x-cloak x-show="open" x-transition.origin.top.left class="fx-combobox-panel">
            <div class="fx-combobox-search">
                <input
                    x-ref="searchInput"
                    x-model="query"
                    type="search"
                    placeholder="{{ $searchPlaceholder }}"
                    class="fx-combobox-search-input"
                >
            </div>

            <div class="fx-combobox-list">
                <template x-if="filteredOptions.length === 0">
                    <div class="fx-combobox-empty">{{ $emptyText }}</div>
                </template>

                <template x-for="option in filteredOptions" :key="option.value">
                    <button
                        type="button"
                        class="fx-combobox-option"
                        :data-selected="isSelected(option.value)"
                        x-on:click="toggleOption(option.value)"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-700" x-text="option.label"></span>
                            <span class="block truncate text-xs text-slate-500" x-text="option.subtitle"></span>
                        </span>
                        <svg x-show="isSelected(option.value)" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
