<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actualizacion masiva:
 * - Nave y viaje de la RO (agrupan las RO que van en el mismo buque).
 * - Lote en los cambios de ETD/ETA hechos juntos, para que el Customer los
 *   valide en grupo y reciba un solo correo B1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('nave', 120)->nullable()->after('naviera');
            $table->string('viaje', 60)->nullable()->after('nave');
            $table->index(['nave', 'viaje']);
        });

        Schema::table('shipment_date_changes', function (Blueprint $table) {
            $table->string('lote', 36)->nullable()->after('estado');
            $table->index('lote');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_date_changes', function (Blueprint $table) {
            $table->dropIndex(['lote']);
            $table->dropColumn('lote');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['nave', 'viaje']);
            $table->dropColumn(['nave', 'viaje']);
        });
    }
};
