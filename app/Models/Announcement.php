<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anuncio de la pizarra de oficina. Reemplaza los correos masivos internos.
 * - aviso: lo publica la Gerencia ("Reunion a las 4 PM", "Feriado el viernes").
 * - cumpleanos: lo crea el sistema el dia del cumpleanos de alguien del equipo.
 */
class Announcement extends Model
{
    public const TYPE_NOTICE = 'aviso';
    public const TYPE_BIRTHDAY = 'cumpleanos';

    /** Dias que se muestra un aviso si no se indica otra fecha. */
    public const DEFAULT_DAYS = 7;

    protected $fillable = [
        'titulo',
        'cuerpo',
        'fijado',
        'publicado_en',
        'expira_en',
    ];

    protected function casts(): array
    {
        return [
            'fijado' => 'boolean',
            'publicado_en' => 'datetime',
            'expira_en' => 'datetime',
            'fecha_evento' => 'date',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_id');
    }

    public function celebrant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Publicados y sin vencer. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('publicado_en', '<=', now())
            ->where(fn (Builder $open) => $open->whereNull('expira_en')->orWhere('expira_en', '>', now()));
    }

    /** Fijados primero, luego los cumpleanos de hoy y los mas recientes. */
    public function scopeBoardOrder(Builder $query): Builder
    {
        return $query->orderByDesc('fijado')
            ->orderByRaw("CASE WHEN tipo = 'cumpleanos' THEN 0 ELSE 1 END")
            ->orderByDesc('publicado_en')
            ->orderByDesc('id');
    }

    public function isBirthday(): bool
    {
        return $this->tipo === self::TYPE_BIRTHDAY;
    }

    public function isExpired(): bool
    {
        return $this->expira_en !== null && $this->expira_en->lte(now());
    }
}
