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
        $data = $request->validate([
            'program_name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'brand_abbr' => ['required', 'string', 'max:20'],
            'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'brand_eyebrow' => ['required', 'string', 'max:255'],
            'brand_title' => ['required', 'string', 'max:255'],
            'login_eyebrow' => ['required', 'string', 'max:255'],
            'login_title' => ['required', 'string', 'max:255'],
            'login_text' => ['required', 'string', 'max:1000'],
        ]);

        $settings = AppSetting::current();

        if ($request->boolean('remove_logo') && filled($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
            $data['logo_path'] = null;
        }

        if ($request->hasFile('logo_image')) {
            if (filled($settings->logo_path)) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $request->file('logo_image')->store('branding', 'public');
        }

        unset($data['logo_image'], $data['remove_logo']);

        $settings->fill($data)->save();

        return redirect()->route('configuracion.edit')->with('status', 'Configuracion actualizada correctamente.');
    }
}
