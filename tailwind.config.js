import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Escala de marca servida como variables CSS (canales RGB). Cada empresa define
// su color principal y de acento en Configuracion; ver App\Support\BrandPalette.
const scale = (name) =>
    Object.fromEntries(
        [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((step) => [
            step,
            `rgb(var(--${name}-${step}) / <alpha-value>)`,
        ]),
    );

const brand = { ...scale('brand'), DEFAULT: 'rgb(var(--brand-600) / <alpha-value>)', contrast: 'rgb(var(--brand-contrast) / <alpha-value>)' };
const accent = { ...scale('accent'), DEFAULT: 'rgb(var(--accent-600) / <alpha-value>)', contrast: 'rgb(var(--accent-contrast) / <alpha-value>)' };

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/Support/ChartPalette.php',
        './app/Services/Alerts/AlertLevel.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans Variable"', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', 'SFMono-Regular', 'Consolas', 'monospace'],
            },
            colors: {
                brand,
                accent,
                // Transitorio: las vistas antiguas usan "cyan"; asi ya toman el color
                // de la empresa. Se reemplaza por "brand" pantalla por pantalla.
                cyan: brand,
            },
        },
    },

    plugins: [forms],
};
