<?php

namespace App\Support;

/**
 * Genera la escala de colores de marca (50..950) a partir de un color base.
 *
 * Cada empresa elige su color principal y su color de acento en Configuracion.
 * La escala se publica como variables CSS (canales RGB) que usan las clases
 * de Tailwind `brand-*` y `accent-*`, asi el sistema cambia de marca sin recompilar.
 */
class BrandPalette
{
    public const DEFAULT_PRIMARY = '#1E40AF';

    public const DEFAULT_ACCENT = '#16A34A';

    /** Mezcla con blanco (+) o negro (-) para cada tono; el 600 es el color elegido. */
    private const STEPS = [
        50 => 0.93,
        100 => 0.86,
        200 => 0.72,
        300 => 0.52,
        400 => 0.28,
        500 => 0.12,
        600 => 0.0,
        700 => -0.16,
        800 => -0.32,
        900 => -0.46,
        950 => -0.62,
    ];

    public static function isValidHex(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /**
     * @return array<int, string> tono => "r g b"
     */
    public static function scale(?string $hex, string $fallback): array
    {
        [$r, $g, $b] = self::toRgb(self::isValidHex($hex) ? $hex : $fallback);

        $scale = [];
        foreach (self::STEPS as $step => $mix) {
            $target = $mix >= 0 ? 255 : 0;
            $amount = abs($mix);
            $scale[$step] = implode(' ', array_map(
                fn (int $channel) => (int) round($channel + ($target - $channel) * $amount),
                [$r, $g, $b]
            ));
        }

        return $scale;
    }

    /** Texto legible (blanco o casi negro) sobre el color dado. */
    public static function contrastText(?string $hex, string $fallback): string
    {
        [$r, $g, $b] = self::toRgb(self::isValidHex($hex) ? $hex : $fallback);

        $luminance = (0.2126 * self::linear($r)) + (0.7152 * self::linear($g)) + (0.0722 * self::linear($b));

        return $luminance > 0.45 ? '15 23 42' : '255 255 255';
    }

    /** Bloque `:root { ... }` con las variables de marca. */
    public static function cssVariables(?string $primary, ?string $accent): string
    {
        $lines = [];

        foreach (self::scale($primary, self::DEFAULT_PRIMARY) as $step => $rgb) {
            $lines[] = "--brand-{$step}: {$rgb};";
        }
        foreach (self::scale($accent, self::DEFAULT_ACCENT) as $step => $rgb) {
            $lines[] = "--accent-{$step}: {$rgb};";
        }

        $lines[] = '--brand-contrast: '.self::contrastText($primary, self::DEFAULT_PRIMARY).';';
        $lines[] = '--accent-contrast: '.self::contrastText($accent, self::DEFAULT_ACCENT).';';

        return ':root{'.implode('', $lines).'}';
    }

    /** @return array{0:int,1:int,2:int} */
    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function linear(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
