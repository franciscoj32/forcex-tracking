<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fechas de cada contenedor para demurrage y detention:
 * - Retiro: sale lleno del terminal (detiene el demurrage, arranca la detention).
 * - Devolucion: entrega del vacio (detiene la detention).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_containers', function (Blueprint $table) {
            $table->date('fecha_retiro')->nullable()->after('volumen_cbm');
            $table->date('fecha_devolucion')->nullable()->after('fecha_retiro');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_containers', function (Blueprint $table) {
            $table->dropColumn(['fecha_retiro', 'fecha_devolucion']);
        });
    }
};
