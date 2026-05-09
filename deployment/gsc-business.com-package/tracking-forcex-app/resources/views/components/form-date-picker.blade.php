@props([
    'id',
    'name',
    'label' => null,
    'value' => '',
    'placeholder' => 'Seleccionar fecha',
    'enableTime' => false,
    'required' => false,
])

<div
    x-data="formDatePicker({
        value: {{ Illuminate\Support\Js::from($value ? (string) $value : '') }},
        enableTime: {{ $enableTime ? 'true' : 'false' }},
        placeholder: {{ Illuminate\Support\Js::from($placeholder) }},
    })"
    {{ $attributes->class('fx-date-field') }}
>
    @if ($label)
        <label class="fx-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div x-ref="wrapper" class="fx-date-wrapper" data-open="false">
        <input
            x-ref="input"
            id="{{ $id }}"
            name="{{ $name }}"
            type="text"
            value="{{ $value }}"
            class="fx-date-input"
            @if ($required) required @endif
        >
        <svg class="fx-date-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2"></rect>
            <path d="M16 2v4"></path>
            <path d="M8 2v4"></path>
            <path d="M3 10h18"></path>
        </svg>
    </div>
</div>
