@props(['name' => 'default'])

@php
    $paths = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/><path d="M10 20v-5h4v5"/>',
        'shipments' => '<path d="M3 7h11v8H3z"/><path d="M14 10h3l3 3v2h-6z"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/>',
        'clients' => '<path d="M4 20V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v15"/><path d="M14 9h5a1 1 0 0 1 1 1v10"/><path d="M3 20h18"/><path d="M8 8h2M8 12h2M8 16h2"/>',
        // Hoja con signo de dolar (cotizaciones)
        'quotation' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/><path d="M13.8 11.2c-.4-.6-1-.9-1.8-.9-1 0-1.7.5-1.7 1.3 0 1.8 3.5 1 3.5 2.8 0 .8-.7 1.3-1.8 1.3-.8 0-1.5-.3-1.9-.9"/><path d="M12 9.3v1M12 15.7v1"/>',
        'provisions' => '<path d="M6 4h9l3 3v13H6z"/><path d="M15 4v4h4"/><path d="M9 12h6"/><path d="M9 16h4"/>',
        'billing' => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M4 10h16"/><path d="M8 15h3"/>',
        'suppliers' => '<path d="M3 17h13V7H3z"/><path d="M16 11h3l2 3v3h-5"/><circle cx="7" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/>',
        'concepts' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/>',
        'reports' => '<path d="M4 19h16"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-7"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 19a6 6 0 0 1 12 0"/><path d="M16 5a3 3 0 0 1 0 6"/><path d="M21 19a6 6 0 0 0-4-5.6"/>',
        'roles' => '<path d="m12 3 7 4v5c0 4.2-2.7 8.1-7 9-4.3-.9-7-4.8-7-9V7z"/><path d="m9.5 12 1.8 1.8 3.2-3.6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
        'summary' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9z"/><path d="M9 11h6"/><path d="M9 15h4"/>',
        'tracking' => '<circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18h8.5a3.5 3.5 0 0 0 0-7h-9a3.5 3.5 0 0 1 0-7H16"/>',
        'documents' => '<path d="M3 7.5A1.5 1.5 0 0 1 4.5 6H9l2 2h8.5A1.5 1.5 0 0 1 21 9.5v8a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z"/>',
        'finance' => '<circle cx="12" cy="12" r="8.5"/><path d="M14.6 9.4c-.5-.8-1.4-1.2-2.6-1.2-1.5 0-2.5.8-2.5 1.9 0 2.6 5 1.4 5 4 0 1.1-1 1.9-2.5 1.9-1.2 0-2.2-.5-2.7-1.4"/><path d="M12 6.6v1.6M12 15.9v1.5"/>',
        // Burbuja de chat (WhatsApp)
        'whatsapp' => '<path d="M4 20l1.3-3.9A8 8 0 1 1 8 18.8z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a4 4 0 0 1-1.8-1.8l.8-1-1-2z"/>',
        // Usuario
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'logout' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l-4-4 4-4"/><path d="M6 12h10"/>',
        // Alertas criticas
        'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'tasks' => '<path d="m4 7 2 2 3.5-4"/><path d="M13 7h7"/><path d="m4 15 2 2 3.5-4"/><path d="M13 15h7"/>',
        'hourglass' => '<path d="M7 3h10M7 21h10"/><path d="M8 3c0 5 8 5 8 9s-8 4-8 9"/><path d="M16 3c0 5-8 5-8 9s8 4 8 9"/>',
        'container' => '<rect x="3" y="7" width="18" height="11" rx="1.5"/><path d="M8 7v11M12 7v11M16 7v11"/>',
        'return' => '<path d="M20 9H7l3-3"/><path d="M4 15h13l-3 3"/>',
        'warehouse' => '<path d="M3 10 12 4l9 6v10H3z"/><path d="M8 20v-6h8v6"/>',
        // Avion de papel (Pre-alerta de llegada)
        'send' => '<path d="M21 3 3 10.5l7 2.5 2.5 7z"/><path d="m21 3-11 10"/>',
        // Vistas rapidas: el mismo significado usa el mismo icono en todas las pantallas
        'activity' => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
        'alert' => '<path d="M10.3 4.3 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v3.5"/><path d="M12 17h.01"/>',
        'arrival' => '<path d="M12 3v11"/><path d="m8 10 4 4 4-4"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'calendar-check' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/><path d="m9.5 15 1.8 1.8 3.2-3.3"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'check-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'x-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="m9.5 9.5 5 5M14.5 9.5l-5 5"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'default' => '<circle cx="12" cy="12" r="8"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['default'] !!}</svg>
