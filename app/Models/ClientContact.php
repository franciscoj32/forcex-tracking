<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    public const AREAS = ['operaciones', 'logistica', 'facturacion', 'comercial', 'gerencia'];

    public const AREA_LABELS = [
        'operaciones' => 'Operaciones',
        'logistica' => 'Logistica',
        'facturacion' => 'Facturacion',
        'comercial' => 'Comercial',
        'gerencia' => 'Gerencia',
    ];

    protected $fillable = [
        'client_id',
        'nombre',
        'cargo',
        'correo',
        'telefono',
        'area',
        'recibe_tracking',
        'recibe_facturacion',
        'es_principal',
    ];

    protected function casts(): array
    {
        return [
            'recibe_tracking' => 'boolean',
            'recibe_facturacion' => 'boolean',
            'es_principal' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function areaLabel(): string
    {
        return self::AREA_LABELS[$this->area] ?? ucfirst((string) $this->area);
    }
}
