<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentUserAssignment extends Model
{
    use HasFactory;

    public const TYPE_RESPONSABLE = 'responsable';
    public const TYPE_SEGUIMIENTO = 'seguimiento';

    protected $fillable = [
        'shipment_id',
        'user_id',
        'assignment_type',
        'alerts_enabled',
    ];

    protected function casts(): array
    {
        return [
            'alerts_enabled' => 'boolean',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
