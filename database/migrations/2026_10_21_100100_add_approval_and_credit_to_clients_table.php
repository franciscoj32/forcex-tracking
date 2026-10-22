<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 - Clientes.
 *
 * - Flujo de aprobacion: el Comercial registra al cliente (queda "pendiente")
 *   y el Administrador valida datos fiscales y credito antes de que pueda operar.
 * - Condicion de credito maestra del cliente. Cada embarque la hereda y el
 *   Administrador puede forzarla por embarque sin tocar esta ficha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Aprobacion
            $table->enum('estado_aprobacion', ['pendiente', 'aprobado', 'rechazado'])
                ->default('pendiente')
                ->after('estado');
            $table->foreignId('registrado_por_id')->nullable()->after('estado_aprobacion')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('aprobado_por_id')->nullable()->after('registrado_por_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable()->after('aprobado_por_id');
            $table->text('observacion_aprobacion')->nullable()->after('aprobado_en');

            // Condicion de credito maestra
            $table->enum('condicion_credito', ['contado', 'credito'])->default('contado')->after('observacion_aprobacion');
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('condicion_credito');
            $table->decimal('linea_credito', 14, 2)->nullable()->after('dias_credito');
            $table->enum('moneda_linea_credito', ['USD', 'PEN'])->default('USD')->after('linea_credito');

            $table->index('estado_aprobacion');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['estado_aprobacion']);
            $table->dropConstrainedForeignId('registrado_por_id');
            $table->dropConstrainedForeignId('aprobado_por_id');
            $table->dropColumn([
                'estado_aprobacion',
                'aprobado_en',
                'observacion_aprobacion',
                'condicion_credito',
                'dias_credito',
                'linea_credito',
                'moneda_linea_credito',
            ]);
        });
    }
};
