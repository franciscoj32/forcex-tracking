<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Proveedor: naviera, aerolinea, agente, almacen, transportista, agencia de aduana. */
class Supplier extends Model
{
    public const TYPES = ['naviera', 'aerolinea', 'agente', 'almacen', 'transportista', 'agencia_aduana', 'otro'];

    public const TYPE_LABELS = [
        'naviera' => 'Naviera',
        'aerolinea' => 'Aerolinea',
        'agente' => 'Agente de origen',
        'almacen' => 'Almacen',
        'transportista' => 'Transportista',
        'agencia_aduana' => 'Agencia de aduana',
        'otro' => 'Otro',
    ];

    protected $fillable = ['razon_social', 'ruc', 'tipo', 'correo', 'telefono', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function provisions(): HasMany
    {
        return $this->hasMany(Provision::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->tipo] ?? ucfirst((string) $this->tipo);
    }
}
