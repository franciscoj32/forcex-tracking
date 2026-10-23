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
        // Datos de la empresa para los documentos (cotizaciones)
        'empresa_ruc',
        'empresa_direccion',
        'empresa_telefono',
        'empresa_correo',
        'empresa_web',
        'cuentas_bancarias',
        'cotizacion_condiciones',
        'cotizacion_validez_dias',
        // Modulos opcionales (solo Desarrollo)
        'modulo_productividad',
    ];

    /** Modulos opcionales: clave => columna de app_settings. */
    public const MODULES = [
        'productividad' => 'modulo_productividad',
    ];

    /** Condiciones por defecto de las cotizaciones (se editan en Configuracion). */
    public const QUOTATION_CONDITIONS = "Validez: 7 días calendario. Sujeto a variaciones tarifarias (GRI, BAF, etc.) y disponibilidad de equipo/espacio.\n\n"
        ."Pesos/Medidas: Tarifas en base al peso/volumen declarado (relación W/M). Aplica solo para carga general (Dry).\n\n"
        ."Exclusiones: NO incluye impuestos aduaneros, seguro de carga, gastos por aforos físicos, ni sobrestadía/almacenaje (Demurrage/Detention).\n\n"
        ."Responsabilidad: FORCEX LOGISTICS actúa como Agente de Carga. Se recomienda contratar seguro puerta a puerta.";

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
            'cotizacion_validez_dias' => 'integer',
            'modulo_productividad' => 'boolean',
        ];
    }

    /**
     * ¿Esta encendido el modulo? Lee la configuracion ya cargada en memoria:
     * no cuesta ninguna consulta. Si la migracion aun no corrio, queda apagado.
     */
    public static function moduleEnabled(string $module): bool
    {
        $column = self::MODULES[$module] ?? null;

        return $column !== null && (bool) static::current()->getAttribute($column);
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
            'cotizacion_condiciones' => self::QUOTATION_CONDITIONS,
            'cotizacion_validez_dias' => 7,
        ];
    }

    /** Linea de datos de la empresa para el encabezado de los documentos. */
    public function companyDocumentLine(): string
    {
        return collect([
            filled($this->empresa_ruc) ? 'RUC '.$this->empresa_ruc : null,
            $this->empresa_direccion,
            $this->empresa_telefono,
            $this->empresa_correo,
            $this->empresa_web,
        ])->filter(fn ($part) => filled($part))->join(' · ');
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
