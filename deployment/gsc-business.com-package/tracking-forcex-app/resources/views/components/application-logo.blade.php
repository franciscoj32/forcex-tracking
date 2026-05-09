@php($appSettings = \App\Models\AppSetting::current())

@if ($appSettings->logoUrl())
    <div class="flex min-h-12 items-center">
        <img src="{{ $appSettings->logoUrl() }}" alt="{{ $appSettings->company_name }}" class="h-12 max-w-[210px] object-contain">
    </div>
@else
    <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-500 text-lg font-black text-slate-950 shadow-lg shadow-cyan-500/30">
            {{ $appSettings->brand_abbr }}
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-300">{{ $appSettings->brand_eyebrow }}</p>
            <p class="text-2xl font-semibold text-white">{{ $appSettings->brand_title }}</p>
        </div>
    </div>
@endif
