@props([
    'id',
    'name',
    'label' => null,
    'options' => [],
    'value' => '',
    'placeholder' => 'Seleccionar',
    'allLabel' => null,
    'searchPlaceholder' => 'Buscar...',
    'emptyText' => 'No hay opciones disponibles.',
    'searchable' => false,
    'required' => false,
])

@php
    $normalizedOptions = collect($options)
        ->map(function ($option) {
            if (is_array($option)) {
                return [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                ];
            }

            return [
                'value' => (string) data_get($option, 'value', ''),
                'label' => (string) data_get($option, 'label', ''),
            ];
        })
        ->values();
@endphp

<div
    x-data="filterCombobox({
        options: {{ Illuminate\Support\Js::from($normalizedOptions) }},
        value: {{ Illuminate\Support\Js::from((string) $value) }},
        placeholder: {{ Illuminate\Support\Js::from($placeholder) }},
        allLabel: {{ Illuminate\Support\Js::from($allLabel) }},
        searchPlaceholder: {{ Illuminate\Support\Js::from($searchPlaceholder) }},
        emptyText: {{ Illuminate\Support\Js::from($emptyText) }},
        searchEnabled: {{ $searchable ? 'true' : 'false' }},
    })"
    x-on:click.outside="closePanel()"
    x-on:keydown.escape.prevent.stop="closePanel()"
    x-on:keydown.arrow-down.prevent.stop="move(1)"
    x-on:keydown.arrow-up.prevent.stop="move(-1)"
    x-on:keydown.enter.prevent.stop="confirmActive()"
    {{ $attributes->class('space-y-1') }}
>
    @if ($label)
        <label class="fx-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="fx-combobox">
        <input id="{{ $id }}" type="hidden" name="{{ $name }}" x-model="value" @if ($required) data-required="true" @endif>
        @if ($required)
            <input
                type="text"
                class="pointer-events-none absolute h-0 w-0 opacity-0"
                tabindex="-1"
                aria-hidden="true"
                x-model="value"
                required
            >
        @endif

        <button type="button" class="fx-combobox-trigger" :data-open="open" x-on:click="toggle()">
            <span class="fx-combobox-trigger-label" x-text="buttonLabel"></span>
            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="m7 15 5 5 5-5"></path>
                <path d="m7 9 5-5 5 5"></path>
            </svg>
        </button>

        <div x-cloak x-show="open" x-transition.origin.top.left class="fx-combobox-panel">
            @if ($searchable)
                <div class="fx-combobox-search">
                    <input
                        x-ref="searchInput"
                        x-model="query"
                        type="search"
                        placeholder="{{ $searchPlaceholder }}"
                        class="fx-combobox-search-input"
                    >
                </div>
            @endif

            <div class="fx-combobox-list">
                @if ($allLabel !== null)
                    <button
                        type="button"
                        class="fx-combobox-option"
                        :data-active="activeIndex === -1 && value === ''"
                        :data-selected="value === ''"
                        x-on:click="clear()"
                    >
                        <span>{{ $allLabel }}</span>
                        <svg x-show="value === ''" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </button>
                @endif

                <template x-if="filteredOptions.length === 0">
                    <div class="fx-combobox-empty">{{ $emptyText }}</div>
                </template>

                <template x-for="(option, index) in filteredOptions" :key="option.value">
                    <button
                        type="button"
                        class="fx-combobox-option"
                        x-ref="optionNodes"
                        :data-active="activeIndex === index"
                        :data-selected="value === option.value"
                        x-on:mousemove="activeIndex = index"
                        x-on:click="choose(option)"
                    >
                        <span class="truncate" x-text="option.label"></span>
                        <svg x-show="value === option.value" class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
