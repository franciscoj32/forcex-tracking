{{-- Tarea en el calendario (semana o mes). $task, $payload, $now, $compact --}}
@php
    $overdue = $task->isOverdue($now);
    $tone = match (true) {
        $task->isCompleted() => 'border-slate-300 bg-slate-50 text-slate-400 line-through',
        $overdue => 'border-rose-500 bg-rose-50 text-rose-900',
        $task->isPostIt() => 'border-amber-400 bg-amber-50 text-amber-900',
        default => 'border-brand-500 bg-brand-50 text-brand-900',
    };
    $canEdit = auth()->user()->can('update', $task);
@endphp
<button type="button"
    @if ($canEdit) x-on:click.stop="$dispatch('edit-task', @js($payload))" @else x-on:click.stop @endif
    @class([
        'block w-full truncate rounded-md border-l-[3px] text-left transition',
        $tone,
        'px-1.5 py-0.5 text-[11px]' => $compact ?? false,
        'px-2 py-1.5 text-xs' => ! ($compact ?? false),
        'hover:brightness-95' => $canEdit,
        'cursor-default' => ! $canEdit,
    ])
    title="{{ $task->titulo }}{{ $task->shipment ? ' · RO '.$task->shipment->routing_order : '' }}{{ $task->assignee ? ' · '.$task->assignee->name : '' }}">
    @if ($task->vence_en)
        <span class="font-semibold tabular-nums">{{ $task->vence_en->format('H:i') }}</span>
    @endif
    {{ $task->titulo }}
    @unless ($compact ?? false)
        @if ($task->shipment)
            <span class="mt-0.5 block truncate text-[10px] opacity-70">RO {{ $task->shipment->routing_order }}</span>
        @endif
        @if ((int) $task->asignado_a_id !== (int) auth()->id())
            <span class="mt-0.5 block truncate text-[10px] opacity-70">Para {{ $task->assignee?->firstName() }}</span>
        @endif
    @endunless
</button>
