<?php

namespace App\Models;

use App\Support\BrandPalette;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

class AppSetting extends Model
{
    protected $fillable = [
        'program_name',
        'company_name',
        'brand_abbr',
        'logo_path',
        'color_primario',
        'color_acento',
        'tipo_cambio_referencial',
        'tipo_cambio_actualizado_en',
        'alerta_dias_preventiva',
        'alerta_horas_advertencia',
        'alerta_horas_critica',
        'alerta_horas_escalamiento',
        'alerta_dias_retraso_eta',
        'alerta_horas_prealerta',
    ];

    /** Umbrales por defecto de las alertas criticas (iguales a la migracion). */
    public const ALERT_DEFAULTS = [
        'alerta_dias_preventiva' => 5,
        'alerta_horas_advertencia' => 48,
        'alerta_horas_critica' => 24,
        'alerta_horas_escalamiento' => 4,
        'alerta_dias_retraso_eta' => 2,
        'alerta_horas_prealerta' => 72,
    ];

    protected function casts(): array
    {
        return [
            'tipo_cambio_referencial' => 'decimal:4',
            'tipo_cambio_actualizado_en' => 'datetime',
            'alerta_dias_preventiva' => 'integer',
            'alerta_horas_advertencia' => 'integer',
            'alerta_horas_critica' => 'integer',
            'alerta_horas_escalamiento' => 'integer',
            'alerta_dias_retraso_eta' => 'integer',
            'alerta_horas_prealerta' => 'integer',
        ];
    }

    /** Tipo de cambio de referencia (PEN por USD) que toma cada RO nueva. */
    public static function referenceExchangeRate(): ?float
    {
        $rate = static::current()->tipo_cambio_referencial;

        return $rate !== null ? (float) $rate : null;
    }

    /** Limpia la cache estatica (despues de guardar o en pruebas). */
    public static function flushCache(): void
    {
        static::$cachedCurrent = null;
    }

    protected static ?self $cachedCurrent = null;

    public static function current(): self
    {
        if (static::$cachedCurrent instanceof self) {
            return static::$cachedCurrent;
        }

        // Sin preguntar antes si existe la tabla (en MySQL eso es una consulta
        // extra a information_schema en cada pagina): si falla, valores por defecto.
        try {
            $settings = static::query()->firstOrCreate(['id' => 1], static::defaults());
        } catch (QueryException) {
            return static::$cachedCurrent = new self(static::defaults() + static::ALERT_DEFAULTS);
        }

        // Si el servidor aun no corrio la migracion de umbrales, se usan los valores por defecto.
        foreach (static::ALERT_DEFAULTS as $key => $default) {
            if ($settings->getAttribute($key) === null) {
                $settings->setAttribute($key, $default);
                $settings->syncOriginalAttribute($key);
            }
        }

        return static::$cachedCurrent = $settings;
    }

    public static function defaults(): array
    {
        return [
            'program_name' => 'FORCEX Tracking',
            'company_name' => 'FORCEX LOGISTICS',
            'brand_abbr' => 'FX',
            'logo_path' => null,
            'color_primario' => BrandPalette::DEFAULT_PRIMARY,
            'color_acento' => BrandPalette::DEFAULT_ACCENT,
        ];
    }

    /** Variables CSS de la marca de esta empresa (ver BrandPalette). */
    public function brandCss(): string
    {
        return BrandPalette::cssVariables($this->color_primario, $this->color_acento);
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    private ?bool $logoIsEmblem = null;

    /**
     * Logo cuadrado o redondo (emblema), no horizontal. A tamaño de menú no se
     * lee, asi que se muestra mas grande o junto al nombre de la empresa.
     * Los SVG se tratan como horizontales (no se puede medir su proporcion).
     */
    public function logoIsEmblem(): bool
    {
        if ($this->logoIsEmblem !== null) {
            return $this->logoIsEmblem;
        }

        $disk = Storage::disk('public');

        if (blank($this->logo_path) || ! $disk->exists($this->logo_path)) {
            return $this->logoIsEmblem = false;
        }

        $size = @getimagesize($disk->path($this->logo_path));

        return $this->logoIsEmblem = $size !== false && $size[1] > 0 && $size[0] / $size[1] < 1.6;
    }
}
