<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => AppSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // La marca (nombre, logo, sigla y colores) solo la cambia Desarrollo.
        // A la Gerente esos campos no se le validan ni se guardan aunque los envie.
        $canBrand = $request->user()->can('manage-branding');

        $brandRules = $canBrand ? [
            'program_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'brand_abbr' => ['required', 'string', 'max:20'],
            'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'color_primario' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_acento' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ] : [];

        // Modulos opcionales: tambien solo Desarrollo.
        $moduleRules = $request->user()->can('manage-modules')
            ? collect(AppSetting::MODULES)->mapWithKeys(fn (string $column) => [$column => ['required', 'boolean']])->all()
            : [];

        $data = $request->validate($brandRules + $moduleRules + [
            // PEN por 1 USD. Cada RO nueva copia este valor y lo congela.
            'tipo_cambio_referencial' => ['nullable', 'numeric', 'between:1,10'],
            // Semaforo de alertas: verde desde N dias, amarilla desde X h, roja desde Y h.
            'alerta_dias_preventiva' => ['required', 'integer', 'between:1,30'],
            'alerta_horas_advertencia' => [
                'required', 'integer', 'between:2,720',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if ((int) $value >= (int) $request->input('alerta_dias_preventiva') * 24) {
                        $fail('La alerta amarilla debe empezar después de la verde (menos horas que los días preventivos).');
                    }
                },
            ],
            'alerta_horas_critica' => ['required', 'integer', 'between:1,240', 'lt:alerta_horas_advertencia'],
            'alerta_horas_escalamiento' => ['required', 'integer', 'between:0,72'],
            'alerta_dias_retraso_eta' => ['required', 'integer', 'between:1,30'],
            // Pre-alerta de llegada: horas antes de la ETA (amarilla); roja desde la critica.
            'alerta_horas_prealerta' => ['required', 'integer', 'between:12,240', 'gt:alerta_horas_critica'],
            // Datos de la empresa que se imprimen en las cotizaciones.
            'empresa_ruc' => ['nullable', 'digits:11'],
            'empresa_direccion' => ['nullable', 'string', 'max:255'],
            'empresa_telefono' => ['nullable', 'string', 'max:60'],
            'empresa_correo' => ['nullable', 'email', 'max:255'],
            'empresa_web' => ['nullable', 'string', 'max:255'],
            'cuentas_bancarias' => ['nullable', 'string', 'max:2000'],
            'cotizacion_condiciones' => ['nullable', 'string', 'max:5000'],
            'cotizacion_validez_dias' => ['required', 'integer', 'between:1,180'],
        ], [
            'alerta_horas_critica.lt' => 'La alerta roja debe empezar después de la amarilla (menos horas).',
            'alerta_horas_prealerta.gt' => 'La pre-alerta debe pedirse antes de que la alerta se ponga roja (más horas que la roja).',
        ], [
            'alerta_horas_prealerta' => 'horas de la pre-alerta de llegada',
            'tipo_cambio_referencial' => 'tipo de cambio de referencia',
            'color_primario' => 'color principal',
            'color_acento' => 'color de acento',
            'alerta_dias_preventiva' => 'días de la alerta verde',
            'alerta_horas_advertencia' => 'horas de la alerta amarilla',
            'alerta_horas_critica' => 'horas de la alerta roja',
            'alerta_horas_escalamiento' => 'horas para escalar al Administrador',
            'alerta_dias_retraso_eta' => 'días de retraso de ETA',
            'empresa_ruc' => 'RUC de la empresa',
            'empresa_direccion' => 'dirección de la empresa',
            'empresa_telefono' => 'teléfono de la empresa',
            'empresa_correo' => 'correo de la empresa',
            'empresa_web' => 'página web',
            'cuentas_bancarias' => 'cuentas bancarias',
            'cotizacion_condiciones' => 'condiciones de las cotizaciones',
            'cotizacion_validez_dias' => 'días de validez de las cotizaciones',
        ]);

        if ($canBrand) {
            $data['color_primario'] = strtoupper($data['color_primario']);
            $data['color_acento'] = strtoupper($data['color_acento']);
        }

        $settings = AppSetting::current();

        $newRate = filled($data['tipo_cambio_referencial'] ?? null) ? round((float) $data['tipo_cambio_referencial'], 4) : null;
        $currentRate = $settings->tipo_cambio_referencial !== null ? round((float) $settings->tipo_cambio_referencial, 4) : null;

        if ($newRate !== $currentRate) {
            $data['tipo_cambio_actualizado_en'] = now();
        }

        if ($canBrand && $request->boolean('remove_logo') && filled($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
            $data['logo_path'] = null;
        }

        if ($canBrand && $request->hasFile('logo_image')) {
            if (filled($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $request->file('logo_image')->store('branding', 'public');
        }

        unset($data['logo_image'], $data['remove_logo']);

        $settings->fill($data)->save();
        AppSetting::flushCache();

        return redirect()->route('configuracion.edit')->with('status', 'Configuracion actualizada correctamente.');
    }
}
