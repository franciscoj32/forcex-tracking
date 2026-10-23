<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cotizacion a un cliente o prospecto. Los datos del cliente se copian al
 * cotizar: el PDF enviado no cambia si despues se edita la ficha del cliente.
 * "Vencida" no es un estado guardado: se calcula con la fecha y la validez.
 */
class Quotation extends Model
{
    public const STATUS_DRAFT = 'borrador';
    public const STATUS_SENT = 'enviada';
    public const STATUS_ACCEPTED = 'aceptada';
    public const STATUS_REJECTED = 'rechazada';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_ACCEPTED, self::STATUS_REJECTED];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Borrador',
        self::STATUS_SENT => 'Enviada',
        self::STATUS_ACCEPTED => 'Aceptada',
        self::STATUS_REJECTED => 'Rechazada',
    ];

    public const CHANNEL_EMAIL = 'correo';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CURRENCIES = ['USD', 'PEN'];

    protected $fillable = [
        'client_id',
        'ruc',
        'razon_social',
        'direccion',
        'contacto_nombre',
        'contacto_correo',
        'contacto_telefono',
        'tipo_carga',
        'incoterm',
        'origen',
        'puerto_carga',
        'puerto_descarga',
        'destino',
        'mercancia',
        'peso',
        'volumen',
        'contenedores',
        'naviera',
        'tiempo_transito',
        'frecuencia',
        'dias_libres',
        'fecha',
        'validez_dias',
        'condiciones',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'validez_dias' => 'integer',
            'dias_libres' => 'integer',
            'peso' => 'decimal:2',
            'volumen' => 'decimal:3',
            'enviada_en' => 'datetime',
            'respondida_en' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_por_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('orden')->orderBy('id');
    }

    /** Vencidas: pasada la validez y sin respuesta del cliente. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereIn('estado', [self::STATUS_DRAFT, self::STATUS_SENT])
            ->whereRaw(static::validUntilSql($query).' < ?', [today()->toDateString()]);
    }

    /** No vencidas (la fecha de validez todavia no paso). */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->whereRaw(static::validUntilSql($query).' >= ?', [today()->toDateString()]);
    }

    /** Fecha + dias de validez en SQL (MySQL en produccion, SQLite en pruebas). */
    private static function validUntilSql(Builder $query): string
    {
        return $query->getConnection()->getDriverName() === 'sqlite'
            ? "date(fecha, '+' || validez_dias || ' days')"
            : 'DATE_ADD(fecha, INTERVAL validez_dias DAY)';
    }

    /** Ultimo dia en que la cotizacion es valida. */
    public function validUntil(): Carbon
    {
        return $this->fecha->copy()->addDays($this->validez_dias);
    }

    public function isExpired(): bool
    {
        return in_array($this->estado, [self::STATUS_DRAFT, self::STATUS_SENT], true)
            && $this->validUntil()->lt(today());
    }

    public function isDraft(): bool
    {
        return $this->estado === self::STATUS_DRAFT;
    }

    /** Aceptada o rechazada: el cliente ya respondio y no se edita. */
    public function isClosed(): bool
    {
        return in_array($this->estado, [self::STATUS_ACCEPTED, self::STATUS_REJECTED], true);
    }

    public function statusLabel(): string
    {
        return $this->isExpired() ? 'Vencida' : (self::STATUS_LABELS[$this->estado] ?? $this->estado);
    }

    /** Clases del distintivo de estado. */
    public function statusBadge(): string
    {
        if ($this->isExpired()) {
            return 'bg-slate-100 text-slate-600';
        }

        return match ($this->estado) {
            self::STATUS_SENT => 'bg-sky-100 text-sky-800',
            self::STATUS_ACCEPTED => 'bg-green-100 text-green-800',
            self::STATUS_REJECTED => 'bg-rose-100 text-rose-700',
            default => 'bg-amber-100 text-amber-800',
        };
    }

    public function cargoLabel(): string
    {
        return $this->tipo_carga === 'AEREO' ? 'Aéreo' : $this->tipo_carga;
    }

    /** "Callao → Shanghai" con lo que haya de la ruta. */
    public function routeLabel(): string
    {
        $from = $this->puerto_carga ?: $this->origen;
        $to = $this->puerto_descarga ?: $this->destino;

        return trim(($from ?? '').' → '.($to ?? ''), ' →');
    }

    public static function igvRate(): float
    {
        return (float) config('forcex.igv_rate', 0.18);
    }

    /**
     * Totales por moneda (USD primero). El IGV solo se calcula sobre las
     * lineas afectas (gastos locales); el flete internacional es inafecto.
     *
     * @return array<string, array{subtotal: float, igv: float, total: float}>
     */
    public function totals(): array
    {
        $rate = static::igvRate();
        $totals = [];

        foreach ($this->items as $item) {
            $line = $item->total();
            $totals[$item->moneda] ??= ['subtotal' => 0.0, 'igv' => 0.0, 'total' => 0.0];
            $totals[$item->moneda]['subtotal'] += $line;

            if ($item->afecto_igv) {
                $totals[$item->moneda]['igv'] += $line * $rate;
            }
        }

        foreach ($totals as $currency => $amounts) {
            $totals[$currency]['subtotal'] = round($amounts['subtotal'], 2);
            $totals[$currency]['igv'] = round($amounts['igv'], 2);
            $totals[$currency]['total'] = round($amounts['subtotal'] + $amounts['igv'], 2);
        }

        uksort($totals, fn ($a, $b) => array_search($a, self::CURRENCIES, true) <=> array_search($b, self::CURRENCIES, true));

        return $totals;
    }

    /** Total con IGV por moneda, para listados: ['USD' => 1200.0, 'PEN' => 354.0]. */
    public function grandTotals(): array
    {
        return collect($this->totals())->map(fn (array $amounts) => $amounts['total'])->all();
    }

    public function pdfFileName(): string
    {
        return 'Cotizacion_'.$this->numero.'.pdf';
    }
}
