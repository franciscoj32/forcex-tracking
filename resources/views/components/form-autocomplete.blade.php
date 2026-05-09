@props([
    'id',
    'name',
    'label' => null,
    'options' => [],
    'value' => '',
    'placeholder' => '',
    'searchPlaceholder' => 'Buscar...',
    'emptyText' => 'No hay coincidencias.',
    'required' => false,
])

@php
    $normalizedOptions = collect($options)
        ->map(function ($option) {
            if (is_array($option)) {
                return [
                    'value' => (string) ($option['value'] ?? $option['label'] ?? ''),
                    'label' => (string) ($option['label'] ?? $option['value'] ?? ''),
                ];
            }

            return [
                'value' => (string) data_get($option, 'value', data_get($option, 'label', '')),
                'label' => (string) data_get($option, 'label', data_get($option, 'value', '')),
            ];
        })
        ->values();
@endphp

<div
    x-data="formAutocomplete({
        options: {{ Illuminate\Support\Js::from($normalizedOptions) }},
        value: {{ Illuminate\Support\Js::from((string) $value) }},
        placeholder: {{ Illuminate\Support\Js::from($placeholder) }},
        searchPlaceholder: {{ Illuminate\Support\Js::from($searchPlaceholder) }},
        emptyText: {{ Illuminate\Support\Js::from($emptyText) }},
    })"
    x-on:click.outside="closePanel()"
    x-on:keydown.escape.prevent.stop="closePanel()"
    x-on:keydown.arrow-down.prevent.stop="move(1)"
    x-on:keydown.arrow-up.prevent.stop="move(-1)"
    x-on:keydown.enter.prevent.stop="confirmActive()"
    data-country-options="{{ str_contains((string) $id, 'origen') || str_contains((string) $id, 'destino') ? 'true' : 'false' }}"
    {{ $attributes->class('space-y-1') }}
>
    @if ($label)
        <label class="fx-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="fx-autocomplete">
        <div class="relative">
            <input
                x-ref="input"
                x-model="value"
                x-on:focus="openPanel()"
                x-on:input="openPanel()"
                id="{{ $id }}"
                name="{{ $name }}"
                type="text"
                placeholder="{{ $placeholder }}"
                class="fx-autocomplete-input pr-11"
                @if ($required) required @endif
            >
            <button
                type="button"
                x-on:click="open ? closePanel() : openPanel()"
                class="absolute inset-y-0 right-3 my-auto flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-cyan-700"
                aria-label="Mostrar sugerencias"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m7 10 5 5 5-5"></path>
                </svg>
            </button>
        </div>

        <div x-cloak x-show="open" x-transition.origin.top.left class="fx-autocomplete-panel">
            <div class="fx-combobox-list">
                <template x-if="filteredOptions.length === 0">
                    <div class="fx-combobox-empty">{{ $emptyText }}</div>
                </template>

                <template x-for="(option, index) in filteredOptions" :key="option.value">
                    <button
                        type="button"
                        class="fx-combobox-option"
                        x-ref="optionNodes"
                        :data-active="activeIndex === index"
                        :data-selected="value.toLowerCase() === option.label.toLowerCase()"
                        x-on:mousemove="activeIndex = index"
                        x-on:click="pick(option.label)"
                    >
                        <span class="truncate" x-text="option.label"></span>
                        <svg x-show="value.toLowerCase() === option.label.toLowerCase()" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
