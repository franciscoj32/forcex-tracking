<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentContainer extends Model
{
    public const TYPES = ['20GP', '40GP', '40HC', '20RF', '40RF', '20OT', '40OT', '20FR', '40FR'];

    public const TYPE_LABELS = [
        '20GP' => "20' Standard",
        '40GP' => "40' Standard",
        '40HC' => "40' High Cube",
        '20RF' => "20' Reefer",
        '40RF' => "40' Reefer",
        '20OT' => "20' Open Top",
        '40OT' => "40' Open Top",
        '20FR' => "20' Flat Rack",
        '40FR' => "40' Flat Rack",
    ];

    protected $fillable = [
        'shipment_id',
        'numero',
        'tipo',
        'precinto',
        'bultos',
        'peso_kg',
        'volumen_cbm',
        'fecha_retiro',
        'fecha_devolucion',
    ];

    protected function casts(): array
    {
        return [
            'bultos' => 'integer',
            'peso_kg' => 'decimal:2',
            'volumen_cbm' => 'decimal:2',
            'fecha_retiro' => 'date',
            'fecha_devolucion' => 'date',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->tipo] ?? (string) $this->tipo;
    }

    /**
     * Valida un numero de contenedor ISO 6346: 4 letras (duenio + categoria),
     * 6 digitos de serie y 1 digito de control. Ej: MSCU1234565.
     */
    public static function isValidNumber(string $number): bool
    {
        $number = strtoupper(trim($number));

        if (! preg_match('/^[A-Z]{3}[UJZ][0-9]{7}$/', $number)) {
            return false;
        }

        return static::checkDigit(substr($number, 0, 10)) === (int) $number[10];
    }

    /** Digito de control ISO 6346 para los 10 primeros caracteres. */
    public static function checkDigit(string $firstTen): int
    {
        $sum = 0;

        foreach (str_split(strtoupper($firstTen)) as $position => $char) {
            $sum += static::charValue($char) * (2 ** $position);
        }

        return ($sum % 11) % 10;
    }

    /** Valor numerico ISO 6346: A=10 ... Z=38, saltando multiplos de 11. */
    private static function charValue(string $char): int
    {
        if (ctype_digit($char)) {
            return (int) $char;
        }

        $value = 10;

        foreach (range('A', 'Z') as $letter) {
            if ($value % 11 === 0) {
                $value++;
            }

            if ($letter === $char) {
                return $value;
            }

            $value++;
        }

        return 0;
    }
}
