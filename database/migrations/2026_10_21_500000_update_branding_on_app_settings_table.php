<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca configurable por empresa: color principal y color de acento.
 * Se quitan las frases decorativas del logo y del login.
 */
return new class extends Migration
{
    private const REMOVED = ['brand_eyebrow', 'brand_title', 'login_eyebrow', 'login_title', 'login_text'];

    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('color_primario', 7)->default('#1E40AF')->after('logo_path');
            $table->string('color_acento', 7)->default('#16A34A')->after('color_primario');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(self::REMOVED);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('brand_eyebrow')->default('FORCEX');
            $table->string('brand_title')->default('LOGISTICS TRACKING');
            $table->string('login_eyebrow')->default('Acceso seguro');
            $table->string('login_title')->default('Control de seguimiento para operaciones y clientes.');
            $table->string('login_text', 1000)->default('');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['color_primario', 'color_acento']);
        });
    }
};
