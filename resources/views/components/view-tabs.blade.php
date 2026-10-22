{{--
    Vistas rapidas con numero (reemplazan a las tarjetas de indicadores).
    $views: clave => ['label' => ..., 'count' => int, 'url' => ..., 'alert' => bool, 'icon' => nombre de x-nav-icon (opcional)]
--}}
@props(['views', 'current'])

<nav {{ $attributes->class('-mx-4 overflow-x-auto border-b border-slate-200 px-4 sm:mx-0 sm:px-0') }} aria-label="Vistas">
    <div class="fx-tabs">
        @foreach ($views as $key => $view)
            @php
                $isCurrent = (string) $current === (string) $key;
                $alert = ($view['alert'] ?? false) && ($view['count'] ?? 0) > 0;
            @endphp
            <a href="{{ $view['url'] }}" @class(['fx-tab', 'fx-tab-active' => $isCurrent]) @if ($isCurrent) aria-current="page" @endif>
                @isset($view['icon'])
                    <x-nav-icon :name="$view['icon']" class="fx-tab-icon" />
                @endisset
                {{ $view['label'] }}
                @isset($view['count'])
                    <span @class([
                        'tabular-nums',
                        'font-semibold text-amber-700' => $alert,
                        'text-slate-400' => ! $alert && ! $isCurrent,
                    ])>{{ $view['count'] }}</span>
                @endisset
            </a>
        @endforeach
    </div>
</nav>
