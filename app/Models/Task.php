<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tarea del modulo de Productividad.
 *
 * - Post-it del dia: tiene "fecha" pero no "vence_en".
 * - Tarea programada: "vence_en" con fecha y hora (y "fecha" es ese dia).
 * - Puede ser generica o un acceso directo a una RO (shipment_id).
 * - Para uno mismo (creado_por_id = asignado_a_id) o asignada a otro.
 */
class Task extends Model
{
    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha',
        'vence_en',
        'shipment_id',
        'asignado_a_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'vence_en' => 'datetime',
            'completada_en' => 'datetime',
            'recordatorio_enviado_en' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completada_por_id');
    }

    /**
     * Lo que el usuario puede ver: las que creo y las que le asignaron.
     * La Gerencia ve las de todo el equipo, salvo las personales de la
     * cuenta de Desarrollo (que es oculta).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isDeveloper()) {
            return $query;
        }

        if ($user->isAdmin()) {
            $developerIds = User::query()->where('role', User::ROLE_DEVELOPER)->pluck('id');

            return $query->where(fn (Builder $visible) => $visible
                ->whereNotIn('asignado_a_id', $developerIds)
                ->orWhere('creado_por_id', $user->id));
        }

        return $query->where(fn (Builder $mine) => $mine
            ->where('asignado_a_id', $user->id)
            ->orWhere('creado_por_id', $user->id));
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completada_en');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completada_en');
    }

    /** Ordena: primero las que tienen hora (por hora) y luego los post-its. */
    public function scopeAgenda(Builder $query): Builder
    {
        return $query->orderBy('fecha')
            ->orderByRaw('CASE WHEN vence_en IS NULL THEN 1 ELSE 0 END')
            ->orderBy('vence_en')
            ->orderBy('id');
    }

    public function isCompleted(): bool
    {
        return $this->completada_en !== null;
    }

    public function isPostIt(): bool
    {
        return $this->vence_en === null;
    }

    /** Pendiente y ya paso su hora (o su dia, si es un post-it). */
    public function isOverdue(?Carbon $now = null): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $now ??= now();

        return $this->vence_en ? $this->vence_en->lt($now) : $this->fecha->lt($now->copy()->startOfDay());
    }

    /** Vence dentro de las proximas N horas. */
    public function isDueSoon(?Carbon $now = null): bool
    {
        $now ??= now();

        return ! $this->isCompleted()
            && $this->vence_en
            && $this->vence_en->gte($now)
            && $this->vence_en->lte($now->copy()->addHours(static::reminderHours()));
    }

    public function isAssignedByOther(): bool
    {
        return (int) $this->creado_por_id !== (int) $this->asignado_a_id;
    }

    public static function reminderHours(): int
    {
        return max(1, (int) config('forcex.task_reminder_hours', 2));
    }

    /** "Hoy 16:00", "Vie 14/11 09:30" o "Hoy" para los post-its. */
    public function whenLabel(?Carbon $now = null): string
    {
        $now ??= now();
        $day = $this->fecha->isSameDay($now) ? 'Hoy'
            : ($this->fecha->isSameDay($now->copy()->addDay()) ? 'Mañana'
            : ($this->fecha->isSameDay($now->copy()->subDay()) ? 'Ayer'
            : ucfirst($this->fecha->locale('es')->translatedFormat('D d/m'))));

        return $this->vence_en ? $day.' '.$this->vence_en->format('H:i') : $day;
    }

    /** Datos para el post-it flotante y las respuestas JSON. */
    public function toPostItArray(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'hora' => $this->vence_en?->format('H:i'),
            'cuando' => $this->whenLabel(),
            'vencida' => $this->isOverdue(),
            'completada' => $this->isCompleted(),
            'ro' => $this->shipment?->routing_order,
            'ro_url' => $this->shipment_id ? route('embarques.show', $this->shipment_id) : null,
            'de' => $this->isAssignedByOther() ? $this->creator?->firstName() : null,
            'toggle_url' => route('tareas.completar', $this->id),
        ];
    }
}
