<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('program_name')->default('FORCEX Tracking');
            $table->string('company_name')->default('FORCEX LOGISTICS');
            $table->string('brand_abbr', 20)->default('FX');
            $table->string('brand_eyebrow')->default('FORCEX');
            $table->string('brand_title')->default('LOGISTICS TRACKING');
            $table->string('login_eyebrow')->default('Acceso seguro');
            $table->string('login_title')->default('Control de seguimiento para operaciones y clientes.');
            $table->string('login_text', 1000)->default('Gestiona embarques, revisa eventos y consulta documentos desde una sola plataforma privada de FORCEX LOGISTICS.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
