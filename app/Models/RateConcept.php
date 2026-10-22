<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Concepto de cobro/costo: flete, THC, despacho de aduana, etc. */
class RateConcept extends Model
{
    protected $fillable = ['codigo', 'nombre', 'activo', 'orden'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShipmentRate::class);
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(Provision::class);
    }

    public function isInUse(): bool
    {
        return $this->rates()->exists() || $this->provisions()->exists();
    }
}
