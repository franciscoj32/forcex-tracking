<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 - Responsables y condiciones comerciales del embarque (RO).
 *
 * - comercial_id: quien vendio y creo la RO (base del ranking por margen).
 * - customer_id:  Customer Service asignado, unico contacto con el cliente.
 * - operador_id:  Operador duenio de los datos tecnicos.
 * - incoterm y condicion de credito heredada del cliente (forzable por el Admin).
 * - El BL deja de ser obligatorio: la RO se crea antes de tener BL/AWB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('comercial_id')->nullable()->after('client_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('comercial_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('operador_id')->nullable()->after('customer_id')
                ->constrained('users')->nullOnDelete();

            $table->string('incoterm', 3)->nullable()->after('tipo_carga');

            $table->enum('condicion_credito', ['contado', 'credito'])->default('contado')->after('incoterm');
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('condicion_credito');
            $table->boolean('credito_forzado')->default(false)->after('dias_credito');

            $table->string('bl')->nullable()->change();

            $table->index(['comercial_id', 'fecha_etd']);
            $table->index(['customer_id', 'estado_actual']);
            $table->index(['operador_id', 'estado_actual']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['comercial_id', 'fecha_etd']);
            $table->dropIndex(['customer_id', 'estado_actual']);
            $table->dropIndex(['operador_id', 'estado_actual']);

            $table->dropConstrainedForeignId('comercial_id');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('operador_id');

            $table->dropColumn(['incoterm', 'condicion_credito', 'dias_credito', 'credito_forzado']);

            $table->string('bl')->nullable(false)->change();
        });
    }
};
