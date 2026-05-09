<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AppSetting extends Model
{
    protected $fillable = [
        'program_name',
        'company_name',
        'brand_abbr',
        'logo_path',
        'brand_eyebrow',
        'brand_title',
        'login_eyebrow',
        'login_title',
        'login_text',
    ];

    protected static ?self $cachedCurrent = null;

    public static function current(): self
    {
        if (static::$cachedCurrent instanceof self) {
            return static::$cachedCurrent;
        }

        if (! Schema::hasTable('app_settings')) {
            return static::$cachedCurrent = new self(static::defaults());
        }

        return static::$cachedCurrent = static::query()->firstOrCreate(
            ['id' => 1],
            static::defaults()
        );
    }

    public static function defaults(): array
    {
        return [
            'program_name' => 'FORCEX Tracking',
            'company_name' => 'FORCEX LOGISTICS',
            'brand_abbr' => 'FX',
            'logo_path' => null,
            'brand_eyebrow' => 'FORCEX',
            'brand_title' => 'LOGISTICS TRACKING',
            'login_eyebrow' => 'Acceso seguro',
            'login_title' => 'Control de seguimiento para operaciones y clientes.',
            'login_text' => 'Gestiona embarques, revisa eventos y consulta documentos desde una sola plataforma privada de FORCEX LOGISTICS.',
        ];
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}
