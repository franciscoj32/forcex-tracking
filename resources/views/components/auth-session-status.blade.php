@props(['status'])

@if ($status)
    <p {{ $attributes->merge(['class' => 'rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700']) }} role="status">
        {{ $status }}
    </p>
@endif
