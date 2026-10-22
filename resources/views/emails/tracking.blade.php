<x-mail::message>
# {{ $payload['heading'] }}

{{ $payload['intro'] }}

@if (! empty($payload['lines']))
<x-mail::table>
| | |
|:--|:--|
@foreach ($payload['lines'] as [$label, $value])
| **{{ $label }}** | {{ $value }} |
@endforeach
</x-mail::table>
@endif

@if (! empty($payload['items']))
@foreach ($payload['items'] as $item)
- {{ $item }}
@endforeach
@endif

@if (filled($payload['note'] ?? null))
<x-mail::panel>
**{{ $payload['note_label'] ?? 'Nota' }}:** {{ $payload['note'] }}
</x-mail::panel>
@endif

@if (filled($payload['cta_url'] ?? null))
<x-mail::button :url="$payload['cta_url']">
{{ $payload['cta_label'] ?? 'Ver detalle' }}
</x-mail::button>
@endif

{{-- Al cliente: invitacion a responder a su Customer. Internos: solo el pie que traiga el evento. --}}
@if (filled($payload['footer'] ?? null))
{{ $payload['footer'] }}
@elseif (filled($payload['reply_to'] ?? null))
Si tienes consultas, responde este correo: te atenderá {{ $payload['reply_to_name'] ?? 'tu Customer Service' }}.
@endif

Saludos,<br>
{{ $company }}
</x-mail::message>
